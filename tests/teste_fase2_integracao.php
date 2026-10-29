<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
if (getenv('FASE2_TEST_DB') !== '1' || !str_ends_with(DB_NAME, '_test')) {
    throw new RuntimeException('Use somente banco descartável terminado em _test e FASE2_TEST_DB=1.');
}
require_once __DIR__ . '/../src/Models/Efetivo.php';
require_once __DIR__ . '/../src/Models/SelecaoFase2.php';
require_once __DIR__ . '/../src/Models/HistoricoVencedores.php';
require_once __DIR__ . '/../src/Models/Pleito.php';
require_once __DIR__ . '/../src/Auth/AuthManager.php';
$pdo = Database::getConnection();
if ($pdo->query('SHOW TABLES')->fetch()) throw new RuntimeException('O banco de testes deve estar vazio.');
$pdo->exec(file_get_contents(__DIR__ . '/../database/schema.sql'));
require __DIR__ . '/../database/migrate_fase2.php';
require __DIR__ . '/../database/migrate_fase2.php';
function check(bool $ok, string $msg): void { if (!$ok) throw new RuntimeException($msg); echo "OK: $msg\n"; }
function pessoa(string $nome, string $cat, string $div, string $posto = '1S'): int {
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare('INSERT INTO efetivo (nome,nome_guerra,posto,categoria,tipo_vinculo,divisao_sigla,setor,data_admissao_comara,data_inicio_servico)
        VALUES (?,?,?,?,"MILITAR_CARREIRA",?,?,"2020-01-01","2000-01-01")');
    $stmt->execute([$nome,$nome,$posto,$cat,$div,$div]); return (int)$pdo->lastInsertId();
}
function usuario(int $ef, string $perfil): int {
    $pdo = Database::getConnection();
    $pdo->prepare('INSERT INTO usuarios(login,efetivo_id,perfil) VALUES (?,?,?)')->execute(['user'.$ef,$ef,$perfil]);
    return (int)$pdo->lastInsertId();
}
function chefe(string $sigla, int $u): void {
    Database::getConnection()->prepare('INSERT INTO divisoes_assessorias(sigla,nome,tipo,chefe_usuario_id) VALUES (?, ?, "ASSESSORIA", ?)
        ON DUPLICATE KEY UPDATE chefe_usuario_id=VALUES(chefe_usuario_id)')->execute([$sigla,$sigla,$u]);
}
function ficha(int $id, int $u, float $nota, int $nob=0): void {
    Database::getConnection()->prepare('INSERT INTO fichas_indicacao(pleito_id,candidato_id,avaliador_id,media_calculada,is_nob,hash_integridade)
        VALUES (1,?,?,?,?,"teste")')->execute([$id,$u,$nota,$nob]);
}
$pdo->exec("INSERT INTO pleitos(id,ano,titulo,fase_atual) VALUES (1,2026,'Teste',2)");
$levy = usuario(pessoa('LEVY','ineligivel','DPC','Cel'),'CHEFE_DIV_ASSESS');
$thiagoEf = pessoa('THIAGO','ineligivel','DA','Ten Cel');
$thiago = usuario($thiagoEf,'CHEFE_DIV_ASSESS');
$terceiro = usuario(pessoa('TERCEIRO','ineligivel','APOG','Maj'),'CHEFE_DIV_ASSESS');
$semChefia = usuario(pessoa('SEM CHEFIA','ineligivel','ACI','Cel'),'CHEFE_DIV_ASSESS');
$admin = usuario(pessoa('ADMIN','ineligivel','DA','Cap'),'ADMIN');
chefe('DPC',$levy); chefe('AINT',$levy); chefe('ACI',$thiago); chefe('APOG',$terceiro);
check((int)Efetivo::getMilitarMaisAntigoAssessorias()['id'] === $thiagoEf, 'Thiago representa as assessorias; Levy acumula DPC');
check(Efetivo::orgaoSelecaoUsuario($levy) === 'DPC', 'Levy continua selecionando DPC');
check(!AuthManager::isChefeOuRepresentanteFase2(['id'=>$semChefia,'perfil'=>'CHEFE_DIV_ASSESS']), 'perfil/lotação não substituem chefia');
chefe('DA',$thiago);
check((int)Efetivo::getMilitarMaisAntigoAssessorias()['usuario_id'] === $terceiro, 'pula também o segundo chefe acumulado');
$pdo->exec("DELETE FROM divisoes_assessorias WHERE sigla='DA'");
$cands=[];
for ($i=1;$i<=5;$i++) { $cands[]=pessoa('C'.$i,'graduado','DPC'); ficha(end($cands),$admin,5-$i/10); }
$outra=pessoa('OUTRA','graduado','DL'); ficha($outra,$admin,5);
$sem=pessoa('SEM NOTA','graduado','DPC');
$nob=pessoa('NOB','graduado','DPC'); ficha($nob,$admin,0,1);
$lista=Efetivo::listarParaSelecaoFase2(1,'graduado','DPC');
check(array_column($lista,'id') === array_slice($cands,0,2), 'classificação por divisão, mínimo 2 e exclusão sem nota/NOB');
$idsHist=[]; foreach (['graduado','praca','sppf','sptf'] as $cat) $idsHist[$cat]=pessoa('V'.$cat,$cat,'DS');
$hist=[2024=>$idsHist,2025=>$idsHist]; $hist[2024]['graduado']=$cands[0];
HistoricoVencedores::salvar(2026,$hist,$admin);
$enriq=HistoricoVencedores::enriquecer($lista,1);
check($enriq[0]['vitorias_ultimos_dois_anos'] === 1 && $enriq[1]['vitorias_ultimos_dois_anos'] === 0,'histórico 2024/25 por vencedor');
$pdo->exec('UPDATE historico_vencedores SET ano=2023 WHERE ano=2024');
check(HistoricoVencedores::enriquecer($lista,1)[0]['vitorias_ultimos_dois_anos'] === 0,'vitória em 2023 não conta para 2026');
$antes=(int)$pdo->query('SELECT COUNT(*) FROM historico_vencedores')->fetchColumn();
$invalido=$hist; $invalido[2024]['sppf']=$cands[0];
try { HistoricoVencedores::salvar(2026,$invalido,$admin); throw new RuntimeException('Aceitou grupo incorreto'); } catch (InvalidArgumentException $e) {}
check((int)$pdo->query('SELECT COUNT(*) FROM historico_vencedores')->fetchColumn()===$antes,'histórico inválido não grava parcialmente');
check(!SelecaoFase2::salvarSelecao(1,'DPC',$levy,['graduado'=>[$outra]])['sucesso'],'rejeita candidato de outra divisão');
check(!SelecaoFase2::salvarSelecao(1,'DPC',$levy,['graduado'=>[$cands[4]]])['sucesso'],'rejeita candidato fora do corte');
check(!SelecaoFase2::salvarSelecao(1,'DPC',$levy,['graduado'=>[$cands[0],$cands[1]]])['sucesso'],'rejeita dois por grupo');
check(!SelecaoFase2::salvarSelecao(1,'DPC',$thiago,['graduado'=>[$cands[0]]])['sucesso'],'rejeita outro selecionador');
check(SelecaoFase2::salvarSelecao(1,'DPC',$levy,['graduado'=>[$cands[0]]])['sucesso'],'aceita um e grupos vazios');
check(SelecaoFase2::relatorioProgressoOrgaos(1)['DPC']['concluido'],'progresso considera vazios');
check(!Pleito::avancarFase(1,$admin)['sucesso'],'avanço bloqueado com DL pendente');
check(SelecaoFase2::salvarSelecao(1,'DL',$admin,['graduado'=>[$outra]])['sucesso'],'único candidato disponível');
check(Pleito::avancarFase(1,$admin)['sucesso'],'avanço após escolhas válidas');
check(count(Efetivo::listarParaUrna(1,'graduado'))===2,'urna recebe somente escolhidos');
check(!SelecaoFase2::salvarSelecao(1,'DPC',$levy,['graduado'=>[$cands[1]]])['sucesso'],'seleção bloqueada após Fase 2');
echo "Integração concluída.\n";
