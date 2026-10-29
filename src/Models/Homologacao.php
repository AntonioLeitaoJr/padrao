<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../Services/DesempateService.php';
require_once __DIR__ . '/../Services/AuditoriaService.php';

/**
 * Modelo de Apuração, Voto de Minerva e Homologação Oficial (Fase 5)
 */
class Homologacao {

    /**
     * Apura o resultado geral dos votos da urna eletrônica para todas as 4 categorias,
     * aplicando os 4 critérios automatizados de desempate e verificando minerva.
     */
    public static function apurarResultados(int $pleitoId): array {
        $pdo = Database::getConnection();
        $categorias = [CAT_GRADUADO, CAT_PRACA, CAT_SPPF, CAT_SPTF];
        $resultados = [];

        // Votos de minerva já registrados pelo presidente
        $stmtMin = $pdo->prepare('SELECT * FROM voto_minerva_presidente WHERE pleito_id = :p');
        $stmtMin->execute([':p' => $pleitoId]);
        $minervas = [];
        foreach ($stmtMin->fetchAll() as $vm) {
            $minervas[$vm['categoria']] = $vm;
        }

        foreach ($categorias as $cat) {
            // Contagem de votos na urna anônima
            $sql = "
                SELECT e.id as candidato_id, e.nome, e.nome_guerra, e.posto, e.quadro, e.especialidade,
                       e.setor, e.divisao_sigla, e.funcao, e.saram, e.foto_custom,
                       e.data_admissao_comara, e.data_inicio_servico,
                       COALESCE(MAX(t.nota_tacf), 0) as nota_tacf,
                       COUNT(u.id) as total_votos
                FROM indicados_consolidados i
                INNER JOIN efetivo e ON e.id = i.candidato_id
                LEFT JOIN avaliacoes_tacf t ON t.candidato_id = e.id AND t.pleito_id = :p
                LEFT JOIN urna_votos u ON u.candidato_id = e.id AND u.pleito_id = :p AND u.categoria = :cat
                WHERE i.pleito_id = :p 
                  AND i.categoria = :cat
                  AND i.status_validacao_superior = 'VALIDADO'
                GROUP BY e.id
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([':p' => $pleitoId, ':cat' => $cat]);
            $candidatos = $stmt->fetchAll();

            require_once __DIR__ . '/HistoricoVencedores.php';
            $candidatos = HistoricoVencedores::enriquecer($candidatos, $pleitoId);

            // Aplica ordenação e desempate automatizado
            $apuracao = DesempateService::ordenarCandidatos($candidatos, 'total_votos');
            $ranking = $apuracao['ranking'];
            $requerMinerva = $apuracao['requer_voto_de_minerva'];

            // Se o presidente já deu voto de minerva nesta categoria, define o vencedor escolhido
            $vencedor = null;
            if (isset($minervas[$cat])) {
                $candMinervaId = (int)$minervas[$cat]['candidato_vencedor_id'];
                foreach ($ranking as $idx => $c) {
                    if ((int)$c['candidato_id'] === $candMinervaId) {
                        $vencedor = $c;
                        // Move para o topo do ranking
                        unset($ranking[$idx]);
                        array_unshift($ranking, $vencedor);
                        $requerMinerva = false;
                        break;
                    }
                }
            } elseif (!empty($ranking) && !$requerMinerva) {
                $vencedor = $ranking[0];
            }

            $resultados[$cat] = [
                'categoria'              => CATEGORIAS_PADRAO[$cat],
                'ranking'                => $ranking,
                'vencedor'               => $vencedor,
                'requer_voto_de_minerva' => $requerMinerva,
                'candidatos_empatados'   => $apuracao['candidatos_empatados'],
                'minerva_registrado'     => $minervas[$cat] ?? null
            ];
        }

        // Verifica se já foi homologado
        $stmtHomol = $pdo->prepare('SELECT * FROM homologacao_pleito WHERE pleito_id = :p LIMIT 1');
        $stmtHomol->execute([':p' => $pleitoId]);
        $homol = $stmtHomol->fetch();

        return [
            'categorias'          => $resultados,
            'homologado'          => (bool)$homol,
            'dados_homologacao'   => $homol ?: null
        ];
    }

    /**
     * Registra o Voto de Minerva do Presidente da COMARA
     */
    public static function registrarVotoMinerva(
        int $pleitoId,
        string $categoria,
        int $candidatoId,
        string $justificativa,
        int $presidenteUsuarioId
    ): array {
        $justificativa = trim($justificativa);
        if (mb_strlen($justificativa) < 10) {
            return ['sucesso' => false, 'mensagem' => 'A fundamentação do Voto de Minerva é obrigatória.'];
        }

        $pdo = Database::getConnection();

        $stmt = $pdo->prepare('
            INSERT INTO `voto_minerva_presidente` (
                `pleito_id`, `categoria`, `candidato_vencedor_id`, `justificativa`, `presidente_usuario_id`, `registrado_em`
            ) VALUES (
                :pleito, :cat, :cand, :justif, :pres, NOW()
            ) ON DUPLICATE KEY UPDATE
                `candidato_vencedor_id` = VALUES(`candidato_vencedor_id`),
                `justificativa`         = VALUES(`justificativa`),
                `registrado_em`         = NOW()
        ');

        $stmt->execute([
            ':pleito' => $pleitoId,
            ':cat'    => $categoria,
            ':cand'   => $candidatoId,
            ':justif' => $justificativa,
            ':pres'   => $presidenteUsuarioId
        ]);

        AuditoriaService::log('VOTO_MINERVA_REGISTRADO', [
            'pleito_id'     => $pleitoId,
            'categoria'     => $categoria,
            'candidato_id'  => $candidatoId,
            'justificativa' => $justificativa
        ], $presidenteUsuarioId);

        return ['sucesso' => true, 'mensagem' => 'Voto de Minerva registrado com sucesso pelo Sr. Presidente.'];
    }

    /**
     * Homologação Oficial Definitiva dos Padrões Eleitos
     */
    public static function homologarResultados(
        int $pleitoId,
        int $presidenteUsuarioId,
        string $ata
    ): array {
        $apuracao = self::apurarResultados($pleitoId);

        // Verifica se há alguma categoria ainda sem vencedor ou com empate não resolvido
        foreach ($apuracao['categorias'] as $catKey => $dadosCat) {
            if (empty($dadosCat['vencedor'])) {
                return [
                    'sucesso' => false,
                    'mensagem' => "Não é possível homologar: A categoria '{$dadosCat['categoria']['nome']}' ainda requer resolução de empate via Voto de Minerva."
                ];
            }
        }

        $vGrad  = (int)$apuracao['categorias'][CAT_GRADUADO]['vencedor']['candidato_id'];
        $vPraca = (int)$apuracao['categorias'][CAT_PRACA]['vencedor']['candidato_id'];
        $vSppf  = (int)$apuracao['categorias'][CAT_SPPF]['vencedor']['candidato_id'];
        $vSptf  = (int)$apuracao['categorias'][CAT_SPTF]['vencedor']['candidato_id'];

        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare('
                INSERT INTO `homologacao_pleito` (
                    `pleito_id`, `presidente_usuario_id`,
                    `vencedor_graduado_id`, `vencedor_praca_id`, `vencedor_sppf_id`, `vencedor_sptf_id`,
                    `ata_homologacao`, `data_homologacao`, `bloqueado`
                ) VALUES (
                    :pleito, :pres,
                    :grad, :praca, :sppf, :sptf,
                    :ata, NOW(), 1
                )
            ');

            $stmt->execute([
                ':pleito' => $pleitoId,
                ':pres'   => $presidenteUsuarioId,
                ':grad'   => $vGrad,
                ':praca'  => $vPraca,
                ':sppf'   => $vSppf,
                ':sptf'   => $vSptf,
                ':ata'    => $ata
            ]);

            // Atualiza status do pleito para 'homologado' e bloqueia edições
            $stmtPleito = $pdo->prepare('
                UPDATE pleitos 
                SET status = "homologado", homologado_em = NOW(), homologado_por = :pres 
                WHERE id = :p
            ');
            $stmtPleito->execute([':pres' => $presidenteUsuarioId, ':p' => $pleitoId]);

            // Atualiza histórico de indicações dos eleitos
            $stmtHist = $pdo->prepare('UPDATE efetivo SET indicacoes_anteriores_qtd = indicacoes_anteriores_qtd + 1 WHERE id IN (:g, :pr, :sp, :st)');
            $stmtHist->execute([':g' => $vGrad, ':pr' => $vPraca, ':sp' => $vSppf, ':st' => $vSptf]);

            $pdo->commit();

            AuditoriaService::log('HOMOLOGACAO_OFICIAL_CONCLUIDA', [
                'pleito_id'        => $pleitoId,
                'vencedor_grad'    => $vGrad,
                'vencedor_praca'   => $vPraca,
                'vencedor_sppf'    => $vSppf,
                'vencedor_sptf'    => $vSptf
            ], $presidenteUsuarioId);

            return [
                'sucesso'  => true,
                'mensagem' => 'Pleito homologado com sucesso! Os vencedores oficiais foram proclamados e os resultados foram bloqueados para auditoria.'
            ];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
