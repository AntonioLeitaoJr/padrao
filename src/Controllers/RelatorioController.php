<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../Middleware/RoleMiddleware.php';
require_once __DIR__ . '/../Models/Pleito.php';
require_once __DIR__ . '/../Models/Homologacao.php';

class RelatorioController {

    /**
     * Layout da Placa Alusiva para o Hall de Entrada do Prédio do Comando da COMARA
     */
    public function placa(): void {
        AuthMiddleware::handle();
        RoleMiddleware::handle(PERFIL_ADMIN);
        $pleito = Pleito::paraConsulta();

        if (!$pleito) {
            flash_message('erro', 'Nenhum pleito ativo.', 'warning');
            header('Location: /index.php?r=dashboard');
            exit;
        }

        if ($pleito['status']!=='homologado') {
            flash_message('erro','Os vencedores serão divulgados somente após a homologação.','warning');
            header('Location: /index.php?r=dashboard'); exit;
        }
        $historicos = Pleito::homologados();
        $apuracao = Homologacao::apurarResultados((int)$pleito['id']);
        $homologado = $apuracao['homologado'];
        $dadosHomol = $apuracao['dados_homologacao'];

        require_once ROOT_PATH . '/views/relatorios/placa_hall.php';
    }

    /**
     * Módulo para o Site Oficial da COMARA (www2.comara.intraer)
     */
    public function siteOficial(): void {
        AuthMiddleware::handle();
        $pleito = Pleito::paraConsulta();

        if (!$pleito) {
            flash_message('erro', 'Nenhum pleito ativo.', 'warning');
            header('Location: /index.php?r=dashboard');
            exit;
        }

        if ($pleito['status']!=='homologado') {
            $historicos=Pleito::homologados(); $rotaHistorico='relatorios/site';
            require ROOT_PATH.'/views/relatorios/aguardando_homologacao.php'; return;
        }
        $historicos = Pleito::homologados();
        $apuracao = Homologacao::apurarResultados((int)$pleito['id']);

        require_once ROOT_PATH . '/views/relatorios/site_oficial.php';
    }

    /**
     * Relatório de Auditoria e Estatísticas Gerais
     */
    public function estatisticas(): void {
        AuthMiddleware::handle();
        $pleito = Pleito::getAtivo();
        $pdo = Database::getConnection();

        $stats = [
            'total_efetivo'       => (int)$pdo->query('SELECT COUNT(*) FROM efetivo WHERE ativo = 1')->fetchColumn(),
            'total_aptos_voto'    => (int)$pdo->query('SELECT COUNT(*) FROM efetivo WHERE ativo = 1 AND categoria != "ineligivel"')->fetchColumn(),
            'total_votos_cedulas' => (int)$pdo->query("SELECT COUNT(*) FROM urna_votos WHERE pleito_id = {$pleito['id']}")->fetchColumn(),
            'total_eleitores'     => (int)$pdo->query("SELECT COUNT(*) FROM eleitores_ciclo WHERE pleito_id = {$pleito['id']} AND votou = 1")->fetchColumn(),
            'total_fichas_fase1'  => (int)$pdo->query("SELECT COUNT(*) FROM fichas_indicacao WHERE pleito_id = {$pleito['id']}")->fetchColumn(),
            'total_selecoes_fase2'=> (int)$pdo->query("SELECT COUNT(*) FROM selecao_fase2 WHERE pleito_id = {$pleito['id']}")->fetchColumn(),
        ];

        require_once ROOT_PATH . '/views/relatorios/estatisticas.php';
    }
}

