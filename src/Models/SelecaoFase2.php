<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../Services/AuditoriaService.php';

/**
 * Modelo de Seleção pelos Chefes de Divisão e Assessoria (Fase 2)
 * Validação rigorosa: EXATAMENTE 6 candidatos por categoria (nem mais, nem menos)
 */
class SelecaoFase2 {

    /**
     * Retorna os IDs dos candidatos selecionados por um determinado órgão em uma categoria
     */
    public static function obterSelecionados(int $pleitoId, string $orgaoSigla, string $categoria): array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            SELECT candidato_id 
            FROM selecao_fase2 
            WHERE pleito_id = :p AND orgao_sigla = :org AND categoria = :cat
        ');
        $stmt->execute([':p' => $pleitoId, ':org' => $orgaoSigla, ':cat' => $categoria]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Salva as seleções do chefe de divisão ou representante das assessorias
     * Regra: Exatamente 1 candidato por categoria onde houver candidatos disponíveis
     */
    public static function salvarSelecao(
        int $pleitoId,
        string $orgaoSigla,
        int $chefeUsuarioId,
        array $selecoesPorCategoria
    ): array {
        require_once ROOT_PATH . '/src/Models/Efetivo.php';
        $categorias = [CAT_GRADUADO, CAT_PRACA, CAT_SPPF, CAT_SPTF];
        $totalIndicados = 0;

        // Validação: exatamente 1 por categoria onde houver candidatos
        foreach ($categorias as $cat) {
            $selecionados = $selecoesPorCategoria[$cat] ?? [];
            if (!is_array($selecionados)) {
                $selecionados = !empty($selecionados) ? [$selecionados] : [];
            }
            $selecionados = array_unique(array_filter(array_map('intval', (array)$selecionados)));

            $disponiveis = Efetivo::listarParaSelecaoFase2($pleitoId, $cat, $orgaoSigla);
            if (!empty($disponiveis)) {
                if (count($selecionados) !== QTD_SELECAO_FASE2) {
                    $nomeCat = CATEGORIAS_PADRAO[$cat]['nome'];
                    $qtd = count($selecionados);
                    return [
                        'sucesso' => false,
                        'mensagem' => "Regra regimental: É obrigatório selecionar exatamente 1 candidato na categoria '{$nomeCat}'. Atualmente você selecionou {$qtd}."
                    ];
                }
                $totalIndicados += count($selecionados);
            }
        }

        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            // Remove seleções anteriores deste órgão para re-gravação
            $stmtDel = $pdo->prepare('DELETE FROM selecao_fase2 WHERE pleito_id = :p AND orgao_sigla = :org');
            $stmtDel->execute([':p' => $pleitoId, ':org' => $orgaoSigla]);

            $stmtIns = $pdo->prepare('
                INSERT INTO `selecao_fase2` (`pleito_id`, `orgao_sigla`, `chefe_usuario_id`, `categoria`, `candidato_id`, `created_at`)
                VALUES (:pleito, :org, :chefe, :cat, :cand, NOW())
            ');

            foreach ($categorias as $cat) {
                $selecionados = $selecoesPorCategoria[$cat] ?? [];
                if (!is_array($selecionados)) {
                    $selecionados = !empty($selecionados) ? [$selecionados] : [];
                }
                $selecionados = array_unique(array_filter(array_map('intval', (array)$selecionados)));

                foreach ($selecionados as $candId) {
                    $stmtIns->execute([
                        ':pleito' => $pleitoId,
                        ':org'    => $orgaoSigla,
                        ':chefe'  => $chefeUsuarioId,
                        ':cat'    => $cat,
                        ':cand'   => $candId
                    ]);
                }
            }

            $pdo->commit();

            AuditoriaService::log('SELECAO_FASE2_CONCLUIDA', [
                'pleito_id'       => $pleitoId,
                'orgao_sigla'     => $orgaoSigla,
                'total_indicados' => $totalIndicados
            ], $chefeUsuarioId);

            $nomeOrgao = ($orgaoSigla === 'ASSESSORIAS') ? 'Assessorias Unificadas' : "Divisão {$orgaoSigla}";
            return [
                'sucesso'  => true,
                'mensagem' => "Seleção oficial da {$nomeOrgao} registrada com sucesso (1 indicado por categoria)."
            ];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Retorna o status de preenchimento da Fase 2 para os 6 órgãos autorizados (5 Divisões + Assessorias)
     */
    public static function relatorioProgressoOrgaos(int $pleitoId): array {
        $pdo = Database::getConnection();
        $relatorio = [];

        $todosOrgaos = array_merge(
            ORGAOS_SELECAO_FASE2['DIVISOES'],
            ORGAOS_SELECAO_FASE2['ASSESSORIAS']
        );

        foreach ($todosOrgaos as $sigla => $nome) {
            $stmt = $pdo->prepare('
                SELECT categoria, COUNT(DISTINCT candidato_id) as qtd 
                FROM selecao_fase2 
                WHERE pleito_id = :p AND orgao_sigla = :sigla 
                GROUP BY categoria
            ');
            $stmt->execute([':p' => $pleitoId, ':sigla' => $sigla]);
            $contagens = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            // Considera concluído se selecionou pelo menos 1 nas categorias militares principais
            $gradQtd  = $contagens[CAT_GRADUADO] ?? 0;
            $pracaQtd = $contagens[CAT_PRACA] ?? 0;
            $concluido = ($gradQtd >= 1 && $pracaQtd >= 1);

            $relatorio[$sigla] = [
                'nome'       => $nome,
                'contagens'  => $contagens,
                'concluido'  => $concluido
            ];
        }

        return $relatorio;
    }
}
