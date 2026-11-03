<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Execute via CLI.'); }
require_once __DIR__.'/../src/Models/EstadoMaior.php';
try {
    EstadoMaior::verificarEstrutura();
    Database::getConnection()->exec(file_get_contents(__DIR__.'/fase5.sql'));
    echo "Migração da Fase 5 concluída. Homologações anteriores preservadas sem conversão.\n";
} catch (Throwable $e) { fwrite(STDERR,$e->getMessage()."\n"); exit(1); }
