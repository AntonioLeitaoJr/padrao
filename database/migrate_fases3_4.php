<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Execute via CLI.'); }
require_once __DIR__ . '/../config/database.php';
try {
    $pdo = Database::getConnection();
    if (!$pdo->query("SHOW TABLES LIKE 'historico_vencedores'")->fetch()
        || !$pdo->query("SHOW COLUMNS FROM efetivo LIKE 'data_inicio_servico'")->fetch()) {
        throw new RuntimeException('Execute primeiro: php database/migrate_fase2.php');
    }
    $pdo->exec(file_get_contents(__DIR__ . '/fases3_4.sql'));
    // Preserva composição já registrada. Não reativa membros desmarcados em nova execução.
    $pdo->exec('INSERT INTO fase4_composicao(pleito_id,usuario_id,habilitado)
        SELECT a.pleito_id,a.usuario_id,1 FROM fase4_avaliadores a
        LEFT JOIN fase4_composicao c ON c.pleito_id=a.pleito_id AND c.usuario_id=a.usuario_id
        WHERE c.usuario_id IS NULL');
    echo "Migração das Fases 3 e 4 concluída. Nenhum resultado existente foi convertido.\n";
} catch (Throwable $e) { fwrite(STDERR, $e->getMessage() . "\n"); exit(1); }
