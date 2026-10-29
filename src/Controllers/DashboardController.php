<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../Models/Pleito.php';
require_once __DIR__ . '/../Models/Efetivo.php';
require_once __DIR__ . '/../Models/SelecaoFase2.php';

class DashboardController {

    public function index(): void {
        AuthMiddleware::handle();
        $user = AuthManager::user();
        $pleito = Pleito::getAtivo();

        $dados = [
            'user'            => $user,
            'pleito'          => $pleito,
            'subordinados'    => [],
            'tacf_pendentes'  => 0,
            'progresso_fase2' => null,
            'votou_fase3'     => false,
            'metricas'        => []
        ];

        if ($pleito) {
            $pdo = Database::getConnection();

            // Métricas gerais
            $dados['metricas'] = [
                'total_graduado' => (int)$pdo->query("SELECT COUNT(*) FROM efetivo WHERE categoria = 'graduado' AND ativo = 1")->fetchColumn(),
                'total_praca'    => (int)$pdo->query("SELECT COUNT(*) FROM efetivo WHERE categoria = 'praca' AND ativo = 1")->fetchColumn(),
                'total_sppf'     => (int)$pdo->query("SELECT COUNT(*) FROM efetivo WHERE categoria = 'sppf' AND ativo = 1")->fetchColumn(),
                'total_sptf'     => (int)$pdo->query("SELECT COUNT(*) FROM efetivo WHERE categoria = 'sptf' AND ativo = 1")->fetchColumn(),
                'total_votos'    => (int)$pdo->query("SELECT COUNT(*) FROM urna_votos WHERE pleito_id = {$pleito['id']}")->fetchColumn() / 4
            ];

            // Subordinados apenas se for Oficial Chefe Direto
            if (AuthManager::isOficial() && !empty($user['efetivo_id'])) {
                $dados['subordinados'] = Efetivo::getSubordinadosDiretos((int)$user['efetivo_id'], (int)$pleito['id']);
            }

            // TACF se for Ed. Física ou Admin
            if (AuthManager::hasRole([PERFIL_ED_FISICA, PERFIL_ADMIN])) {
                $stmtTacf = $pdo->prepare("
                    SELECT COUNT(*) FROM efetivo e
                    LEFT JOIN avaliacoes_tacf t ON t.candidato_id = e.id AND t.pleito_id = :p
                    WHERE e.categoria IN ('graduado', 'praca') AND e.ativo = 1 AND t.id IS NULL
                ");
                $stmtTacf->execute([':p' => $pleito['id']]);
                $dados['tacf_pendentes'] = (int)$stmtTacf->fetchColumn();
            }

            // Progresso do órgão na Fase 2
            $orgaoFase2 = Efetivo::orgaoSelecaoUsuario((int)$user['id']);
            $dados['orgao_fase2'] = $orgaoFase2;
            if ($orgaoFase2) {
                $prog = SelecaoFase2::relatorioProgressoOrgaos((int)$pleito['id']);
                $dados['progresso_fase2'] = $prog[$orgaoFase2] ?? null;
            }

            // Verifica se o usuário já votou na Fase 3
            if (!empty($user['cpf'])) {
                $cleanCpf = preg_replace('/[^0-9]/', '', $user['cpf']);
                $cpfHash = hash('sha256', $cleanCpf);
                $stmtV = $pdo->prepare('SELECT votou, codigo_comprovante FROM eleitores_ciclo WHERE pleito_id = :p AND cpf_hash = :h LIMIT 1');
                $stmtV->execute([':p' => $pleito['id'], ':h' => $cpfHash]);
                $reg = $stmtV->fetch();
                $dados['votou_fase3'] = ($reg && (int)$reg['votou'] === 1);
                $dados['comprovante_voto'] = $reg['codigo_comprovante'] ?? null;
            }
        }

        require_once ROOT_PATH . '/views/dashboard/index.php';
    }
}
