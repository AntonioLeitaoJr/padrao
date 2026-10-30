<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/AuditoriaService.php';
require_once __DIR__ . '/../Models/Efetivo.php';

/**
 * Serviço da Urna Eletrônica Sigilosa
 * Implementa o protocolo de desacoplamento cego e controle estrito de unicidade de voto por CPF
 */
class UrnaService {

    /**
     * Valida matematicamente os dígitos verificadores do CPF
     */
    public static function validarCpf(string $cpf): bool {
        $cpf = preg_replace('/[^0-9]/', '', $cpf);
        if (strlen($cpf) !== 11) {
            return false;
        }

        // Rejeita sequências conhecidas de dígitos iguais
        if (preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        // 1º Dígito Verificador
        $soma = 0;
        for ($i = 0; $i < 9; $i++) {
            $soma += (int)$cpf[$i] * (10 - $i);
        }
        $resto = 11 - ($soma % 11);
        $dv1 = ($resto >= 10) ? 0 : $resto;
        if ((int)$cpf[9] !== $dv1) {
            return false;
        }

        // 2º Dígito Verificador
        $soma = 0;
        for ($i = 0; $i < 10; $i++) {
            $soma += (int)$cpf[$i] * (11 - $i);
        }
        $resto = 11 - ($soma % 11);
        $dv2 = ($resto >= 10) ? 0 : $resto;
        return (int)$cpf[10] === $dv2;
    }

    /**
     * Verifica elegibilidade do eleitor para votar no ciclo
     */
    public static function podeVotar(int $pleitoId, string $cpf): array {
        $cleanCpf = preg_replace('/[^0-9]/', '', $cpf);
        if (!self::validarCpf($cleanCpf)) {
            return ['pode' => false, 'motivo' => 'Número de CPF inválido.'];
        }

        $cpfHash = hash('sha256', $cleanCpf);
        $pdo = Database::getConnection();

        // 1. Verifica se o CPF pertence ao efetivo credenciado da COMARA
        $stmtMembro = $pdo->prepare('SELECT id, nome, nome_guerra, posto FROM efetivo WHERE cpf_hash = :hash AND ativo = 1 LIMIT 1');
        $stmtMembro->execute([':hash' => $cpfHash]);
        $membro = $stmtMembro->fetch();

        if (!$membro) {
            return ['pode' => false, 'motivo' => 'CPF não localizado no efetivo credenciado da COMARA.'];
        }

        // 2. Verifica se o CPF já registrou voto no pleito atual
        $stmtVotou = $pdo->prepare('SELECT votou, votou_em, codigo_comprovante FROM eleitores_ciclo WHERE pleito_id = :p AND cpf_hash = :hash LIMIT 1');
        $stmtVotou->execute([':p' => $pleitoId, ':hash' => $cpfHash]);
        $registroVoto = $stmtVotou->fetch();

        if ($registroVoto && (int)$registroVoto['votou'] === 1) {
            return [
                'pode' => false,
                'motivo' => 'Voto já registrado para este CPF neste ciclo eleitoral.',
                'comprovante' => $registroVoto['codigo_comprovante'],
                'data_voto' => $registroVoto['votou_em']
            ];
        }

        return [
            'pode' => true,
            'membro' => $membro,
            'cpf_hash' => $cpfHash
        ];
    }

    /**
     * Deposita as cédulas na urna com garantia matemática de sigilo absoluto
     */
    public static function registrarVotos(
        int $pleitoId,
        string $cpf,
        array $votosPorCategoria,
        ?int $usuarioId = null
    ): array {
        $checagem = self::podeVotar($pleitoId, $cpf);
        if (!$checagem['pode']) {
            throw new RuntimeException($checagem['motivo']);
        }

        $cpfHash = $checagem['cpf_hash'];
        $pdo = Database::getConnection();

        // Validar se foram escolhidos candidatos válidos para as 4 categorias oficiais
        $categoriasObrigatorias = [CAT_GRADUADO, CAT_PRACA, CAT_SPPF, CAT_SPTF];
        if (array_diff(array_keys($votosPorCategoria), $categoriasObrigatorias)) {
            throw new InvalidArgumentException('Categoria de voto inválida.');
        }
        foreach ($categoriasObrigatorias as $cat) {
            if (empty($votosPorCategoria[$cat])) {
                throw new InvalidArgumentException("O voto para a categoria {$cat} é obrigatório.");
            }
        }

        // Gera código de comprovante único do eleitor
        $comprovante = 'COMARA-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(6)));
        $horarioExato = date('Y-m-d H:i:s');
        $horarioArredondado = date('Y-m-d H:00:00'); // Anti-correlação temporal com logs

        $pdo->beginTransaction();

        try {
            // Impede voto após fechamento, inclusive requisições iniciadas antes da transição.
            $stmtFase = $pdo->prepare('SELECT fase_atual, status FROM pleitos WHERE id = :p FOR UPDATE');
            $stmtFase->execute([':p' => $pleitoId]);
            $pleito = $stmtFase->fetch();
            if (!$pleito || (int)$pleito['fase_atual'] !== FASE_3 || $pleito['status'] !== 'em_andamento') {
                throw new RuntimeException('A votação geral não está aberta no momento.');
            }
            foreach ($categoriasObrigatorias as $cat) {
                $ids = array_map('intval', array_column(Efetivo::listarParaUrna($pleitoId, $cat), 'id'));
                $id = filter_var($votosPorCategoria[$cat], FILTER_VALIDATE_INT);
                if ($id === false || !in_array($id, $ids, true)) {
                    throw new InvalidArgumentException('Escolha somente representantes válidos da Fase 2 na categoria correspondente.');
                }
            }
            // 1. Bloqueia e marca no controle de eleitores que o CPF votou
            $stmtLock = $pdo->prepare('
                INSERT INTO `eleitores_ciclo` (`pleito_id`, `usuario_id`, `cpf_hash`, `votou`, `votou_em`, `codigo_comprovante`)
                VALUES (:pleito, :user, :cpf_hash, 1, :horario, :comprovante)
                ON DUPLICATE KEY UPDATE
                    `votou_em` = IF(`votou` = 0, VALUES(`votou_em`), `votou_em`),
                    `codigo_comprovante` = IF(`votou` = 0, VALUES(`codigo_comprovante`), `codigo_comprovante`),
                    `votou` = 1
            ');

            $stmtLock->execute([
                ':pleito'      => $pleitoId,
                ':user'        => $usuarioId,
                ':cpf_hash'    => $cpfHash,
                ':horario'     => $horarioExato,
                ':comprovante' => $comprovante
            ]);

            // Se linhas afetadas for 0 ou se já tinha votado, aborta
            $stmtCheck = $pdo->prepare('SELECT COUNT(*) FROM eleitores_ciclo WHERE pleito_id = :p AND cpf_hash = :hash AND codigo_comprovante = :c');
            $stmtCheck->execute([':p' => $pleitoId, ':hash' => $cpfHash, ':c' => $comprovante]);
            if ((int)$stmtCheck->fetchColumn() === 0) {
                throw new RuntimeException('Concorrência detectada: Voto já computado para este CPF.');
            }

            // 2. Insere as cédulas anônimas na tabela URNA_VOTOS (totalmente desvinculada do eleitor)
            $stmtUrna = $pdo->prepare('
                INSERT INTO `urna_votos` (`pleito_id`, `categoria`, `candidato_id`, `horario_arredondado`)
                VALUES (:pleito, :cat, :candidato, :horario)
            ');

            foreach ($votosPorCategoria as $cat => $candId) {
                $stmtUrna->execute([
                    ':pleito'    => $pleitoId,
                    ':cat'       => $cat,
                    ':candidato' => (int)$candId,
                    ':horario'   => $horarioArredondado
                ]);
            }

            $pdo->commit();

            // Log de auditoria (registra APENAS que um voto legítimo foi computado, sem identificar a cédula)
            AuditoriaService::log('VOTO_COMPUTADO', [
                'pleito_id'   => $pleitoId,
                'comprovante' => $comprovante,
                'horario'     => $horarioExato
            ], $usuarioId);

            return [
                'sucesso'     => true,
                'comprovante' => $comprovante,
                'data_hora'   => $horarioExato
            ];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
