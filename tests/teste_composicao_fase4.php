<?php
declare(strict_types=1);
require __DIR__.'/teste_deploy_fases3_4.php';

$pdo->exec("INSERT INTO pleitos(id,ano,titulo,fase_atual) VALUES(100,2100,'Composição por pleito',3)");
$pdo->exec('INSERT INTO fase4_ciclos SELECT 100,categorias_json,encerrado_em,encerrado_por,NULL FROM fase4_ciclos WHERE pleito_id=2');
$pdo->exec('INSERT INTO fase4_decisoes_corte SELECT 100,categoria,candidatos_json,justificativa,usuario_id,confirmado_em FROM fase4_decisoes_corte WHERE pleito_id=2');
check(!Pleito::avancarFase(100,$admin)['sucesso'],'nenhuma seleção impede abertura da Fase 4');
$pdo->exec("UPDATE usuarios SET perfil='CHEFE_DIV_ASSESS' WHERE id=1");
$versao=EstadoMaior::versaoComposicao(100);
rejeita(fn()=>EstadoMaior::salvarComposicao(100,[1],$major),'não ADMIN não altera composição');
rejeita(fn()=>EstadoMaior::salvarComposicao(100,[$cap],$admin),'capitão não pode integrar composição');
rejeita(fn()=>EstadoMaior::salvarComposicao(100,[['id'=>1]],$admin),'membro malformado rejeitado');
EstadoMaior::salvarComposicao(100,[1,$major],$admin,$versao);
check(EstadoMaior::usuario(1)['perfil']==='CHEFE_DIV_ASSESS','seleção preserva perfil de chefe de divisão');
rejeita(fn()=>EstadoMaior::salvarComposicao(100,[1],$admin,$versao),'formulário antigo não sobrescreve composição');
check(Pleito::avancarFase(100,$admin)['sucesso'],'abre Fase 4 apenas com selecionados');
$idsProgresso=array_map('intval',array_column(EstadoMaior::progresso(100),'usuario_id')); sort($idsProgresso);
$idsEsperados=[1,$major]; sort($idsEsperados);
check($idsProgresso===$idsEsperados,'somente dois marcados no progresso');
$ordNova=[];
foreach (EstadoMaior::candidatos(100) as $cat=>$cs) foreach ($cs as $i=>$c)
    $ordNova[$cat][]=['candidato_id'=>$c['candidato_id'],'posicao'=>$i+1,'justificativa'=>'Parecer de teste.'];
$naoMarcado=criarUsuarioTeste('Cel','DIRECAO_SUPERIOR','NãoSelecionado');
rejeita(fn()=>EstadoMaior::confirmar(100,$naoMarcado,$ordNova),'posto e perfil não substituem marcação');
EstadoMaior::confirmar(100,1,$ordNova);
check(!Pleito::avancarFase(100,$admin)['sucesso'],'membro marcado pendente bloqueia avanço');
$confirmacao=EstadoMaior::avaliacao(100,1);
EstadoMaior::salvarComposicao(100,[1],$admin);
check(count(EstadoMaior::progresso(100))===1,'desmarcado pendente deixa de ser exigido');
EstadoMaior::exigirConcluida(100);
check(EstadoMaior::avaliacao(100,1)===$confirmacao,'salvar composição preserva parecer já confirmado');
rejeita(fn()=>EstadoMaior::confirmar(100,$major,$ordNova),'desmarcado não confirma com sessão antiga');
EstadoMaior::salvarComposicao(100,[1,$major,$naoMarcado],$admin);
check(count(EstadoMaior::progresso(100))===3,'novo marcado na Fase 4 entra no progresso');
rejeita(fn()=>EstadoMaior::exigirConcluida(100),'novo marcado pendente bloqueia');
EstadoMaior::salvarComposicao(100,[1,$major],$admin);
EstadoMaior::confirmar(100,$major,$ordNova);
$dupla=EstadoMaior::resultados(100);
$itensMajor=EstadoMaior::avaliacao(100,$major);
EstadoMaior::salvarComposicao(100,[1],$admin);
$solo=EstadoMaior::resultados(100);
check($dupla['sppf']['vencedor']['pontos_fase4']===10 && $solo['sppf']['vencedor']['pontos_fase4']===5,'desmarcado confirmado não soma pontos');
check(EstadoMaior::avaliacao(100,$major)===$itensMajor,'desmarcar preserva posições e pareceres armazenados');
EstadoMaior::salvarComposicao(100,[1,$major],$admin);
rejeita(fn()=>EstadoMaior::confirmar(100,$major,$ordNova),'remarcar não permite editar confirmação anterior');
$estadoAntes=EstadoMaior::composicao(100);
$pdo->exec("CREATE TRIGGER falha_composicao BEFORE INSERT ON logs_auditoria FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='auditoria indisponível'");
rejeita(fn()=>EstadoMaior::salvarComposicao(100,[1],$admin),'falha de auditoria reverte alteração');
check(EstadoMaior::composicao(100)===$estadoAntes,'composição e auditoria atômicas');
$pdo->exec('DROP TRIGGER falha_composicao');
EstadoMaior::salvarComposicao(100,[],$admin);
rejeita(fn()=>EstadoMaior::exigirConcluida(100),'zero marcados não libera Fase 5');
EstadoMaior::salvarComposicao(100,[1],$admin);
$pdo->prepare('UPDATE usuarios SET ativo=0 WHERE id=?')->execute([$major]);
check(count(EstadoMaior::progresso(100))===1,'inativo desmarcado não bloqueia avanço');
rejeita(fn()=>EstadoMaior::salvarComposicao(100,[1,$major],$admin),'conta inativa não pode ser marcada');
require __DIR__.'/../database/migrate_fases3_4.php';
check(count(EstadoMaior::progresso(100))===1,'repetir migração não reativa desmarcados');
check(Pleito::avancarFase(100,$admin)['sucesso'],'avança com apenas chefe marcado e confirmado');
check(count(Homologacao::avaliacoesIndividuais(100,$pres))===count(array_merge(...array_values($ordNova))),'consulta presidencial inclui somente membros selecionados');
rejeita(fn()=>EstadoMaior::salvarComposicao(100,[$naoMarcado],$admin),'Fase 5 congela composição');
rejeita(fn()=>EstadoMaior::salvarComposicao(1,[1],$admin),'homologação anterior permanece protegida');
$pdo->prepare('UPDATE usuarios SET ativo=1 WHERE id=?')->execute([$major]);
// Compatibilidade: instalação existente mantém roster até conferência expressa do ADMIN.
$pdo->exec("INSERT INTO pleitos(id,ano,titulo,fase_atual) VALUES(101,2101,'Legado em curso',4)");
$pdo->exec('INSERT INTO fase4_ciclos SELECT 101,categorias_json,encerrado_em,encerrado_por,aberto_em FROM fase4_ciclos WHERE pleito_id=100');
$pdo->exec('INSERT INTO fase4_avaliadores SELECT 101,usuario_id,nome_guerra,posto FROM fase4_avaliadores WHERE pleito_id=100');
require __DIR__.'/../database/migrate_fases3_4.php';
check(count(EstadoMaior::composicao(101))===3,'migração inicial preserva roster existente');
EstadoMaior::salvarComposicao(101,[1],$admin);
require __DIR__.'/../database/migrate_fases3_4.php';
check(count(EstadoMaior::progresso(101))===1,'migração idempotente preserva seleção corrigida');

// A tela e a rota reais devem refletir o mesmo conjunto, inclusive CHEFE_DIV_ASSESS.
$html=executarControllerTeste('AdminController','chefias',$admin);
check(str_contains($html,'Fase 4: Ordenação e Pareceres'),'tela administrativa apresenta finalidade correta');
check((bool)preg_match('/name="membros_direcao\[\]" value="1"[^>]*checked/',$html),'checkbox de chefe selecionado permanece marcado');
check(!preg_match('/name="membros_direcao\[\]" value="'.(int)$major.'"[^>]*checked/',$html),'checkbox desmarcado corresponde ao progresso');
function salvarComposicaoPelaRotaTeste(int $autor,array $post): array {
    $script=tempnam(sys_get_temp_dir(),'comara-composicao-');
    $code='<?php require '.var_export(ROOT_PATH.'/src/Controllers/AdminController.php',true).'; '
        .'$_SESSION["user"]=EstadoMaior::usuario('.$autor.'); $_SESSION["csrf_token"]="composicao-teste"; '
        .'$_SERVER["REQUEST_METHOD"]="POST"; $_POST='.var_export($post,true).'; '
        .'register_shutdown_function(function(){echo json_encode($_SESSION["flash"]??[],JSON_UNESCAPED_UNICODE);}); '
        .'(new AdminController())->salvarChefias();';
    file_put_contents($script,$code); $pipes=[];
    $proc=proc_open([PHP_BINARY,$script],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
    $out=stream_get_contents($pipes[1]); $err=stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]); $exit=proc_close($proc); unlink($script);
    if ($exit!==0) throw new RuntimeException($err);
    return json_decode($out,true,512,JSON_THROW_ON_ERROR);
}
$post=['secao'=>'direcao_superior','pleito_id'=>101,'versao_composicao'=>EstadoMaior::versaoComposicao(101),'membros_direcao'=>[1,$major],'csrf_token'=>'invalido'];
$flash=salvarComposicaoPelaRotaTeste($admin,$post);
check(isset($flash['erro']) && count(EstadoMaior::progresso(101))===1,'rota rejeita CSRF sem alterar composição');
$post['csrf_token']='composicao-teste';
$flash=salvarComposicaoPelaRotaTeste($admin,$post);
check(isset($flash['sucesso']) && count(EstadoMaior::progresso(101))===2,'rota salva seleção independente do perfil');
check(EstadoMaior::usuario(1)['perfil']==='CHEFE_DIV_ASSESS','rota preserva perfil do chefe');

echo "$n verificações acumuladas aprovadas.\n";
