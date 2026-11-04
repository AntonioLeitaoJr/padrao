<?php
declare(strict_types=1);
require __DIR__.'/teste_fase5_integracao.php';

// Exercita cabeçalho real: mensagens flash não podem sobrescrever o erro da página.
function renderizarFase4Deploy(?string $erro, ?array $ciclo, ?string $flash=null): string {
    $_SESSION['user']=['id'=>1,'perfil'=>PERFIL_ADMIN,'posto'=>'','nome_guerra'=>'Admin'];
    unset($_SESSION['flash']);
    if ($flash !== null) flash_message('erro',$flash,'danger');
    $situacao=[]; $candidatos=[]; $avaliacao=[]; $progresso=[]; $rascunho=[];
    $podeConfirmar=false; $confirmado=false;
    set_error_handler(function($severity,$message,$file,$line) { throw new ErrorException($message,0,$severity,$file,$line); });
    ob_start();
    try { require ROOT_PATH.'/views/fase4/index.php'; return ob_get_contents(); }
    finally { ob_end_clean(); restore_error_handler(); }
}
foreach ([null,'Mensagem flash independente'] as $flash) {
    $html=renderizarFase4Deploy('Atualização do banco necessária.',null,$flash);
    check(str_contains($html,'Atualização do banco necessária.'),'preserva diagnóstico com/sem flash');
    check(!str_contains($html,'Urna encerrada; Fase 4 bloqueada.'),'não inventa empate quando ciclo não existe');
}
check(str_contains(renderizarFase4Deploy(null,null),'Aguarde o encerramento da Fase 3'),'ciclo ausente sem warning');
check(str_contains(renderizarFase4Deploy(null,['aberto_em'=>null]),'Urna encerrada; Fase 4 bloqueada.'),'corte pendente mantém bloqueio');
check(str_contains(renderizarFase4Deploy(null,['aberto_em'=>'2026-11-04']),'Consulta somente'),'ciclo aberto mantém consulta');

$pdo->exec("INSERT INTO pleitos(id,ano,titulo,fase_atual,status) VALUES(99,2099,'Regressão deploy',3,'em_andamento')");
$pdo->exec('RENAME TABLE fase4_ciclos TO fase4_ciclos_backup_teste');
try {
    $html=executarControllerTeste('Fase3Controller','index',$pres);
    check(str_contains($html,'Atualização do banco necessária.'),'urna identifica migração pendente');
    check(!str_contains($html,'id="formUrna"'),'migração ausente nunca libera voto');
    $html=executarControllerTeste('Fase4Controller','index',$pres);
    check(str_contains($html,'Atualização do banco necessária.'),'Fase 4 preserva diagnóstico da migração');
    $pdo->exec('UPDATE pleitos SET fase_atual=2 WHERE id=99');
    $html=executarControllerTeste('Fase3Controller','index',$pres);
    check(str_contains($html,'A Urna Geral não está aberta'),'fase anterior exibe urna fechada');
} finally { $pdo->exec('RENAME TABLE fase4_ciclos_backup_teste TO fase4_ciclos'); }
// Reaplicar migrações não apaga votos, confirmações ou resultados existentes.
$antes=[];
foreach (['urna_votos','fase4_confirmacoes','fase5_resultados'] as $t) $antes[$t]=$pdo->query("SELECT * FROM $t")->fetchAll();
require __DIR__.'/../database/migrate_fase2.php';
require __DIR__.'/../database/migrate_fases3_4.php';
require __DIR__.'/../database/migrate_fase5.php';
foreach ($antes as $t=>$linhas) check($linhas===$pdo->query("SELECT * FROM $t")->fetchAll(),'migrações preservam '.$t);

// POST real em subprocesso: preserva CSRF e captura somente a mensagem exibida.
function executarPostDeploy(string $classe,string $metodo,array $post): array {
    $script=tempnam(sys_get_temp_dir(),'comara-post-');
    $code='<?php require '.var_export(ROOT_PATH.'/src/Controllers/'.$classe.'.php',true).'; '
        .'$_SESSION["user"]=["id"=>1,"perfil"=>"ADMIN","posto"=>"Cel","nome_guerra"=>"Teste"]; '
        .'$_SESSION["csrf_token"]="token-teste"; $_POST='.var_export($post,true).'; $_SERVER["REQUEST_METHOD"]="POST"; '
        .'set_error_handler(function($s,$m,$f,$l){throw new ErrorException($m,0,$s,$f,$l);}); '
        .'register_shutdown_function(function(){echo json_encode($_SESSION["flash"]??[],JSON_UNESCAPED_UNICODE);}); '
        .'(new '.$classe.'())->'.$metodo.'();';
    file_put_contents($script,$code);
    $pipes=[]; $proc=proc_open([PHP_BINARY,$script],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
    $out=stream_get_contents($pipes[1]); $err=stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]); $exit=proc_close($proc); unlink($script);
    if ($exit!==0) throw new RuntimeException($err);
    return json_decode($out,true,512,JSON_THROW_ON_ERROR);
}
$pdo->exec('UPDATE pleitos SET fase_atual=3 WHERE id=99');
$flash=executarPostDeploy('Fase3Controller','votar',['csrf_token'=>['invalido']]);
check(str_contains($flash['erro']['message'],'Token de segurança'),'CSRF malformado rejeitado sem erro fatal');
$flash=executarPostDeploy('Fase3Controller','votar',['csrf_token'=>'token-teste','cpf'=>['invalido']]);
check($flash['erro']['message']==='Número de CPF inválido.','CPF malformado rejeitado sem erro fatal');
$pdo->exec('RENAME TABLE eleitores_ciclo TO eleitores_ciclo_backup_teste');
try {
    $flash=executarPostDeploy('Fase3Controller','votar',['csrf_token'=>'token-teste','cpf'=>'52998224725']);
    check(str_contains($flash['erro']['message'],'Solicite à STI'),'falha SQL no voto orienta STI');
    check(!str_contains(json_encode($flash),'SQLSTATE') && !isset($flash['sucesso']),'POST do voto não revela SQL nem anuncia sucesso');
} finally { $pdo->exec('RENAME TABLE eleitores_ciclo_backup_teste TO eleitores_ciclo'); }
$pdo->exec('RENAME TABLE pleitos TO pleitos_backup_teste');
try {
    $flash=executarPostDeploy('Fase4Controller','confirmar',['csrf_token'=>'token-teste','confirmacao'=>'1']);
    check(str_contains($flash['erro']['message'],'Solicite à STI'),'falha SQL na ordenação orienta STI');
    check(!str_contains(json_encode($flash),'SQLSTATE') && !isset($flash['sucesso']),'POST do Estado-Maior não revela SQL nem anuncia sucesso');
} finally { $pdo->exec('RENAME TABLE pleitos_backup_teste TO pleitos'); }
$flash=executarPostDeploy('Fase4Controller','confirmar',['csrf_token'=>'token-teste']);
check($flash['erro']['message']==='Confirme expressamente a avaliação.','validação funcional continua específica');
foreach ($antes as $t=>$linhas) check($linhas===$pdo->query("SELECT * FROM $t")->fetchAll(),'POST inválido preserva '.$t);

echo "$n verificações acumuladas aprovadas.\n";
