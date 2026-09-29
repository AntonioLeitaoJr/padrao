<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../Models/Pleito.php';
require_once __DIR__ . '/../Models/Efetivo.php';
require_once __DIR__ . '/../Models/FichaIndicacao.php';
require_once __DIR__ . '/../Models/AvaliacaoTacf.php';

class Fase1Controller {

    public function index(): void {
        AuthMiddleware::handle();

        if (!AuthManager::isAvaliadorFase1() && !AuthManager::hasRole([PERFIL_ED_FISICA, PERFIL_ADMIN])) {
            flash_message('erro', 'Acesso negado: Na primeira rodada, apenas Oficiais nos postos de Tenente ou Capitão possuem prerrogativa para avaliar subordinados.', 'danger');
            header('Location: /index.php?r=dashboard');
            exit;
        }

        $user = AuthManager::user();
        $pleito = Pleito::getAtivo();

        if (!$pleito) {
            flash_message('erro', 'Nenhum pleito ativo no momento.', 'warning');
            header('Location: /index.php?r=dashboard');
            exit;
        }

        $subordinados = [];
        if (!empty($user['efetivo_id'])) {
            $subordinados = Efetivo::getSubordinadosDiretos((int)$user['efetivo_id'], (int)$pleito['id']);
        }

        // Candidatos disponíveis para avaliação voluntária (excluindo os subordinados diretos para não aparecerem repetidos)
        $subordinadosIds = array_column($subordinados, 'id');
        $todosCandidatos = Efetivo::listarCandidatosFase1((int)$pleito['id']);
        $candidatosVoluntarios = array_values(array_filter($todosCandidatos, function($c) use ($subordinadosIds, $user) {
            if ((int)$c['id'] === (int)($user['efetivo_id'] ?? 0)) return false;
            if (in_array((int)$c['id'], $subordinadosIds, true)) return false;
            return true;
        }));

        // Fichas voluntárias já preenchidas por este oficial
        $fichasVoluntarias = [];
        if (!empty($user['id'])) {
            $pdo = Database::getConnection();
            $stmtFv = $pdo->prepare("SELECT candidato_id, id, is_nob, status, media_calculada, updated_at FROM fichas_indicacao WHERE pleito_id = :p AND avaliador_id = :u AND tipo_avaliacao = 'VOLUNTARIA'");
            $stmtFv->execute([':p' => $pleito['id'], ':u' => $user['id']]);
            foreach ($stmtFv->fetchAll() as $row) {
                $fichasVoluntarias[$row['candidato_id']] = $row;
            }
        }

        // Se for Educação Física, prepara lista de militares para TACF
        $militaresTacf = [];
        if (AuthManager::hasRole([PERFIL_ED_FISICA, PERFIL_ADMIN])) {
            $pdo = Database::getConnection();
            $sqlTacf = "
                SELECT e.*, t.nota_tacf, t.data_realizacao, t.observacoes, t.updated_at as tacf_atualizado_em
                FROM efetivo e
                LEFT JOIN avaliacoes_tacf t ON t.candidato_id = e.id AND t.pleito_id = :p
                WHERE e.categoria IN ('graduado', 'praca') AND e.ativo = 1
                ORDER BY e.categoria, e.posto, e.nome
            ";
            $stmt = $pdo->prepare($sqlTacf);
            $stmt->execute([':p' => $pleito['id']]);
            $militaresTacf = $stmt->fetchAll();
        }

        // Lista de oficiais disponíveis para delegação de chefia em missão
        $oficiais = Efetivo::listarOficiais();

        // Pendências globais para o Admin ou Direção Superior
        $verificacaoPendencias = Pleito::validarPendenciasFase1((int)$pleito['id']);

        require_once ROOT_PATH . '/views/fase1/index.php';
    }

    /**
     * Retorna os dados do candidato e sua ficha (se já preenchida) em JSON para o Popup de Avaliação
     */
    public function dadosFicha(): void {
        AuthMiddleware::handle();

        if (!AuthManager::isAvaliadorFase1()) {
            header('Content-Type: application/json');
            echo json_encode(['sucesso' => false, 'mensagem' => 'Acesso negado: Apenas Tenentes e Capitães avaliam na Fase 1.']);
            exit;
        }

        $user = AuthManager::user();
        $pleito = Pleito::getAtivo();
        $candidatoId = (int)($_GET['id'] ?? 0);
        $candidato = Efetivo::getPorId($candidatoId);

        if (!$candidato || !$pleito) {
            header('Content-Type: application/json');
            echo json_encode(['sucesso' => false, 'mensagem' => 'Candidato ou pleito não encontrado.']);
            exit;
        }

        $isSubordinado = ($candidato['chefe_direto_id'] && (int)$candidato['chefe_direto_id'] === (int)($user['efetivo_id'] ?? 0));
        $isDelegado = Efetivo::isOficialDelegado((int)$pleito['id'], $candidatoId, (int)($user['efetivo_id'] ?? 0));
        $tipoAval = ($isSubordinado || $isDelegado) ? 'OBRIGATORIA_CHEFE' : 'VOLUNTARIA';

        $ficha = FichaIndicacao::getFicha((int)$pleito['id'], $candidatoId, (int)$user['id']);
        $isMilitar = in_array($candidato['categoria'], ['graduado', 'praca'], true);
        $fichaStatus = $ficha['status'] ?? 'preenchido';
        $podeEditar = !($ficha && $fichaStatus === 'concluido' && !AuthManager::hasRole(PERFIL_ADMIN));

        header('Content-Type: application/json');
        echo json_encode([
            'sucesso' => true,
            'candidato' => [
                'id'          => $candidato['id'],
                'posto'       => $candidato['posto'],
                'nome_guerra' => $candidato['nome_guerra'],
                'setor'       => $candidato['setor'],
                'categoria'   => $candidato['categoria'],
                'is_militar'  => $isMilitar,
                'foto_url'    => '/index.php?r=foto&saram=' . urlencode((string)$candidato['saram']) . '&id=' . $candidato['id']
            ],
            'tipo_avaliacao' => $tipoAval,
            'pode_editar'    => $podeEditar,
            'ficha' => $ficha ? [
                'is_nob'                     => (int)($ficha['is_nob'] ?? 0),
                'status'                     => $ficha['status'] ?? 'concluido',
                'servico_escala'             => (int)($ficha['servico_escala'] ?? 5),
                'eficiencia_eficacia'        => (int)($ficha['eficiencia_eficacia'] ?? 5),
                'conhecimento_especialidade' => (int)($ficha['conhecimento_especialidade'] ?? 5),
                'iniciativa_adaptabilidade'  => (int)($ficha['iniciativa_adaptabilidade'] ?? 5),
                'conduta_pontuacao'          => (int)($ficha['conduta_pontuacao'] ?? 5),
                'conduta_fichas'             => $ficha['conduta_fichas'] ?? '',
                'apresentacao_pessoal'       => (int)($ficha['apresentacao_pessoal'] ?? 5),
                'relacionamento_trabalho'    => (int)($ficha['relacionamento_trabalho'] ?? 5),
                'lideranca'                  => (int)($ficha['lideranca'] ?? 5)
            ] : null
        ]);
        exit;
    }

    /**
     * Processa a gravação da avaliação do subordinado (submetida pelo Popup ou formulário)
     */
    public function avaliar(): void {
        AuthMiddleware::handle();

        if (!AuthManager::isAvaliadorFase1()) {
            flash_message('erro', 'Acesso negado: Apenas Oficiais nos postos de Tenente ou Capitão podem avaliar na Fase 1.', 'danger');
            header('Location: /index.php?r=dashboard');
            exit;
        }

        $user = AuthManager::user();
        $pleito = Pleito::getAtivo();

        $candidatoId = (int)($_POST['candidato_id'] ?? ($_GET['id'] ?? 0));
        $candidato = Efetivo::getPorId($candidatoId);

        if (!$candidato || !$pleito) {
            flash_message('erro', 'Candidato ou pleito não encontrado.', 'danger');
            header('Location: /index.php?r=fase1');
            exit;
        }

        $isSubordinado = ($candidato['chefe_direto_id'] && (int)$candidato['chefe_direto_id'] === (int)($user['efetivo_id'] ?? 0));
        $isDelegado = Efetivo::isOficialDelegado((int)$pleito['id'], $candidatoId, (int)($user['efetivo_id'] ?? 0));
        $tipoAval = ($isSubordinado || $isDelegado) ? 'OBRIGATORIA_CHEFE' : 'VOLUNTARIA';

        // Bloqueia edição se a ficha já foi concluída e enviada
        $fichaExistente = FichaIndicacao::getFicha((int)$pleito['id'], $candidatoId, (int)$user['id']);
        if ($fichaExistente && ($fichaExistente['status'] ?? '') === 'concluido' && !AuthManager::hasRole(PERFIL_ADMIN)) {
            flash_message('erro', 'Esta ficha de avaliação já foi concluída e enviada, não sendo permitidas novas alterações.', 'warning');
            header('Location: /index.php?r=fase1');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
                flash_message('erro', 'Token CSRF inválido.', 'danger');
                header('Location: /index.php?r=fase1');
                exit;
            }

            $dados = $_POST;
            $dados['pleito_id'] = $pleito['id'];
            $dados['candidato_id'] = $candidatoId;
            $dados['tipo_avaliacao'] = $tipoAval;

            $resultado = FichaIndicacao::salvarFicha($dados, (int)$user['id']);
            if ($resultado['sucesso']) {
                $substitutoMsg = $isDelegado ? ' (Avaliação concluída em substituição ao chefe em missão)' : '';
                $detalhe = ($resultado['is_nob'] ?? false) 
                    ? "registrada como Não Observado (NOB){$substitutoMsg}."
                    : ((($resultado['status'] ?? '') === 'preenchido')
                        ? "salva com sucesso! (Status: Preenchido - você pode editá-la antes de enviar)"
                        : "concluída e enviada com sucesso!{$substitutoMsg} (Média: {$resultado['media']})");

                flash_message('sucesso', "Ficha de {$candidato['posto']} {$candidato['nome_guerra']} {$detalhe}", 'success');
            } else {
                flash_message('erro', $resultado['mensagem'], 'danger');
            }
        }

        header('Location: /index.php?r=fase1');
        exit;
    }

    /**
     * Delega a avaliação obrigatória da Fase 1 a outro Oficial caso o chefe direto esteja em missão
     */
    public function salvarDelegacao(): void {
        AuthMiddleware::handle();

        if (!AuthManager::isOficial() && !AuthManager::hasRole(PERFIL_ADMIN)) {
            flash_message('erro', 'Acesso negado: Apenas Oficiais ou Administradores podem realizar a delegação de avaliação.', 'danger');
            header('Location: /index.php?r=fase1');
            exit;
        }

        $user = AuthManager::user();
        $pleito = Pleito::getAtivo();

        if (!$pleito) {
            flash_message('erro', 'Nenhum pleito ativo.', 'danger');
            header('Location: /index.php?r=fase1');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
                flash_message('erro', 'Token CSRF inválido.', 'danger');
                header('Location: /index.php?r=fase1');
                exit;
            }

            $candidatoId = (int)($_POST['candidato_id'] ?? 0);
            $oficialDelegadoId = (int)($_POST['oficial_delegado_id'] ?? 0);
            $motivo = trim((string)($_POST['motivo'] ?? ''));

            $candidato = Efetivo::getPorId($candidatoId);
            if (!$candidato) {
                flash_message('erro', 'Candidato não localizado.', 'danger');
                header('Location: /index.php?r=fase1');
                exit;
            }

            // Se não for administrador, deve ser o chefe direto registrado do militar
            if (!AuthManager::hasRole(PERFIL_ADMIN)) {
                if ((int)$candidato['chefe_direto_id'] !== (int)($user['efetivo_id'] ?? 0)) {
                    flash_message('erro', 'Permissão negada: Você só pode delegar a avaliação de seus próprios subordinados diretos.', 'danger');
                    header('Location: /index.php?r=fase1');
                    exit;
                }
            }

            $res = Efetivo::salvarDelegacao((int)$pleito['id'], $candidatoId, $oficialDelegadoId, (int)$user['id'], $motivo);

            if ($res['sucesso']) {
                AuditoriaService::log('DELEGACAO_FASE1_REGISTRADA', [
                    'pleito_id'           => $pleito['id'],
                    'candidato_id'        => $candidatoId,
                    'oficial_delegado_id' => $oficialDelegadoId,
                    'motivo'              => $motivo
                ], (int)$user['id']);
                flash_message('sucesso', $res['mensagem'], 'success');
            } else {
                flash_message('erro', $res['mensagem'], 'danger');
            }
        }

        header('Location: /index.php?r=fase1');
        exit;
    }

    /**
     * Remove delegação de avaliação da Fase 1
     */
    public function removerDelegacao(): void {
        AuthMiddleware::handle();

        if (!AuthManager::isOficial() && !AuthManager::hasRole(PERFIL_ADMIN)) {
            flash_message('erro', 'Acesso negado.', 'danger');
            header('Location: /index.php?r=fase1');
            exit;
        }

        $user = AuthManager::user();
        $pleito = Pleito::getAtivo();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
                flash_message('erro', 'Token CSRF inválido.', 'danger');
                header('Location: /index.php?r=fase1');
                exit;
            }

            $candidatoId = (int)($_POST['candidato_id'] ?? 0);
            $candidato = Efetivo::getPorId($candidatoId);

            if ($candidato) {
                if (!AuthManager::hasRole(PERFIL_ADMIN)) {
                    if ((int)$candidato['chefe_direto_id'] !== (int)($user['efetivo_id'] ?? 0)) {
                        flash_message('erro', 'Permissão negada para cancelar esta delegação.', 'danger');
                        header('Location: /index.php?r=fase1');
                        exit;
                    }
                }

                Efetivo::removerDelegacao((int)$pleito['id'], $candidatoId);
                AuditoriaService::log('DELEGACAO_FASE1_REMOVIDA', [
                    'pleito_id'    => $pleito['id'],
                    'candidato_id' => $candidatoId
                ], (int)$user['id']);
                flash_message('sucesso', 'Delegação removida com sucesso. A responsabilidade volta ao chefe direto.', 'success');
            }
        }

        header('Location: /index.php?r=fase1');
        exit;
    }

    /**
     * Lançamento exclusivo de TACF pelo Setor de Educação Física
     */
    public function salvarTacf(): void {
        AuthMiddleware::handle();
        RoleMiddleware::handle([PERFIL_ED_FISICA, PERFIL_ADMIN]);

        $user = AuthManager::user();
        $pleito = Pleito::getAtivo();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
                flash_message('erro', 'Token CSRF inválido.', 'danger');
                header('Location: /index.php?r=fase1');
                exit;
            }

            $candidatoId = (int)($_POST['candidato_id'] ?? 0);
            $notaTacf = (float)str_replace(',', '.', $_POST['nota_tacf'] ?? '0');
            $dataRealizacao = $_POST['data_realizacao'] ?? date('Y-m-d');
            $obs = trim($_POST['observacoes'] ?? '');

            $res = AvaliacaoTacf::salvarNota(
                (int)$pleito['id'],
                $candidatoId,
                $notaTacf,
                (int)$user['id'],
                $obs,
                $dataRealizacao
            );

            if ($res['sucesso']) {
                flash_message('sucesso', $res['mensagem'], 'success');
            } else {
                flash_message('erro', $res['mensagem'], 'danger');
            }
        }

        header('Location: /index.php?r=fase1');
        exit;
    }
}
