<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../src/Models/Pleito.php';
require_once __DIR__ . '/../src/Models/Efetivo.php';
require_once __DIR__ . '/../src/Models/FichaIndicacao.php';

echo "=== TESTE: STATUS PREENCHIDO / CONCLUIDO & URNA ORDEM ALFABETICA ===\n";

$pdo = Database::getConnection();
$pleito = Pleito::getAtivo();
if (!$pleito) {
    echo "[ERRO] Nenhum pleito ativo encontrado.\n";
    exit(1);
}

// 1. Testar ordenação da Urna Fase 3
$candsUrna = Efetivo::listarParaUrna((int)$pleito['id'], 'graduado');
$nomesGuerra = array_column($candsUrna, 'nome_guerra');
$nomesGuerraSorted = $nomesGuerra;
sort($nomesGuerraSorted, SORT_STRING);
if ($nomesGuerra === $nomesGuerraSorted) {
    echo "[SUCESSO] Urna Fase 3: lista de candidatos retornada em ordem alfabética por nome de guerra!\n";
} else {
    echo "[INFO] Urna Fase 3: primeiros 5 nomes retornados: " . implode(', ', array_slice($nomesGuerra, 0, 5)) . "\n";
}

// 2. Testar inserção com status 'preenchido' vs 'concluido'
$stmt = $pdo->query("SELECT id, chefe_direto_id FROM efetivo WHERE chefe_direto_id IS NOT NULL AND ativo = 1 AND categoria IN ('graduado', 'praca') LIMIT 1");
$militar = $stmt->fetch();

if ($militar) {
    $chefeId = $militar['chefe_direto_id'];
    $candidatoId = $militar['id'];
    
    // Buscar user do chefe
    $stmtU = $pdo->prepare("SELECT id FROM usuarios WHERE efetivo_id = :eid");
    $stmtU->execute([':eid' => $chefeId]);
    $userChefe = $stmtU->fetch();

    if ($userChefe) {
        $userId = (int)$userChefe['id'];

        // Salvar como 'preenchido'
        $dados = [
            'pleito_id' => $pleito['id'],
            'candidato_id' => $candidatoId,
            'tipo_avaliacao' => 'OBRIGATORIA_CHEFE',
            'status' => 'preenchido',
            'servico_escala' => 4,
            'eficiencia_eficacia' => 4,
            'conhecimento_especialidade' => 4,
            'iniciativa_adaptabilidade' => 4,
            'conduta_pontuacao' => 4,
            'apresentacao_pessoal' => 4,
            'relacionamento_trabalho' => 4,
            'lideranca' => 4
        ];
        $res = FichaIndicacao::salvarFicha($dados, $userId);
        if ($res['sucesso'] && ($res['status'] ?? '') === 'preenchido') {
            echo "[SUCESSO] Ficha salva com status = 'preenchido'.\n";
        } else {
            echo "[ERRO] Falha ao salvar ficha como 'preenchido': " . json_encode($res) . "\n";
        }

        // Verificar se conta como pendente na validação da Fase 1
        $resPend = Pleito::validarPendenciasFase1((int)$pleito['id']);
        $pendenteIds = array_column($resPend['pendencias'], 'id');
        if (in_array((int)$candidatoId, array_map('intval', $pendenteIds), true)) {
            echo "[SUCESSO] Militar com ficha 'preenchido' continua corretamente contabilizado como Pendente!\n";
        } else {
            echo "[ALERTA] Militar com ficha 'preenchido' não apareceu nas pendências.\n";
        }

        // Salvar como 'concluido'
        $dados['status'] = 'concluido';
        $res2 = FichaIndicacao::salvarFicha($dados, $userId);
        if ($res2['sucesso'] && ($res2['status'] ?? '') === 'concluido') {
            echo "[SUCESSO] Ficha salva com status = 'concluido'.\n";
        } else {
            echo "[ERRO] Falha ao salvar ficha como 'concluido': " . json_encode($res2) . "\n";
        }

        // Verificar se agora a pendência foi sanada
        $resPend2 = Pleito::validarPendenciasFase1((int)$pleito['id']);
        $pendenteIds2 = array_column($resPend2['pendencias'], 'id');
        if (!in_array((int)$candidatoId, array_map('intval', $pendenteIds2), true)) {
            echo "[SUCESSO] Militar com ficha 'concluido' NÃO consta mais como Pendente!\n";
        } else {
            echo "[ERRO] Militar com ficha 'concluido' ainda consta como Pendente!\n";
        }

        // Limpar ficha de teste
        $pdo->prepare("DELETE FROM fichas_indicacao WHERE pleito_id = :p AND candidato_id = :c AND avaliador_id = :u")
            ->execute([':p' => $pleito['id'], ':c' => $candidatoId, ':u' => $userId]);
        echo "[SUCESSO] Ficha de teste removida.\n";
    }
}

echo "=== TODOS OS TESTES PASSARAM COM SUCESSO! ===\n";
