<?php
declare(strict_types=1);

/**
 * Ponto de Entrada Oficial (Front Controller & Router)
 * Sistema de Eleição dos Padrões COMARA
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Controllers/AuthController.php';
require_once __DIR__ . '/../src/Controllers/DashboardController.php';
require_once __DIR__ . '/../src/Controllers/Fase1Controller.php';
require_once __DIR__ . '/../src/Controllers/Fase2Controller.php';
require_once __DIR__ . '/../src/Controllers/Fase3Controller.php';
require_once __DIR__ . '/../src/Controllers/Fase4Controller.php';
require_once __DIR__ . '/../src/Controllers/Fase5Controller.php';
require_once __DIR__ . '/../src/Controllers/RelatorioController.php';
require_once __DIR__ . '/../src/Controllers/AdminController.php';
require_once __DIR__ . '/../src/Controllers/FotoController.php';

$rota = $_GET['r'] ?? (AuthManager::check() ? 'dashboard' : 'login');

try {
switch ($rota) {
    // Autenticação
    case 'login':
    case 'auth/login':
        (new AuthController())->login();
        break;
    case 'logout':
    case 'auth/logout':
        (new AuthController())->logout();
        break;
    case 'alterar_senha':
        (new AuthController())->alterarSenha();
        break;
    case 'reset_senha_login':
        (new AuthController())->resetSenhaPublico();
        break;

    // Painel Principal
    case 'dashboard':
        (new DashboardController())->index();
        break;

    // Fase 1: Avaliações pelos Chefes Diretos & TACF
    case 'fase1':
        (new Fase1Controller())->index();
        break;
    case 'fase1/avaliar':
        (new Fase1Controller())->avaliar();
        break;
    case 'fase1/dados_ficha':
        (new Fase1Controller())->dadosFicha();
        break;
    case 'fase1/tacf':
        (new Fase1Controller())->salvarTacf();
        break;
    case 'fase1/delegar':
        (new Fase1Controller())->salvarDelegacao();
        break;
    case 'fase1/remover_delegacao':
        (new Fase1Controller())->removerDelegacao();
        break;

    // Fase 2: Seleção Chefias Divisão e Assessoria
    case 'fase2':
        (new Fase2Controller())->index();
        break;

    // Fase 3: Votação Geral do Efetivo (Urna Eletrônica)
    case 'fase3':
        (new Fase3Controller())->index();
        break;
    case 'fase3/votar':
        (new Fase3Controller())->votar();
        break;
    case 'fase3/comprovante':
        (new Fase3Controller())->comprovante();
        break;

    // Fase 4: Estado-Maior (Oficiais Superiores: Majores, Tenentes-Coronéis e Coronéis)
    case 'fase4':
        (new Fase4Controller())->index();
        break;
    case 'fase4/confirmar':
        (new Fase4Controller())->confirmar();
        break;
    case 'fase4/decidir_corte':
        (new Fase4Controller())->decidirCorte();
        break;

    // Fase 5: Apuração, Minerva e Homologação
    case 'fase5':
        (new Fase5Controller())->index();
        break;
    case 'fase5/minerva':
        (new Fase5Controller())->votoMinerva();
        break;
    case 'fase5/homologar':
        (new Fase5Controller())->homologar();
        break;

    // Relatórios e Publicações
    case 'relatorios/placa':
        (new RelatorioController())->placa();
        break;
    case 'relatorios/site':
        (new RelatorioController())->siteOficial();
        break;
    case 'relatorios/estatisticas':
        (new RelatorioController())->estatisticas();
        break;

    // Gestão Administrativa
    case 'admin/pleito':
        (new AdminController())->pleito();
        break;
    case 'admin/avancar_fase':
        (new AdminController())->avancarFase();
        break;
    case 'admin/voltar_fase':
        (new AdminController())->voltarFase();
        break;
    case 'admin/reiniciar_pleito':
        (new AdminController())->reiniciarPleito();
        break;
    case 'admin/vencedores':
        (new AdminController())->vencedores();
        break;
    case 'admin/efetivo':
        (new AdminController())->efetivo();
        break;
    case 'admin/salvar_efetivo':
        (new AdminController())->salvarEfetivo();
        break;
    case 'admin/toggle_ativo_efetivo':
        (new AdminController())->toggleAtivoEfetivo();
        break;
    case 'admin/resetar_senha':
        (new AdminController())->resetarSenhaUsuario();
        break;
    case 'admin/baixar_modelo_csv':
        (new AdminController())->baixarModeloCsv();
        break;
    case 'admin/baixar_efetivo_csv':
        (new AdminController())->baixarEfetivoCsv();
        break;
    case 'admin/importar_csv':
        (new AdminController())->importarCsv();
        break;
    case 'admin/chefias':
        (new AdminController())->chefias();
        break;
    case 'admin/salvar_chefias':
        (new AdminController())->salvarChefias();
        break;
    case 'admin/fotos':
        (new AdminController())->fotos();
        break;
    case 'admin/upload_foto':
        (new AdminController())->uploadFoto();
        break;
    case 'admin/logs':
        (new AdminController())->logs();
        break;
    case 'admin/backups':
        (new AdminController())->backups();
        break;

    // Servidor de Fotos (SIGPES / Manual / SVG)
    case 'foto':
        (new FotoController())->serve();
        break;

    default:
        http_response_code(404);
        require_once ROOT_PATH . '/views/layouts/header.php';
        echo '<div class="alert alert-danger text-center my-5 p-5"><h4>Erro 404 - Página Não Encontrada</h4><p>O recurso solicitado não existe no Sistema de Votação da COMARA.</p><a href="/index.php?r=dashboard" class="btn btn-primary">Voltar ao Painel</a></div>';
        require_once ROOT_PATH . '/views/layouts/footer.php';
        break;
}

} catch (Throwable $e) {
    error_log('[ELEICAO] '.$e->getMessage());
    http_response_code(503);
    $mensagem = $e instanceof PDOException
        ? 'Não foi possível acessar os dados. STI: confira o banco e execute php database/migrate_fase2.php e php database/migrate_fases3_4.php. Nenhum resultado será presumido.'
        : ($e instanceof RuntimeException ? $e->getMessage() : 'Operação não concluída. Solicite suporte ao STI.');
    echo '<div role="alert" style="padding:2rem"><h2>Operação indisponível</h2><p>'.sanitize_output($mensagem).'</p><a href="/index.php?r=dashboard">Voltar ao painel</a></div>';
}
