<?php
declare(strict_types=1);
require_once __DIR__.'/../src/Services/ZeramentoService.php';

if (getenv('RESET_TEST_DB')!=='1' || !str_ends_with(DB_NAME,'_test')) {
    throw new RuntimeException('Zeramento só pode ser testado em banco descartável _test com RESET_TEST_DB=1.');
}
$pdo=Database::getConnection();
if ($pdo->query('SHOW TABLES')->fetch()) throw new RuntimeException('Banco descartável precisa estar vazio.');
$pdo->exec(file_get_contents(__DIR__.'/../database/schema.sql'));

$checks=0;
function assertReset(bool $ok,string $msg): void {
    global $checks;
    if (!$ok) throw new RuntimeException('Falha: '.$msg);
    $checks++; echo 'OK: '.$msg."\n";
}
function shouldRejectReset(callable $call,string $msg): void {
    try { $call(); } catch (RuntimeException $e) {
        assertReset(true,$msg); return;
    }
    throw new RuntimeException('Permitiu operação proibida: '.$msg);
}
$hashAdmin=password_hash('SenhaPessoal!456',PASSWORD_BCRYPT);
$pdo->prepare("INSERT INTO usuarios(login,senha_hash,perfil,ativo) VALUES('admin',?,'ADMIN',1)")->execute([$hashAdmin]);
$adminId=(int)$pdo->lastInsertId();
$pdo->exec("INSERT INTO divisoes_assessorias(sigla,nome,tipo) VALUES('DA','Divisão de Apoio','DIVISAO')");
$pdo->exec("INSERT INTO pleitos(ano,titulo,status,fase_atual,fase3_inicio) VALUES(2026,'Eleição teste','em_andamento',3,NOW())");
$pleitoId=(int)$pdo->lastInsertId();
$pdo->exec("INSERT INTO pleitos(ano,titulo,status,fase_atual) VALUES(2025,'Ciclo anterior','cancelado',1)");
$pdo->exec("INSERT INTO efetivo(saram,cpf,cpf_hash,nome,nome_guerra,posto,categoria,tipo_vinculo) VALUES('00012','123.456.789-09','hashteste','Teste Civil','CIVIL','CV','sppf','CIVIL_PERMANENTE')");
$efId=(int)$pdo->lastInsertId();
$pdo->exec("INSERT INTO efetivo(saram,nome,nome_guerra,posto,categoria,tipo_vinculo) VALUES('00999','Admin vinculado','ADMIN','Cel','ineligivel','MILITAR_CARREIRA')");
$efAdminId=(int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO usuarios(login,senha_hash,efetivo_id,perfil,ativo) VALUES('00012',?,?, 'ELEITOR',1)")
    ->execute([password_hash('padrao@2026',PASSWORD_BCRYPT),$efId]);
$pdo->exec("INSERT INTO urna_votos(pleito_id,categoria,candidato_id,horario_arredondado) VALUES(".$pleitoId.",'sppf',".$efId.",NOW())");
$pdo->exec("INSERT INTO logs_auditoria(login,ip,acao,hash_registro) VALUES('admin','127.0.0.1','TESTE_ANTERIOR','abc123')");
$pdo->exec("UPDATE divisoes_assessorias SET chefe_usuario_id=".$adminId." WHERE sigla='DA'");
$pdo->exec("UPDATE usuarios SET efetivo_id=".$efAdminId." WHERE id=".$adminId);
$pdo->prepare('INSERT INTO historico_vencedores_manuais(ano,categoria,nome_vencedor,atualizado_por) VALUES (2024,\'praca\',\'VENCEDOR HISTORICO\',?)')->execute([$adminId]);
$resumo=ZeramentoService::resumo();
assertReset($resumo['contagens']['historico_vencedores_manuais']===1,'prévia considera histórico nominal');
assertReset($resumo['contagens']['efetivo']===2 && $resumo['contagens']['usuarios']===2,'prévia inclui efetivo e contas');
assertReset($resumo['contagens']['urna_votos']===1 && $resumo['homologados']===0,'prévia identifica voto e ausência de homologação');

assertReset(!ZeramentoService::habilitado(),'zeramento vem desativado por padrão');
shouldRejectReset(fn()=>ZeramentoService::executar($adminId,$resumo['fingerprint']),'configuração bloqueia exclusão');
$_ENV['ALLOW_DATABASE_RESET']='true';
assertReset(ZeramentoService::habilitado(),'habilitação exige variável explícita');

// Esquemas desconhecidos ou pleitos homologados interrompem a operação.
$pdo->exec('CREATE TABLE tabela_desconhecida(id INT PRIMARY KEY)');
shouldRejectReset(fn()=>ZeramentoService::resumo(),'tabela adicional bloqueia limpeza por precaução');
$pdo->exec('DROP TABLE tabela_desconhecida');
$pdo->exec("UPDATE pleitos SET status='homologado' WHERE ano=2025");
$hom=ZeramentoService::resumo();
shouldRejectReset(fn()=>ZeramentoService::executar($adminId,$hom['fingerprint']),'pleito homologado nunca pode ser zerado');
$pdo->exec("UPDATE pleitos SET status='cancelado' WHERE ano=2025");
$resumo=ZeramentoService::resumo();
shouldRejectReset(fn()=>ZeramentoService::executar($adminId,'hash-errado'),'fingerprint de dados divergente bloqueia operação');
assertReset((int)$pdo->query('SELECT COUNT(*) FROM urna_votos')->fetchColumn()===1,'tentativas inválidas preservam voto');

// Auditoria obrigatória precisa ser atômica com as exclusões.
$pdo->exec("CREATE TRIGGER bloqueio_reset_auditoria BEFORE INSERT ON logs_auditoria FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='falha injetada'");
shouldRejectReset(fn()=>ZeramentoService::executar($adminId,$resumo['fingerprint']),'falha de auditoria provoca rollback');
assertReset((int)$pdo->query('SELECT COUNT(*) FROM urna_votos')->fetchColumn()===1
    && (int)$pdo->query('SELECT COUNT(*) FROM efetivo')->fetchColumn()===2,'rollback preserva votos e cadastros');
$pdo->exec('DROP TRIGGER bloqueio_reset_auditoria');

$backup=ZeramentoService::executar($adminId,$resumo['fingerprint']);
assertReset(is_file($backup['caminho']) && $backup['tamanho_bytes']>0,'backup compactado obrigatório salvo no servidor');
assertReset(hash_file('sha256',$backup['caminho'])===$backup['sha256'],'integridade SHA-256 do backup');
$g=@gzopen($backup['caminho'],'rb');
if (!$g) throw new RuntimeException('Arquivo de backup não abre.');
$sql='';
while (!gzeof($g)) $sql.=gzread($g,16384);
gzclose($g);
assertReset(str_contains($sql,'CREATE TABLE') && str_contains($sql,'Teste Civil') && str_contains($sql,'VENCEDOR HISTORICO'),'backup contém estrutura e cadastros anteriores');
assertReset(str_contains($sql,'saram_unico') && !preg_match('/INSERT INTO .efetivo. \([^)]*saram_unico/i',$sql),'backup preserva DDL gerado sem tentar inserir coluna GENERATED');
assertReset((int)$pdo->query('SELECT COUNT(*) FROM urna_votos')->fetchColumn()===0,'urnas zeradas');
assertReset((int)$pdo->query('SELECT COUNT(*) FROM efetivo')->fetchColumn()===0,'efetivo zerado');
assertReset((int)$pdo->query('SELECT COUNT(*) FROM historico_vencedores_manuais')->fetchColumn()===0,'histórico nominal zerado apenas pelo procedimento autorizado');
assertReset((int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE perfil<>'ADMIN'")->fetchColumn()===0,'contas não administrativas zeradas');
assertReset((int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE perfil='ADMIN' AND ativo=1")->fetchColumn()===1,'administrador preservado');
$u=$pdo->query('SELECT senha_hash,efetivo_id FROM usuarios WHERE id='.$adminId)->fetch();
assertReset(password_verify('SenhaPessoal!456',$u['senha_hash']) && $u['efetivo_id']===null,'senha do ADMIN preservada e vínculo eliminado');
assertReset((int)$pdo->query('SELECT COUNT(*) FROM divisoes_assessorias')->fetchColumn()===1
    && $pdo->query('SELECT chefe_usuario_id FROM divisoes_assessorias')->fetchColumn()===null,'divisões preservadas sem chefias antigas');
assertReset((int)$pdo->query('SELECT COUNT(*) FROM pleitos')->fetchColumn()===1
    && (int)$pdo->query('SELECT fase_atual FROM pleitos')->fetchColumn()===1
    && $pdo->query("SELECT status FROM pleitos")->fetchColumn()==='planejamento'
    && $pdo->query('SELECT fase3_inicio FROM pleitos')->fetchColumn()===null,'pleito de referência reaberto em planejamento sem cronograma');
assertReset((int)$pdo->query("SELECT COUNT(*) FROM logs_auditoria WHERE acao='TESTE_ANTERIOR'")->fetchColumn()===1
    && (int)$pdo->query("SELECT COUNT(*) FROM logs_auditoria WHERE acao='ZERAMENTO_BASE_OPERACIONAL'")->fetchColumn()===1,'auditoria histórica e ato de zeramento preservados');

// Segundo reset em banco já limpo não deve perder acesso administrativo.
$novo=ZeramentoService::resumo();
$backup2=ZeramentoService::executar($adminId,$novo['fingerprint']);
assertReset(is_file($backup2['caminho']) && (int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE perfil='ADMIN'")->fetchColumn()===1,'zeramento repetido permanece seguro para ADMIN');
echo "$checks testes de zeramento aprovados.\n";
