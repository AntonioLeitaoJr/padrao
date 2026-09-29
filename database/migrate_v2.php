<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$pdo = Database::getConnection();

echo "Iniciando migração de banco de dados (v2)...\n";

// 1. Atualizar ENUM de perfis para incluir 'RH'
try {
    $pdo->exec("ALTER TABLE usuarios MODIFY COLUMN perfil ENUM('ADMIN', 'RH', 'PRESIDENTE', 'DIRECAO_SUPERIOR', 'CHEFE_DIV_ASSESS', 'CHEFE_DIRETO', 'ED_FISICA', 'ELEITOR') NOT NULL DEFAULT 'ELEITOR'");
    echo "[OK] ENUM de perfis em 'usuarios' atualizado com 'RH'.\n";
} catch (Throwable $e) {
    echo "[AVISO/ERRO perfil] " . $e->getMessage() . "\n";
}

// 2. Adicionar coluna is_nob em fichas_indicacao se não existir
try {
    $colunasExistentes = $pdo->query("SHOW COLUMNS FROM fichas_indicacao LIKE 'is_nob'")->fetchAll();
    if (empty($colunasExistentes)) {
        $pdo->exec("ALTER TABLE fichas_indicacao ADD COLUMN is_nob TINYINT(1) NOT NULL DEFAULT 0 AFTER tipo_avaliacao");
        echo "[OK] Coluna 'is_nob' adicionada em 'fichas_indicacao'.\n";
    } else {
        echo "[INFO] Coluna 'is_nob' já existe em 'fichas_indicacao'.\n";
    }
} catch (Throwable $e) {
    echo "[AVISO/ERRO is_nob] " . $e->getMessage() . "\n";
}

// 2.1 Adicionar coluna status em fichas_indicacao se não existir
try {
    $colunasStatus = $pdo->query("SHOW COLUMNS FROM fichas_indicacao LIKE 'status'")->fetchAll();
    if (empty($colunasStatus)) {
        $pdo->exec("ALTER TABLE fichas_indicacao ADD COLUMN status ENUM('preenchido', 'concluido') NOT NULL DEFAULT 'concluido' AFTER is_nob");
        echo "[OK] Coluna 'status' adicionada em 'fichas_indicacao'.\n";
    } else {
        echo "[INFO] Coluna 'status' já existe em 'fichas_indicacao'.\n";
    }
} catch (Throwable $e) {
    echo "[AVISO/ERRO status] " . $e->getMessage() . "\n";
}

// 3. Tornar justificativa NULLable
try {
    $pdo->exec("ALTER TABLE fichas_indicacao MODIFY COLUMN justificativa TEXT NULL");
    echo "[OK] Coluna 'justificativa' em 'fichas_indicacao' alterada para NULL.\n";
} catch (Throwable $e) {
    echo "[AVISO/ERRO justificativa] " . $e->getMessage() . "\n";
}

echo "Ajustando restrições de fichas_indicacao para suportar NOB...\n";

// Dropar checks antigos
for ($i = 1; $i <= 7; $i++) {
    try {
        $pdo->exec("ALTER TABLE fichas_indicacao DROP CHECK fichas_indicacao_chk_{$i}");
    } catch (Throwable $e) {
        // Pode já ter sido dropado
    }
}

// Alterar colunas para NULL
$colunas = [
    'eficiencia_eficacia',
    'conhecimento_especialidade',
    'iniciativa_adaptabilidade',
    'conduta_pontuacao',
    'apresentacao_pessoal',
    'relacionamento_trabalho',
    'lideranca'
];

foreach ($colunas as $col) {
    try {
        $pdo->exec("ALTER TABLE fichas_indicacao MODIFY COLUMN `{$col}` TINYINT NULL DEFAULT NULL");
        echo "[OK] Coluna {$col} alterada para NULL.\n";
    } catch (Throwable $e) {
        echo "[ERRO] {$col}: " . $e->getMessage() . "\n";
    }
}

// Adicionar novas constraints com suporte a NULL e 0..5
$checks = [
    'chk_eficiencia'    => 'eficiencia_eficacia',
    'chk_conhecimento'  => 'conhecimento_especialidade',
    'chk_iniciativa'    => 'iniciativa_adaptabilidade',
    'chk_conduta'       => 'conduta_pontuacao',
    'chk_apresentacao'  => 'apresentacao_pessoal',
    'chk_relacionamento'=> 'relacionamento_trabalho',
    'chk_lideranca'     => 'lideranca'
];

foreach ($checks as $chkName => $col) {
    try {
        $pdo->exec("ALTER TABLE fichas_indicacao DROP CHECK `{$chkName}`");
    } catch (Throwable) {
    }
    try {
        $pdo->exec("ALTER TABLE fichas_indicacao ADD CONSTRAINT `{$chkName}` CHECK (`{$col}` IS NULL OR (`{$col}` BETWEEN 0 AND 5))");
        echo "[OK] Constraint {$chkName} adicionada.\n";
    } catch (Throwable $e) {
        echo "[AVISO] {$chkName}: " . $e->getMessage() . "\n";
    }
}

echo "Migração de constraints finalizada com sucesso!\n";
