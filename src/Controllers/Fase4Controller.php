<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../Models/Pleito.php';
require_once __DIR__ . '/../Models/Efetivo.php';
require_once __DIR__ . '/../Models/ValidacaoSuperior.php';

class Fase4Controller {

    public function index(): void {
        AuthMiddleware::handle();

        if (!AuthManager::isEstadoMaior()) {
            flash_message('erro', 'Acesso negado: A Fase 4 (Estado-Maior) é exclusiva para Oficiais Superiores (Majores, Tenentes-Coronéis e Coronéis).', 'danger');
            header('Location: /index.php?r=dashboard');
            exit;
        }

        $user = AuthManager::user();
        $pleito = Pleito::getAtivo();

        if (!$pleito) {
            flash_message('erro', 'Nenhum pleito ativo.', 'warning');
            header('Location: /index.php?r=dashboard');
            exit;
        }

        $candidatos = ValidacaoSuperior::listarCandidatos((int)$pleito['id']);

        // Agrupa por categoria
        $porCategoria = [
            CAT_GRADUADO => [],
            CAT_PRACA    => [],
            CAT_SPPF     => [],
            CAT_SPTF     => [],
        ];

        foreach ($candidatos as $c) {
            if (isset($porCategoria[$c['categoria']])) {
                $porCategoria[$c['categoria']][] = $c;
            }
        }

        require_once ROOT_PATH . '/views/fase4/index.php';
    }

    /**
     * Retorna os dados detalhados da ficha de indicação e TACF do candidato para o popup da Fase 4
     */
    public function resumoFicha(): void {
        AuthMiddleware::handle();

        if (!AuthManager::isEstadoMaior()) {
            header('Content-Type: application/json');
            echo json_encode(['sucesso' => false, 'mensagem' => 'Acesso negado: Exclusivo para integrantes do Estado-Maior.']);
            exit;
        }

        $pleito = Pleito::getAtivo();
        $candidatoId = (int)($_GET['id'] ?? 0);

        if (!$pleito || $candidatoId <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['sucesso' => false, 'mensagem' => 'Parâmetros inválidos.']);
            exit;
        }

        $resumo = Efetivo::getResumoFichaCandidato((int)$pleito['id'], $candidatoId);

        header('Content-Type: application/json');
        if (!$resumo) {
            echo json_encode(['sucesso' => false, 'mensagem' => 'Ficha ou candidato não localizado.']);
            exit;
        }

        echo json_encode(['sucesso' => true, 'dados' => $resumo]);
        exit;
    }

    public function excluir(): void {
        AuthMiddleware::handle();

        if (!AuthManager::isEstadoMaior()) {
            flash_message('erro', 'Acesso negado.', 'danger');
            header('Location: /index.php?r=dashboard');
            exit;
        }

        $user = AuthManager::user();
        $pleito = Pleito::getAtivo();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
                flash_message('erro', 'Token CSRF inválido.', 'danger');
                header('Location: /index.php?r=fase4');
                exit;
            }

            $candidatoId = (int)($_POST['candidato_id'] ?? 0);
            $justificativa = trim($_POST['justificativa'] ?? '');

            $res = ValidacaoSuperior::excluirCandidato(
                (int)$pleito['id'],
                $candidatoId,
                $justificativa,
                (int)$user['id']
            );

            if ($res['sucesso']) {
                flash_message('sucesso', $res['mensagem'], 'success');
            } else {
                flash_message('erro', $res['mensagem'], 'danger');
            }
        }

        header('Location: /index.php?r=fase4');
        exit;
    }

    public function reverter(): void {
        AuthMiddleware::handle();

        if (!AuthManager::isEstadoMaior()) {
            flash_message('erro', 'Acesso negado.', 'danger');
            header('Location: /index.php?r=dashboard');
            exit;
        }

        $user = AuthManager::user();
        $pleito = Pleito::getAtivo();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
                flash_message('erro', 'Token CSRF inválido.', 'danger');
                header('Location: /index.php?r=fase4');
                exit;
            }

            $candidatoId = (int)($_POST['candidato_id'] ?? 0);
            $res = ValidacaoSuperior::reverterExclusao((int)$pleito['id'], $candidatoId, (int)$user['id']);

            if ($res['sucesso']) {
                flash_message('sucesso', $res['mensagem'], 'success');
            } else {
                flash_message('erro', $res['mensagem'], 'danger');
            }
        }

        header('Location: /index.php?r=fase4');
        exit;
    }
}
