<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../Services/AuditoriaService.php';

/**
 * Modelo da Ficha Digital de Indicação a Padrão (Fase 1)
 * Validação estrita de notas (1 a 5 inteiros), serviço de escala militar e integridade criptográfica
 */
class FichaIndicacao {

    public static function salvarFicha(array $dados, int $avaliadorUsuarioId): array {
        $pleitoId    = (int)($dados['pleito_id'] ?? 0);
        $candidatoId = (int)($dados['candidato_id'] ?? 0);
        $tipoAval    = $dados['tipo_avaliacao'] ?? 'OBRIGATORIA_CHEFE';

        if (!$pleitoId || !$candidatoId) {
            return ['sucesso' => false, 'mensagem' => 'Pleito e Candidato são obrigatórios.'];
        }

        $candidato = Efetivo::getPorId($candidatoId);
        if (!$candidato) {
            return ['sucesso' => false, 'mensagem' => 'Candidato não localizado.'];
        }

        $isMilitar = in_array($candidato['categoria'], ['graduado', 'praca'], true);
        $isNob = (!empty($dados['is_nob']) && (int)$dados['is_nob'] === 1) ? 1 : 0;
        $status = in_array($dados['status'] ?? '', ['preenchido', 'concluido'], true) ? $dados['status'] : 'concluido';

        if ($isNob === 1) {
            $status = 'concluido';
            $servicoEscala = null;
            $criterios = [
                'eficiencia_eficacia'        => null,
                'conhecimento_especialidade' => null,
                'iniciativa_adaptabilidade'  => null,
                'conduta_pontuacao'          => null,
                'apresentacao_pessoal'       => null,
                'relacionamento_trabalho'    => null,
                'lideranca'                  => null
            ];
            $condutaFichas = '';
            $justificativa = 'Não Observado (NOB)';
            $media = 0.00;
        } else {
            // Validação de Serviço de Escala (obrigatório para militares: nota inteira de 1 a 5)
            $servicoEscala = null;
            if ($isMilitar) {
                $notaEscala = (int)($dados['servico_escala'] ?? 0);
                if ($notaEscala < 1 || $notaEscala > 5) {
                    return ['sucesso' => false, 'mensagem' => 'A nota do Serviço de Escala deve variar exclusivamente entre 1 e 5.'];
                }
                $servicoEscala = (string)$notaEscala;
            }

            // Validação dos 7 Critérios Avaliativos (Valores exclusivamente inteiros de 1 a 5)
            $criterios = [
                'eficiencia_eficacia'        => (int)($dados['eficiencia_eficacia'] ?? 0),
                'conhecimento_especialidade' => (int)($dados['conhecimento_especialidade'] ?? 0),
                'iniciativa_adaptabilidade'  => (int)($dados['iniciativa_adaptabilidade'] ?? 0),
                'conduta_pontuacao'          => (int)($dados['conduta_pontuacao'] ?? 0),
                'apresentacao_pessoal'       => (int)($dados['apresentacao_pessoal'] ?? 0),
                'relacionamento_trabalho'    => (int)($dados['relacionamento_trabalho'] ?? 0),
                'lideranca'                  => (int)($dados['lideranca'] ?? 0)
            ];

            foreach ($criterios as $campo => $valor) {
                if ($valor < 1 || $valor > 5) {
                    return [
                        'sucesso' => false,
                        'mensagem' => "A nota do critério {$campo} deve variar exclusivamente entre 1 e 5."
                    ];
                }
            }

            $condutaFichas = trim($dados['conduta_fichas'] ?? '');
            $justificativa = trim($dados['justificativa'] ?? '');

            // Cálculo da média aritmética dos critérios
            // Para militares: 8 critérios (7 regimentais + 1 de serviço de escala)
            // Para civis: 7 critérios regimentais
            if ($isMilitar) {
                $media = (array_sum($criterios) + (int)$servicoEscala) / 8;
            } else {
                $media = array_sum($criterios) / count($criterios);
            }
        }

        // Assinatura e Hash de integridade dos dados
        $agora = date('Y-m-d H:i:s');
        $stringIntegridade = "{$pleitoId}|{$candidatoId}|{$avaliadorUsuarioId}|{$isNob}|{$status}|" . implode('|', array_map(fn($v) => (string)($v ?? 'NULL'), $criterios)) . "|{$justificativa}|{$agora}";
        $hashIntegridade = hash('sha256', $stringIntegridade);

        $pdo = Database::getConnection();

        $stmt = $pdo->prepare('
            INSERT INTO `fichas_indicacao` (
                `pleito_id`, `candidato_id`, `avaliador_id`, `tipo_avaliacao`,
                `is_nob`, `status`, `servico_escala`, `eficiencia_eficacia`, `conhecimento_especialidade`,
                `iniciativa_adaptabilidade`, `conduta_pontuacao`, `conduta_fichas`,
                `apresentacao_pessoal`, `relacionamento_trabalho`, `lideranca`,
                `justificativa`, `media_calculada`, `hash_integridade`, `created_at`
            ) VALUES (
                :pleito, :candidato, :avaliador, :tipo,
                :is_nob, :status, :escala, :efic, :conhec,
                :inic, :conduta, :fichas,
                :apres, :relac, :lider,
                :justif, :media, :hash, :created
            ) ON DUPLICATE KEY UPDATE
                `is_nob`                    = VALUES(`is_nob`),
                `status`                    = VALUES(`status`),
                `servico_escala`            = VALUES(`servico_escala`),
                `eficiencia_eficacia`        = VALUES(`eficiencia_eficacia`),
                `conhecimento_especialidade` = VALUES(`conhecimento_especialidade`),
                `iniciativa_adaptabilidade`  = VALUES(`iniciativa_adaptabilidade`),
                `conduta_pontuacao`          = VALUES(`conduta_pontuacao`),
                `conduta_fichas`             = VALUES(`conduta_fichas`),
                `apresentacao_pessoal`       = VALUES(`apresentacao_pessoal`),
                `relacionamento_trabalho`    = VALUES(`relacionamento_trabalho`),
                `lideranca`                  = VALUES(`lideranca`),
                `justificativa`              = VALUES(`justificativa`),
                `media_calculada`            = VALUES(`media_calculada`),
                `hash_integridade`           = VALUES(`hash_integridade`),
                `updated_at`                 = VALUES(`created_at`)
        ');

        $stmt->execute([
            ':pleito'    => $pleitoId,
            ':candidato' => $candidatoId,
            ':avaliador' => $avaliadorUsuarioId,
            ':tipo'      => $tipoAval,
            ':is_nob'    => $isNob,
            ':status'    => $status,
            ':escala'    => $servicoEscala,
            ':efic'      => $criterios['eficiencia_eficacia'],
            ':conhec'    => $criterios['conhecimento_especialidade'],
            ':inic'      => $criterios['iniciativa_adaptabilidade'],
            ':conduta'   => $criterios['conduta_pontuacao'],
            ':fichas'    => $condutaFichas,
            ':apres'     => $criterios['apresentacao_pessoal'],
            ':relac'     => $criterios['relacionamento_trabalho'],
            ':lider'     => $criterios['lideranca'],
            ':justif'    => $justificativa,
            ':media'     => $media,
            ':hash'      => $hashIntegridade,
            ':created'   => $agora
        ]);

        AuditoriaService::log('AVALIACAO_FASE1_REGISTRADA', [
            'pleito_id'    => $pleitoId,
            'candidato_id' => $candidatoId,
            'tipo'         => $tipoAval,
            'status'       => $status,
            'media'        => round($media, 2)
        ], $avaliadorUsuarioId);

        $msgSucesso = ($isNob === 1) 
            ? 'Ficha registrada com sucesso como Não Observado (NOB).' 
            : ($status === 'preenchido'
                ? 'Ficha salva com sucesso! O status foi atualizado para PREENCHIDO (você ainda pode editá-la antes de enviar).'
                : 'Ficha concluída e enviada com sucesso! O status é CONCLUÍDO e a edição foi bloqueada.');

        return [
            'sucesso'  => true,
            'is_nob'   => ($isNob === 1),
            'status'   => $status,
            'media'    => round($media, 2),
            'mensagem' => $msgSucesso
        ];
    }

    public static function getFicha(int $pleitoId, int $candidatoId, int $avaliadorId): ?array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM fichas_indicacao WHERE pleito_id = :p AND candidato_id = :c AND avaliador_id = :a LIMIT 1');
        $stmt->execute([':p' => $pleitoId, ':c' => $candidatoId, ':a' => $avaliadorId]);
        $res = $stmt->fetch();
        return $res ?: null;
    }
}
