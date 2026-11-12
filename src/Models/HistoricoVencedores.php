<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';

class HistoricoVencedores {
    private const CATEGORIAS = ['graduado','praca','sppf','sptf'];

    private static function permiteManual(PDO $pdo): bool {
        return (bool)$pdo->query("SHOW TABLES LIKE 'historico_vencedores_manuais'")->fetchColumn();
    }

    // Registros corrigidos manualmente têm precedência sobre as homologações anteriores.
    // Vencedores nominais não se tornam candidatos no efetivo vigente.
    public static function listar(int $anoPleito): array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT h.*, e.nome_guerra, e.posto FROM historico_vencedores h
            JOIN efetivo e ON e.id = h.candidato_id WHERE h.ano IN (:a, :b)');
        $stmt->execute([':a' => $anoPleito - 2, ':b' => $anoPleito - 1]);
        $dados = [];
        foreach ($stmt->fetchAll() as $h) $dados[$h['ano']][$h['categoria']] = $h;

        // A migração aditiva pode ser instalada após a publicação sem interromper as telas.
        if (self::permiteManual($pdo)) {
            $stmt = $pdo->prepare('SELECT ano, categoria, nome_vencedor, atualizado_por, updated_at
                FROM historico_vencedores_manuais WHERE ano IN (:a, :b)');
            $stmt->execute([':a' => $anoPleito - 2, ':b' => $anoPleito - 1]);
            foreach ($stmt->fetchAll() as $h) {
                $dados[$h['ano']][$h['categoria']] = [
                    'candidato_id' => null,
                    'nome_manual' => $h['nome_vencedor'],
                    'origem' => 'manual',
                    'atualizado_por' => $h['atualizado_por'],
                    'updated_at' => $h['updated_at']
                ];
            }
        }

        $stmt = $pdo->prepare('SELECT h.*, p.ano FROM homologacao_pleito h
            JOIN pleitos p ON p.id = h.pleito_id WHERE p.ano IN (:a, :b) AND p.status = "homologado"');
        $stmt->execute([':a' => $anoPleito - 2, ':b' => $anoPleito - 1]);
        foreach ($stmt->fetchAll() as $h) {
            foreach (self::CATEGORIAS as $cat) {
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
                // Histórico informado apenas pelo nome não tem ID confiável para
                // aplicar desempate a um integrante atual do efetivo.
                $id = (int)($v['candidato_id'] ?? 0);
                if ($id > 0) $vitorias[$id] = ($vitorias[$id] ?? 0) + 1;
            }
        }
        foreach ($candidatos as &$c) $c['vitorias_ultimos_dois_anos'] = $vitorias[(int)($c['id'] ?? $c['candidato_id'])] ?? 0;
        unset($c);
        return $candidatos;
    }

    public static function salvar(int $anoPleito, array $vencedores, int $usuarioId, array $nomesManuais = []): void {
        $pdo = Database::getConnection();
        $suporteManual = self::permiteManual($pdo);
        $registros = [];
        foreach ([$anoPleito - 2, $anoPleito - 1] as $ano) {
            foreach (self::CATEGORIAS as $cat) {
                $valor = $vencedores[$ano][$cat] ?? null;
                if (!is_string($valor) && !is_int($valor)) {
                    throw new InvalidArgumentException('Selecione um vencedor de cada grupo para cada ano.');
                }
                if ($valor === '__manual__') {
                    if (!$suporteManual) {
                        throw new InvalidArgumentException('Cadastro nominal indisponível. Solicite à STI a migração database/migrate_fase2.php antes de salvar.');
                    }
                    $bruto = $nomesManuais[$ano][$cat] ?? null;
                    if (!is_string($bruto)) throw new InvalidArgumentException('Informe o nome do vencedor não cadastrado.');
                    $nome = preg_replace('/\s+/u', ' ', trim($bruto));
                    if (!is_string($nome) || mb_strlen($nome) < 3 || mb_strlen($nome) > 255) {
                        throw new InvalidArgumentException('Informe um nome válido, com 3 a 255 caracteres.');
                    }
                    // Se já estiver na base, vincular pelo ID é indispensável ao desempate.
                    $existe = $pdo->prepare('SELECT id FROM efetivo WHERE categoria = :cat
                        AND (TRIM(nome) = :nome_completo OR TRIM(nome_guerra) = :nome_guerra) LIMIT 1');
                    $existe->execute([':cat' => $cat, ':nome_completo' => $nome, ':nome_guerra' => $nome]);
                    if ($existe->fetch()) {
                        throw new InvalidArgumentException("O vencedor de {$ano} já consta no efetivo. Selecione-o na lista para preservar o critério de desempate.");
                    }
                    $registros[] = ['ano' => $ano, 'cat' => $cat, 'id' => null, 'nome' => $nome];
                    continue;
                }
                $id = filter_var($valor, FILTER_VALIDATE_INT);
                if ($id === false || $id <= 0) throw new InvalidArgumentException('Selecione um vencedor de cada grupo para cada ano.');
                $stmt = $pdo->prepare('SELECT id FROM efetivo WHERE id = :id AND categoria = :cat');
                $stmt->execute([':id' => $id, ':cat' => $cat]);
                if (!$stmt->fetch()) throw new InvalidArgumentException('Vencedor não pertence ao grupo informado.');
                $registros[] = ['ano' => $ano, 'cat' => $cat, 'id' => $id, 'nome' => null];
            }
        }

        $pdo->beginTransaction();
        try {
            $salvarVinculado = $pdo->prepare('INSERT INTO historico_vencedores (ano,categoria,candidato_id,atualizado_por)
                VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE candidato_id=VALUES(candidato_id), atualizado_por=VALUES(atualizado_por)');
            $apagarVinculado = $pdo->prepare('DELETE FROM historico_vencedores WHERE ano=? AND categoria=?');
            if ($suporteManual) {
                $salvarManual = $pdo->prepare('INSERT INTO historico_vencedores_manuais (ano,categoria,nome_vencedor,atualizado_por)
                    VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE nome_vencedor=VALUES(nome_vencedor), atualizado_por=VALUES(atualizado_por)');
                $apagarManual = $pdo->prepare('DELETE FROM historico_vencedores_manuais WHERE ano=? AND categoria=?');
            }
            foreach ($registros as $r) {
                if ($r['id'] === null) {
                    $salvarManual->execute([$r['ano'], $r['cat'], $r['nome'], $usuarioId]);
                    $apagarVinculado->execute([$r['ano'], $r['cat']]);
                } else {
                    $salvarVinculado->execute([$r['ano'], $r['cat'], $r['id'], $usuarioId]);
                    if ($suporteManual) $apagarManual->execute([$r['ano'], $r['cat']]);
                }
            }
            require_once __DIR__ . '/../Services/AuditoriaService.php';
            AuditoriaService::log('HISTORICO_VENCEDORES_ATUALIZADO', ['registros' => $registros], $usuarioId);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}
