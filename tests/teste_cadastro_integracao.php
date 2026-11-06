<?php
declare(strict_types=1);
require_once __DIR__.'/../src/Models/Efetivo.php';
require_once __DIR__.'/../database/perfil_rh.php';
require_once __DIR__.'/../database/migrar_esquema.php';
if (getenv('CADASTRO_TEST_DB')!=='1' || !str_ends_with(DB_NAME,'_test')) throw new RuntimeException('Use banco descartável _test e CADASTRO_TEST_DB=1.');
$pdo=Database::getConnection();
if ($pdo->query('SHOW TABLES')->fetch()) throw new RuntimeException('Banco deve estar vazio.');
$pdo->exec(file_get_contents(__DIR__.'/../database/schema.sql'));
$n=0;
function check(bool $ok,string $msg): void { global $n; if (!$ok) throw new RuntimeException($msg); echo 'OK: '.$msg."\n"; $n++; }
function rejeita(callable $f,string $msg): void { try { $f(); } catch (Throwable $e) { check(true,$msg); return; } throw new RuntimeException($msg); }
function dados(string $saram,string $nome='Pessoa teste'): array { return ['saram'=>$saram,'nome'=>$nome,'nome_guerra'=>$nome,'posto'=>'1S','categoria'=>'graduado','tipo_vinculo'=>'MILITAR_CARREIRA']; }
function phpScript(string $code,int $esperado=0): string {
    $f=tempnam(sys_get_temp_dir(),'cadastro-'); file_put_contents($f,'<?php '.$code);
    $pipes=[]; $p=proc_open([PHP_BINARY,$f],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
    $out=stream_get_contents($pipes[1]); $err=stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); $exit=proc_close($p); unlink($f);
    if ($exit!==$esperado) throw new RuntimeException($err); return $out;
}
function postCadastro(array $post,int $autor=1,string $perfil='ADMIN'): array {
    return json_decode(phpScript('require '.var_export(ROOT_PATH.'/src/Controllers/AdminController.php',true).'; $_SESSION["user"]=["id"=>'.$autor.',"perfil"=>'.var_export($perfil,true).']; $_SESSION["csrf_token"]="teste"; $_SERVER["REQUEST_METHOD"]="POST"; $_POST='.var_export($post,true).'; set_error_handler(function($s,$m,$f,$l){throw new ErrorException($m,0,$s,$f,$l);}); register_shutdown_function(function(){echo json_encode($_SESSION["flash"]??[]);}); (new AdminController())->salvarEfetivo();'),true,512,JSON_THROW_ON_ERROR);
}
$pdo->exec("INSERT INTO usuarios(id,login,perfil) VALUES(1,'admin','ADMIN')");
check(correcaoPerfilRh($pdo)===null,'schema novo aceita RH');
// Simula base antiga: os scripts eleitorais não adicionavam RH.
$pdo->exec("ALTER TABLE usuarios MODIFY perfil ENUM('ADMIN','ELEITOR','PERFIL_LOCAL') NOT NULL DEFAULT 'ELEITOR' COMMENT 'Perfis locais'");
$pdo->exec("INSERT INTO usuarios(login,perfil) VALUES('local','PERFIL_LOCAL')");
$id=Efetivo::salvarOuAtualizar(dados('001234','Original'));
$post=dados('001234','Não deve mudar')+['id'=>$id,'csrf_token'=>'teste','is_admin_enviado'=>'1','is_rh_enviado'=>'1','is_rh'=>'1'];
$f=postCadastro($post);
check(str_contains($f['erro']['message'],'migrate_perfil_rh.php'),'rota indica migração específica');
check(Efetivo::getPorId($id)['nome']==='ORIGINAL','perfil inválido não salva cadastro parcialmente');
$correcao=correcaoPerfilRh($pdo);
check($correcao!==null && count(array_filter(identificarCorrecoesEsquema($pdo),fn($c)=>str_contains($c['descricao'],'usuarios.perfil')))===1,'reconciliador detecta RH ausente');
$antes=$pdo->query('SELECT id,login,perfil,efetivo_id FROM usuarios ORDER BY id')->fetchAll();
$pdo->exec($correcao['sql']);
check(correcaoPerfilRh($pdo)===null,'migração idempotente');
check($antes===$pdo->query('SELECT id,login,perfil,efetivo_id FROM usuarios ORDER BY id')->fetchAll(),'migração preserva perfis e vínculos existentes');
$col=$pdo->query("SHOW FULL COLUMNS FROM usuarios LIKE 'perfil'")->fetch();
check($col['Comment']==='Perfis locais' && $col['Default']==='ELEITOR' && str_contains($col['Type'],'PERFIL_LOCAL'),'preserva atributos e perfis personalizados');
$f=postCadastro($post); check(isset($f['sucesso']),'ADMIN concede RH pela rota real');
$u=$pdo->query('SELECT * FROM usuarios WHERE efetivo_id='.$id)->fetch();
check($u['perfil']==='RH','perfil RH efetivamente persistido');
$promover=$post; unset($promover['is_rh']); $promover['is_admin']='1';
check(isset(postCadastro($promover)['sucesso']) && $pdo->query('SELECT perfil FROM usuarios WHERE efetivo_id='.$id)->fetchColumn()==='ADMIN','promoção RH para ADMIN não rebaixa para ELEITOR');
$post['is_rh']='1'; postCadastro($post);
$semRh=$post; unset($semRh['is_rh']); postCadastro($semRh);
check($pdo->query('SELECT perfil FROM usuarios WHERE efetivo_id='.$id)->fetchColumn()==='ELEITOR','revogação RH preservada');
$antes=Efetivo::getPorId($id);
$pdo->exec("CREATE TRIGGER falha_cadastro BEFORE INSERT ON logs_auditoria FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='segredo SQL'");
$f=postCadastro($post);
check(isset($f['erro']) && !str_contains(json_encode($f),'segredo SQL'),'falha tratada sem SQL exposto');
check(Efetivo::getPorId($id)===$antes && $pdo->query('SELECT perfil FROM usuarios WHERE efetivo_id='.$id)->fetchColumn()==='ELEITOR','auditoria falha reverte cadastro e perfil');
$pdo->exec('DROP TRIGGER falha_cadastro');
$f=postCadastro($post,(int)$u['id'],'RH');
check($pdo->query('SELECT perfil FROM usuarios WHERE efetivo_id='.$id)->fetchColumn()==='ELEITOR','RH não concede privilégios por POST forjado');
$invalid=$post; $invalid['csrf_token']='errado'; check(isset(postCadastro($invalid)['erro']),'CSRF preservado');
$total=(int)$pdo->query('SELECT COUNT(*) FROM efetivo')->fetchColumn();
rejeita(fn()=>Efetivo::salvarOuAtualizar(dados('001234')),'novo SARAM repetido rejeitado');
rejeita(fn()=>Efetivo::salvarOuAtualizar(dados(' 001234 ')),'espaços externos não burlam unicidade');
check((int)$pdo->query('SELECT COUNT(*) FROM efetivo')->fetchColumn()===$total,'tentativas não deixam pessoa órfã');
check((int)$pdo->query('SELECT efetivo_id FROM usuarios WHERE id='.(int)$u['id'])->fetchColumn()===$id,'conta nunca transferida ao duplicado');
$id2=Efetivo::salvarOuAtualizar(dados('5678'));
rejeita(fn()=>Efetivo::salvarOuAtualizar(dados('001234')+['id'=>$id2]),'edição não pode repetir SARAM');
check(Efetivo::salvarOuAtualizar(dados('001234')+['id'=>$id])===$id,'edição mantém próprio SARAM');
$pdo->exec('UPDATE efetivo SET ativo=0 WHERE id='.$id);
rejeita(fn()=>Efetivo::salvarOuAtualizar(dados('001234')),'inativo também reserva o SARAM');
check(Efetivo::salvarOuAtualizar(dados(''))!==Efetivo::salvarOuAtualizar(dados('')),'ausência de SARAM permitida em pessoas distintas');
$pdo->exec("INSERT INTO usuarios(login,perfil) VALUES('9999','ELEITOR')");
$total=(int)$pdo->query('SELECT COUNT(*) FROM efetivo')->fetchColumn();
rejeita(fn()=>Efetivo::salvarOuAtualizar(dados('9999')),'login ocupado não vincula outra conta');
check((int)$pdo->query('SELECT COUNT(*) FROM efetivo')->fetchColumn()===$total,'falha na conta reverte nova pessoa');
rejeita(fn()=>$pdo->exec("INSERT INTO efetivo(saram,nome,posto,categoria,tipo_vinculo) VALUES('001234','SQL','1S','graduado','MILITAR_CARREIRA')"),'índice protege gravação direta');
// Sem índice, a mesma proteção deve funcionar durante saneamento de uma base antiga.
$pdo->exec('ALTER TABLE efetivo DROP INDEX uq_efetivo_saram, DROP COLUMN saram_unico');
$f=tempnam(sys_get_temp_dir(),'concorrencia-cadastro-');
file_put_contents($f,'<?php require '.var_export(ROOT_PATH.'/src/Models/Efetivo.php',true).'; try { Efetivo::salvarOuAtualizar('.var_export(dados('7777'),true).'); echo "ok"; } catch (InvalidArgumentException $e) { echo "duplicado"; }');
$ps=[]; for($i=0;$i<2;$i++){ $pipes=[]; $proc=proc_open([PHP_BINARY,$f],[1=>['pipe','w'],2=>['pipe','w']],$pipes); $ps[]=[$proc,$pipes]; }
$outs=[]; foreach($ps as [$proc,$pipes]) { $outs[]=stream_get_contents($pipes[1]); $err=stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); check(proc_close($proc)===0,'processo concorrente conclui'); } unlink($f); sort($outs);
check($outs===['duplicado','ok'],'duas requisições simultâneas criam somente um cadastro');
$pdo->exec("INSERT INTO efetivo(saram,nome,posto,categoria,tipo_vinculo) VALUES('001234','Legado duplicado','1S','graduado','MILITAR_CARREIRA')");
rejeita(fn()=>Efetivo::salvarOuAtualizar(dados('001234')+['id'=>$id]),'duplicidade legada exige conferência sem escolher arbitrariamente');
// Migração relata conflitos e não toca nos registros.
$antes=$pdo->query('SELECT * FROM efetivo ORDER BY id')->fetchAll();
$out=phpScript('$argv=["migrate_saram_unico.php","--apply"]; require '.var_export(ROOT_PATH.'/database/migrate_saram_unico.php',true).';',2);
check(str_contains($out,'registros') && $antes===$pdo->query('SELECT * FROM efetivo ORDER BY id')->fetchAll(),'migração reporta IDs e preserva duplicados legados');
echo "$n verificações de cadastro aprovadas.\n";
