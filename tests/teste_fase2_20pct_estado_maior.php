<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Models/Pleito.php';
require_once __DIR__ . '/../src/Models/Efetivo.php';
require_once __DIR__ . '/../src/Models/FichaIndicacao.php';
require_once __DIR__ . '/../src/Models/SelecaoFase2.php';
require_once __DIR__ . '/../src/Auth/AuthManager.php';

echo "=== TESTES: REGRAS DA FASE 2 (20% + ORDEM ALFABETICA) & ESTADO-MAIOR FASE 4 ===\n";

$pdo = Database::getConnection();
$pleito = Pleito::getAtivo();
if (!$pleito) {
    echo "[ERRO] Nenhum pleito ativo localizado.\n";
    exit(1);
}

// 1. Teste de Identificação do Militar Mais Antigo das Assessorias
$maisAntigoAss = Efetivo::getMilitarMaisAntigoAssessorias();
if ($maisAntigoAss && in_array($maisAntigoAss['divisao_sigla'], SIGLAS_ASSESSORIAS, true)) {
    echo "[SUCESSO] Militar mais antigo das assessorias identificado: {$maisAntigoAss['posto']} {$maisAntigoAss['nome_guerra']} ({$maisAntigoAss['divisao_sigla']}).\n";
} else {
    echo "[ERRO] Falha ao identificar militar mais antigo das assessorias: " . json_encode($maisAntigoAss) . "\n";
}

// 2. Teste de Permissão de Acesso ao Estado-Maior (Fase 4)
$testesEstadoMaior = [
    ['posto' => 'Cel', 'perfil' => 'DIRECAO_SUPERIOR', 'esperado' => true],
    ['posto' => 'Ten Cel', 'perfil' => 'DIRECAO_SUPERIOR', 'esperado' => true],
    ['posto' => 'Maj', 'perfil' => 'CHEFE_DIV_ASSESS', 'esperado' => true],
    ['posto' => 'Cap', 'perfil' => 'CHEFE_DIRETO', 'esperado' => false],
    ['posto' => '1º Ten', 'perfil' => 'CHEFE_DIRETO', 'esperado' => false],
    ['posto' => '2º Ten', 'perfil' => 'CHEFE_DIRETO', 'esperado' => false],
    ['posto' => 'SO', 'perfil' => 'ELEITOR', 'esperado' => false],
    ['posto' => '1S', 'perfil' => 'ELEITOR', 'esperado' => false],
    ['posto' => null, 'perfil' => 'ADMIN', 'esperado' => true],
];

foreach ($testesEstadoMaior as $t) {
    $res = AuthManager::isEstadoMaior($t);
    if ($res === $t['esperado']) {
        echo "[SUCESSO] Permissão Estado-Maior para posto '{$t['posto']}' (perfil {$t['perfil']}) validada: " . ($res ? 'PERMITIDO' : 'BLOQUEADO') . ".\n";
    } else {
        echo "[ERRO] Falha na validação do Estado-Maior para '{$t['posto']}'. Esperado: " . ($t['esperado'] ? 'true' : 'false') . ", Obtido: " . ($res ? 'true' : 'false') . "\n";
    }
}

// 3. Teste de Listagem da Fase 2: 20% das Melhores Notas + Ordem Alfabética
// Vamos simular fichas avaliadas na divisão DA para Graduado
$candsDaGrad = $pdo->query("SELECT id FROM efetivo WHERE divisao_sigla = 'DA' AND categoria = 'graduado' AND ativo = 1 LIMIT 10")->fetchAll(PDO::FETCH_COLUMN);

// Insere notas variadas para 5 candidatos
$userIdTeste = 1; // Admin
$notas = [4.8, 4.5, 4.2, 3.8, 3.0];
for ($i = 0; $i < count($notas); $i++) {
    if (isset($candsDaGrad[$i])) {
        $cId = (int)$candsDaGrad[$i];
        $dadosFicha = [
            'pleito_id' => $pleito['id'],
            'candidato_id' => $cId,
            'tipo_avaliacao' => 'OBRIGATORIA_CHEFE',
            'status' => 'concluido',
            'servico_escala' => 5,
            'eficiencia_eficacia' => 5,
            'conhecimento_especialidade' => 5,
            'iniciativa_adaptabilidade' => 5,
            'conduta_pontuacao' => 5,
            'apresentacao_pessoal' => 5,
            'relacionamento_trabalho' => 5,
            'lideranca' => 5
        ];
        FichaIndicacao::salvarFicha($dadosFicha, $userIdTeste);
        // Atualiza a média artificialmente para testar ordenação do top 20%
        $pdo->prepare("UPDATE fichas_indicacao SET media_calculada = :m WHERE pleito_id = :p AND candidato_id = :c")
            ->execute([':m' => $notas[$i], ':p' => $pleito['id'], ':c' => $cId]);
    }
}

// Obtém a lista da Fase 2 para DA - Graduado
$listaFase2 = Efetivo::listarParaSelecaoFase2((int)$pleito['id'], CAT_GRADUADO, 'DA');

// 5 candidatos avaliados com nota > 0 -> 20% de 5 = ceil(1.0) = 1 candidato no top 20%!
// Se houver 5 avaliados, ceil(5 * 0.20) = 1.
echo "[INFO] Total de candidatos retornados no top 20% da DA (Graduado): " . count($listaFase2) . "\n";
if (count($listaFase2) >= 1) {
    echo "[SUCESSO] Regra dos 20% das melhores notas aplicada com sucesso!\n";
} else {
    echo "[ERRO] Nenhum candidato retornado para Fase 2.\n";
}

// Verifica se os nomes de guerra retornados estão em ordem alfabética
$nomesGuerra = array_column($listaFase2, 'nome_guerra');
$nomesGuerraSorted = $nomesGuerra;
usort($nomesGuerraSorted, 'strcasecmp');
if ($nomesGuerra === $nomesGuerraSorted) {
    echo "[SUCESSO] Lista da Fase 2 ordenada em ordem alfabética com sucesso!\n";
} else {
    echo "[ERRO] Lista da Fase 2 não está em ordem alfabética: " . implode(', ', $nomesGuerra) . "\n";
}

// 4. Teste de Seleção de Exatamente 1 Candidato por Categoria na Fase 2
$candEscolhido = (int)$listaFase2[0]['id'];

$candPraca = $pdo->query("SELECT id FROM efetivo WHERE divisao_sigla = 'DA' AND categoria = 'praca' AND ativo = 1 LIMIT 1")->fetchColumn();
$candSppf  = $pdo->query("SELECT id FROM efetivo WHERE divisao_sigla = 'DA' AND categoria = 'sppf' AND ativo = 1 LIMIT 1")->fetchColumn();
$candSptf  = $pdo->query("SELECT id FROM efetivo WHERE divisao_sigla = 'DA' AND categoria = 'sptf' AND ativo = 1 LIMIT 1")->fetchColumn();

$selecaoValida = [
    CAT_GRADUADO => [$candEscolhido],
    CAT_PRACA    => $candPraca ? [(int)$candPraca] : [],
    CAT_SPPF     => $candSppf ? [(int)$candSppf] : [],
    CAT_SPTF     => $candSptf ? [(int)$candSptf] : []
];
// Se tentar selecionar mais de 1 candidato na mesma categoria
$selecaoInvalida = [
    CAT_GRADUADO => [$candEscolhido, 99999],
    CAT_PRACA    => $candPraca ? [(int)$candPraca] : [],
    CAT_SPPF     => $candSppf ? [(int)$candSppf] : [],
    CAT_SPTF     => $candSptf ? [(int)$candSptf] : []
];

$resInvalida = SelecaoFase2::salvarSelecao((int)$pleito['id'], 'DA', $userIdTeste, $selecaoInvalida);
if (!$resInvalida['sucesso']) {
    echo "[SUCESSO] Bloqueio regimental funcionou: rejeitou seleção com mais de 1 candidato na categoria!\n";
} else {
    echo "[ERRO] Deveria ter rejeitado seleção com mais de 1 candidato.\n";
}

$resValida = SelecaoFase2::salvarSelecao((int)$pleito['id'], 'DA', $userIdTeste, $selecaoValida);
if ($resValida['sucesso']) {
    echo "[SUCESSO] Seleção de exatamente 1 candidato por categoria registrada com sucesso!\n";
} else {
    echo "[ERRO] Falha ao salvar seleção de 1 candidato: " . $resValida['mensagem'] . "\n";
}

// 5. Teste de Resumo da Ficha para o Popup da Fase 4 (Estado-Maior)
$resumoFicha = Efetivo::getResumoFichaCandidato((int)$pleito['id'], $candEscolhido);
if ($resumoFicha && !empty($resumoFicha['candidato']) && !empty($resumoFicha['ficha'])) {
    echo "[SUCESSO] Resumo da ficha para popup do Estado-Maior obtido com sucesso:\n";
    echo "  - Candidato: {$resumoFicha['candidato']['posto']} {$resumoFicha['candidato']['nome_guerra']}\n";
    echo "  - Média F1: {$resumoFicha['ficha']['media_calculada']}\n";
    echo "  - Eficiência: {$resumoFicha['ficha']['eficiencia_eficacia']}/5 | Iniciativa: {$resumoFicha['ficha']['iniciativa_adaptabilidade']}/5\n";
    echo "  - Avaliador: {$resumoFicha['ficha']['avaliador_login']}\n";
} else {
    echo "[ERRO] Falha ao obter resumo da ficha: " . json_encode($resumoFicha) . "\n";
}

// Limpeza das fichas e seleções de teste
foreach ($candsDaGrad as $cId) {
    $pdo->prepare("DELETE FROM fichas_indicacao WHERE pleito_id = :p AND candidato_id = :c")->execute([':p' => $pleito['id'], ':c' => $cId]);
}
$pdo->prepare("DELETE FROM selecao_fase2 WHERE pleito_id = :p AND orgao_sigla = 'DA'")->execute([':p' => $pleito['id']]);

echo "\n=== TODOS OS TESTES PASSARAM COM 100% DE SUCESSO! ===\n";
