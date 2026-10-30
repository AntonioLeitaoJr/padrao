<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../Models/Pleito.php';
require_once __DIR__ . '/../Models/Efetivo.php';
require_once __DIR__ . '/../Services/UrnaService.php';

class Fase3Controller {

    public function index(): void {
        AuthMiddleware::handle();
        $user = AuthManager::user();
        $pleito = Pleito::getAtivo();

        if (!$pleito) {
            flash_message('erro', 'Nenhum pleito ativo.', 'warning');
            header('Location: /index.php?r=dashboard');
            exit;
        }

        // Verifica se a Fase 3 está aberta
        $faseAtual = (int)$pleito['fase_atual'];
        $faseAberta = ($faseAtual === FASE_3 && $pleito['status'] === 'em_andamento');

        // Verifica se o usuário atual já votou (por CPF)
        $jaVotou = false;
        $registroVoto = null;
        if (!empty($user['cpf'])) {
            $checagem = UrnaService::podeVotar((int)$pleito['id'], $user['cpf']);
            if (!$checagem['pode'] && !empty($checagem['comprovante'])) {
                $jaVotou = true;
                $registroVoto = $checagem;
            }
        }

        // Busca candidatos indicados da Fase 2 (consolidados) para compor a cédula
        $candidatosUrna = [];
        $erroUrna = null;
        try {
            foreach ([CAT_GRADUADO, CAT_PRACA, CAT_SPPF, CAT_SPTF] as $cat) {
                $candidatosUrna[$cat] = Efetivo::listarParaUrna((int)$pleito['id'], $cat);
                if ($faseAberta && !$candidatosUrna[$cat]) {
                    $erroUrna = 'Há categoria sem candidatos. A votação exige uma escolha nas quatro categorias; aguarde orientação da Administração.';
                }
            }
        } catch (RuntimeException $e) {
            $erroUrna = 'Não foi possível carregar os representantes. Solicite à Administração a conferência das seleções da Fase 2 e da consolidação.';
            error_log($e->getMessage());
        }

        require_once ROOT_PATH . '/views/fase3/urna.php';
    }

    /**
     * Processamento do voto depositado na urna com desacoplamento cego
     */
    public function votar(): void {
        AuthMiddleware::handle();
        $user = AuthManager::user();
        $pleito = Pleito::getAtivo();

        if (!$pleito || (int)$pleito['fase_atual'] !== FASE_3) {
            flash_message('erro', 'A votação geral não está aberta no momento.', 'warning');
            header('Location: /index.php?r=fase3');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /index.php?r=fase3');
            exit;
        }

        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            flash_message('erro', 'Token de segurança expirado. Tente novamente.', 'danger');
            header('Location: /index.php?r=fase3');
            exit;
        }

        $cpfDigitado = trim($_POST['cpf'] ?? '');
        $votos = [
            CAT_GRADUADO => (int)($_POST['voto_graduado'] ?? 0),
            CAT_PRACA    => (int)($_POST['voto_praca'] ?? 0),
            CAT_SPPF     => (int)($_POST['voto_sppf'] ?? 0),
            CAT_SPTF     => (int)($_POST['voto_sptf'] ?? 0)
        ];

        try {
            $resultado = UrnaService::registrarVotos(
                (int)$pleito['id'],
                $cpfDigitado,
                $votos,
                (int)$user['id']
            );

            $_SESSION['ultimo_comprovante'] = [
                'comprovante' => $resultado['comprovante'],
                'data_hora'   => $resultado['data_hora'],
                'eleitor'     => $user['nome_guerra'] . ' (' . $user['posto'] . ')',
                'ano'         => $pleito['ano']
            ];

            flash_message('sucesso', 'Seu voto foi registrado com sucesso na Urna Eletrônica Sigilosa!', 'success');
            header('Location: /index.php?r=fase3/comprovante');
            exit;
        } catch (\Throwable $e) {
            flash_message('erro', $e->getMessage(), 'danger');
            header('Location: /index.php?r=fase3');
            exit;
        }
    }

    /**
     * Exibição do Comprovante Oficial de Votação
     */
    public function comprovante(): void {
        AuthMiddleware::handle();
        $comprovante = $_SESSION['ultimo_comprovante'] ?? null;

        if (!$comprovante) {
            header('Location: /index.php?r=dashboard');
            exit;
        }

        require_once ROOT_PATH . '/views/fase3/comprovante.php';
    }
}
