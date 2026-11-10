<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(403); exit('Execute via CLI.'); }
require_once __DIR__.'/../config/database.php';
try {
    $opcao=$argv[1]??'--check';
    if (!in_array($opcao,['--check','--apply'],true) || count($argv)>2) throw new InvalidArgumentException('Uso: php database/migrate_saram_unico.php --check|--apply');
    $pdo=Database::getConnection();
    $duplicados=$pdo->query("SELECT TRIM(saram) AS saram,COUNT(*) AS quantidade FROM efetivo WHERE NULLIF(TRIM(saram),'') IS NOT NULL GROUP BY TRIM(saram) HAVING COUNT(*)>1")->fetchAll();
    if ($duplicados) {
        $q=$pdo->prepare('SELECT e.id,e.nome,e.ativo,u.id AS usuario_id FROM efetivo e LEFT JOIN usuarios u ON u.efetivo_id=e.id WHERE TRIM(e.saram)=? ORDER BY e.id');
        foreach ($duplicados as $d) { $q->execute([$d['saram']]); echo json_encode(['saram'=>$d['saram'],'registros'=>$q->fetchAll()],JSON_UNESCAPED_UNICODE)."\n"; }
        fwrite(STDERR,"[BLOQUEADO] Há matrículas/SARAM duplicados, inclusive inativos. Nenhum registro foi alterado. Confira os IDs e vínculos eleitorais com a Administração antes de corrigir os identificadores.\n"); exit(2);
    }
    $indices=$pdo->query('SHOW INDEX FROM efetivo')->fetchAll(); $unico=false;
    $grupos=[]; foreach ($indices as $i) if (!(int)$i['Non_unique']) $grupos[$i['Key_name']][]=$i['Column_name'];
    foreach ($grupos as $cols) if ($cols===['saram_unico']) $unico=true;
    if ($unico) { echo "[OK] Índice único já instalado; sem duplicidades.\n"; exit(0); }
    if ($opcao==='--check') { echo "[PENDENTE] Sem duplicidades. Após backup, execute php database/migrate_saram_unico.php --apply\n"; exit(2); }
    if ($pdo->query("SHOW COLUMNS FROM efetivo LIKE 'saram_unico'")->fetch()) throw new RuntimeException('Coluna saram_unico já existe sem índice único. Solicite conferência do esquema ao STI.');
    $pdo->exec("ALTER TABLE efetivo ADD COLUMN saram_unico VARCHAR(20) GENERATED ALWAYS AS (NULLIF(TRIM(saram), '')) STORED, ADD UNIQUE KEY uq_efetivo_saram(saram_unico)");
    echo "[OK] Unicidade de matrícula/SARAM instalada. Identificadores vazios permitidos; zeros iniciais preservados.\n";
} catch (Throwable $e) { fwrite(STDERR,'[ERRO] '.$e->getMessage()."\n"); exit(1); }
