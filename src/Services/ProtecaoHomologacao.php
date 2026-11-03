<?php
declare(strict_types=1);
require_once __DIR__.'/../../config/database.php';
/** Serializa gravações legadas com a homologação, sem mudar permissões anteriores. */
class ProtecaoHomologacao {
    public static function executar(int $p, callable $gravar): mixed {
        $pdo=Database::getConnection(); $propria=!$pdo->inTransaction();
        if ($propria) $pdo->beginTransaction();
        try {
            $s=$pdo->prepare('SELECT status FROM pleitos WHERE id=? FOR UPDATE'); $s->execute([$p]); $status=$s->fetchColumn();
            $s=$pdo->prepare('SELECT pleito_id FROM homologacao_pleito WHERE pleito_id=?'); $s->execute([$p]);
            if (!$status || $status==='homologado' || $s->fetch()) throw new RuntimeException('Pleito inexistente ou homologado: alteração eleitoral bloqueada.');
            $resultado=$gravar();
            if ($propria) $pdo->commit();
            return $resultado;
        } catch (Throwable $e) { if ($propria && $pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    }
}
