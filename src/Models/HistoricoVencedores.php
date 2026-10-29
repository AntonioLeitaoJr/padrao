<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';

class HistoricoVencedores {
    // Histórico manual tem precedência; homologações oficiais também são lidas.
    public static function listar(int $anoPleito): array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT h.*, e.nome_guerra, e.posto FROM historico_vencedores h
            JOIN efetivo e ON e.id = h.candidato_id WHERE h.ano IN (:a, :b)');
        $stmt->execute([':a' => $anoPleito - 2, ':b' => $anoPleito - 1]);
        $dados = [];
        foreach ($stmt->fetchAll() as $h) $dados[$h['ano']][$h['categoria']] = $h;
        $stmt = $pdo->prepare('SELECT h.*, p.ano FROM homologacao_pleito h
            JOIN pleitos p ON p.id = h.pleito_id WHERE p.ano IN (:a, :b) AND p.status = "homologado"');
        $stmt->execute([':a' => $anoPleito - 2, ':b' => $anoPleito - 1]);
        foreach ($stmt->fetchAll() as $h) {
            foreach (['graduado','praca','sppf','sptf'] as $cat) {
                if (!isset($dados[$h['ano']][$cat])) {
                    $dados[$h['ano']][$cat] = ['candidato_id' => $h['vencedor_' . $cat . '_id'], 'origem' => 'homologacao'];
                }
            }
        }
        return $dados;
    }

    public static function enriquecer(array $candidatos, int $pleitoId): array {
        $stmt = Database::getConnection()->prepare('SELECT ano FROM pleitos WHERE id = :p');
        $stmt->execute([':p' => $pleitoId]);
        $ano = $stmt->fetchColumn();
        if (!$ano) throw new InvalidArgumentException('Pleito não encontrado.');
        $vitorias = [];
        foreach (self::listar((int)$ano) as $grupos) {
            foreach ($grupos as $v) {
                $id = (int)$v['candidato_id'];
                $vitorias[$id] = ($vitorias[$id] ?? 0) + 1;
            }
        }
        foreach ($candidatos as &$c) $c['vitorias_ultimos_dois_anos'] = $vitorias[(int)($c['id'] ?? $c['candidato_id'])] ?? 0;
        unset($c);
        return $candidatos;
    }

    public static function salvar(int $anoPleito, array $vencedores, int $usuarioId): void {
        $pdo = Database::getConnection();
        $registros = [];
        foreach ([$anoPleito - 2, $anoPleito - 1] as $ano) {
            foreach (['graduado','praca','sppf','sptf'] as $cat) {
                $id = filter_var($vencedores[$ano][$cat] ?? '', FILTER_VALIDATE_INT);
                if ($id === false || $id <= 0) throw new InvalidArgumentException('Selecione um vencedor de cada grupo para cada ano.');
                $stmt = $pdo->prepare('SELECT id FROM efetivo WHERE id = :id AND categoria = :cat');
                $stmt->execute([':id' => $id, ':cat' => $cat]);
                if (!$stmt->fetch()) throw new InvalidArgumentException('Vencedor não pertence ao grupo informado.');
                $registros[] = [$ano, $cat, $id, $usuarioId];
            }
        }
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('INSERT INTO historico_vencedores (ano,categoria,candidato_id,atualizado_por)
                VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE candidato_id=VALUES(candidato_id), atualizado_por=VALUES(atualizado_por)');
            foreach ($registros as $v) $stmt->execute($v);
            require_once __DIR__ . '/../Services/AuditoriaService.php';
            AuditoriaService::log('HISTORICO_VENCEDORES_ATUALIZADO', ['registros' => $registros], $usuarioId);
            $pdo->commit();
        } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    }
}
