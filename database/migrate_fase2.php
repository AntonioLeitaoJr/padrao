<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Execute via CLI.'); }
require_once __DIR__ . '/../config/database.php';
$pdo = Database::getConnection();
if (!$pdo->query("SHOW COLUMNS FROM efetivo LIKE 'data_inicio_servico'")->fetch()) {
    $pdo->exec('ALTER TABLE efetivo ADD COLUMN data_inicio_servico DATE NULL');
}
$pdo->exec(file_get_contents(__DIR__ . '/fase2_historico.sql'));
echo "Migração da Fase 2 concluída. Preencha o histórico e as datas de serviço.\n";
