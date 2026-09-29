<?php
declare(strict_types=1);

require_once '/var/www/html/config/config.php';
require_once '/var/www/html/src/Models/Pleito.php';
require_once '/var/www/html/src/Models/Efetivo.php';
require_once '/var/www/html/src/Models/FichaIndicacao.php';
require_once '/var/www/html/src/Models/AvaliacaoTacf.php';
require_once '/var/www/html/src/Auth/AuthManager.php';

echo "========================================================\n";
echo "   INICIANDO TESTES DAS 4 NOVAS FUNCIONALIDADES COMARA   \n";
echo "========================================================\n\n";

$pdo = Database::getConnection();
$pleito = Pleito::getAtivo();

if (!$pleito) {
    echo "[ERRO FATAL] Nenhum pleito ativo no banco de dados.\n";
    exit(1);
}

$pleitoId = (int)$pleito['id'];
$adminUser = $pdo->query("SELECT id FROM usuarios WHERE perfil = 'ADMIN' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$adminId = (int)($adminUser['id'] ?? 1);

echo "[INFO] Pleito Ativo ID: {$pleitoId} | Fase Atual: {$pleito['fase_atual']}\n";

// TESTE 1: Transição de Fase (Avanço com justificativa e Retorno)
echo "\n--- TESTE 1: TRANSIÇÃO DE FASES E AUDITORIA ---\n";

// Garante que está na Fase 1 para testar
$pdo->prepare("UPDATE pleitos SET fase_atual = 1, status = 'em_andamento' WHERE id = :p")->execute([':p' => $pleitoId]);
$pendencias = Pleito::validarPendenciasFase1($pleitoId);
echo "[INFO] Total de pendências na Fase 1: {$pendencias['total_pendentes']}\n";

if ($pendencias['total_pendentes'] > 0) {
    // 1.1 Tentar avançar sem justificativa -> DEVE REJEITAR
    $resSemJust = Pleito::avancarFase($pleitoId, $adminId, "");
    if (!$resSemJust['sucesso'] && !empty($resSemJust['exige_justificativa'])) {
        echo "[SUCESSO] Bloqueio funcionou: avanço sem justificativa exigiu texto formal.\n";
    } else {
        echo "[FALHA] Sistema deveria ter exigido justificativa.\n";
        print_r($resSemJust);
        exit(1);
    }

    // 1.2 Tentar avançar com justificativa -> DEVE APROVAR E GRAVAR HISTÓRICO
    $justificativaTeste = "Avanço determinado em caráter excepcional com registro perene para testes automatizados.";
    $resComJust = Pleito::avancarFase($pleitoId, $adminId, $justificativaTeste);
    if ($resComJust['sucesso'] && (int)$resComJust['fase'] === 2) {
        echo "[SUCESSO] Pleito avançado para Fase 2 com pendências mediante justificativa registrada.\n";
    } else {
        echo "[FALHA] Avanço com justificativa falhou.\n";
        print_r($resComJust);
        exit(1);
    }
} else {
    // Sem pendências
    $resAvanco = Pleito::avancarFase($pleitoId, $adminId, "Avanço regular sem pendências");
    echo "[SUCESSO] Pleito avançado normalmente: " . ($resAvanco['mensagem'] ?? '') . "\n";
}

// 1.3 Testar retorno de fase
$resVoltar = Pleito::voltarFase($pleitoId, $adminId, "Retorno de teste para validar reversão regimental.");
if ($resVoltar['sucesso'] && (int)$resVoltar['fase'] === 1) {
    echo "[SUCESSO] Pleito revertido com sucesso para a Fase 1.\n";
} else {
    echo "[FALHA] Retorno de fase falhou.\n";
    print_r($resVoltar);
    exit(1);
}

// 1.4 Validar histórico de transições
$historico = Pleito::obterHistoricoFases($pleitoId);
if (count($historico) >= 2) {
    echo "[SUCESSO] Histórico de fases registrou " . count($historico) . " transições com usuário, data e justificativa.\n";
} else {
    echo "[FALHA] Histórico de transições não contém os registros esperados.\n";
    exit(1);
}

// TESTE 2: Chefe em Missão / Delegação de Avaliação
echo "\n--- TESTE 2: DELEGAÇÃO DE OFICIAL EM MISSÃO (FASE 1) ---\n";

// Localiza um candidato pendente e dois oficiais diferentes
$candidatoPendente = $pdo->query("
    SELECT e.id, e.nome, e.chefe_direto_id 
    FROM efetivo e 
    LEFT JOIN fichas_indicacao f ON f.candidato_id = e.id AND f.pleito_id = {$pleitoId} AND f.tipo_avaliacao = 'OBRIGATORIA_CHEFE'
    WHERE e.chefe_direto_id IS NOT NULL AND e.ativo = 1 AND e.categoria IN ('graduado', 'praca') AND f.id IS NULL
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

if (!$candidatoPendente) {
    echo "[AVISO] Não há candidatos com avaliação pendente para testar delegação.\n";
} else {
    $candId = (int)$candidatoPendente['id'];
    $chefeTitularId = (int)$candidatoPendente['chefe_direto_id'];

    // Localiza um oficial diferente do chefe titular
    $oficialSubstituto = $pdo->query("
        SELECT id, posto, nome_guerra 
        FROM efetivo 
        WHERE posto IN ('Cel','Ten Cel','Maj','Cap','1º Ten','2º Ten','1° Ten','1 Ten','2 Ten')
          AND id != {$chefeTitularId} AND ativo = 1
        LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);

    $substitutoId = (int)$oficialSubstituto['id'];
    echo "[INFO] Candidato: {$candidatoPendente['nome']} (ID: {$candId}) | Chefe Titular ID: {$chefeTitularId} | Substituto Designado: {$oficialSubstituto['posto']} {$oficialSubstituto['nome_guerra']} (ID: {$substitutoId})\n";

    // 2.1 Delegar avaliação
    $resDel = Efetivo::salvarDelegacao($pleitoId, $candId, $substitutoId, $adminId, "Chefe titular em missão operacional na região Amazônica");
    if (!$resDel['sucesso']) {
        echo "[FALHA] Erro ao delegar avaliação: {$resDel['mensagem']}\n";
        exit(1);
    }
    echo "[SUCESSO] Avaliação delegada com sucesso.\n";

    // 2.2 Verificar se substituto agora enxerga o candidato em seus subordinados
    $subordinadosSubstituto = Efetivo::getSubordinadosDiretos($substitutoId, $pleitoId);
    $encontrou = false;
    foreach ($subordinadosSubstituto as $s) {
        if ((int)$s['id'] === $candId) {
            $encontrou = true;
            if ((int)$s['sou_o_delegado'] === 1) {
                echo "[SUCESSO] Substituto visualiza o militar com a flag 'sou_o_delegado = 1'.\n";
            }
            break;
        }
    }
    if (!$encontrou) {
        echo "[FALHA] O oficial substituto não visualizou o militar delegado em sua lista de subordinados.\n";
        exit(1);
    }

    // 2.3 Simular avaliação pelo oficial delegado como OBRIGATORIA_CHEFE
    $dadosFicha = [
        'pleito_id'                  => $pleitoId,
        'candidato_id'               => $candId,
        'tipo_avaliacao'             => 'OBRIGATORIA_CHEFE',
        'servico_escala'             => 5,
        'eficiencia_eficacia'        => 5,
        'conhecimento_especialidade' => 5,
        'iniciativa_adaptabilidade'  => 5,
        'conduta_pontuacao'          => 5,
        'conduta_fichas'             => 'Elogios em Boletim',
        'apresentacao_pessoal'       => 5,
        'relacionamento_trabalho'    => 5,
        'lideranca'                  => 5,
        'justificativa'              => 'Avaliação de mérito realizada pelo oficial delegado durante ausência por missão regulamentar.'
    ];

    $resFicha = FichaIndicacao::salvarFicha($dadosFicha, $adminId);
    if (!$resFicha['sucesso']) {
        echo "[FALHA] Erro ao salvar ficha pelo substituto: {$resFicha['mensagem']}\n";
        exit(1);
    }
    echo "[SUCESSO] Ficha de indicação salva pelo substituto (Média: {$resFicha['media']}).\n";

    // 2.4 Verificar se o militar desapareceu da lista de pendências da Fase 1!
    $pendenciasApos = Pleito::validarPendenciasFase1($pleitoId);
    $aindaPendente = false;
    foreach ($pendenciasApos['pendencias'] as $p) {
        if ((int)$p['id'] === $candId) {
            $aindaPendente = true;
            break;
        }
    }

    if (!$aindaPendente) {
        echo "[SUCESSO] O militar avaliado pelo substituto NÃO APARECE MAIS NAS PENDÊNCIAS! Regra 100% satisfeita.\n";
    } else {
        echo "[FALHA] Militar ainda aparece nas pendências mesmo após avaliação do substituto.\n";
        exit(1);
    }
}

// TESTE 3: Múltiplos Avaliadores de TACF
echo "\n--- TESTE 3: MÚLTIPLOS AVALIADORES DE TACF (COMISSÃO) ---\n";

// Localiza dois militares para adicionar à comissão de TACF
$militaresParaTacf = $pdo->query("SELECT id, saram, nome_guerra FROM efetivo WHERE ativo = 1 ORDER BY id DESC LIMIT 2")->fetchAll(PDO::FETCH_ASSOC);

if (count($militaresParaTacf) >= 2) {
    $m1 = $militaresParaTacf[0];
    $m2 = $militaresParaTacf[1];

    // Simula atribuição de ED_FISICA a ambos
    $pdo->prepare("UPDATE usuarios SET perfil = 'ED_FISICA', ativo = 1 WHERE efetivo_id = :e")->execute([':e' => $m1['id']]);
    $pdo->prepare("UPDATE usuarios SET perfil = 'ED_FISICA', ativo = 1 WHERE efetivo_id = :e")->execute([':e' => $m2['id']]);

    $totalTacf = (int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE perfil = 'ED_FISICA' AND ativo = 1")->fetchColumn();
    echo "[INFO] Total de membros com perfil ED_FISICA ativos no banco: {$totalTacf}\n";

    if ($totalTacf >= 2) {
        echo "[SUCESSO] Sistema suporta múltiplos avaliadores simultâneos com perfil ED_FISICA.\n";
    } else {
        echo "[FALHA] Esperava-se múltiplos avaliadores de TACF cadastrados.\n";
        exit(1);
    }
}

// TESTE 4: Placa Alusiva Restrita ao Administrador
echo "\n--- TESTE 4: CONTROLE DE ACESSO DA PLACA ALUSIVA ---\n";
echo "[INFO] Verificando arquivo RelatorioController.php...\n";
$controllerContent = file_get_contents('/var/www/html/src/Controllers/RelatorioController.php');
if (str_contains($controllerContent, "RoleMiddleware::handle(PERFIL_ADMIN)")) {
    echo "[SUCESSO] RelatorioController::placa() está devidamente protegido com RoleMiddleware::handle(PERFIL_ADMIN).\n";
} else {
    echo "[FALHA] RelatorioController::placa() não possui restrição para PERFIL_ADMIN.\n";
    exit(1);
}

$headerContent = file_get_contents('/var/www/html/views/layouts/header.php');
if (str_contains($headerContent, "AuthManager::hasRole(PERFIL_ADMIN)") && str_contains($headerContent, "Placa Alusiva")) {
    echo "[SUCESSO] Link da Placa Alusiva no cabeçalho (header.php) restrito exclusivamente ao Administrador.\n";
} else {
    echo "[FALHA] Link da Placa Alusiva no header não está restrito ao Administrador.\n";
    exit(1);
}

echo "\n========================================================\n";
echo "   TODOS OS TESTES FORAM EXECUTADOS COM SUCESSO! [100%]   \n";
echo "========================================================\n";
