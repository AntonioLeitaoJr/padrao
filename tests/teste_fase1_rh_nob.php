<?php
declare(strict_types=1);

require_once '/var/www/html/config/config.php';
require_once '/var/www/html/config/constants.php';
require_once '/var/www/html/src/Models/Pleito.php';
require_once '/var/www/html/src/Models/Efetivo.php';
require_once '/var/www/html/src/Models/FichaIndicacao.php';
require_once '/var/www/html/src/Auth/AuthManager.php';
require_once '/var/www/html/src/Middleware/RoleMiddleware.php';

echo "========================================================\n";
echo "   INICIANDO TESTES DO PERFIL RH, AVALIADOR FASE 1 & NOB \n";
echo "========================================================\n\n";

$pdo = Database::getConnection();
$pleito = Pleito::getAtivo();

if (!$pleito) {
    echo "[ERRO FATAL] Nenhum pleito ativo no banco de dados.\n";
    exit(1);
}
$pleitoId = (int)$pleito['id'];

// --------------------------------------------------------------------------
// TESTE 1: AuthManager::isAvaliadorFase1 (Apenas Tenente ou Capitão)
// --------------------------------------------------------------------------
echo "--- TESTE 1: VALIDAÇÃO DE POSTOS DE AVALIADORES FASE 1 ---\n";

$testCases = [
    ['posto' => 'Cap',    'perfil' => 'ELEITOR', 'esperado' => true],
    ['posto' => '1º Ten', 'perfil' => 'ELEITOR', 'esperado' => true],
    ['posto' => '2º Ten', 'perfil' => 'ELEITOR', 'esperado' => true],
    ['posto' => 'Ten',    'perfil' => 'ELEITOR', 'esperado' => true],
    ['posto' => 'Cel',    'perfil' => 'ELEITOR', 'esperado' => false],
    ['posto' => 'Ten Cel','perfil' => 'ELEITOR', 'esperado' => false],
    ['posto' => 'Maj',    'perfil' => 'ELEITOR', 'esperado' => false],
    ['posto' => 'SO',     'perfil' => 'ELEITOR', 'esperado' => false],
    ['posto' => '1S',     'perfil' => 'ELEITOR', 'esperado' => false],
    ['posto' => '2S',     'perfil' => 'ELEITOR', 'esperado' => false],
    ['posto' => '3S',     'perfil' => 'ELEITOR', 'esperado' => false],
    ['posto' => 'Cb',     'perfil' => 'ELEITOR', 'esperado' => false],
    ['posto' => 'S1',     'perfil' => 'ELEITOR', 'esperado' => false],
    ['posto' => 'CV',     'perfil' => 'ELEITOR', 'esperado' => false],
    // ADMIN tem autoridade total independente de posto
    ['posto' => 'Cel',    'perfil' => 'ADMIN',   'esperado' => true],
    ['posto' => null,     'perfil' => 'ADMIN',   'esperado' => true],
];

foreach ($testCases as $tc) {
    $user = ['posto' => $tc['posto'], 'perfil' => $tc['perfil']];
    $resultado = AuthManager::isAvaliadorFase1($user);
    if ($resultado !== $tc['esperado']) {
        echo "[FALHA] Falha para posto '{$tc['posto']}' (perfil {$tc['perfil']}): esperado " . ($tc['esperado'] ? 'true' : 'false') . ", obtido " . ($resultado ? 'true' : 'false') . "\n";
        exit(1);
    }
}
echo "[SUCESSO] Todos os 16 casos de teste para postos de avaliadores da Fase 1 foram validados com êxito!\n";

// --------------------------------------------------------------------------
// TESTE 2: Perfil RH e Políticas de Acesso
// --------------------------------------------------------------------------
echo "\n--- TESTE 2: PERFIL RH E POLÍTICAS DE ACESSO ---\n";

if (!defined('PERFIL_RH') || PERFIL_RH !== 'RH') {
    echo "[FALHA] Constante PERFIL_RH não definida ou com valor incorreto.\n";
    exit(1);
}
echo "[SUCESSO] Constante PERFIL_RH='RH' definida corretamente.\n";

$usuarioRh = ['id' => 99999, 'perfil' => PERFIL_RH];
// Simula login de RH
$_SESSION['user'] = $usuarioRh;

// Permissões esperadas:
// Pode acessar [ADMIN, RH]
if (!AuthManager::hasRole([PERFIL_ADMIN, PERFIL_RH])) {
    echo "[FALHA] Usuário RH deveria ter acesso à rota com permissão [ADMIN, RH].\n";
    exit(1);
}
// Não pode acessar ADMIN exclusivo
if (AuthManager::hasRole(PERFIL_ADMIN)) {
    echo "[FALHA] Usuário RH NÃO deveria ter acesso à rota exclusiva de ADMIN.\n";
    exit(1);
}
echo "[SUCESSO] Controle de acesso RBAC validado para o perfil RH (acesso ao efetivo permitido, gestão de pleito bloqueada).\n";

// --------------------------------------------------------------------------
// TESTE 3: Avaliação de Militar com Serviço de Escala (1 a 5)
// --------------------------------------------------------------------------
echo "\n--- TESTE 3: AVALIAÇÃO DE MILITAR COM SERVIÇO DE ESCALA (1 A 5) ---\n";

// Encontra ou cria avaliador Tenente/Capitão
$avaliadorOficial = $pdo->query("SELECT id, saram, nome_guerra, posto FROM efetivo WHERE posto IN ('Cap', '1º Ten', '2º Ten') AND ativo = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$avaliadorOficial) {
    echo "[INFO] Criando Oficial Avaliador de teste...\n";
    $pdo->prepare("INSERT INTO efetivo (saram, nome, nome_guerra, posto, categoria, ativo) VALUES ('999001', 'OFICIAL TESTE', 'TESTE', '1º Ten', 'ineligivel', 1)")->execute();
    $avaliadorId = (int)$pdo->lastInsertId();
} else {
    $avaliadorId = (int)$avaliadorOficial['id'];
}

// Busca id de usuário do avaliador
$avaliadorUserId = (int)$pdo->query("SELECT id FROM usuarios WHERE efetivo_id = {$avaliadorId} LIMIT 1")->fetchColumn();
if (!$avaliadorUserId) {
    $pdo->prepare("INSERT INTO usuarios (login, senha_hash, efetivo_id, perfil, ativo) VALUES ('user_teste_oficial', 'hash', :ef, 'ELEITOR', 1)")->execute([':ef' => $avaliadorId]);
    $avaliadorUserId = (int)$pdo->lastInsertId();
}

// Cria subordinado militar de teste
$pdo->prepare("INSERT INTO efetivo (saram, nome, nome_guerra, posto, setor, divisao_sigla, categoria, chefe_direto_id, ativo) VALUES ('999002', 'SUBORDINADO MILITAR TESTE', 'MIL_TESTE', '3S', 'TESTE_SETOR', 'DA', 'graduado', :chefe, 1)")->execute([':chefe' => $avaliadorId]);
$subordinadoMilitarId = (int)$pdo->lastInsertId();

$criteriosMil = [
    'pleito_id'                  => $pleitoId,
    'candidato_id'               => $subordinadoMilitarId,
    'tipo_avaliacao'             => 'OBRIGATORIA_CHEFE',
    'eficiencia_eficacia'        => 4,
    'conhecimento_especialidade' => 5,
    'iniciativa_adaptabilidade'  => 4,
    'conduta_pontuacao'          => 5,
    'apresentacao_pessoal'       => 4,
    'relacionamento_trabalho'    => 5,
    'lideranca'                  => 4,
    'servico_escala'             => 4,
    'is_nob'                     => 0
];
$servicoEscala = 4;
// Média esperada: (4 + 5 + 4 + 5 + 4 + 5 + 4 + 4) / 8 = 35 / 8 = 4.375 -> 4.38
$mediaEsperadaMil = round((4 + 5 + 4 + 5 + 4 + 5 + 4 + 4) / 8, 2);

$resMil = FichaIndicacao::salvarFicha($criteriosMil, $avaliadorUserId);

if (!$resMil['sucesso']) {
    echo "[FALHA] Erro ao salvar ficha militar: " . ($resMil['mensagem'] ?? '') . "\n";
    exit(1);
}
if ((float)$resMil['media'] !== $mediaEsperadaMil) {
    echo "[FALHA] Média incorreta: esperada {$mediaEsperadaMil}, obtida {$resMil['media']}\n";
    exit(1);
}

$fichaMilDb = $pdo->query("SELECT * FROM fichas_indicacao WHERE pleito_id = {$pleitoId} AND candidato_id = {$subordinadoMilitarId} LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$fichaMilDb || (int)$fichaMilDb['is_nob'] !== 0 || (int)$fichaMilDb['servico_escala'] !== 4) {
    echo "[FALHA] Registro no banco de dados não confere com os dados salvos.\n";
    print_r($fichaMilDb);
    exit(1);
}
echo "[SUCESSO] Avaliação militar registrada com sucesso: 7 critérios + serviço de escala (1 a 5) = média {$resMil['media']}.\n";

// --------------------------------------------------------------------------
// TESTE 4: Avaliação como NOB (Não Observado) e Resolução de Pendências
// --------------------------------------------------------------------------
echo "\n--- TESTE 4: AVALIAÇÃO COMO NOB (NÃO OBSERVADO) E RESOLUÇÃO DE PENDÊNCIAS ---\n";

// Cria segundo subordinado militar de teste para testar NOB
$pdo->prepare("INSERT INTO efetivo (saram, nome, nome_guerra, posto, setor, divisao_sigla, categoria, chefe_direto_id, ativo) VALUES ('999003', 'SUBORDINADO NOB TESTE', 'NOB_TESTE', 'Cb', 'TESTE_SETOR', 'DA', 'praca', :chefe, 1)")->execute([':chefe' => $avaliadorId]);
$subordinadoNobId = (int)$pdo->lastInsertId();

// Verifica que inicialmente ele consta como pendente
$pendenciasAntes = Pleito::validarPendenciasFase1($pleitoId);
$estaPendenteAntes = false;
foreach ($pendenciasAntes['pendencias'] as $p) {
    if ((int)$p['id'] === $subordinadoNobId) {
        $estaPendenteAntes = true;
        break;
    }
}
if (!$estaPendenteAntes) {
    echo "[FALHA] Subordinado recém-criado deveria constar na lista de pendências da Fase 1.\n";
    exit(1);
}
echo "[SUCESSO] Subordinado não avaliado está corretamente pendente na Fase 1.\n";

// Salva avaliação com NOB = 1
$dadosNob = [
    'pleito_id'      => $pleitoId,
    'candidato_id'   => $subordinadoNobId,
    'tipo_avaliacao' => 'OBRIGATORIA_CHEFE',
    'is_nob'         => 1
];
$resNob = FichaIndicacao::salvarFicha($dadosNob, $avaliadorUserId);

if (!$resNob['sucesso']) {
    echo "[FALHA] Erro ao salvar ficha NOB: " . ($resNob['mensagem'] ?? '') . "\n";
    exit(1);
}
if ((float)$resNob['media'] !== 0.0) {
    echo "[FALHA] Média do NOB deveria ser 0.00, obtida: {$resNob['media']}\n";
    exit(1);
}

$fichaNobDb = $pdo->query("SELECT * FROM fichas_indicacao WHERE pleito_id = {$pleitoId} AND candidato_id = {$subordinadoNobId} LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$fichaNobDb || (int)$fichaNobDb['is_nob'] !== 1 || (float)$fichaNobDb['media_calculada'] !== 0.0) {
    echo "[FALHA] Ficha NOB gravada incorretamente no banco de dados.\n";
    print_r($fichaNobDb);
    exit(1);
}

// Verifica que após salvar como NOB, ele NÃO consta mais nas pendências
$pendenciasDepois = Pleito::validarPendenciasFase1($pleitoId);
$estaPendenteDepois = false;
foreach ($pendenciasDepois['pendencias'] as $p) {
    if ((int)$p['id'] === $subordinadoNobId) {
        $estaPendenteDepois = true;
        break;
    }
}
if ($estaPendenteDepois) {
    echo "[FALHA] Subordinado avaliado como NOB NÃO deveria mais constar nas pendências da Fase 1.\n";
    exit(1);
}
echo "[SUCESSO] Ficha NOB salva com sucesso (Média 0.00, is_nob=1) e pendência da Fase 1 foi baixada automaticamente!\n";

// --------------------------------------------------------------------------
// TESTE 5: Verificação na Fase 2 para Candidato NOB (Não Selecionável)
// --------------------------------------------------------------------------
echo "\n--- TESTE 5: COMPORTAMENTO DE CANDIDATO NOB NA FASE 2 ---\n";

$candsFase2 = Efetivo::listarParaSelecaoFase2($pleitoId, 'praca', 'DA');
$candidatoNobEncontrado = null;
foreach ($candsFase2 as $c) {
    if ((int)$c['id'] === $subordinadoNobId) {
        $candidatoNobEncontrado = $c;
        break;
    }
}
if (!$candidatoNobEncontrado) {
    echo "[FALHA] Candidato não localizado na listagem da Fase 2.\n";
    exit(1);
}
if ((int)$candidatoNobEncontrado['foi_nob'] !== 1 || (float)$candidatoNobEncontrado['media_fase1'] != 0.0) {
    echo "[FALHA] Candidato NOB deveria ter foi_nob=1 e media_fase1=0 na Fase 2.\n";
    print_r($candidatoNobEncontrado);
    exit(1);
}
echo "[SUCESSO] Candidato NOB tem foi_nob=1 e média 0.00 na listagem da Fase 2 (bloqueado para seleção).\n";

// --------------------------------------------------------------------------
// TESTE 6: Avaliação de Servidor Civil (SPPF/SPTF - 7 Critérios)
// --------------------------------------------------------------------------
echo "\n--- TESTE 6: AVALIAÇÃO DE SERVIDOR CIVIL (SEM SERVIÇO DE ESCALA) ---\n";

// Cria servidor civil de teste
$pdo->prepare("INSERT INTO efetivo (saram, nome, nome_guerra, posto, setor, divisao_sigla, categoria, chefe_direto_id, ativo) VALUES ('8599004', 'CIVIL TEMPORARIO TESTE', 'CIVIL_TESTE', 'CV', 'TESTE_SETOR', 'DA', 'sptf', :chefe, 1)")->execute([':chefe' => $avaliadorId]);
$subordinadoCivilId = (int)$pdo->lastInsertId();

$criteriosCivil = [
    'pleito_id'                  => $pleitoId,
    'candidato_id'               => $subordinadoCivilId,
    'tipo_avaliacao'             => 'OBRIGATORIA_CHEFE',
    'eficiencia_eficacia'        => 5,
    'conhecimento_especialidade' => 4,
    'iniciativa_adaptabilidade'  => 5,
    'conduta_pontuacao'          => 4,
    'apresentacao_pessoal'       => 5,
    'relacionamento_trabalho'    => 4,
    'lideranca'                  => 5,
    'is_nob'                     => 0
];
// Média esperada: (5 + 4 + 5 + 4 + 5 + 4 + 5) / 7 = 32 / 7 = 4.5714... -> 4.57
$mediaEsperadaCivil = round(32 / 7, 2);

$resCivil = FichaIndicacao::salvarFicha($criteriosCivil, $avaliadorUserId);

if (!$resCivil['sucesso']) {
    echo "[FALHA] Erro ao salvar ficha de servidor civil: " . ($resCivil['mensagem'] ?? '') . "\n";
    exit(1);
}
if ((float)$resCivil['media'] !== $mediaEsperadaCivil) {
    echo "[FALHA] Média civil incorreta: esperada {$mediaEsperadaCivil}, obtida {$resCivil['media']}\n";
    exit(1);
}
echo "[SUCESSO] Avaliação de servidor civil registrada com sucesso: 7 critérios divididos por 7 = média {$resCivil['media']}.\n";

// --------------------------------------------------------------------------
// LIMPEZA DOS REGISTROS DE TESTE
// --------------------------------------------------------------------------
echo "\n--- LIMPANDO REGISTROS DE TESTE ---\n";
$pdo->prepare("DELETE FROM fichas_indicacao WHERE candidato_id IN (:m1, :m2, :m3)")->execute([
    ':m1' => $subordinadoMilitarId,
    ':m2' => $subordinadoNobId,
    ':m3' => $subordinadoCivilId
]);
$pdo->prepare("DELETE FROM efetivo WHERE saram IN ('999001', '999002', '999003', '8599004')")->execute();
echo "[SUCESSO] Base de dados limpa com sucesso.\n";

echo "\n========================================================\n";
echo "   TODOS OS TESTES FORAM CONCLUÍDOS COM SUCESSO TOTAL!   \n";
echo "========================================================\n";
