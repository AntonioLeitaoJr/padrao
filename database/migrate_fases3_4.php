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
    echo "Migração das Fases 3 e 4 concluída. Nenhum resultado existente foi convertido.\n";
} catch (Throwable $e) { fwrite(STDERR, $e->getMessage() . "\n"); exit(1); }
