<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../Services/AuditoriaService.php';

/**
 * Modelo de Validação pela Direção Superior (Fase 4)
 * Análise regimental de candidatos e registro obrigatório de justificativas para exclusão
 */
class ValidacaoSuperior {

    public static function listarCandidatos(int $pleitoId): array {
        $pdo = Database::getConnection();

        $sql = "
            SELECT i.*, 
                   e.saram, e.nome, e.nome_guerra, e.posto, e.quadro, e.especialidade, 
                   e.setor, e.divisao_sigla, e.funcao, e.data_admissao_comara, e.data_vinculo_om,
                   e.indicacoes_anteriores_qtd,
                   t.nota_tacf,
                   u.login as excluidor_login
            FROM indicados_consolidados i
            INNER JOIN efetivo e ON e.id = i.candidato_id
            LEFT JOIN avaliacoes_tacf t ON t.candidato_id = e.id AND t.pleito_id = :p
            LEFT JOIN usuarios u ON u.id = i.excluido_por_usuario_id
            WHERE i.pleito_id = :p
            ORDER BY i.categoria, e.nome_guerra COLLATE utf8mb4_unicode_ci, e.id
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':p' => $pleitoId]);
        return $stmt->fetchAll();
    }

    public static function excluirCandidato(
        int $pleitoId,
        int $candidatoId,
        string $justificativa,
        int $usuarioId
    ): array {
        $justificativa = trim($justificativa);
        if (mb_strlen($justificativa) < 15) {
            return [
                'sucesso' => false,
                'mensagem' => 'A justificativa de exclusão é obrigatória e deve conter pelo menos 15 caracteres fundamentando o motivo regimental.'
            ];
        }

        $pdo = Database::getConnection();

        $stmt = $pdo->prepare('
            UPDATE `indicados_consolidados`
            SET `status_validacao_superior` = "EXCLUIDO",
                `justificativa_exclusao`    = :justif,
                `excluido_por_usuario_id`   = :user,
                `excluido_em`               = NOW()
            WHERE `pleito_id` = :p AND `candidato_id` = :c
        ');

        $stmt->execute([
            ':justif' => $justificativa,
            ':user'   => $usuarioId,
            ':p'      => $pleitoId,
            ':c'      => $candidatoId
        ]);

        AuditoriaService::log('EXCLUSAO_CANDIDATO_DIRECAO_SUPERIOR', [
            'pleito_id'     => $pleitoId,
            'candidato_id'  => $candidatoId,
            'justificativa' => $justificativa
        ], $usuarioId);

        return [
            'sucesso' => true,
            'mensagem' => 'Candidato inabilitado da fase final com justificativa registrada em log de auditoria permanente.'
        ];
    }

    public static function reverterExclusao(int $pleitoId, int $candidatoId, int $usuarioId): array {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare('
            UPDATE `indicados_consolidados`
            SET `status_validacao_superior` = "VALIDADO",
                `justificativa_exclusao`    = NULL,
                `excluido_por_usuario_id`   = NULL,
                `excluido_em`               = NULL
            WHERE `pleito_id` = :p AND `candidato_id` = :c
        ');

        $stmt->execute([':p' => $pleitoId, ':c' => $candidatoId]);

        AuditoriaService::log('REVERSAO_EXCLUSAO_DIRECAO_SUPERIOR', [
            'pleito_id'    => $pleitoId,
            'candidato_id' => $candidatoId
        ], $usuarioId);

        return [
            'sucesso' => true,
            'mensagem' => 'Exclusão revertida. Candidato reabilitado para a fase final.'
        ];
    }
}
