<?php
declare(strict_types=1);
require __DIR__.'/teste_fase4_integracao.php';
require __DIR__.'/../database/migrate_fase5.php';
$original=Homologacao::apurarResultados(1);
$pdo->exec("INSERT INTO pleitos(id,ano,titulo,fase_atual) VALUES(2,2027,'Fase 5 teste',5)");
$pdo->exec('INSERT INTO fase4_ciclos SELECT 2,categorias_json,encerrado_em,encerrado_por,aberto_em FROM fase4_ciclos WHERE pleito_id=1');
$pdo->exec('INSERT INTO fase4_decisoes_corte SELECT 2,categoria,candidatos_json,justificativa,usuario_id,confirmado_em FROM fase4_decisoes_corte WHERE pleito_id=1');
$pdo->exec('INSERT INTO fase4_avaliadores SELECT 2,usuario_id,nome_guerra,posto FROM fase4_avaliadores WHERE pleito_id=1');
$pdo->exec('INSERT INTO fase4_composicao SELECT 2,usuario_id,habilitado FROM fase4_composicao WHERE pleito_id=1');
$pdo->exec('INSERT INTO fase4_confirmacoes SELECT 2,usuario_id,confirmado_em FROM fase4_confirmacoes WHERE pleito_id=1');
$pdo->exec('INSERT INTO fase4_ordenacoes SELECT 2,usuario_id,categoria,candidato_id,posicao,justificativa FROM fase4_ordenacoes WHERE pleito_id=1');
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
$r=Homologacao::apurarResultados(2)['categorias'];
check(count(array_filter($r,fn($c)=>$c['vencedor']!==null))===0,'não predefine vencedor nem em categoria sem empate');
$html=renderizarFase5Teste(2,$pres);
check(substr_count($html,'name="categoria"')===4,'todas as categorias recebem decisão presidencial');
check(str_contains($html,'Excluir nesta fase') && str_contains($html,'Incluir integrante deste grupo'),'interface oferece exclusão e inclusão');
check(!preg_match('/<option value="[123]" selected/',$html),'nenhuma posição inicialmente selecionada');
check(strpos($html,'Avaliações individuais da Fase 4 — consulta restrita')<strpos($html,'Ato de Homologação Oficial do Pleito'),'avaliações individuais antes da homologação');
check(!str_contains(renderizarFase5Teste(2,$major),'name="categoria"'),'Direção não recebe controles presidenciais');
check(!str_contains(renderizarFase5Teste(2,$major),'Avaliações individuais da Fase 4 — consulta restrita'),'Direção não consulta avaliações identificadas');
check(!Homologacao::homologarResultados(2,$pres,$ata)['sucesso'],'sem decisões homologação bloqueada');
$refs=$r['graduado']['referencia_fase4']; $g1=(int)$refs[0]['candidato_id']; $g2=(int)$refs[1]['candidato_id']; $g3=(int)$refs[2]['candidato_id'];
$ord=[['candidato_id'=>$g1,'posicao'=>3],['candidato_id'=>$g2,'posicao'=>1],['candidato_id'=>$g3,'posicao'=>2]];
$vazio=Homologacao::versaoDecisao(null);
check(!Homologacao::salvarOrdenacao(2,'graduado',$ord,'Motivo',$major,$vazio)['sucesso'],'Direção não ordena');
check(!Homologacao::salvarOrdenacao(2,'graduado',$ord,"\u{00a0}",$pres,$vazio)['sucesso'],'fundamentação não pode ser só espaços');
$x=$ord; $x[1]['posicao']=3;
check(!Homologacao::salvarOrdenacao(2,'graduado',$x,'Motivo',$pres,$vazio)['sucesso'],'posição repetida rejeitada');
$x=$ord; $x[1]['candidato_id']=$g1;
check(!Homologacao::salvarOrdenacao(2,'graduado',$x,'Motivo',$pres,$vazio)['sucesso'],'pessoa repetida rejeitada');
check(!Homologacao::salvarOrdenacao(2,'graduado',[['candidato_id'=>[],'posicao'=>1]],'Motivo',$pres,$vazio)['sucesso'],'item malformado rejeitado');
check(!Homologacao::salvarOrdenacao(2,'invalida',$ord,'Motivo',$pres,$vazio)['sucesso'],'categoria inválida rejeitada');
check(!Homologacao::salvarOrdenacao(2,'graduado',array_slice($ord,0,1),'Motivo',$pres,$vazio)['sucesso'],'omissão de finalista rejeitada');
$pdo->exec("UPDATE pleitos SET fase_atual=4 WHERE id=2");
check(!Homologacao::salvarOrdenacao(2,'graduado',$ord,'Motivo',$pres,$vazio)['sucesso'],'fora da fase rejeitado');
$pdo->exec("UPDATE pleitos SET fase_atual=5 WHERE id=2");
$pdo->exec("CREATE TRIGGER falha_decisao BEFORE INSERT ON logs_auditoria FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='segredo SQL'");
check(!Homologacao::salvarOrdenacao(2,'graduado',$ord,'Motivo',$pres,$vazio)['sucesso'],'auditoria é obrigatória');
check((int)$pdo->query('SELECT COUNT(*) FROM fase5_decisoes WHERE pleito_id=2')->fetchColumn()===0,'falha reverte decisão');
$pdo->exec('DROP TRIGGER falha_decisao');
check(Homologacao::salvarOrdenacao(2,'graduado',$ord,'Ordem diferente da Fase 4.',$pres,$vazio)['sucesso'],'Presidente pode mudar todas as posições');
check(!Homologacao::salvarOrdenacao(2,'graduado',$ord,'Obsoleto',$pres,$vazio)['sucesso'],'formulário obsoleto não sobrescreve');
$agora=Homologacao::apurarResultados(2)['categorias']['graduado'];
check(array_map('intval',array_column($agora['ranking'],'candidato_id'))===[$g2,$g3,$g1],'ordenação salva 1º 2º e 3º');
$map=array_column($refs,null,'candidato_id');
foreach ($agora['ranking'] as $c) check($c['posicao_fase3']===$map[$c['candidato_id']]['posicao_fase3'] && $c['posicao_fase4']===$map[$c['candidato_id']]['posicao_fase4'],'mantém posições de referência');
// Pessoa sem qualquer participação eleitoral anterior, cadastrada depois da Fase 3.
$pdo->exec("INSERT INTO efetivo(nome,nome_guerra,posto,categoria,tipo_vinculo) VALUES('Novo','Novo','1S','graduado','MILITAR_CARREIRA')");
$novo=(int)$pdo->lastInsertId();
check(decidirCategoriaTeste(2,'graduado',[$novo,$g3])['sucesso'],'inclui pessoa sem Fase 1 e exclui finalistas');
$agora=Homologacao::apurarResultados(2)['categorias']['graduado'];
check((int)$agora['vencedor']['candidato_id']===$novo && count($agora['excluidos'])===2,'exclusão retira finalistas da classificação');
check($agora['vencedor']['posicao_fase3']===null && !$agora['vencedor']['participou_fase4'],'inclusão não inventa posições anteriores');
check(str_contains(renderizarFase5Teste(2,$pres),'Não participou'),'tela identifica ausência nas etapas anteriores');
$fora=(int)$r['praca']['ranking'][0]['candidato_id'];
check(!decidirCategoriaTeste(2,'graduado',[$fora])['sucesso'],'não inclui pessoa de outra categoria');
$pdo->exec('UPDATE efetivo SET ativo=0 WHERE id='.$novo);
check(!decidirCategoriaTeste(2,'graduado',[$novo])['sucesso'],'não inclui inativo');
foreach (['praca','sppf','sptf'] as $cat) check(decidirCategoriaTeste(2,$cat)['sucesso'],'decisão expressa '.$cat);
check(!Homologacao::homologarResultados(2,$pres,$ata)['sucesso'],'revalida cadastro ativo ao homologar');
$pdo->exec('UPDATE efetivo SET ativo=1 WHERE id='.$novo);
// Real POST: validação de CSRF e arrays sem fatal, usando sessão em subprocesso.
$post=['pleito_id'=>2,'categoria'=>'graduado','escolhas'=>$ord,'justificativa'=>'Tentativa','confirmar'=>'1','versao'=>'x','csrf_token'=>['malformado']];
$out=executarControllerTeste('Fase5Controller','ordenar',$pres,$post);
check(str_contains($out,'Recarregue'),'CSRF malformado rejeitado sem fatal');
rejeita(fn()=>Homologacao::avaliacoesIndividuais(2,$major),'Direção não consulta avaliações identificadas');
check(count(Homologacao::avaliacoesIndividuais(2,$pres))===12,'Presidência consulta avaliações');
check(!Homologacao::homologarResultados(2,$major,$ata)['sucesso'],'Direção não homologa');
check(!Homologacao::homologarResultados(2,$pres,'curta')['sucesso'],'ata mínima obrigatória');
check(!Homologacao::homologarResultados(2,$pres,$ata,'obsoleto')['sucesso'],'ata exige versão conferida');
$antes=Homologacao::apurarResultados(2);
$pdo->exec("CREATE TRIGGER falha_retrato BEFORE INSERT ON fase5_resultados FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='segredo SQL'");
$res=Homologacao::homologarResultados(2,$pres,$ata);
check(!$res['sucesso'] && !str_contains($res['mensagem'],'segredo SQL'),'falha de snapshot não expõe SQL');
check((int)$pdo->query('SELECT COUNT(*) FROM homologacao_pleito WHERE pleito_id=2')->fetchColumn()===0,'falha de snapshot reverte ata');
$pdo->exec('DROP TRIGGER falha_retrato');
$worker=tempnam(sys_get_temp_dir(),'fase5-');
file_put_contents($worker,'<?php require '.var_export(ROOT_PATH.'/src/Models/Homologacao.php',true).'; echo json_encode(Homologacao::homologarResultados(2,'.(int)$pres.','.var_export($ata,true).'));');
$pdo->beginTransaction(); $pdo->query('SELECT id FROM pleitos WHERE id=2 FOR UPDATE'); $processos=[];
for($i=0;$i<2;$i++){ $pipes=[]; $proc=proc_open([PHP_BINARY,$worker],[1=>['pipe','w'],2=>['pipe','w']],$pipes); $processos[]=[$proc,$pipes]; }
$pdo->commit(); $sucessos=0;
foreach($processos as [$proc,$pipes]) { $out=stream_get_contents($pipes[1]); $err=stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); check(proc_close($proc)===0,'processo concorrente conclui'); $sucessos+=(int)json_decode($out,true,512,JSON_THROW_ON_ERROR)['sucesso']; } unlink($worker);
check($sucessos===1,'somente uma homologação concorrente');
$salvo=Homologacao::apurarResultados(2);
check(count($salvo['categorias']['graduado']['excluidos'])===2 && (int)$salvo['categorias']['graduado']['vencedor']['candidato_id']===$novo,'snapshot preserva inclusões exclusões e posições');
check(!Homologacao::salvarOrdenacao(2,'graduado',$ord,'Tardia',$pres,$vazio)['sucesso'],'homologação bloqueia revisão');
check(!Homologacao::homologarResultados(2,$pres,$ata)['sucesso'],'não duplica homologação');
check(!Pleito::reiniciar(2,true,false,$admin)['sucesso'] && !Pleito::voltarFase(2,$admin)['sucesso'],'protege eleição homologada');
check(!str_contains(renderizarFase5Teste(2,$pres),'name="categoria"'),'tela homologada sem controles');
$out=executarControllerTeste('RelatorioController','siteOficial',1,[],['pleito_id'=>2]);
check(str_contains($out,'Novo'),'publicação usa vencedor presidencial');
$pdo->exec("UPDATE efetivo SET nome_guerra='Alterado' WHERE id=".$novo);
$pdo->exec('DELETE FROM fase4_ordenacoes WHERE pleito_id=2');
check(Homologacao::apurarResultados(2)===$salvo,'snapshot não recalcula após alteração cadastral');
require __DIR__.'/../database/migrate_fase5.php';
check(Homologacao::apurarResultados(2)===$salvo,'migração repetida preserva homologação');
$pdo->exec('DELETE FROM fase5_resultados WHERE pleito_id=1');
$pdo->exec('DELETE FROM fase4_ciclos WHERE pleito_id=1');
check(Homologacao::apurarResultados(1)['legado'],'consulta ata antiga sem reconstruir eleição');
check(str_contains(renderizarFase5Teste(1,$pres),'pontuações históricas não são presumidas'),'tela legada sem warnings');
echo "$n verificações acumuladas aprovadas.\n";
