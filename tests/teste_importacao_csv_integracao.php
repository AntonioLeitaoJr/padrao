<?php
declare(strict_types=1);
require_once __DIR__.'/../src/Services/ImportacaoEfetivoService.php';

if (getenv('CSV_TEST_DB')!=='1' || !str_ends_with(DB_NAME,'_test')) {
    throw new RuntimeException('Use banco descartável _test e CSV_TEST_DB=1.');
}
$pdo=Database::getConnection();
if ($pdo->query('SHOW TABLES')->fetch()) throw new RuntimeException('Banco de testes deve estar vazio.');
$pdo->exec(file_get_contents(__DIR__.'/../database/schema.sql'));
$pdo->exec("INSERT INTO usuarios(login,senha_hash,perfil,ativo)
            VALUES('admin', 'hash-de-teste', 'ADMIN', 1)");
$pdo->exec("INSERT INTO pleitos(ano,titulo,status,fase_atual)
            VALUES(2026,'Pleito em planejamento','planejamento',1)");
$checks=0;
function verificarCsv(bool $ok,string $descricao): void {
    global $checks;
    if (!$ok) throw new RuntimeException('Falhou: '.$descricao);
    $checks++;
    echo 'OK: '.$descricao."\n";
}
function falhaCsv(callable $fn,string $descricao): void {
    try { $fn(); } catch (InvalidArgumentException|RuntimeException $e) {
        verificarCsv(true,$descricao); return;
    }
    throw new RuntimeException('A operação deveria ter sido bloqueada: '.$descricao);
}
function gerarCsv(array $linhas): string {
    $path=tempnam(sys_get_temp_dir(),'comara-csv-');
    $fp=fopen($path,'wb');
    fputcsv($fp,['SARAM','CPF','NOME','NOME_GUERRA','POSTO','QUADRO',
        'ESPECIALIDADE','SETOR','DIVISAO','CATEGORIA','VINCULO','TELEFONE','ATIVO'],',','"','\\');
    foreach ($linhas as $row) fputcsv($fp,$row,',','"','\\');
    fclose($fp);
    return $path;
}
$rows=[];
for ($i=1;$i<=484;$i++) {
    $civil=$i%5===0;
    $rows[]=[
        sprintf('%07d',1000000+$i),sprintf('%011d',12345000000+$i),
        $i===1 ? "PESSOA,\nTESTE ESPECIAL" : 'PESSOA DE TESTE '.$i,
        'PESSOA '.$i,$civil?'CV':'1S',
        $civil?'PC-PERM':'QSS','CIV','SETOR', 'DA',
        $civil?'sppf':'graduado',
        $civil?'CIVIL_PERMANENTE':'MILITAR_CARREIRA','',
        $i<=11?'0':'1'
    ];
}
$path=gerarCsv($rows);
$analise=ImportacaoEfetivoService::analisar($path);
verificarCsv(count($analise['linhas'])===484,'CSV de 484 pessoas analisado integralmente');
verificarCsv($analise['ativos']===473 && $analise['inativos']===11,'11 inativos identificados antes de gravar');
verificarCsv($analise['linhas'][0]['dados']['nome']==="PESSOA,\nTESTE ESPECIAL",'texto entre aspas com vírgula e quebra de linha preservado');
falhaCsv(fn()=>ImportacaoEfetivoService::importar($path,null,485),'quantidade esperada divergente impede gravação');
verificarCsv((int)$pdo->query('SELECT COUNT(*) FROM efetivo')->fetchColumn()===0,'pré-validação não grava nada');

$result=ImportacaoEfetivoService::importar($path,null,484,$analise['sha256']);
verificarCsv($result['total']===484 && $result['inseridos']===484 && $result['atualizados']===0,'importação completa insere 484 registros');
verificarCsv((int)$pdo->query('SELECT COUNT(*) FROM efetivo')->fetchColumn()===484,'banco possui os 484 integrantes');
verificarCsv((int)$pdo->query('SELECT COUNT(*) FROM efetivo WHERE ativo=1')->fetchColumn()===473,'473 integrantes ficam ativos');
verificarCsv((int)$pdo->query('SELECT COUNT(*) FROM efetivo WHERE ativo=0')->fetchColumn()===11,'11 integrantes ficam inativos');
verificarCsv((int)$pdo->query('SELECT COUNT(*) FROM usuarios WHERE efetivo_id IS NOT NULL')->fetchColumn()===484,'484 contas vinculadas sem duplicação');
verificarCsv((int)$pdo->query('SELECT COUNT(*) FROM usuarios WHERE ativo=0')->fetchColumn()===11,'inativos também não podem entrar pela conta');
verificarCsv(is_file(ROOT_PATH.'/backups/daily/'.$result['backup']),'backup anterior preservado em disco');
verificarCsv((int)$pdo->query("SELECT COUNT(*) FROM logs_auditoria WHERE acao='IMPORTACAO_CSV_EFETIVO'")->fetchColumn()===1,'importação auditada');
$first=(int)$pdo->query("SELECT id FROM efetivo WHERE saram='1000001'")->fetchColumn();
$pdo->exec("UPDATE usuarios SET perfil='RH' WHERE efetivo_id=".$first);
$again=ImportacaoEfetivoService::importar($path,null,484,$analise['sha256']);
verificarCsv($again['inseridos']===0 && $again['atualizados']===484,'reimportação é idempotente por matrícula');
verificarCsv((int)$pdo->query('SELECT COUNT(*) FROM usuarios WHERE efetivo_id IS NOT NULL')->fetchColumn()===484,'reimportação não duplica usuários');
verificarCsv($pdo->query('SELECT perfil FROM usuarios WHERE efetivo_id='.$first)->fetchColumn()==='RH','perfil RH existente é preservado');
verificarCsv((int)$pdo->query("SELECT COUNT(*) FROM efetivo WHERE saram='1000001'")->fetchColumn()===1,'identificador permanece único');
$rowsConflito=$rows;
$rowsConflito[0][2]='ALTERACAO NAO DEVE PERSISTIR';
$aux=$rowsConflito[1][1];$rowsConflito[1][1]=$rowsConflito[2][1];$rowsConflito[2][1]=$aux;
$pathConflito=gerarCsv($rowsConflito);
falhaCsv(fn()=>ImportacaoEfetivoService::importar($pathConflito,null,484),'CPF e matrícula apontando para pessoas diferentes bloqueiam toda carga');
verificarCsv($pdo->query('SELECT nome FROM efetivo WHERE id='.$first)->fetchColumn()==="PESSOA,\nTESTE ESPECIAL",'falha na segunda linha desfaz alteração da primeira');
$rowsInvalidos=$rows;
$rowsInvalidos[13][9]='categoria_inesperada';
$pathInvalido=gerarCsv($rowsInvalidos);
falhaCsv(fn()=>ImportacaoEfetivoService::importar($pathInvalido),'categoria inválida nunca gera carga parcial');
$rowsRepetidos=$rows;
$rowsRepetidos[13][0]=$rowsRepetidos[12][0];
$pathRepetido=gerarCsv($rowsRepetidos);
falhaCsv(fn()=>ImportacaoEfetivoService::importar($pathRepetido),'matrícula repetida no CSV rejeitada');
falhaCsv(fn()=>ImportacaoEfetivoService::importar($path,null,484,str_repeat('0',64)),'SHA-256 incorreto impede gravação');
$pdo->exec("UPDATE pleitos SET status='em_andamento',fase_atual=3 WHERE ano=2026");
falhaCsv(fn()=>ImportacaoEfetivoService::importar($path,null,484,$analise['sha256']),'votação Fase 3 bloqueia importação');
$pdo->exec("UPDATE pleitos SET status='homologado',fase_atual=5 WHERE ano=2026");
falhaCsv(fn()=>ImportacaoEfetivoService::importar($path,null,484,$analise['sha256']),'pleito homologado bloqueia importação');
foreach ([$path,$pathConflito,$pathInvalido,$pathRepetido] as $tmp) @unlink($tmp);
echo $checks." verificações da importação CSV aprovadas.\n";
