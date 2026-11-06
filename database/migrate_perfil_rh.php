<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(403); exit('Execute via CLI.'); }
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/perfil_rh.php';
try {
    $opcao=$argv[1]??'--check';
    if (!in_array($opcao,['--check','--apply'],true) || count($argv)>2) throw new InvalidArgumentException('Uso: php database/migrate_perfil_rh.php --check|--apply');
    $pdo=Database::getConnection(); $correcao=correcaoPerfilRh($pdo);
    if (!$correcao) { echo "[OK] usuarios.perfil aceita RH. Nenhuma alteração necessária.\n"; exit(0); }
    if ($opcao==='--check') { echo "[PENDENTE] usuarios.perfil não aceita RH. Após backup, execute php database/migrate_perfil_rh.php --apply\n"; exit(2); }
    $pdo->exec($correcao['sql']);
    if (correcaoPerfilRh($pdo)!==null) throw new RuntimeException('A validação final do perfil RH falhou.');
    echo "[OK] Perfil RH habilitado no esquema. Contas, perfis atribuídos e dados eleitorais preservados.\n";
} catch (Throwable $e) { fwrite(STDERR,'[ERRO] '.$e->getMessage()."\n"); exit(1); }
