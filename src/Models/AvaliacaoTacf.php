<?php
declare(strict_types=1);
require_once __DIR__.'/../Services/ProtecaoHomologacao.php';

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../Services/AuditoriaService.php';

/**
 * Modelo de Avaliação do TACF (Teste de Aptidão do Condicionamento Físico)
 * PERMISSÃO EXCLUSIVA: Setor de Educação Física (DAEF)
 */
class AvaliacaoTacf {

    public static function salvarNota(int $pleitoId,int $candidatoId,float $notaTacf,int $usuarioId,?string $obs=null,?string $dataRealizacao=null): array {
        return ProtecaoHomologacao::executar($pleitoId,fn()=>self::salvarNotaInterna($pleitoId,$candidatoId,$notaTacf,$usuarioId,$obs,$dataRealizacao));
    }
    private static function salvarNotaInterna(
        int $pleitoId,
        int $candidatoId,
        float $notaTacf,
        int $usuarioId,
        ?string $obs = null,
        ?string $dataRealizacao = null
    ): array {
        // Validação da faixa da nota (1 a 5)
        if ($notaTacf < 1.0 || $notaTacf > 5.0) {
            return ['sucesso' => false, 'mensagem' => 'A nota do TACF deve estar compreendida entre 1,00 e 5,00.'];
        }

        $candidato = Efetivo::getPorId($candidatoId);
        if (!$candidato || !in_array($candidato['categoria'], ['graduado', 'praca'], true)) {
            return ['sucesso' => false, 'mensagem' => 'Avaliação do TACF é aplicável exclusivamente a militares (Graduado e Praça).'];
        }

        $pdo = Database::getConnection();

        $stmt = $pdo->prepare('
            INSERT INTO `avaliacoes_tacf` (
                `pleito_id`, `candidato_id`, `avaliador_ed_fisica_id`, `nota_tacf`,
                `data_realizacao`, `observacoes`
            ) VALUES (
                :pleito, :candidato, :avaliador, :nota,
                :data_r, :obs
            ) ON DUPLICATE KEY UPDATE
                `nota_tacf`              = VALUES(`nota_tacf`),
                `avaliador_ed_fisica_id` = VALUES(`avaliador_ed_fisica_id`),
                `data_realizacao`        = VALUES(`data_realizacao`),
                `observacoes`            = VALUES(`observacoes`),
                `updated_at`             = NOW()
        ');

        $stmt->execute([
            ':pleito'    => $pleitoId,
            ':candidato' => $candidatoId,
            ':avaliador' => $usuarioId,
            ':nota'      => $notaTacf,
            ':data_r'    => $dataRealizacao ?: date('Y-m-d'),
            ':obs'       => $obs
        ]);

        AuditoriaService::log('TACF_REGISTRADO', [
            'pleito_id'    => $pleitoId,
            'candidato_id' => $candidatoId,
            'nota'         => $notaTacf
        ], $usuarioId);

        return ['sucesso' => true, 'mensagem' => 'Nota do TACF registrada com sucesso.'];
    }

    public static function getNota(int $pleitoId, int $candidatoId): ?array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM avaliacoes_tacf WHERE pleito_id = :p AND candidato_id = :c LIMIT 1');
        $stmt->execute([':p' => $pleitoId, ':c' => $candidatoId]);
        $res = $stmt->fetch();
        return $res ?: null;
    }
}

