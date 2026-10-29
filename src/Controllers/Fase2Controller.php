<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../Middleware/RoleMiddleware.php';
require_once __DIR__ . '/../Models/Pleito.php';
require_once __DIR__ . '/../Models/Efetivo.php';
require_once __DIR__ . '/../Models/SelecaoFase2.php';

class Fase2Controller {

    public function index(): void {
        AuthMiddleware::handle();

        if (!AuthManager::isChefeOuRepresentanteFase2()) {
            flash_message('erro', 'Acesso negado: A Fase 2 é restrita aos Chefes de Divisão e ao Representante Unificado das Assessorias.', 'danger');
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

        // Determina o órgão do chefe ou permite admin selecionar o órgão a visualizar
        $orgaosValidos = array_merge(array_keys(ORGAOS_SELECAO_FASE2['DIVISOES']), array_keys(ORGAOS_SELECAO_FASE2['ASSESSORIAS']));

        if (AuthManager::hasRole(PERFIL_ADMIN)) {
            $orgaoSigla = $_POST['orgao'] ?? ($_GET['orgao'] ?? 'DPC');
        } else {
            $orgaoSigla = Efetivo::orgaoSelecaoUsuario((int)$user['id']);
        }

        if (!in_array($orgaoSigla, $orgaosValidos, true)) {
            $orgaoSigla = 'DPC';
        }

        // Carrega candidatos de cada uma das 4 categorias com suas médias da Fase 1
        $candidatosPorCategoria = [
            CAT_GRADUADO => Efetivo::listarParaSelecaoFase2((int)$pleito['id'], CAT_GRADUADO, $orgaoSigla),
            CAT_PRACA    => Efetivo::listarParaSelecaoFase2((int)$pleito['id'], CAT_PRACA, $orgaoSigla),
            CAT_SPPF     => Efetivo::listarParaSelecaoFase2((int)$pleito['id'], CAT_SPPF, $orgaoSigla),
            CAT_SPTF     => Efetivo::listarParaSelecaoFase2((int)$pleito['id'], CAT_SPTF, $orgaoSigla),
        ];

        // Carrega selecionados atuais deste órgão
        $selecionadosAtuais = [
            CAT_GRADUADO => SelecaoFase2::obterSelecionados((int)$pleito['id'], $orgaoSigla, CAT_GRADUADO),
            CAT_PRACA    => SelecaoFase2::obterSelecionados((int)$pleito['id'], $orgaoSigla, CAT_PRACA),
            CAT_SPPF     => SelecaoFase2::obterSelecionados((int)$pleito['id'], $orgaoSigla, CAT_SPPF),
            CAT_SPTF     => SelecaoFase2::obterSelecionados((int)$pleito['id'], $orgaoSigla, CAT_SPTF),
        ];

        // Relatório geral de progresso de todos os órgãos (para visão do admin ou chefes)
        $progressoGeral = SelecaoFase2::relatorioProgressoOrgaos((int)$pleito['id']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
                flash_message('erro', 'Token CSRF inválido.', 'danger');
                header('Location: /index.php?r=fase2&orgao=' . urlencode($orgaoSigla));
                exit;
            }

            $escolhas = [
                CAT_GRADUADO => $_POST['selecao'][CAT_GRADUADO] ?? [],
                CAT_PRACA    => $_POST['selecao'][CAT_PRACA] ?? [],
                CAT_SPPF     => $_POST['selecao'][CAT_SPPF] ?? [],
                CAT_SPTF     => $_POST['selecao'][CAT_SPTF] ?? [],
            ];

            $res = SelecaoFase2::salvarSelecao(
                (int)$pleito['id'],
                $orgaoSigla,
                (int)$user['id'],
                $escolhas
            );

            if ($res['sucesso']) {
                flash_message('sucesso', $res['mensagem'], 'success');
                header('Location: /index.php?r=fase2&orgao=' . urlencode($orgaoSigla));
                exit;
            } else {
                flash_message('erro', $res['mensagem'], 'danger');
            }
        }

        require_once ROOT_PATH . '/views/fase2/index.php';
    }
}
