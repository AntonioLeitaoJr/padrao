<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../Middleware/RoleMiddleware.php';
require_once __DIR__ . '/../Models/Pleito.php';
require_once __DIR__ . '/../Models/Homologacao.php';

class Fase5Controller {

    public function index(): void {
        AuthMiddleware::handle();
        RoleMiddleware::handle([PERFIL_PRESIDENTE, PERFIL_DIRECAO_SUPERIOR, PERFIL_ADMIN]);

        $user = AuthManager::user();
        $pleito = Pleito::paraConsulta();

        if (!$pleito) {
            flash_message('erro', 'Nenhum pleito ativo.', 'warning');
            header('Location: /index.php?r=dashboard');
            exit;
        }

        $apuracao = Homologacao::apurarResultados((int)$pleito['id']);

        $historicos = Pleito::homologados();
        $avaliacoes = AuthManager::hasRole([PERFIL_PRESIDENTE, PERFIL_ADMIN]) ? Homologacao::avaliacoesIndividuais((int)$pleito['id'], (int)$user['id']) : [];
        require_once ROOT_PATH . '/views/fase5/index.php';
    }

    /**
     * Registro do Voto de Minerva pelo Presidente
     */
    public function votoMinerva(): void {
        AuthMiddleware::handle();
        RoleMiddleware::handle([PERFIL_PRESIDENTE, PERFIL_ADMIN]);

        $user = AuthManager::user();
        $pleito = Pleito::getAtivo();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$pleito || (int)($_POST['pleito_id'] ?? 0)!==(int)$pleito['id'] || !is_string($_POST['versao'] ?? null) || ($_POST['confirmar'] ?? '')!=='1') {
                flash_message('erro', 'Recarregue o pleito e confirme expressamente a operação.', 'warning');
                header('Location: /index.php?r=fase5'); exit;
            }
            if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
                flash_message('erro', 'Token CSRF inválido.', 'danger');
                header('Location: /index.php?r=fase5');
                exit;
            }

            $categoria = $_POST['categoria'] ?? '';
            $candidatoId = (int)($_POST['candidato_id'] ?? 0);
            $justificativa = trim($_POST['justificativa'] ?? '');

            $res = Homologacao::registrarVotoMinerva(
                (int)$pleito['id'],
                $categoria,
                $candidatoId,
                $justificativa,
                (int)$user['id'],
                $_POST['versao']
            );

            if ($res['sucesso']) {
                flash_message('sucesso', $res['mensagem'], 'success');
            } else {
                flash_message('erro', $res['mensagem'], 'danger');
            }
        }

        header('Location: /index.php?r=fase5');
        exit;
    }

    /**
     * Homologação Oficial Definitiva dos Resultados
     */
    public function homologar(): void {
        AuthMiddleware::handle();
        RoleMiddleware::handle([PERFIL_PRESIDENTE, PERFIL_ADMIN]);

        $user = AuthManager::user();
        $pleito = Pleito::getAtivo();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$pleito || (int)($_POST['pleito_id'] ?? 0)!==(int)$pleito['id'] || !is_string($_POST['versao'] ?? null) || ($_POST['confirmar'] ?? '')!=='1') {
                flash_message('erro', 'Recarregue o pleito e confirme expressamente a operação.', 'warning');
                header('Location: /index.php?r=fase5'); exit;
            }
            if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
                flash_message('erro', 'Token CSRF inválido.', 'danger');
                header('Location: /index.php?r=fase5');
                exit;
            }

            $ata = trim($_POST['ata_homologacao'] ?? '');
            if (mb_strlen($ata) < 20) {
                flash_message('erro', 'A ata de homologação oficial deve conter a fundamentação do ato (mínimo 20 caracteres).', 'danger');
                header('Location: /index.php?r=fase5');
                exit;
            }

            $res = Homologacao::homologarResultados((int)$pleito['id'], (int)$user['id'], $ata, $_POST['versao']);

            if ($res['sucesso']) {
                flash_message('sucesso', $res['mensagem'], 'success');
            } else {
                flash_message('erro', $res['mensagem'], 'danger');
            }
        }

        header('Location: /index.php?r=fase5');
        exit;
    }
}

