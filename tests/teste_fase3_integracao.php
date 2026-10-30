<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
if (getenv('FASE34_TEST_DB') !== '1' || !str_ends_with(DB_NAME, '_test')) {
    throw new RuntimeException('Use somente banco descartável terminado em _test e FASE34_TEST_DB=1.');
}
require_once __DIR__ . '/../src/Models/Efetivo.php';
require_once __DIR__ . '/../src/Models/ValidacaoSuperior.php';
require_once __DIR__ . '/../src/Services/UrnaService.php';
$pdo = Database::getConnection();
if ($pdo->query('SHOW TABLES')->fetch()) throw new RuntimeException('O banco de testes deve estar vazio.');
$pdo->exec(file_get_contents(__DIR__ . '/../database/schema.sql'));
require __DIR__ . '/../database/migrate_fase2.php';
require __DIR__ . '/../database/migrate_fase2.php';
$n=0;
function check(bool $ok, string $msg): void { global $n; if (!$ok) throw new RuntimeException($msg); $n++; echo "OK: $msg\n"; }
function rejeita(callable $f, string $msg): void { try {$f();} catch (RuntimeException|InvalidArgumentException $e) {check(true,$msg);return;} throw new RuntimeException($msg); }
$pdo->exec("INSERT INTO pleitos(id,ano,titulo,fase_atual) VALUES(1,2026,'Teste',3)");
$pdo->exec("INSERT INTO efetivo(id,nome,nome_guerra,posto,categoria,tipo_vinculo,cpf_hash) VALUES(1,'Eleitor','Eleitor','Cel','ineligivel','MILITAR_CARREIRA',SHA2('52998224725',256))");
$pdo->exec("INSERT INTO usuarios(id,login,efetivo_id,perfil) VALUES(1,'eleitor',1,'ELEITOR')");
$unidades=['DPC','DA','DL','DE','DS','ASSESSORIAS'];
$nomes=['zélia','Érica','bruno','Álvaro','dora','célia'];
$votos=[];
foreach (['graduado','praca','sppf','sptf'] as $cat) {
    foreach ($unidades as $i=>$unidade) {
        $pdo->prepare("INSERT INTO efetivo(nome,nome_guerra,posto,categoria,tipo_vinculo,divisao_sigla,setor) VALUES(?,?,'1S',?,'MILITAR_CARREIRA',?,'Setor')")->execute(['Nome completo '.$nomes[$i],$nomes[$i],$cat,$unidade==='ASSESSORIAS'?'ACI':$unidade]);
        $id=(int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO selecao_fase2(pleito_id,orgao_sigla,chefe_usuario_id,categoria,candidato_id) VALUES(1,?,1,?,?)')->execute([$unidade,$cat,$id]);
        $pdo->prepare('INSERT INTO indicados_consolidados(pleito_id,categoria,candidato_id) VALUES(1,?,?)')->execute([$cat,$id]);
        $votos[$cat]=$id;
    }
    $lista=Efetivo::listarParaUrna(1,$cat);
    check(count($lista)===6,"até seis em $cat");
    check(array_column($lista,'nome_guerra')===['Álvaro','bruno','célia','dora','Érica','zélia'],"alfabética com acentos/caixa em $cat");
    check(!isset($lista[0]['media_fase1']) && !isset($lista[0]['nome']),"sem pontuação/nome completo em $cat");
    if(in_array($cat,['sppf','sptf'])) check($lista[0]['posto']==='CIVIL '.strtoupper($cat),"identificação civil $cat");
}
$lista=ValidacaoSuperior::listarCandidatos(1);
check(array_column(array_slice($lista,0,6),'nome_guerra')===['Álvaro','bruno','célia','dora','Érica','zélia'],'Fase 4 alfabética');
$pdo->prepare('DELETE FROM selecao_fase2 WHERE candidato_id=?')->execute([$votos['sptf']]);
check(count(Efetivo::listarParaUrna(1,'sptf'))===5,'ausência de representante e consolidado antigo fora da urna');
rejeita(fn()=>UrnaService::registrarVotos(1,'52998224725',$votos,1),'rejeita antigo selecionado');
$votos['sptf']=(int)Efetivo::listarParaUrna(1,'sptf')[0]['id'];
$x=$votos; $x['graduado']=$votos['praca'];
rejeita(fn()=>UrnaService::registrarVotos(1,'52998224725',$x,1),'rejeita categoria incorreta');
$x=$votos; $x['extra']=1;
rejeita(fn()=>UrnaService::registrarVotos(1,'52998224725',$x,1),'rejeita categoria extra');
$x=$votos; unset($x['praca']);
rejeita(fn()=>UrnaService::registrarVotos(1,'52998224725',$x,1),'preserva exigência das quatro categorias');
check((int)$pdo->query('SELECT COUNT(*) FROM urna_votos')->fetchColumn()===0,'falhas não gravam cédulas');
check((int)$pdo->query('SELECT COUNT(*) FROM eleitores_ciclo')->fetchColumn()===0,'falhas não consomem eleitor');
$pdo->exec('UPDATE pleitos SET fase_atual=4 WHERE id=1');
rejeita(fn()=>UrnaService::registrarVotos(1,'52998224725',$votos,1),'bloqueia votação fora da Fase 3');
$pdo->exec('UPDATE pleitos SET fase_atual=3 WHERE id=1');
$pdo->exec("INSERT INTO eleitores_ciclo(pleito_id,usuario_id,cpf_hash,votou) VALUES(1,1,SHA2('52998224725',256),0)");
$r=UrnaService::registrarVotos(1,'52998224725',$votos,1);
check($r['sucesso'] && str_starts_with($r['comprovante'],'COMARA-'),'voto e comprovante com eleitor pré-cadastrado');
rejeita(fn()=>UrnaService::registrarVotos(1,'52998224725',$votos,1),'rejeita voto duplicado');
check((int)$pdo->query('SELECT COUNT(*) FROM urna_votos')->fetchColumn()===4,'uma cédula por categoria');
$colunas=$pdo->query('SHOW COLUMNS FROM urna_votos')->fetchAll(PDO::FETCH_COLUMN);
check(!array_intersect(['cpf_hash','usuario_id','codigo_comprovante'], $colunas),'cédulas sem identificadores do eleitor');
$log=$pdo->query("SELECT detalhes_json FROM logs_auditoria WHERE acao='VOTO_COMPUTADO'")->fetchColumn();
check(!str_contains($log,'candidato') && !str_contains($log,'categoria'),'auditoria sem escolhas');
echo "$n verificações de integração aprovadas.\n";
