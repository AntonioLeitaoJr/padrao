<?php
declare(strict_types=1);

/**
 * Reconciliacao segura do schema COMARA (sem DROP, TRUNCATE ou carga de dados).
 * Terminal: php database/migrar_esquema.php --check|--apply
 * Tambem pode ser incluido pelo instalador, apos a carga inicial.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/perfil_rh.php';

/** @return list<array{descricao:string,sql:string}> */
function identificarCorrecoesEsquema(PDO $pdo): array
{
    $existeTabela = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $obterColuna = $pdo->prepare('SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');

    $temTabela = static function (string $nome) use ($existeTabela): bool {
        $existeTabela->execute([$nome]);
        return (int)$existeTabela->fetchColumn() > 0;
    };
    $colunaNula = static function (string $tabela, string $coluna) use ($obterColuna): string|false {
        $obterColuna->execute([$tabela, $coluna]);
        return $obterColuna->fetchColumn();
    };

    foreach (['pleitos', 'efetivo', 'usuarios', 'fichas_indicacao'] as $tabela) {
        if (!$temTabela($tabela)) {
            throw new RuntimeException("Tabela essencial ausente: {$tabela}. Esta migracao nao substitui a instalacao inicial.");
        }
    }

    $correcoes = [];
    $perfilRh = correcaoPerfilRh($pdo);
    if ($perfilRh !== null) $correcoes[] = $perfilRh;
    $adicionarSeAusente = [
        ['efetivo', 'data_inicio_servico', 'ALTER TABLE efetivo ADD COLUMN data_inicio_servico DATE NULL AFTER data_admissao_comara'],
        ['fichas_indicacao', 'is_nob', 'ALTER TABLE fichas_indicacao ADD COLUMN is_nob TINYINT(1) NOT NULL DEFAULT 0 AFTER tipo_avaliacao'],
        ['fichas_indicacao', 'status', "ALTER TABLE fichas_indicacao ADD COLUMN status ENUM('preenchido', 'concluido') NOT NULL DEFAULT 'concluido' AFTER is_nob"],
    ];
    foreach ($adicionarSeAusente as [$tabela, $coluna, $sql]) {
        if ($colunaNula($tabela, $coluna) === false) {
            $correcoes[] = ['descricao' => "Adicionar {$tabela}.{$coluna}", 'sql' => $sql];
        }
    }

    // Fichas NOB precisam aceitar NULL nos criterios, sem substituir notas ja registradas.
    $criterios = [
        'eficiencia_eficacia', 'conhecimento_especialidade', 'iniciativa_adaptabilidade',
        'conduta_pontuacao', 'apresentacao_pessoal', 'relacionamento_trabalho', 'lideranca',
    ];
    foreach ($criterios as $coluna) {
        $aceitaNulo = $colunaNula('fichas_indicacao', $coluna);
        if ($aceitaNulo === false) {
            throw new RuntimeException("Coluna essencial ausente: fichas_indicacao.{$coluna}.");
        }
        if ($aceitaNulo === 'NO') {
            $correcoes[] = [
                'descricao' => "Permitir NULL em fichas_indicacao.{$coluna}",
                'sql' => "ALTER TABLE fichas_indicacao MODIFY COLUMN {$coluna} TINYINT NULL",
            ];
        }
    }
    $aceitaNulo = $colunaNula('fichas_indicacao', 'justificativa');
    if ($aceitaNulo === false) {
        throw new RuntimeException('Coluna essencial ausente: fichas_indicacao.justificativa.');
    }
    if ($aceitaNulo === 'NO') {
        $correcoes[] = [
            'descricao' => 'Permitir NULL em fichas_indicacao.justificativa',
            'sql' => 'ALTER TABLE fichas_indicacao MODIFY COLUMN justificativa TEXT NULL',
        ];
    }

    if (!$temTabela('historico_vencedores')) {
        $correcoes[] = [
            'descricao' => 'Criar a tabela historico_vencedores',
            'sql' => "CREATE TABLE IF NOT EXISTS historico_vencedores (
                ano INT NOT NULL,
                categoria ENUM('graduado','praca','sppf','sptf') NOT NULL,
                candidato_id INT NOT NULL,
                atualizado_por INT NOT NULL,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (ano, categoria),
                FOREIGN KEY (candidato_id) REFERENCES efetivo(id),
                FOREIGN KEY (atualizado_por) REFERENCES usuarios(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];
    }
    return $correcoes;
}

function aplicarCorrecoesEsquema(PDO $pdo): void
{
    $correcoes = identificarCorrecoesEsquema($pdo);
    if (!$correcoes) {
        echo "[OK] Esquema compativel; nenhuma alteracao necessaria.\n";
        return;
    }
    foreach ($correcoes as $correcao) {
        echo '[APLICAR] ' . $correcao['descricao'] . "\n";
        $pdo->exec($correcao['sql']);
        echo "[OK] Concluido.\n";
    }
    $pendentes = identificarCorrecoesEsquema($pdo);
    if ($pendentes) {
        throw new RuntimeException('Validacao final falhou: ainda existem ' . count($pendentes) . ' ajustes pendentes.');
    }
    echo "[OK] Migracao concluida e validada, sem importar ou excluir registros.\n";
}

// Nao executa automaticamente quando incluido pelo instalador.
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string)$_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    if (PHP_SAPI !== 'cli') {
        http_response_code(403);
        exit('Acesso permitido somente por terminal.');
    }
    $opcao = $argv[1] ?? '--check';
    if (!in_array($opcao, ['--check', '--apply'], true) || count($argv) > 2) {
        fwrite(STDERR, "Uso: php database/migrar_esquema.php --check|--apply\n");
        exit(1);
    }
    try {
        $pdo = Database::getConnection();
        if ($opcao === '--check') {
            $correcoes = identificarCorrecoesEsquema($pdo);
            if (!$correcoes) {
                echo "[OK] Estrutura de banco compativel.\n";
            } else {
                echo '[AVISO] ' . count($correcoes) . " ajustes necessarios (nenhuma alteracao realizada):\n";
                foreach ($correcoes as $correcao) {
                    echo ' - ' . $correcao['descricao'] . "\n";
                }
                exit(2);
            }
        } else {
            echo "[AVISO] Confirme que realizou um backup ANTES de aplicar a migracao.\n";
            aplicarCorrecoesEsquema($pdo);
        }
    } catch (Throwable $e) {
        fwrite(STDERR, '[ERRO] ' . $e->getMessage() . "\n");
        exit(1);
    }
}
