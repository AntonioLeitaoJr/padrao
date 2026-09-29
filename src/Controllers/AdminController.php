<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../Middleware/RoleMiddleware.php';
require_once __DIR__ . '/../Models/Pleito.php';
require_once __DIR__ . '/../Models/Efetivo.php';
require_once __DIR__ . '/../Services/BackupService.php';
require_once __DIR__ . '/../Services/AuditoriaService.php';

class AdminController {

    public function pleito(): void {
        AuthMiddleware::handle();
        RoleMiddleware::handle(PERFIL_ADMIN);

        $pleito = Pleito::getAtivo();
        $pdo = Database::getConnection();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
                flash_message('erro', 'Token CSRF inválido.', 'danger');
                header('Location: /index.php?r=admin/pleito');
                exit;
            }

            $titulo = trim($_POST['titulo'] ?? '');
            $fase1Ini = $_POST['fase1_inicio'] ?? null;
            $fase1Fim = $_POST['fase1_fim'] ?? null;
            $fase2Ini = $_POST['fase2_inicio'] ?? null;
            $fase2Fim = $_POST['fase2_fim'] ?? null;
            $fase3Ini = $_POST['fase3_inicio'] ?? null;
            $fase3Fim = $_POST['fase3_fim'] ?? null;
            $fase4Ini = $_POST['fase4_inicio'] ?? null;
            $fase4Fim = $_POST['fase4_fim'] ?? null;
            $fase5Ini = $_POST['fase5_inicio'] ?? null;
            $fase5Fim = $_POST['fase5_fim'] ?? null;

            $stmt = $pdo->prepare('
                UPDATE pleitos SET
                    titulo = :t,
                    fase1_inicio = :f1i, fase1_fim = :f1f,
                    fase2_inicio = :f2i, fase2_fim = :f2f,
                    fase3_inicio = :f3i, fase3_fim = :f3f,
                    fase4_inicio = :f4i, fase4_fim = :f4f,
                    fase5_inicio = :f5i, fase5_fim = :f5f
                WHERE id = :id
            ');

            $stmt->execute([
                ':t'   => $titulo,
                ':f1i' => $fase1Ini, ':f1f' => $fase1Fim,
                ':f2i' => $fase2Ini, ':f2f' => $fase2Fim,
                ':f3i' => $fase3Ini, ':f3f' => $fase3Fim,
                ':f4i' => $fase4Ini, ':f4f' => $fase4Fim,
                ':f5i' => $fase5Ini, ':f5f' => $fase5Fim,
                ':id'  => $pleito['id']
            ]);

            flash_message('sucesso', 'Configurações de prazos atualizadas com sucesso.', 'success');
            header('Location: /index.php?r=admin/pleito');
            exit;
        }

        $verificacaoFase1 = Pleito::validarPendenciasFase1((int)$pleito['id']);
        $historicoFases = Pleito::obterHistoricoFases((int)$pleito['id']);

        require_once ROOT_PATH . '/views/admin/pleito.php';
    }

    public function avancarFase(): void {
        AuthMiddleware::handle();
        RoleMiddleware::handle(PERFIL_ADMIN);

        $pleito = Pleito::getAtivo();
        $user = AuthManager::user();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
                flash_message('erro', 'Token CSRF inválido.', 'danger');
                header('Location: /index.php?r=admin/pleito');
                exit;
            }

            $justificativa = trim((string)($_POST['justificativa_pendencias'] ?? ''));
            $res = Pleito::avancarFase((int)$pleito['id'], (int)$user['id'], $justificativa !== '' ? $justificativa : null);
            if ($res['sucesso']) {
                flash_message('sucesso', $res['mensagem'], 'success');
            } else {
                flash_message('erro', $res['mensagem'], 'danger');
            }
        }

        header('Location: /index.php?r=admin/pleito');
        exit;
    }

    public function voltarFase(): void {
        AuthMiddleware::handle();
        RoleMiddleware::handle(PERFIL_ADMIN);

        $pleito = Pleito::getAtivo();
        $user = AuthManager::user();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
                flash_message('erro', 'Token CSRF inválido.', 'danger');
                header('Location: /index.php?r=admin/pleito');
                exit;
            }

            if (!$pleito) {
                flash_message('erro', 'Nenhum pleito ativo.', 'danger');
                header('Location: /index.php?r=admin/pleito');
                exit;
            }

            $motivo = trim((string)($_POST['motivo_retorno'] ?? ''));
            $res = Pleito::voltarFase((int)$pleito['id'], (int)$user['id'], $motivo !== '' ? $motivo : null);
            if ($res['sucesso']) {
                flash_message('sucesso', $res['mensagem'], 'success');
            } else {
                flash_message('erro', $res['mensagem'], 'danger');
            }
        }

        header('Location: /index.php?r=admin/pleito');
        exit;
    }

    /**
     * Reinicialização oficial do pleito pelo Administrador
     */
    public function reiniciarPleito(): void {
        AuthMiddleware::handle();
        RoleMiddleware::handle(PERFIL_ADMIN);

        $pleito = Pleito::getAtivo();
        $user = AuthManager::user();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
                flash_message('erro', 'Token CSRF inválido.', 'danger');
                header('Location: /index.php?r=admin/pleito');
                exit;
            }

            if (!$pleito) {
                flash_message('erro', 'Nenhum pleito ativo ou cadastrado para reinicialização.', 'danger');
                header('Location: /index.php?r=admin/pleito');
                exit;
            }

            $limparDados = isset($_POST['limpar_dados']) && (string)$_POST['limpar_dados'] === '1';
            $limparTacf = isset($_POST['limpar_tacf']) && (string)$_POST['limpar_tacf'] === '1';

            $res = Pleito::reiniciar((int)$pleito['id'], $limparDados, $limparTacf, (int)$user['id']);

            if ($res['sucesso']) {
                flash_message('sucesso', $res['mensagem'], 'success');
            } else {
                flash_message('erro', $res['mensagem'], 'danger');
            }
        }

        header('Location: /index.php?r=admin/pleito');
        exit;
    }

    /**
     * Gestão de Fotografias dos Candidatos (API SIGPES + Upload Manual)
     */
    public function fotos(): void {
        AuthMiddleware::handle();
        RoleMiddleware::handle(PERFIL_ADMIN);

        $pdo = Database::getConnection();
        $categoria = $_GET['cat'] ?? 'graduado';
        $termo = trim($_GET['q'] ?? '');

        $sql = "SELECT * FROM efetivo WHERE categoria = :cat AND ativo = 1";
        $params = [':cat' => $categoria];

        if ($termo !== '') {
            $sql .= " AND (nome LIKE :q OR nome_guerra LIKE :q OR saram LIKE :q)";
            $params[':q'] = "%{$termo}%";
        }

        $sql .= " ORDER BY posto, nome";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $candidatos = $stmt->fetchAll();

        require_once ROOT_PATH . '/views/admin/fotos.php';
    }

    public function uploadFoto(): void {
        AuthMiddleware::handle();
        RoleMiddleware::handle(PERFIL_ADMIN);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
                flash_message('erro', 'Token CSRF inválido.', 'danger');
                header('Location: /index.php?r=admin/fotos');
                exit;
            }

            $candidatoId = (int)($_POST['candidato_id'] ?? 0);
            $candidato = Efetivo::getPorId($candidatoId);

            if (!$candidato) {
                flash_message('erro', 'Candidato não encontrado.', 'danger');
                header('Location: /index.php?r=admin/fotos');
                exit;
            }

            if (!empty($_FILES['foto']['name']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                $fileTmp = $_FILES['foto']['tmp_name'];
                $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];

                if (!in_array($ext, $allowed, true)) {
                    flash_message('erro', 'Formato de imagem inválido. Use JPG, PNG ou WebP.', 'danger');
                    header('Location: /index.php?r=admin/fotos');
                    exit;
                }

                // Cria pasta caso não exista
                if (!is_dir(UPLOAD_FOTOS_PATH)) {
                    @mkdir(UPLOAD_FOTOS_PATH, 0775, true);
                }

                $novoNome = "cand_{$candidatoId}_" . time() . ".{$ext}";
                $destino = UPLOAD_FOTOS_PATH . '/' . $novoNome;

                if (move_uploaded_file($fileTmp, $destino)) {
                    Efetivo::atualizarFotoManual($candidatoId, $novoNome);
                    AuditoriaService::log('FOTO_MANUAL_ATUALIZADA', [
                        'candidato_id' => $candidatoId,
                        'arquivo'      => $novoNome
                    ]);
                    flash_message('sucesso', "Fotografia de {$candidato['nome_guerra']} atualizada manualmente com sucesso!", 'success');
                } else {
                    flash_message('erro', 'Falha ao salvar o arquivo enviado no servidor.', 'danger');
                }
            } else {
                flash_message('erro', 'Nenhum arquivo enviado ou erro no upload.', 'danger');
            }
        }

        header('Location: /index.php?r=admin/fotos');
        exit;
    }

    /**
     * Visualizador de Logs de Auditoria (5 anos de retenção)
     */
    public function logs(): void {
        AuthMiddleware::handle();
        RoleMiddleware::handle(PERFIL_ADMIN);

        $termo = trim($_GET['termo'] ?? '');
        $pagina = max(1, (int)($_GET['p'] ?? 1));
        $porPagina = 50;
        $offset = ($pagina - 1) * $porPagina;

        $logs = AuditoriaService::listarLogs($porPagina, $offset, $termo ?: null);

        require_once ROOT_PATH . '/views/admin/logs.php';
    }

    /**
     * Gestão de Backups Diários e Semanais
     */
    public function backups(): void {
        AuthMiddleware::handle();
        RoleMiddleware::handle(PERFIL_ADMIN);

        $user = AuthManager::user();

        if (isset($_POST['acao']) && $_POST['acao'] === 'gerar') {
            if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
                flash_message('erro', 'Token CSRF inválido.', 'danger');
                header('Location: /index.php?r=admin/backups');
                exit;
            }

            $tipo = $_POST['tipo'] ?? 'diario';
            $res = BackupService::gerarBackup($tipo, (int)$user['id']);

            if ($res['sucesso']) {
                flash_message('sucesso', "Backup {$tipo} gerado com sucesso! Arquivo: {$res['arquivo']} ({$res['tamanho']})", 'success');
            } else {
                flash_message('erro', 'Falha na geração do backup.', 'danger');
            }

            header('Location: /index.php?r=admin/backups');
            exit;
        }

        $listaBackups = BackupService::listarBackups();

        require_once ROOT_PATH . '/views/admin/backups.php';
    }

    /**
     * Painel de Gestão do Efetivo e Candidatos (COMARA)
     */
    public function efetivo(): void {
        AuthMiddleware::handle();
        RoleMiddleware::handle([PERFIL_ADMIN, PERFIL_RH]);

        $categoria = $_GET['cat'] ?? 'todas';
        $divisao = $_GET['div'] ?? 'todas';
        $status = $_GET['status'] ?? 'ativo';
        $termo = trim($_GET['q'] ?? '');
        $pagina = max(1, (int)($_GET['p'] ?? 1));
        $porPagina = 30;
        $offset = ($pagina - 1) * $porPagina;

        $totais = Efetivo::obterTotaisPorCategoria();
        $divisoes = Efetivo::listarDivisoes();
        $potenciaisChefes = Efetivo::listarOficiaisEChefes();

        $totalRegistros = Efetivo::contarTotal(
            $termo ?: null,
            $divisao !== 'todas' ? $divisao : null,
            $categoria !== 'todas' ? $categoria : null,
            $status !== 'todos' ? $status : null
        );

        $efetivo = Efetivo::listarPaginado(
            $porPagina,
            $offset,
            $termo ?: null,
            $divisao !== 'todas' ? $divisao : null,
            $categoria !== 'todas' ? $categoria : null,
            $status !== 'todos' ? $status : null
        );

        $totalPaginas = max(1, (int)ceil($totalRegistros / $porPagina));

        require_once ROOT_PATH . '/views/admin/efetivo.php';
    }

    /**
     * Cadastra ou edita um militar/servidor civil
     */
    public function salvarEfetivo(): void {
        AuthMiddleware::handle();
        RoleMiddleware::handle([PERFIL_ADMIN, PERFIL_RH]);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
                flash_message('erro', 'Token CSRF inválido.', 'danger');
                header('Location: /index.php?r=admin/efetivo');
                exit;
            }

            $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
            $fotoCustom = null;

            // Upload de foto se fornecida
            if (!empty($_FILES['foto']['name']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                    if (!is_dir(UPLOAD_FOTOS_PATH)) {
                        @mkdir(UPLOAD_FOTOS_PATH, 0775, true);
                    }
                    $saram = trim($_POST['saram'] ?? '');
                    $identificador = $saram ? "saram_{$saram}" : "user_" . time();
                    $fotoCustom = "{$identificador}_" . time() . ".{$ext}";
                    move_uploaded_file($_FILES['foto']['tmp_name'], UPLOAD_FOTOS_PATH . '/' . $fotoCustom);
                }
            }

            $salvoId = Efetivo::salvarOuAtualizar($_POST, $fotoCustom);

            // Atualiza papel de Administrador ou RH exclusivamente se a alteração for feita por um Administrador Geral
            if (AuthManager::hasRole(PERFIL_ADMIN)) {
                $pdo = Database::getConnection();
                $stmtU = $pdo->prepare('SELECT id, login, perfil FROM usuarios WHERE efetivo_id = :ef LIMIT 1');
                $stmtU->execute([':ef' => $salvoId]);
                $userFound = $stmtU->fetch();

                if ($userFound && $userFound['login'] !== 'admin') {
                    $novoPerfil = $userFound['perfil'];

                    if (isset($_POST['is_admin_enviado'])) {
                        $isAdmin = !empty($_POST['is_admin']);
                        if ($isAdmin) {
                            $novoPerfil = PERFIL_ADMIN;
                        } elseif ($userFound['perfil'] === PERFIL_ADMIN) {
                            $novoPerfil = PERFIL_ELEITOR;
                        }
                    }

                    if (isset($_POST['is_rh_enviado'])) {
                        $isRh = !empty($_POST['is_rh']);
                        if ($isRh && $novoPerfil !== PERFIL_ADMIN) {
                            $novoPerfil = PERFIL_RH;
                        } elseif (!$isRh && $userFound['perfil'] === PERFIL_RH) {
                            $novoPerfil = PERFIL_ELEITOR;
                        }
                    }

                    if ($novoPerfil !== $userFound['perfil']) {
                        $pdo->prepare('UPDATE usuarios SET perfil = :p WHERE id = :id')->execute([':p' => $novoPerfil, ':id' => $userFound['id']]);
                        AuditoriaService::log('PERFIL_USUARIO_ATUALIZADO', ['efetivo_id' => $salvoId, 'novo_perfil' => $novoPerfil]);
                    }
                }
            }

            AuditoriaService::log($id ? 'EFETIVO_ATUALIZADO' : 'EFETIVO_CADASTRADO', [
                'efetivo_id' => $salvoId,
                'nome'       => $_POST['nome'] ?? '',
                'saram'      => $_POST['saram'] ?? '',
                'categoria'  => $_POST['categoria'] ?? ''
            ]);

            flash_message('sucesso', 'Registro do militar/servidor salvo com sucesso!', 'success');
        }

        header('Location: /index.php?r=admin/efetivo');
        exit;
    }

    /**
     * Alterna status ativo/inativo de um integrante do efetivo
     */
    public function toggleAtivoEfetivo(): void {
        AuthMiddleware::handle();
        RoleMiddleware::handle([PERFIL_ADMIN, PERFIL_RH]);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
                flash_message('erro', 'Token CSRF inválido.', 'danger');
                header('Location: /index.php?r=admin/efetivo');
                exit;
            }

            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $ativo = Efetivo::toggleAtivo($id);
                AuditoriaService::log('EFETIVO_STATUS_ALTERADO', ['id' => $id, 'ativo' => $ativo]);
                flash_message('sucesso', 'Status do integrante atualizado para ' . ($ativo ? 'ATIVO' : 'INATIVO') . '.', 'info');
            }
        }

        header('Location: /index.php?r=admin/efetivo');
        exit;
    }

    /**
     * Baixa modelo oficial de planilha CSV para importação
     */
    public function baixarModeloCsv(): void {
        AuthMiddleware::handle();
        RoleMiddleware::handle([PERFIL_ADMIN, PERFIL_RH]);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="modelo_efetivo_comara.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');

        // BOM para abrir com acentuação correta no Excel
        echo "\xEF\xBB\xBF";

        $cabecalho = ["SARAM", "CPF", "NOME", "NOME_GUERRA", "POSTO", "QUADRO", "ESPECIALIDADE", "SETOR", "DIVISAO", "CATEGORIA", "VINCULO", "TELEFONE"];
        echo implode(";", $cabecalho) . "\r\n";

        // Exemplos representativos das 4 categorias + oficiais
        $exemplos = [
            ["3257347", "111.111.111-03", "ADENIRSON LEVY SANTOS DA CRUZ", "LEVY", "Cel", "QOAV", "NTE", "DPC", "DPC", "ineligivel", "MILITAR_CARREIRA", "91999990001"],
            ["2309165", "222.222.222-01", "RONALDO AUGUSTO DA SILVA COTA", "COTA", "SO", "QSS", "SEM", "SCS", "SCS", "graduado", "MILITAR_CARREIRA", "91999990002"],
            ["7497920", "333.333.333-01", "HERALDO WAGNER CONCEIÇÃO MONTEIRO", "MONTEIRO", "Cb", "QCBCON", "TMC", "DLAL", "DL", "praca", "MILITAR_TEMPORARIO", "91999990003"],
            ["4590123", "444.444.444-01", "CARLOS EDUARDO SILVA SANTOS", "CARLOS", "CV", "CIVIL", "ENG", "SDPJ", "DE", "sppf", "CIVIL_PERMANENTE", "91999990004"],
            ["8512345", "555.555.555-01", "ANA BEATRIZ CARVALHO ROCHA", "ANA BEATRIZ", "CV", "CIVIL", "ADM", "DA", "DA", "sptf", "CIVIL_TEMPORARIO", "91999990005"],
        ];

        foreach ($exemplos as $linha) {
            echo implode(";", $linha) . "\r\n";
        }
        exit;
    }

    /**
     * Exporta o efetivo completo atualmente cadastrado em formato CSV compatível com Excel
     */
    public function baixarEfetivoCsv(): void {
        AuthMiddleware::handle();
        RoleMiddleware::handle([PERFIL_ADMIN, PERFIL_RH]);

        $pdo = Database::getConnection();
        $stmt = $pdo->query('
            SELECT saram, cpf, nome, nome_guerra, posto, quadro, especialidade, setor, divisao_sigla, categoria, tipo_vinculo, telefone, ativo
            FROM efetivo 
            ORDER BY posto, nome
        ');
        $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="efetivo_completo_comara_' . date('Ymd_His') . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo "\xEF\xBB\xBF";
        echo "SARAM;CPF;NOME;NOME_GUERRA;POSTO;QUADRO;ESPECIALIDADE;SETOR;DIVISAO;CATEGORIA;VINCULO;TELEFONE;ATIVO\r\n";

        foreach ($registros as $r) {
            echo implode(";", [
                $r['saram'] ?? '',
                $r['cpf'] ?? '',
                $r['nome'] ?? '',
                $r['nome_guerra'] ?? '',
                $r['posto'] ?? '',
                $r['quadro'] ?? '',
                $r['especialidade'] ?? '',
                $r['setor'] ?? '',
                $r['divisao_sigla'] ?? '',
                $r['categoria'] ?? '',
                $r['tipo_vinculo'] ?? '',
                $r['telefone'] ?? '',
                $r['ativo'] ? '1' : '0'
            ]) . "\r\n";
        }
        exit;
    }

    /**
     * Importação em lote de militares e servidores via arquivo CSV
     */
    public function importarCsv(): void {
        AuthMiddleware::handle();
        RoleMiddleware::handle([PERFIL_ADMIN, PERFIL_RH]);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
                flash_message('erro', 'Token CSRF inválido.', 'danger');
                header('Location: /index.php?r=admin/efetivo');
                exit;
            }

            if (empty($_FILES['arquivo_csv']['tmp_name']) || $_FILES['arquivo_csv']['error'] !== UPLOAD_ERR_OK) {
                flash_message('erro', 'Arquivo CSV inválido ou não enviado.', 'danger');
                header('Location: /index.php?r=admin/efetivo');
                exit;
            }

            $arquivo = $_FILES['arquivo_csv']['tmp_name'];
            $conteudo = file_get_contents($arquivo);
            if ($conteudo === false) {
                flash_message('erro', 'Não foi possível ler o arquivo enviado.', 'danger');
                header('Location: /index.php?r=admin/efetivo');
                exit;
            }

            // Remove BOM UTF-8
            $conteudo = preg_replace('/^\xEF\xBB\xBF/', '', $conteudo);
            $linhas = preg_split('/\r\n|\r|\n/', trim($conteudo));

            if (count($linhas) < 2) {
                flash_message('erro', 'O arquivo CSV enviado não contém linhas de dados válidas.', 'danger');
                header('Location: /index.php?r=admin/efetivo');
                exit;
            }

            // Detecta delimitador (; ou ,)
            $delimitador = str_contains($linhas[0], ';') ? ';' : ',';
            $cabecalhos = array_map('trim', explode($delimitador, mb_strtoupper($linhas[0])));

            $mapa = [];
            foreach ($cabecalhos as $idx => $nomeCol) {
                $nomeCol = trim(str_replace(['"', "'"], '', $nomeCol));
                $mapa[$nomeCol] = $idx;
            }

            $totalProcessados = 0;
            $totalInseridos = 0;
            $totalAtualizados = 0;
            $pdo = Database::getConnection();

            for ($i = 1; $i < count($linhas); $i++) {
                $linha = trim($linhas[$i]);
                if ($linha === '') continue;

                $cols = str_getcsv($linha, $delimitador);
                if (count($cols) < 3) continue;

                $getVal = function($campo) use ($cols, $mapa) {
                    $idx = $mapa[$campo] ?? null;
                    return ($idx !== null && isset($cols[$idx])) ? trim(str_replace(['"', "'"], '', $cols[$idx])) : '';
                };

                $saram      = $getVal('SARAM');
                $cpf        = $getVal('CPF');
                $nome       = mb_strtoupper($getVal('NOME'));
                $nomeGuerra = mb_strtoupper($getVal('NOME_GUERRA'));
                $posto      = $getVal('POSTO');
                $quadro     = mb_strtoupper($getVal('QUADRO'));
                $esp        = mb_strtoupper($getVal('ESPECIALIDADE'));
                $setor      = mb_strtoupper($getVal('SETOR'));
                $divisao    = mb_strtoupper($getVal('DIVISAO'));
                $cat        = strtolower($getVal('CATEGORIA'));
                $vinculo    = mb_strtoupper($getVal('VINCULO'));
                $tel        = $getVal('TELEFONE');

                if ($nome === '') continue;

                // Deriva nome de guerra se não informado
                if ($nomeGuerra === '') {
                    $partes = explode(' ', $nome);
                    $nomeGuerra = end($partes);
                }

                // Normalização automática de posto e categoria
                if ($posto === '$2' || $posto === 'S 2') $posto = 'S2';
                if ($posto === '' && $quadro === 'QSS') $posto = 'SO';

                if ($cat === '' || !in_array($cat, [CAT_GRADUADO, CAT_PRACA, CAT_SPPF, CAT_SPTF, CAT_INELIGIVEL], true)) {
                    if (in_array($posto, ['Cel', 'Ten Cel', 'Maj', 'Cap', '1º Ten', '2º Ten'], true)) {
                        $cat = CAT_INELIGIVEL;
                        $vinculo = 'MILITAR_CARREIRA';
                    } elseif (in_array($posto, ['SO', '1S', '2S', '3S'], true)) {
                        $cat = CAT_GRADUADO;
                        $vinculo = 'MILITAR_CARREIRA';
                    } elseif (in_array($posto, ['Cb', 'CB', 'S1', 'S2'], true)) {
                        $cat = CAT_PRACA;
                        $vinculo = 'MILITAR_CARREIRA';
                    } elseif ($posto === 'CV' || $posto === 'CIVIL') {
                        if (str_starts_with($saram, '85')) {
                            $cat = CAT_SPTF;
                            $vinculo = 'CIVIL_TEMPORARIO';
                        } else {
                            $cat = CAT_SPPF;
                            $vinculo = 'CIVIL_PERMANENTE';
                        }
                    } else {
                        $cat = CAT_INELIGIVEL;
                    }
                }

                // Verifica se já existe por SARAM ou CPF
                $existenteId = null;
                if ($saram !== '') {
                    $stmtCheck = $pdo->prepare('SELECT id FROM efetivo WHERE saram = :s LIMIT 1');
                    $stmtCheck->execute([':s' => $saram]);
                    $existenteId = $stmtCheck->fetchColumn();
                }
                if (!$existenteId && $cpf !== '') {
                    $cpfNumeros = preg_replace('/\D/', '', $cpf);
                    $stmtCheck = $pdo->prepare('SELECT id FROM efetivo WHERE cpf_hash = :h LIMIT 1');
                    $stmtCheck->execute([':h' => hash('sha256', $cpfNumeros)]);
                    $existenteId = $stmtCheck->fetchColumn();
                }

                $dadosSalvar = [
                    'id'             => $existenteId ?: null,
                    'saram'          => $saram,
                    'cpf'            => $cpf,
                    'nome'           => $nome,
                    'nome_guerra'    => $nomeGuerra,
                    'posto'          => $posto,
                    'quadro'         => $quadro,
                    'especialidade'  => $esp,
                    'setor'          => $setor,
                    'divisao_sigla'  => $divisao,
                    'categoria'      => $cat,
                    'tipo_vinculo'   => $vinculo ?: 'MILITAR_CARREIRA',
                    'telefone'       => $tel,
                    'ativo'          => 1
                ];

                Efetivo::salvarOuAtualizar($dadosSalvar);
                $totalProcessados++;
                if ($existenteId) {
                    $totalAtualizados++;
                } else {
                    $totalInseridos++;
                }
            }

            AuditoriaService::log('IMPORTACAO_CSV_EFETIVO', [
                'processados' => $totalProcessados,
                'novos'       => $totalInseridos,
                'atualizados' => $totalAtualizados
            ]);

            flash_message('sucesso', "Importação concluída! {$totalProcessados} registros processados ({$totalInseridos} novos cadastrados, {$totalAtualizados} atualizados).", 'success');
        }

        header('Location: /index.php?r=admin/efetivo');
        exit;
    }

    /**
     * Gestão de Chefias, Atribuições e Perfis Regimentais
     */
    public function chefias(): void {
        AuthMiddleware::handle();
        RoleMiddleware::handle(PERFIL_ADMIN);

        $pdo = Database::getConnection();

        // 1. Divisões e Assessorias com seus Chefes atuais
        $divisoes = $pdo->query('
            SELECT d.*, 
                   u.id as usuario_chefe_id, u.login as usuario_login,
                   e.id as efetivo_id, e.saram, e.nome, e.nome_guerra, e.posto, e.quadro
            FROM divisoes_assessorias d
            LEFT JOIN usuarios u ON u.id = d.chefe_usuario_id
            LEFT JOIN efetivo e ON e.id = u.efetivo_id
            ORDER BY d.tipo, d.sigla
        ')->fetchAll();

        // 2. Oficiais e Chefes elegíveis para seleção
        $oficiais = Efetivo::listarOficiaisEChefes();

        // 3. Avaliadores TACF atuais (perfil ED_FISICA) - Comissão de Educação Física
        $avaliadoresTacf = $pdo->query('
            SELECT u.id as usuario_id, u.login, u.ativo as usuario_ativo, e.*
            FROM usuarios u
            INNER JOIN efetivo e ON e.id = u.efetivo_id
            WHERE u.perfil = "ED_FISICA" AND u.ativo = 1
            ORDER BY e.posto, e.nome
        ')->fetchAll();
        $avaliadorTacf = $avaliadoresTacf[0] ?? null;

        // 4. Membros da Direção Superior (Fase 4)
        $direcaoSuperior = $pdo->query('
            SELECT u.id as usuario_id, e.*
            FROM usuarios u
            INNER JOIN efetivo e ON e.id = u.efetivo_id
            WHERE u.perfil = "DIRECAO_SUPERIOR"
            ORDER BY e.posto, e.nome
        ')->fetchAll();

        // 5. Presidência da COMARA (Fase 5)
        $presidente = $pdo->query('
            SELECT u.id as usuario_id, e.*
            FROM usuarios u
            INNER JOIN efetivo e ON e.id = u.efetivo_id
            WHERE u.perfil = "PRESIDENTE"
            LIMIT 1
        ')->fetch();

        // 6. Resumo de vinculação de Chefes Diretos
        $statsChefesDiretos = $pdo->query('
            SELECT 
                COUNT(*) as total_efetivo,
                SUM(CASE WHEN chefe_direto_id IS NOT NULL THEN 1 ELSE 0 END) as com_chefe,
                SUM(CASE WHEN chefe_direto_id IS NULL AND categoria IN ("graduado", "praca") THEN 1 ELSE 0 END) as sem_chefe_avaliar
            FROM efetivo
            WHERE ativo = 1
        ')->fetch();

        // 7. Administradores Atuais do Sistema
        $administradores = $pdo->query('
            SELECT u.id as usuario_id, u.login, u.perfil, u.ativo,
                   e.id as efetivo_id, e.nome, e.nome_guerra, e.posto, e.saram, e.setor, e.divisao_sigla
            FROM usuarios u
            LEFT JOIN efetivo e ON e.id = u.efetivo_id
            WHERE u.perfil = "ADMIN"
            ORDER BY u.id ASC
        ')->fetchAll();

        // 8. Operadores de Recursos Humanos (RH)
        $operadoresRh = $pdo->query('
            SELECT u.id as usuario_id, u.login, u.perfil, u.ativo,
                   e.id as efetivo_id, e.nome, e.nome_guerra, e.posto, e.saram, e.setor, e.divisao_sigla
            FROM usuarios u
            LEFT JOIN efetivo e ON e.id = u.efetivo_id
            WHERE u.perfil = "RH"
            ORDER BY u.id ASC
        ')->fetchAll();

        // 9. Todo o Efetivo ativo para seleção de novos administradores e operadores de RH
        $todoEfetivo = $pdo->query('
            SELECT id, nome, nome_guerra, posto, saram, setor, divisao_sigla 
            FROM efetivo 
            WHERE ativo = 1 
            ORDER BY posto, nome_guerra
        ')->fetchAll();

        require_once ROOT_PATH . '/views/admin/chefias.php';
    }

    /**
     * Salva as atribuições de chefias e perfis
     */
    public function salvarChefias(): void {
        AuthMiddleware::handle();
        RoleMiddleware::handle(PERFIL_ADMIN);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
                flash_message('erro', 'Token CSRF inválido.', 'danger');
                header('Location: /index.php?r=admin/chefias');
                exit;
            }

            $pdo = Database::getConnection();
            $secao = $_POST['secao'] ?? '';

            if ($secao === 'chefes_divisao') {
                $chefias = $_POST['chefe_divisao'] ?? []; // sigla => efetivo_id
                $vincularSubordinados = isset($_POST['vincular_subordinados']);

                foreach ($chefias as $sigla => $efetivoId) {
                    $efetivoId = (int)$efetivoId;
                    if ($efetivoId <= 0) continue;

                    // Busca ou cria usuário para o efetivo
                    $stmtU = $pdo->prepare('SELECT id FROM usuarios WHERE efetivo_id = :ef LIMIT 1');
                    $stmtU->execute([':ef' => $efetivoId]);
                    $userId = $stmtU->fetchColumn();

                    if (!$userId) {
                        $ef = Efetivo::getPorId($efetivoId);
                        $login = $ef['saram'] ?: ($ef['cpf'] ?: 'chefe_' . strtolower($sigla));
                        $pdo->prepare('
                            INSERT INTO usuarios (login, senha_hash, efetivo_id, perfil, orgao_chefe_sigla, ativo)
                            VALUES (:l, :s, :ef, "CHEFE_DIV_ASSESS", :sigla, 1)
                        ')->execute([
                            ':l'     => $login,
                            ':s'     => password_hash('padrao@2026', PASSWORD_BCRYPT),
                            ':ef'    => $efetivoId,
                            ':sigla' => $sigla
                        ]);
                        $userId = (int)$pdo->lastInsertId();
                    } else {
                        $pdo->prepare('
                            UPDATE usuarios 
                            SET perfil = "CHEFE_DIV_ASSESS", orgao_chefe_sigla = :sigla 
                            WHERE id = :u
                        ')->execute([':sigla' => $sigla, ':u' => $userId]);
                    }

                    // Atualiza chefe_usuario_id na divisão/assessoria
                    $pdo->prepare('UPDATE divisoes_assessorias SET chefe_usuario_id = :u WHERE sigla = :sigla')
                        ->execute([':u' => $userId, ':sigla' => $sigla]);

                    // Vincula subordinados da divisão se solicitado
                    if ($vincularSubordinados) {
                        Efetivo::vincularChefeDiretoEmLote($sigla, $efetivoId);
                    }
                }

                AuditoriaService::log('CHEFIAS_DIVISAO_ATUALIZADAS', ['qtd' => count($chefias)]);
                flash_message('sucesso', 'Chefias de Divisão e Assessoria atualizadas com sucesso!', 'success');

            } elseif ($secao === 'tacf_adicionar') {
                $efetivoId = (int)($_POST['tacf_efetivo_id'] ?? 0);
                if ($efetivoId > 0) {
                    $militar = Efetivo::getPorId($efetivoId);
                    if ($militar) {
                        $stmtU = $pdo->prepare('SELECT id FROM usuarios WHERE efetivo_id = :ef LIMIT 1');
                        $stmtU->execute([':ef' => $efetivoId]);
                        $userId = $stmtU->fetchColumn();

                        if ($userId) {
                            $pdo->prepare('UPDATE usuarios SET perfil = "ED_FISICA", ativo = 1 WHERE id = :u')->execute([':u' => $userId]);
                        } else {
                            $login = $militar['saram'] ?: preg_replace('/\D/', '', (string)$militar['cpf']);
                            $hashPadrao = password_hash('padrao@2026', PASSWORD_BCRYPT);
                            $pdo->prepare('INSERT INTO usuarios (login, senha_hash, efetivo_id, perfil, ativo) VALUES (:l, :s, :ef, "ED_FISICA", 1)')
                                ->execute([':l' => $login, ':s' => $hashPadrao, ':ef' => $efetivoId]);
                            $userId = (int)$pdo->lastInsertId();
                        }

                        AuditoriaService::log('AVALIADOR_TACF_ADICIONADO', [
                            'efetivo_id'    => $efetivoId,
                            'usuario_id'    => $userId,
                            'atribuido_por' => AuthManager::user()['id']
                        ]);
                        $nome = (!empty($militar['posto']) ? $militar['posto'] . ' ' : '') . ($militar['nome_guerra'] ?: $militar['nome']);
                        flash_message('sucesso', "Avaliador do TACF adicionado com sucesso: {$nome}!", 'success');
                    }
                }

            } elseif ($secao === 'tacf_remover') {
                $removerUserId = (int)($_POST['usuario_id'] ?? 0);
                if ($removerUserId > 0) {
                    $stmtU = $pdo->prepare('SELECT id, login, efetivo_id FROM usuarios WHERE id = :u AND perfil = "ED_FISICA" LIMIT 1');
                    $stmtU->execute([':u' => $removerUserId]);
                    $usr = $stmtU->fetch();

                    if ($usr) {
                        $pdo->prepare('UPDATE usuarios SET perfil = "ELEITOR" WHERE id = :u')->execute([':u' => $removerUserId]);
                        AuditoriaService::log('AVALIADOR_TACF_REMOVIDO', [
                            'usuario_id'   => $removerUserId,
                            'efetivo_id'   => $usr['efetivo_id'],
                            'removido_por' => AuthManager::user()['id']
                        ]);
                        flash_message('sucesso', "Avaliador removido da comissão do TACF com sucesso!", 'success');
                    }
                }

            } elseif ($secao === 'tacf') {
                $efetivoId = (int)($_POST['tacf_efetivo_id'] ?? 0);
                if ($efetivoId > 0) {
                    $militar = Efetivo::getPorId($efetivoId);
                    if ($militar) {
                        $stmtU = $pdo->prepare('SELECT id FROM usuarios WHERE efetivo_id = :ef LIMIT 1');
                        $stmtU->execute([':ef' => $efetivoId]);
                        $userId = $stmtU->fetchColumn();

                        if ($userId) {
                            $pdo->prepare('UPDATE usuarios SET perfil = "ED_FISICA", ativo = 1 WHERE id = :u')->execute([':u' => $userId]);
                        } else {
                            $login = $militar['saram'] ?: preg_replace('/\D/', '', (string)$militar['cpf']);
                            $hashPadrao = password_hash('padrao@2026', PASSWORD_BCRYPT);
                            $pdo->prepare('INSERT INTO usuarios (login, senha_hash, efetivo_id, perfil, ativo) VALUES (:l, :s, :ef, "ED_FISICA", 1)')
                                ->execute([':l' => $login, ':s' => $hashPadrao, ':ef' => $efetivoId]);
                            $userId = (int)$pdo->lastInsertId();
                        }

                        AuditoriaService::log('AVALIADOR_TACF_ATRIBUIDO', ['efetivo_id' => $efetivoId]);
                        flash_message('sucesso', 'Responsável pelo TACF (Educação Física) atualizado com sucesso!', 'success');
                    }
                }
            } elseif ($secao === 'direcao_superior') {
                $membros = $_POST['membros_direcao'] ?? []; // array de efetivo_id

                // Remove anteriores mantendo ADMIN e PRESIDENTE
                $pdo->exec('UPDATE usuarios SET perfil = "ELEITOR" WHERE perfil = "DIRECAO_SUPERIOR"');

                foreach ($membros as $efId) {
                    $efId = (int)$efId;
                    if ($efId <= 0) continue;
                    $pdo->prepare('
                        UPDATE usuarios 
                        SET perfil = "DIRECAO_SUPERIOR" 
                        WHERE efetivo_id = :ef AND perfil NOT IN ("ADMIN", "PRESIDENTE", "CHEFE_DIV_ASSESS")
                    ')->execute([':ef' => $efId]);
                }

                AuditoriaService::log('DIRECAO_SUPERIOR_ATUALIZADA', ['membros' => $membros]);
                flash_message('sucesso', 'Composição da Direção Superior (Fase 4) atualizada com sucesso!', 'success');

            } elseif ($secao === 'presidente') {
                $efetivoId = (int)($_POST['presidente_efetivo_id'] ?? 0);
                if ($efetivoId > 0) {
                    $pdo->exec('UPDATE usuarios SET perfil = "ELEITOR" WHERE perfil = "PRESIDENTE"');
                    $pdo->prepare('UPDATE usuarios SET perfil = "PRESIDENTE" WHERE efetivo_id = :ef')->execute([':ef' => $efetivoId]);

                    AuditoriaService::log('PRESIDENTE_ATRIBUIDO', ['efetivo_id' => $efetivoId]);
                    flash_message('sucesso', 'Presidência da COMARA (Fase 5 / Minerva) atualizada com sucesso!', 'success');
                }

            } elseif ($secao === 'vincular_setor') {
                $setor = trim(mb_strtoupper($_POST['setor'] ?? ''));
                $chefeId = (int)($_POST['chefe_setor_id'] ?? 0);

                if ($setor !== '' && $chefeId > 0) {
                    $afetados = Efetivo::vincularChefeDiretoPorSetor($setor, $chefeId);
                    AuditoriaService::log('CHEFE_SETOR_VINCULADO', ['setor' => $setor, 'chefe_id' => $chefeId, 'afetados' => $afetados]);
                    flash_message('sucesso', "{$afetados} militares do setor {$setor} vinculados com sucesso ao chefe selecionado!", 'success');
                }

            } elseif ($secao === 'administrador_adicionar') {
                $efetivoId = (int)($_POST['admin_efetivo_id'] ?? 0);
                if ($efetivoId > 0) {
                    $militar = Efetivo::getPorId($efetivoId);
                    if ($militar) {
                        $stmtU = $pdo->prepare('SELECT id FROM usuarios WHERE efetivo_id = :ef LIMIT 1');
                        $stmtU->execute([':ef' => $efetivoId]);
                        $userId = $stmtU->fetchColumn();

                        if ($userId) {
                            $pdo->prepare('UPDATE usuarios SET perfil = "ADMIN" WHERE id = :u')->execute([':u' => $userId]);
                        } else {
                            $login = $militar['saram'] ?: preg_replace('/\D/', '', (string)$militar['cpf']);
                            $hashPadrao = password_hash('padrao@2026', PASSWORD_BCRYPT);
                            $pdo->prepare('INSERT INTO usuarios (login, senha_hash, efetivo_id, perfil, ativo) VALUES (:l, :s, :ef, "ADMIN", 1)')
                                ->execute([':l' => $login, ':s' => $hashPadrao, ':ef' => $efetivoId]);
                            $userId = (int)$pdo->lastInsertId();
                        }

                        AuditoriaService::log('ADMINISTRADOR_ATRIBUIDO', [
                            'efetivo_id'    => $efetivoId,
                            'usuario_id'    => $userId,
                            'atribuido_por' => AuthManager::user()['id']
                        ]);

                        $nome = (!empty($militar['posto']) ? $militar['posto'] . ' ' : '') . ($militar['nome_guerra'] ?: $militar['nome']);
                        flash_message('sucesso', "Função de Administrador concedida com sucesso para {$nome}!", 'success');
                    }
                }

            } elseif ($secao === 'administrador_remover') {
                $removerUserId = (int)($_POST['usuario_id'] ?? 0);
                if ($removerUserId > 0) {
                    $stmtU = $pdo->prepare('SELECT id, login, efetivo_id FROM usuarios WHERE id = :u LIMIT 1');
                    $stmtU->execute([':u' => $removerUserId]);
                    $usr = $stmtU->fetch();

                    if ($usr && $usr['login'] === 'admin') {
                        flash_message('erro', 'A conta principal do sistema (admin) não pode ter o perfil de administrador revogado.', 'danger');
                    } elseif ($usr) {
                        $pdo->prepare('UPDATE usuarios SET perfil = "ELEITOR" WHERE id = :u')->execute([':u' => $removerUserId]);
                        AuditoriaService::log('ADMINISTRADOR_REMOVIDO', [
                            'usuario_id'   => $removerUserId,
                            'removido_por' => AuthManager::user()['id']
                        ]);
                        flash_message('sucesso', 'Permissão de Administrador revogada com sucesso.', 'success');
                    }
                }
            } elseif ($secao === 'rh_adicionar') {
                $efetivoId = (int)($_POST['rh_efetivo_id'] ?? 0);
                if ($efetivoId > 0) {
                    $militar = Efetivo::getPorId($efetivoId);
                    if ($militar) {
                        $stmtU = $pdo->prepare('SELECT id FROM usuarios WHERE efetivo_id = :ef LIMIT 1');
                        $stmtU->execute([':ef' => $efetivoId]);
                        $userId = $stmtU->fetchColumn();

                        if ($userId) {
                            $pdo->prepare('UPDATE usuarios SET perfil = "RH", ativo = 1 WHERE id = :u')->execute([':u' => $userId]);
                        } else {
                            $login = $militar['saram'] ?: preg_replace('/\D/', '', (string)$militar['cpf']);
                            $hashPadrao = password_hash('padrao@2026', PASSWORD_BCRYPT);
                            $pdo->prepare('INSERT INTO usuarios (login, senha_hash, efetivo_id, perfil, ativo) VALUES (:l, :s, :ef, "RH", 1)')
                                ->execute([':l' => $login, ':s' => $hashPadrao, ':ef' => $efetivoId]);
                        }

                        AuditoriaService::log('OPERADOR_RH_ADICIONADO', [
                            'efetivo_id'   => $efetivoId,
                            'nome'         => $militar['nome_guerra'],
                            'adicionado_por' => AuthManager::user()['id']
                        ]);
                        flash_message('sucesso', "Permissão de Recursos Humanos (RH) concedida com sucesso para {$militar['posto']} {$militar['nome_guerra']}!", 'success');
                    }
                }
            } elseif ($secao === 'rh_remover') {
                $removerUserId = (int)($_POST['usuario_id'] ?? 0);
                if ($removerUserId > 0) {
                    $stmtU = $pdo->prepare('SELECT id, login, efetivo_id FROM usuarios WHERE id = :u LIMIT 1');
                    $stmtU->execute([':u' => $removerUserId]);
                    $usr = $stmtU->fetch();

                    if ($usr) {
                        $pdo->prepare('UPDATE usuarios SET perfil = "ELEITOR" WHERE id = :u')->execute([':u' => $removerUserId]);
                        AuditoriaService::log('OPERADOR_RH_REMOVIDO', [
                            'usuario_id'   => $removerUserId,
                            'removido_por' => AuthManager::user()['id']
                        ]);
                        flash_message('sucesso', 'Permissão de Recursos Humanos (RH) revogada com sucesso.', 'success');
                    }
                }
            }
        }

        header('Location: /index.php?r=admin/chefias');
        exit;
    }

    /**
     * Redefinição administrativa da senha de um militar/servidor para o padrão institucional padrao@2026
     */
    public function resetarSenhaUsuario(): void {
        AuthMiddleware::handle();
        RoleMiddleware::handle(PERFIL_ADMIN);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
                flash_message('erro', 'Token CSRF inválido.', 'danger');
                header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/index.php?r=admin/efetivo'));
                exit;
            }

            $efetivoId = (int)($_POST['efetivo_id'] ?? 0);
            $usuarioId = (int)($_POST['usuario_id'] ?? 0);
            $pdo = Database::getConnection();

            if ($efetivoId > 0) {
                $stmt = $pdo->prepare('
                    SELECT u.id, u.login, e.nome_guerra, e.posto 
                    FROM usuarios u 
                    JOIN efetivo e ON e.id = u.efetivo_id 
                    WHERE e.id = :ef LIMIT 1
                ');
                $stmt->execute([':ef' => $efetivoId]);
                $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            } elseif ($usuarioId > 0) {
                $stmt = $pdo->prepare('
                    SELECT u.id, u.login, e.nome_guerra, e.posto 
                    FROM usuarios u 
                    LEFT JOIN efetivo e ON e.id = u.efetivo_id 
                    WHERE u.id = :u LIMIT 1
                ');
                $stmt->execute([':u' => $usuarioId]);
                $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $usuario = null;
            }

            if (!$usuario) {
                // Se o militar no efetivo ainda não tem registro em usuarios, provisiona a conta com a senha padrao
                if ($efetivoId > 0) {
                    $militar = Efetivo::getPorId($efetivoId);
                    if ($militar) {
                        $login = $militar['saram'] ?: preg_replace('/\D/', '', (string)$militar['cpf']);
                        if ($login) {
                            $hashPadrao = password_hash('padrao@2026', PASSWORD_BCRYPT);
                            $pdo->prepare('INSERT INTO usuarios (efetivo_id, login, senha_hash, perfil) VALUES (:ef, :log, :sh, "ELEITOR")')
                                ->execute([':ef' => $efetivoId, ':log' => $login, ':sh' => $hashPadrao]);

                            AuditoriaService::log('CONTA_PROVISIONADA_SENHA_PADRAO', ['efetivo_id' => $efetivoId, 'login' => $login]);
                            $nome = (!empty($militar['posto']) ? $militar['posto'] . ' ' : '') . ($militar['nome_guerra'] ?: $login);
                            flash_message('sucesso', "Conta de {$nome} provisionada com sucesso com a senha padrao@2026!", 'success');
                            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/index.php?r=admin/efetivo'));
                            exit;
                        }
                    }
                }
                flash_message('erro', 'Usuário não localizado para redefinição de senha.', 'danger');
                header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/index.php?r=admin/efetivo'));
                exit;
            }

            $hashPadrao = password_hash('padrao@2026', PASSWORD_BCRYPT);
            $pdo->prepare('UPDATE usuarios SET senha_hash = :h WHERE id = :id')->execute([
                ':h'  => $hashPadrao,
                ':id' => $usuario['id']
            ]);

            AuditoriaService::log('SENHA_RESETADA_PELO_ADMIN', [
                'admin_id'   => AuthManager::user()['id'],
                'usuario_id' => $usuario['id'],
                'login'      => $usuario['login']
            ]);

            $nome = (!empty($usuario['posto']) ? $usuario['posto'] . ' ' : '') . ($usuario['nome_guerra'] ?: $usuario['login']);
            flash_message('sucesso', "A senha de {$nome} foi redefinida com sucesso para o padrão: padrao@2026", 'success');
        }

        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/index.php?r=admin/efetivo'));
        exit;
    }
}

