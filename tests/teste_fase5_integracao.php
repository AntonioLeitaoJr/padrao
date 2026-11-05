<?php
declare(strict_types=1);
require __DIR__.'/teste_fase4_integracao.php';
require __DIR__.'/../database/migrate_fase5.php';
require __DIR__.'/../database/migrate_fase5.php';
$original=Homologacao::apurarResultados(1);
$pdo->exec("INSERT INTO pleitos(id,ano,titulo,fase_atual) VALUES(2,2027,'Fase 5 teste',5)");
$pdo->exec('INSERT INTO fase4_ciclos SELECT 2,categorias_json,encerrado_em,encerrado_por,aberto_em FROM fase4_ciclos WHERE pleito_id=1');
$pdo->exec('INSERT INTO fase4_decisoes_corte SELECT 2,categoria,candidatos_json,justificativa,usuario_id,confirmado_em FROM fase4_decisoes_corte WHERE pleito_id=1');
$pdo->exec('INSERT INTO fase4_avaliadores SELECT 2,usuario_id,nome_guerra,posto FROM fase4_avaliadores WHERE pleito_id=1');
$pdo->exec('INSERT INTO fase4_confirmacoes SELECT 2,usuario_id,confirmado_em FROM fase4_confirmacoes WHERE pleito_id=1');
$pdo->exec('INSERT INTO fase4_ordenacoes SELECT 2,usuario_id,categoria,candidato_id,posicao,justificativa FROM fase4_ordenacoes WHERE pleito_id=1');
$r=Homologacao::apurarResultados(2)['categorias'];
$g1=(int)$r['graduado']['candidatos_empatados'][0]['candidato_id'];
$g2=(int)$r['graduado']['candidatos_empatados'][1]['candidato_id'];
$e1=(int)$r['sptf']['candidatos_empatados'][0]['candidato_id'];
$ata='Homologo os quatro vencedores após conferência institucional.';
function renderizarFase5Teste(int $p,int $u): string {
    $_SESSION['user']=EstadoMaior::usuario($u);
    $pleito=Pleito::getPorId($p); $apuracao=Homologacao::apurarResultados($p); $historicos=Pleito::homologados();
    $avaliacoes=AuthManager::hasRole([PERFIL_PRESIDENTE,PERFIL_ADMIN])?Homologacao::avaliacoesIndividuais($p,$u):[];
    set_error_handler(function($severity,$message,$file,$line) { throw new ErrorException($message,0,$severity,$file,$line); });
    ob_start();
    try { require ROOT_PATH.'/views/fase5/index.php'; return ob_get_contents(); }
    finally { ob_end_clean(); restore_error_handler(); }
}
function executarControllerTeste(string $classe,string $metodo,int $usuario,array $post=[],array $get=[]): string {
    $script=tempnam(sys_get_temp_dir(),'comara-controller-');
    $code='<?php require '.var_export(ROOT_PATH.'/src/Controllers/'.$classe.'.php',true).'; '
        .'$_SESSION["user"]=EstadoMaior::usuario('.$usuario.'); $_POST='.var_export($post,true).'; $_GET='.var_export($get,true).'; '
        .'$_SERVER["REQUEST_METHOD"]='.var_export($post?'POST':'GET',true).'; '
        .'register_shutdown_function(function(){echo "FLASH:".json_encode($_SESSION["flash"]??[],JSON_UNESCAPED_UNICODE);}); '
        .'(new '.$classe.'())->'.$metodo.'();';
    file_put_contents($script,$code); $pipes=[];
    $proc=proc_open([PHP_BINARY,$script],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
    $out=stream_get_contents($pipes[1]); $err=stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    $exit=proc_close($proc); unlink($script); if ($exit!==0) throw new RuntimeException($err); return $out;
}
// Comparação das etapas não confunde pontos do Estado-Maior com votos populares.
$populares=[
    ['candidato_id'=>101,'total_votos'=>90],
    ['candidato_id'=>102,'total_votos'=>60],
    ['candidato_id'=>103,'total_votos'=>60],
    ['candidato_id'=>104,'total_votos'=>30],
];
$finalistas=[
    ['candidato_id'=>104,'total_votos'=>30,'pontos_fase4'=>15],
    ['candidato_id'=>102,'total_votos'=>60,'pontos_fase4'=>9],
    ['candidato_id'=>103,'total_votos'=>60,'pontos_fase4'=>9],
];
$comparativo=EstadoMaior::classificarComparativo($finalistas,$populares);
check(array_column($comparativo,'posicao_fase3')===[4,2,2],'popular usa categoria inteira, não apenas finalistas');
check(array_column($comparativo,'posicao_fase4')===[1,2,2],'ordenação consolidada independente da popular');
check(!$comparativo[0]['empate_fase3'] && $comparativo[1]['empate_fase3'] && $comparativo[1]['empate_fase4'],'empates de cada etapa identificados');
$finalistas[1]['data_admissao_comara']='2000-01-01';
$comparativo=EstadoMaior::classificarComparativo($finalistas,$populares);
check(array_column($comparativo,'posicao_fase4')===[1,2,3],'quatro critérios resolvem empate da Fase 4');
check(array_column($comparativo,'posicao_fase3')===[4,2,2],'desempate institucional não inventa diferença de votos populares');
check(EstadoMaior::classificarComparativo([],[])===[],'exceção sem finalistas não recebe posição');
$unico=EstadoMaior::classificarComparativo([['candidato_id'=>1,'total_votos'=>0,'pontos_fase4'=>5]],[['candidato_id'=>1,'total_votos'=>0]]);
check($unico[0]['posicao_fase3']===1 && $unico[0]['posicao_fase4']===1 && !$unico[0]['empate_fase3'],'candidato único e zero votos exibem posições válidas');
$posicoesAntes=array_column($r['graduado']['ranking'],null,'candidato_id');
foreach ($r['graduado']['candidatos_empatados'] as $c) check(isset($c['posicao_fase3'],$c['posicao_fase4']),'opções presidenciais recebem as duas posições');

$html=renderizarFase5Teste(2,$pres);
check(str_contains($html,'Classificação popular — Fase 3') && str_contains($html,'Ordenação final — Fase 4'),'tela separa posições de ambas as etapas');
check(str_contains($html,'(empatado)') && str_contains($html,'não participou das Fases 3 e 4'),'tela distingue empate de exceção');
check(str_contains($html,'Resultado provisório') && str_contains($html,'Exceção: categoria sem finalistas'),'renderiza apuração e situação excepcional');
check(str_contains($html,'Empate persistente') && str_contains($html,'disabled'),'interface bloqueia homologação pendente');
check(substr_count($html,'name="categoria"')===2,'formulário de decisão somente para empate e exceção');
check(!str_contains(renderizarFase5Teste(2,$major),'name="categoria"'),'Direção não recebe controles presidenciais');
check(!str_contains(renderizarFase5Teste(2,$major),'Avaliações individuais da Fase 4 — consulta restrita'),'Direção não recebe justificativas identificadas');
$out=executarControllerTeste('RelatorioController','siteOficial',1);
check(!str_contains($out,'Nome completo') && str_contains($out,'somente após a homologação'),'rota de publicação não revela vencedores antes da homologação');
$out=executarControllerTeste('Fase5Controller','votoMinerva',$pres,['pleito_id'=>2,'categoria'=>'graduado','candidato_id'=>$g1,'justificativa'=>'Tentativa CSRF','confirmar'=>'1','versao'=>Homologacao::versaoDecisao(null),'csrf_token'=>'invalido']);
check((int)$pdo->query('SELECT COUNT(*) FROM voto_minerva_presidente WHERE pleito_id=2')->fetchColumn()===0,'rota rejeita CSRF inválido sem gravar');

check(!Homologacao::registrarVotoMinerva(2,'graduado',$g1,'Fundamentação',$major)['sucesso'],'Direção Superior não decide na Fase 5');
check(!Homologacao::homologarResultados(2,$major,$ata)['sucesso'],'Direção Superior não homologa');
check(!Homologacao::registrarVotoMinerva(2,'graduado',$g1,'  ',$pres)['sucesso'],'fundamentação obrigatória');
check(!Homologacao::registrarVotoMinerva(2,'graduado',$g1,str_repeat("\u{00a0}",25),$pres)['sucesso'],'espaços Unicode não são fundamentação');
check(!Homologacao::homologarResultados(2,$pres,str_repeat("\u{00a0}",25))['sucesso'],'espaços Unicode não são ata');
check(!Homologacao::registrarVotoMinerva(2,'praca',(int)$r['praca']['vencedor']['candidato_id'],'Indevida',$pres)['sucesso'],'vencedor regular não oferece escolha presidencial');
check(!Homologacao::registrarVotoMinerva(2,'inexistente',$g1,'Indevida',$pres)['sucesso'],'categoria inválida rejeitada');
$pdo->exec("UPDATE pleitos SET fase_atual=4 WHERE id=2");
check(!Homologacao::registrarVotoMinerva(2,'graduado',$g1,'Indevida',$pres)['sucesso'],'decisão fora da fase rejeitada');
$pdo->exec("UPDATE pleitos SET fase_atual=5,status='cancelado' WHERE id=2");
check(!Homologacao::homologarResultados(2,$pres,$ata)['sucesso'],'homologação de cancelado rejeitada');
$pdo->exec("UPDATE pleitos SET status='em_andamento' WHERE id=2");
// Falha real no log deve reverter a decisão, não somente ocultar o erro.
$pdo->exec("CREATE TRIGGER teste_auditoria_fase5 BEFORE INSERT ON logs_auditoria FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='falha interna secreta'");
check(!Homologacao::registrarVotoMinerva(2,'graduado',$g1,'Não pode persistir',$pres)['sucesso'],'falha de auditoria bloqueia sucesso da decisão');
check((int)$pdo->query('SELECT COUNT(*) FROM voto_minerva_presidente WHERE pleito_id=2')->fetchColumn()===0,'auditoria e decisão atômicas');
$pdo->exec('DROP TRIGGER teste_auditoria_fase5');
$pdo->exec('RENAME TABLE fase5_resultados TO fase5_resultados_ausente');
$semMigracao=Homologacao::registrarVotoMinerva(2,'graduado',$g1,'Não pode gravar',$pres);
check(!$semMigracao['sucesso'] && str_contains($semMigracao['mensagem'],'migrate_fase5.php'),'estrutura ausente bloqueia com comando de migração');
$pdo->exec('RENAME TABLE fase5_resultados_ausente TO fase5_resultados');
$vazio=Homologacao::versaoDecisao(null);
check(Homologacao::registrarVotoMinerva(2,'graduado',$g1,'Primeira decisão',$pres,$vazio)['sucesso'],'Presidente registra decisão expressa');
$m=Homologacao::apurarResultados(2)['categorias']['graduado']['minerva_registrado'];
$quantidade=(int)$pdo->query('SELECT COUNT(*) FROM logs_auditoria')->fetchColumn();
check(Homologacao::registrarVotoMinerva(2,'graduado',$g1,'Primeira decisão',$pres,$vazio)['sucesso'],'reenvio idêntico idempotente');
check((int)$pdo->query('SELECT COUNT(*) FROM logs_auditoria')->fetchColumn()===$quantidade,'reenvio não duplica auditoria');
check(!Homologacao::registrarVotoMinerva(2,'graduado',$g2,'Formulário desatualizado',$pres,$vazio)['sucesso'],'formulário obsoleto não sobrescreve decisão');
check(Homologacao::registrarVotoMinerva(2,'graduado',$g2,'Decisão revista e fundamentada',$admin,Homologacao::versaoDecisao($m))['sucesso'],'ADMIN preserva poder de decisão e revisão');
$log=$pdo->query("SELECT detalhes_json FROM logs_auditoria WHERE acao='VOTO_MINERVA_REGISTRADO' ORDER BY id DESC LIMIT 1")->fetchColumn();
check(str_contains($log,'Primeira decisão') && str_contains($log,'Decisão revista'),'auditoria preserva fundamentação anterior e posterior');
check(Homologacao::registrarVotoMinerva(2,'sptf',$e1,'Exceção fundamentada',$pres,$vazio)['sucesso'],'escolha excepcional registrada');
$html=renderizarFase5Teste(2,$pres);
check(substr_count($html,'Revisar decisão registrada')===2 && str_contains($html,'Decisão revista e fundamentada'),'interface permite revisão e recupera fundamento');
check(!Homologacao::homologarResultados(2,$pres,'curta')['sucesso'],'ata insuficiente rejeitada');
$antes=Homologacao::apurarResultados(2);
foreach ($antes['categorias']['graduado']['ranking'] as $c) {
    $anterior=$posicoesAntes[$c['candidato_id']];
    check($c['posicao_fase3']===$anterior['posicao_fase3'] && $c['posicao_fase4']===$anterior['posicao_fase4'],'minerva não reescreve posições das etapas anteriores');
}
check(!Homologacao::homologarResultados(2,$pres,$ata,'desatualizado')['sucesso'],'homologação exige a apuração efetivamente conferida');
$pdo->exec("CREATE TRIGGER teste_retrato_fase5 BEFORE INSERT ON fase5_resultados FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='falha interna secreta'");
$falha=Homologacao::homologarResultados(2,$pres,$ata);
check(!$falha['sucesso'] && !str_contains($falha['mensagem'],'secreta'),'falha de persistência não exibe sucesso nem detalhe SQL');
check((int)$pdo->query('SELECT COUNT(*) FROM homologacao_pleito WHERE pleito_id=2')->fetchColumn()===0,'falha no retrato reverte ata e vencedores');
check(Pleito::getPorId(2)['status']==='em_andamento','falha mantém status anterior');
$pdo->exec('DROP TRIGGER teste_retrato_fase5');
$pdo->exec("CREATE TRIGGER teste_auditoria_fase5 BEFORE INSERT ON logs_auditoria FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='falha de auditoria'");
check(!Homologacao::homologarResultados(2,$pres,$ata)['sucesso'],'auditoria obrigatória na homologação');
check((int)$pdo->query('SELECT COUNT(*) FROM fase5_resultados WHERE pleito_id=2')->fetchColumn()===0,'auditoria falha reverte também o retrato');
$pdo->exec('DROP TRIGGER teste_auditoria_fase5');
rejeita(fn()=>Homologacao::avaliacoesIndividuais(2,$major),'Direção não consulta avaliação de terceiros');
check(count(Homologacao::avaliacoesIndividuais(2,$pres))===12,'Presidente consulta posições e justificativas identificadas');
check(count(Homologacao::avaliacoesIndividuais(2,$admin))===12,'ADMIN consulta avaliações identificadas');
// Dados confirmados incompletos não viram pontuação zero.
$item=$pdo->query('SELECT * FROM fase4_ordenacoes WHERE pleito_id=2 LIMIT 1')->fetch();
$pdo->prepare('DELETE FROM fase4_ordenacoes WHERE pleito_id=2 AND usuario_id=? AND categoria=? AND candidato_id=?')->execute([$item['usuario_id'],$item['categoria'],$item['candidato_id']]);
rejeita(fn()=>Homologacao::apurarResultados(2),'item ausente bloqueia apuração');
$pdo->prepare('INSERT INTO fase4_ordenacoes VALUES(?,?,?,?,?,?)')->execute(array_values($item));
// Universo excepcional permanece congelado, inclusive se houver novos cadastros.
$ciclo=EstadoMaior::ciclo(2); $dados=$ciclo['categorias'];
$dados['sptf']['excepcional']=[];
$pdo->prepare('DELETE FROM voto_minerva_presidente WHERE pleito_id=2 AND categoria=?')->execute(['sptf']);
$pdo->prepare('UPDATE fase4_ciclos SET categorias_json=? WHERE pleito_id=2')->execute([json_encode($dados)]);
check(!Homologacao::homologarResultados(2,$pres,$ata)['sucesso'],'categoria sem qualquer integrante bloqueia homologação');
check(!Homologacao::registrarVotoMinerva(2,'sptf',$e1,'Fora do universo',$pres)['sucesso'],'não amplia lista excepcional vazia');
$pdo->prepare('UPDATE fase4_ciclos SET categorias_json=? WHERE pleito_id=2')->execute([$ciclo['categorias_json']]);
check(Homologacao::registrarVotoMinerva(2,'sptf',$e1,'Exceção fundamentada',$pres)['sucesso'],'restaura fixture excepcional');
// Concorrência real: duas conexões aguardam o mesmo lock; só uma pode homologar.
$worker=tempnam(sys_get_temp_dir(),'comara-fase5-');
file_put_contents($worker,'<?php require '.var_export(__DIR__.'/../src/Models/Homologacao.php',true).'; echo json_encode(Homologacao::homologarResultados(2,'.(int)$pres.','.var_export($ata,true).'));');
$pdo->beginTransaction(); $pdo->query('SELECT id FROM pleitos WHERE id=2 FOR UPDATE');
$processos=[];
for ($i=0;$i<2;$i++) { $pipes=[]; $proc=proc_open([PHP_BINARY,$worker],[1=>['pipe','w'],2=>['pipe','w']],$pipes); if (!is_resource($proc)) throw new RuntimeException('Não foi possível iniciar teste concorrente.'); $processos[]=[$proc,$pipes]; }
$pdo->commit(); $sucessos=0;
foreach ($processos as [$proc,$pipes]) { $out=stream_get_contents($pipes[1]); $err=stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); $exit=proc_close($proc); if ($exit!==0) throw new RuntimeException($err); $res=json_decode($out,true,512,JSON_THROW_ON_ERROR); $sucessos+=(int)$res['sucesso']; }
unlink($worker);
check($sucessos===1,'requisições concorrentes geram exatamente uma homologação');
check((int)$pdo->query('SELECT COUNT(*) FROM homologacao_pleito WHERE pleito_id=2')->fetchColumn()===1,'uma única ata persistida');
check(!Homologacao::homologarResultados(2,$pres,$ata)['sucesso'],'reenvio não repete homologação');
check(!Pleito::reiniciar(2,true,false,$admin)['sucesso'],'ADMIN não apaga eleição homologada');
check(!Pleito::voltarFase(2,$admin)['sucesso'],'retorno de fase bloqueado após homologação');
check(!Homologacao::registrarVotoMinerva(2,'graduado',$g1,'Revisão tardia',$pres)['sucesso'],'decisão definitiva após homologação');
require_once __DIR__.'/../src/Models/FichaIndicacao.php';
require_once __DIR__.'/../src/Models/AvaliacaoTacf.php';
rejeita(fn()=>FichaIndicacao::salvarFicha(['pleito_id'=>2,'candidato_id'=>$g1],$admin),'ficha não pode mudar após homologação');
rejeita(fn()=>AvaliacaoTacf::salvarNota(2,$g1,5,$admin),'TACF não pode mudar após homologação');
rejeita(fn()=>Efetivo::salvarDelegacao(2,$g1,1,$admin,'Missão'),'delegação não pode mudar após homologação');
rejeita(fn()=>Efetivo::removerDelegacao(2,$g1),'delegação não pode ser removida após homologação');
$salvo=Homologacao::apurarResultados(2);
check(isset($salvo['categorias']['graduado']['ranking'][0]['posicao_fase3'],$salvo['categorias']['graduado']['ranking'][0]['posicao_fase4']),'homologação arquiva as duas classificações');
$semPosicoes=$salvo['categorias'];
foreach ($semPosicoes as &$catTeste) {
    foreach ($catTeste['ranking'] as &$candTeste) unset($candTeste['posicao_fase3'],$candTeste['posicao_fase4'],$candTeste['empate_fase3'],$candTeste['empate_fase4']);
    unset($candTeste);
}
unset($catTeste);
$pdo->prepare('UPDATE fase5_resultados SET resultado_json=? WHERE pleito_id=2')->execute([json_encode($semPosicoes)]);
check(str_contains(renderizarFase5Teste(2,$pres),'Posição não registrada'),'snapshot anterior sem posições é exibido sem recálculo ou warning');
$pdo->prepare('UPDATE fase5_resultados SET resultado_json=? WHERE pleito_id=2')->execute([json_encode($salvo['categorias'])]);
check(!str_contains(renderizarFase5Teste(2,$pres),'name="categoria"'),'interface homologada não oferece alteração');
$out=executarControllerTeste('RelatorioController','siteOficial',1,[],['pleito_id'=>'2']);
check(str_contains($out,'Nome completo'),'efetivo consulta publicação após homologação');
$pdo->exec("UPDATE efetivo SET nome_guerra='Cadastro alterado' WHERE id=".$g2);
$pdo->exec('DELETE FROM fase4_ordenacoes WHERE pleito_id=2');
check(Homologacao::apurarResultados(2)===$salvo,'resultado homologado independente de alterações cadastrais e apuração atual');
$h=HistoricoVencedores::listar(2028);
check(count($h[2027])===4 && (int)$h[2027]['graduado']['candidato_id']===$g2,'histórico consulta quatro vencedores oficiais sem cópia duplicada');
// Homologação anterior à migração consulta IDs oficiais, não a nova classificação.
$pdo->exec('DELETE FROM fase5_resultados WHERE pleito_id=1');
$pdo->exec('DELETE FROM fase4_ciclos WHERE pleito_id=1');
$legado=Homologacao::apurarResultados(1);
check($legado['legado'] && $legado['homologado'],'homologação legada consultável sem ciclo novo');
check(str_contains(renderizarFase5Teste(1,$pres),'pontuações históricas não são presumidas'),'renderiza legado sem avisos ou pontuação inventada');
foreach (EstadoMaior::CATEGORIAS as $cat) check((int)$legado['categorias'][$cat]['vencedor']['candidato_id']===(int)$original['dados_homologacao']['vencedor_'.$cat.'_id'],'legado preserva vencedor '.$cat);
$pdo->exec('RENAME TABLE historico_vencedores TO historico_vencedores_ausente');
check(Homologacao::apurarResultados(1)['homologado'],'legado não depende de histórico novo');
$pdo->exec('RENAME TABLE historico_vencedores_ausente TO historico_vencedores');
$_GET['pleito_id']='1'; check((int)Pleito::paraConsulta()['id']===1,'seleção explícita de homologação anterior');
$_GET['pleito_id']='999'; rejeita(fn()=>Pleito::paraConsulta(),'histórico inválido rejeitado'); unset($_GET['pleito_id']);
require __DIR__.'/../database/migrate_fase5.php';
check(Homologacao::apurarResultados(2)===$salvo,'migração idempotente preserva retrato homologado');
echo "$n verificações acumuladas aprovadas.\n";
