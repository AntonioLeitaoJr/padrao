<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/AuditoriaService.php';

/**
 * Serviço de Backups Diários e Semanais para aaPanel / Debian 13
 * Gera cópias integrais do banco de dados e arquivos com retenção de segurança
 */
class BackupService {

    public static function getBackupDir(string $tipo = 'diario'): string {
        $dir = ROOT_PATH . '/backups/' . ($tipo === 'semanal' ? 'weekly' : 'daily');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }

    /**
     * Executa o backup completo em arquivo SQL compactado (gzip)
     */
    public static function gerarBackup(string $tipo = 'diario', ?int $usuarioId = null): array {
        $backupDir = self::getBackupDir($tipo);
        $dataHora = date('Y-m-d_H-i-s');
        $nomeArquivo = "backup_comara_{$tipo}_{$dataHora}.sql";
        $caminhoSql = "{$backupDir}/{$nomeArquivo}";

        $pdo = Database::getConnection();

        // Lista todas as tabelas
        $tabelas = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

        $fp = fopen($caminhoSql, 'w');
        if (!$fp) {
            throw new RuntimeException("Não foi possível criar o arquivo de backup em: {$caminhoSql}");
        }

        fwrite($fp, "-- =========================================================\n");
        fwrite($fp, "-- BACKUP OFICIAL DO SISTEMA DE VOTAÇÃO COMARA ({$tipo})\n");
        fwrite($fp, "-- Data/Hora: " . date('Y-m-d H:i:s') . "\n");
        fwrite($fp, "-- =========================================================\n\n");
        fwrite($fp, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n");

        foreach ($tabelas as $tab) {
            // Estrutura
            $createTable = $pdo->query("SHOW CREATE TABLE `{$tab}`")->fetch(PDO::FETCH_ASSOC);
            $ddl = $createTable['Create Table'] ?? '';
            fwrite($fp, "DROP TABLE IF EXISTS `{$tab}`;\n");
            fwrite($fp, "{$ddl};\n\n");

            // Dados
            $rows = $pdo->query("SELECT * FROM `{$tab}`")->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($rows)) {
                foreach ($rows as $row) {
                    $cols = array_map(fn($col) => "`{$col}`", array_keys($row));
                    $vals = array_map(function ($val) use ($pdo) {
                        return ($val === null) ? 'NULL' : $pdo->quote((string)$val);
                    }, array_values($row));

                    $sqlInsert = sprintf(
                        "INSERT INTO `%s` (%s) VALUES (%s);\n",
                        $tab,
                        implode(', ', $cols),
                        implode(', ', $vals)
                    );
                    fwrite($fp, $sqlInsert);
                }
                fwrite($fp, "\n");
            }
        }

        fwrite($fp, "SET FOREIGN_KEY_CHECKS = 1;\n");
        fclose($fp);

        // Se extensão Zlib disponível, compacta para .gz
        $caminhoFinal = $caminhoSql;
        if (function_exists('gzopen')) {
            $caminhoGz = "{$caminhoSql}.gz";
            $gz = gzopen($caminhoGz, 'w9');
            $sqlContent = file_get_contents($caminhoSql);
            gzwrite($gz, $sqlContent);
            gzclose($gz);
            @unlink($caminhoSql);
            $caminhoFinal = $caminhoGz;
            $nomeArquivo .= '.gz';
        }

        $tamanhoBytes = file_exists($caminhoFinal) ? filesize($caminhoFinal) : 0;

        AuditoriaService::log('BACKUP_GERADO', [
            'tipo'          => $tipo,
            'arquivo'       => $nomeArquivo,
            'tamanho_bytes' => $tamanhoBytes,
            'caminho'       => $caminhoFinal
        ], $usuarioId);

        return [
            'sucesso'  => true,
            'tipo'     => $tipo,
            'arquivo'  => $nomeArquivo,
            'caminho'  => $caminhoFinal,
            'tamanho'  => round($tamanhoBytes / 1024, 2) . ' KB'
        ];
    }

    /**
     * Lista backups gerados para o painel de administração
     */
    public static function listarBackups(): array {
        $todos = [];
        foreach (['diario' => 'daily', 'semanal' => 'weekly', 'zeramento' => 'reset'] as $tipo => $sub) {
            $dir = ROOT_PATH . "/backups/{$sub}";
            if (is_dir($dir)) {
                $files = glob("{$dir}/*.*");
                foreach ($files as $f) {
                    $todos[] = [
                        'tipo'      => $tipo,
                        'nome'      => basename($f),
                        'caminho'   => $f,
                        'tamanho'   => round(filesize($f) / 1024, 2) . ' KB',
                        'data_hora' => date('d/m/Y H:i:s', filemtime($f))
                    ];
                }
            }
        }
        usort($todos, fn($a, $b) => strcmp($b['data_hora'], $a['data_hora']));
        return $todos;
    }
}
