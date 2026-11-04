<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

/**
 * Serviço de Auditoria Perene do Sistema de Votação COMARA
 * Garante a imutabilidade e integridade dos registros por no mínimo 5 anos
 */
class AuditoriaService {

    /**
     * Registra uma ação com hash de integridade encadeado (tamper-evident)
     */
    public static function log(
        string $acao,
        ?array $detalhes = null,
        ?int $usuarioId = null,
        ?string $login = null,
        bool $obrigatorio = false
    ): void {
        try {
            $pdo = Database::getConnection();

            if ($usuarioId === null && isset($_SESSION['user']['id'])) {
                $usuarioId = (int)$_SESSION['user']['id'];
            }
            if ($login === null && isset($_SESSION['user']['login'])) {
                $login = (string)$_SESSION['user']['login'];
            }

            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'CLI', 0, 250);
            $jsonDetalhes = $detalhes !== null ? json_encode($detalhes, JSON_UNESCAPED_UNICODE) : null;
            $agora = date('Y-m-d H:i:s');

            // Obtém o hash do último registro para encadeamento criptográfico
            $stmtLast = $pdo->query('SELECT hash_registro FROM logs_auditoria ORDER BY id DESC LIMIT 1');
            $hashAnterior = $stmtLast->fetchColumn() ?: 'GENESIS_COMARA_PADRAO_2026';

            // Gera hash SHA-256 do registro atual
            $payloadParaHash = "{$hashAnterior}|{$agora}|{$usuarioId}|{$login}|{$acao}|{$jsonDetalhes}|{$ip}";
            $hashRegistro = hash('sha256', $payloadParaHash);

            $stmt = $pdo->prepare('
                INSERT INTO `logs_auditoria` (
                    `usuario_id`, `login`, `ip`, `user_agent`, `acao`,
                    `detalhes_json`, `hash_anterior`, `hash_registro`, `created_at`
                ) VALUES (
                    :usuario_id, :login, :ip, :user_agent, :acao,
                    :detalhes_json, :hash_anterior, :hash_registro, :created_at
                )
            ');

            $stmt->execute([
                ':usuario_id'     => $usuarioId,
                ':login'          => $login,
                ':ip'             => $ip,
                ':user_agent'     => $userAgent,
                ':acao'           => $acao,
                ':detalhes_json'  => $jsonDetalhes,
                ':hash_anterior'  => $hashAnterior,
                ':hash_registro'  => $hashRegistro,
                ':created_at'     => $agora
            ]);
        } catch (\Throwable $e) {
            error_log('[AUDITORIA CRITICAL ERROR] ' . $e->getMessage());
            if ($obrigatorio) throw new RuntimeException('Não foi possível registrar a auditoria obrigatória. Nenhuma alteração foi concluída.', 0, $e);
        }
    }

    /**
     * Busca logs com filtros e paginação para o painel de auditoria
     */
    public static function listarLogs(int $limite = 100, int $offset = 0, ?string $termo = null): array {
        $pdo = Database::getConnection();

        $sql = 'SELECT * FROM logs_auditoria';
        $params = [];

        if (!empty($termo)) {
            $sql .= ' WHERE acao LIKE :t OR login LIKE :t OR detalhes_json LIKE :t';
            $params[':t'] = "%{$termo}%";
        }

        $sql .= ' ORDER BY id DESC LIMIT :limit OFFSET :offset';

        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limite, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}

