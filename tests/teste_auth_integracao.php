<?php
declare(strict_types=1);
ob_start();
require_once __DIR__.'/../src/Auth/AuthManager.php';
if (getenv('AUTH_TEST_DB')!=='1' || !str_ends_with(DB_NAME,'_test')) {
    throw new RuntimeException('Use somente banco descartável _test com AUTH_TEST_DB=1.');
}
$pdo=Database::getConnection();
if ($pdo->query('SHOW TABLES')->fetch()) throw new RuntimeException('Banco de testes deve estar vazio.');
$pdo->exec(file_get_contents(__DIR__.'/../database/schema.sql'));
$total=0;
function okAuth(bool $valid,string $message): void {
    global $total;
    if (!$valid) throw new RuntimeException('FALHA: '.$message);
    $total++; echo "OK: ".$message."\n";
}
function newEfetivo(PDO $pdo,string $matricula,string $cpf,string $nome,string $categoria,string $posto): int {
    $s=$pdo->prepare('INSERT INTO efetivo(saram,cpf,cpf_hash,nome,nome_guerra,posto,categoria,tipo_vinculo,ativo) VALUES(?,?,?,?,?,?,?,?,1)');
    $s->execute([$matricula,$cpf,hash('sha256',preg_replace('/\\D/','',$cpf)),$nome,$nome,$posto,$categoria,$posto==='CV'?'CIVIL_PERMANENTE':'MILITAR_CARREIRA']);
    return (int)$pdo->lastInsertId();
}
function newUser(PDO $pdo,string $login,?int $efetivoId,string $password='padrao@2026',string $role='ELEITOR'): int {
    $s=$pdo->prepare('INSERT INTO usuarios(login,efetivo_id,senha_hash,perfil,ativo) VALUES(?,?,?,?,1)');
    $s->execute([$login,$efetivoId,password_hash($password,PASSWORD_BCRYPT),$role]);
    return (int)$pdo->lastInsertId();
}
function childAuth(string $body): string {
    $file=tempnam(sys_get_temp_dir(),'auth-');
    file_put_contents($file,'<?php '. $body);
    $pipes=[];
    $p=proc_open([PHP_BINARY,$file],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if (!is_resource($p)) throw new RuntimeException('Não iniciou processo de teste de autenticação.');
    $out=stream_get_contents($pipes[1]); $err=stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]); $exit=proc_close($p); unlink($file);
    if ($exit!==0) throw new RuntimeException('Processo auth: '.$err.' / '.$out);
    return $out;
}
function postAuth(int $id,string $old,string $new,string $confirm,string $csrf='valido'): void {
    $code='require '.var_export(ROOT_PATH.'/src/Controllers/AuthController.php',true).';'
        .'$_SESSION["user"]=["id"=>'.(int)$id.',"login"=>"civilTeste","perfil"=>"ELEITOR","auth_origin"=>"LOCAL_DATABASE"];'
        .'$_SESSION["csrf_token"]="valido"; $_SERVER["REQUEST_METHOD"]="POST";'
        .'$_POST='.var_export(['csrf_token'=>$csrf,'acao'=>'alterar','senha_atual'=>$old,'nova_senha'=>$new,'confirma_senha'=>$confirm],true).';'
        .'(new AuthController())->alterarSenha();';
    childAuth($code);
}
$efCivil=newEfetivo($pdo,'000123456','123.456.789-09','Servidor Civil','sppf','CV');
$civilId=newUser($pdo,'000123456',$efCivil);
$efMilitar=newEfetivo($pdo,'7000123','987.654.321-00','Militar','graduado','1S');
$militarId=newUser($pdo,'7000123',$efMilitar);
$adminId=newUser($pdo,'admin',null,'padrao@2026','ADMIN');
$ldap=new LdapAuth();
$matricula=$ldap->authenticate('000123456','padrao@2026');
okAuth(($matricula['id']??0)===$civilId,'civil autentica pela matrícula com zeros à esquerda');
$cpfSem=$ldap->authenticate('12345678909','padrao@2026');
$cpfCom=$ldap->authenticate('123.456.789-09','padrao@2026');
okAuth(($cpfSem['id']??0)===$civilId && ($cpfCom['id']??0)===$civilId,'CPF pontuado e sem pontuação chegam à mesma conta civil');
okAuth(($ldap->authenticate('7000123','padrao@2026')['id']??0)===$militarId,'militar mantém acesso pelo SARAM');
okAuth($ldap->authenticate('000123456','senha-incorreta')===false,'senha incorreta é rejeitada');
okAuth($ldap->authenticate('abc12345678909','padrao@2026')===false,'identificador com caracteres inesperados não vira CPF');
okAuth((int)$pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn()===3,'alternar matrícula e CPF não cria usuário duplicado');

// Identificadores repetidos em registros legados nunca selecionam uma conta arbitrária.
$efDuplicado=newEfetivo($pdo,'0099999','123.456.789-09','Outro civil','sptf','CV');
$dupId=newUser($pdo,'0099999',$efDuplicado);
okAuth($ldap->authenticate('12345678909','padrao@2026')===false,'CPF duplicado em duas contas ativas bloqueia login ambíguo');
$pdo->prepare('DELETE FROM usuarios WHERE id=?')->execute([$dupId]);
$pdo->prepare('DELETE FROM efetivo WHERE id=?')->execute([$efDuplicado]);

okAuth(AuthManager::login('000123456','padrao@2026')['sucesso']===true,'login real da matrícula civil funciona');
okAuth((int)AuthManager::user()['id']===$civilId && AuthManager::requiresPasswordChange(),'primeiro acesso com senha padrão exige troca');
$consulta=childAuth('require '.var_export(ROOT_PATH.'/src/Controllers/AuthController.php',true).'; $_SESSION["user"]=["id"=>'.$civilId.',"login"=>"000123456","perfil"=>"ELEITOR","auth_origin"=>"LOCAL_DATABASE"]; $_SERVER["REQUEST_METHOD"]="GET"; (new AuthController())->alterarSenha();');
okAuth(str_contains($consulta,'Primeiro acesso') && str_contains($consulta,'Definir senha e continuar'),'formulário obrigatório é exibido com campos de senha');

$rotaGuardada=childAuth('require '.var_export(ROOT_PATH.'/config/config.php',true).'; $_SESSION["user"]=["id"=>'.$civilId.',"login"=>"000123456","perfil"=>"ELEITOR","auth_origin"=>"LOCAL_DATABASE"]; $_GET=["r"=>"dashboard"]; $_SERVER["REQUEST_METHOD"]="GET"; require '.var_export(ROOT_PATH.'/public/index.php',true).';');
okAuth(trim($rotaGuardada)==='','roteador bloqueia painel para senha inicial');
postAuth($civilId,'senha-errada','Nova!Senha123','Nova!Senha123');
okAuth(AuthManager::requiresPasswordChange(),'senha atual errada não remove bloqueio');
postAuth($civilId,'padrao@2026','padrao@2026','padrao@2026');
okAuth(AuthManager::requiresPasswordChange(),'senha institucional não pode ser reutilizada');
postAuth($civilId,'padrao@2026','Nova!Senha123','NaoConfere');
okAuth(AuthManager::requiresPasswordChange(),'confirmação divergente não modifica senha');
postAuth($civilId,'padrao@2026','Nova!Senha123','Nova!Senha123','token-invalido');
okAuth(AuthManager::requiresPasswordChange(),'CSRF inválido não modifica senha');
postAuth($civilId,'padrao@2026','Nova!Senha123','Nova!Senha123');
okAuth(!AuthManager::requiresPasswordChange(),'troca válida remove bloqueio também em sessão previamente aberta');
okAuth(($ldap->authenticate('12345678909','Nova!Senha123')['id']??0)===$civilId && $ldap->authenticate('12345678909','padrao@2026')===false,'CPF passa a autenticar com a nova senha da mesma conta');
okAuth(($ldap->authenticate('000123456','Nova!Senha123')['id']??0)===$civilId,'matrícula continua funcionando após troca');
okAuth((int)$pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn()===3,'troca não cria conta adicional nem altera vínculo');

// Mudança administrativa para o padrão volta a exigir troca, inclusive em sessão antiga.
$pdo->prepare('UPDATE usuarios SET senha_hash=? WHERE id=?')->execute([password_hash('padrao@2026',PASSWORD_BCRYPT),$civilId]);
okAuth(AuthManager::requiresPasswordChange(),'reset administrativo volta a bloquear conta já autenticada');
$_SESSION['user']['auth_origin']='ACTIVE_DIRECTORY';
okAuth(!AuthManager::requiresPasswordChange(),'login corporativo LDAP segue política de senha do AD');
$_SESSION['user']['auth_origin']='LOCAL_DATABASE';
okAuth(AuthManager::requiresPasswordChange(),'autenticação local não perde a proteção do primeiro acesso');

okAuth(AuthManager::login('admin','padrao@2026')['sucesso']===true && AuthManager::requiresPasswordChange(),'ADMIN também deve alterar senha inicial');
okAuth((int)$pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn()===3,'administrador e civis permanecem com registros únicos');
echo "$total verificações de autenticação aprovadas.\n";
ob_end_flush();
