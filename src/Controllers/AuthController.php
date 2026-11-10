<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../Auth/AuthManager.php';

class AuthController {

    public function login(): void {
        if (AuthManager::check()) {
            header('Location: ' . (AuthManager::requiresPasswordChange() ? '/index.php?r=alterar_senha' : '/index.php?r=dashboard'));
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST['csrf_token'] ?? '';
            if (!verify_csrf_token($token)) {
                flash_message('erro', 'Token de segurança CSRF inválido. Tente novamente.', 'danger');
                header('Location: /index.php?r=login');
                exit;
            }

            $login = trim($_POST['login'] ?? '');
            $senha = trim($_POST['senha'] ?? '');

            $res = AuthManager::login($login, $senha);
            if ($res['sucesso']) {
                flash_message('sucesso', 'Login realizado com sucesso. Bem-vindo(a)!', 'success');
                header('Location: ' . (AuthManager::requiresPasswordChange() ? '/index.php?r=alterar_senha' : '/index.php?r=dashboard'));
                exit;
            } else {
                flash_message('erro', $res['mensagem'], 'danger');
                header('Location: /index.php?r=login');
                exit;
            }
        }

        require_once ROOT_PATH . '/views/auth/login.php';
    }

    public function logout(): void {
        AuthManager::logout();
        flash_message('sucesso', 'Sessão encerrada com segurança.', 'info');
        header('Location: /index.php?r=login');
        exit;
    }

    /**
     * Permite a qualquer usuário logado alterar sua própria senha
     * ou restaurá-la para a senha padrão (padrao@2026)
     */
    public function alterarSenha(): void {
        AuthMiddleware::handle();
        $user = AuthManager::user();
        $trocaObrigatoria = AuthManager::requiresPasswordChange();
        $destino = $trocaObrigatoria ? '/index.php?r=alterar_senha' : '/index.php?r=dashboard';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
                flash_message('erro', 'Token de segurança CSRF inválido. Tente novamente.', 'danger');
                header('Location: ' . $destino);
                exit;
            }

            $pdo = Database::getConnection();
            $acao = $_POST['acao'] ?? 'alterar';

            // Opção de reset restrita unicamente a administradores
            if ($acao === 'reset_padrao') {
                if ($trocaObrigatoria || !AuthManager::hasRole(PERFIL_ADMIN)) {
                    flash_message('erro', 'Apenas o Administrador do Sistema pode resetar senhas para o padrão.', 'danger');
                    header('Location: ' . $destino);
                    exit;
                }

                $hashPadrao = password_hash('padrao@2026', PASSWORD_BCRYPT);
                $stmt = $pdo->prepare('UPDATE usuarios SET senha_hash = :h WHERE id = :id');
                $stmt->execute([':h' => $hashPadrao, ':id' => $user['id']]);

                AuditoriaService::log('SENHA_REDEFINIDA_PADRAO', [
                    'usuario_id' => $user['id'],
                    'login'      => $user['login']
                ]);

                flash_message('sucesso', 'Sua senha foi redefinida com sucesso para o padrão inicial: padrao@2026', 'success');
                header('Location: ' . $destino);
                exit;
            }

            // Opção 2: Definir nova senha customizada
            $senhaAtual = (string)($_POST['senha_atual'] ?? '');
            $novaSenha = (string)($_POST['nova_senha'] ?? '');
            $confirmaSenha = (string)($_POST['confirma_senha'] ?? '');

            // Busca hash atual do usuário no banco
            $stmtHash = $pdo->prepare('SELECT senha_hash FROM usuarios WHERE id = :id LIMIT 1');
            $stmtHash->execute([':id' => $user['id']]);
            $hashAtual = (string)$stmtHash->fetchColumn();

            // Valida senha atual se já tiver senha definida
            if ($hashAtual !== '' && !password_verify($senhaAtual, $hashAtual)) {
                flash_message('erro', 'Senha atual incorreta. Digite sua senha atual para autorizar a alteração.', 'danger');
                header('Location: ' . $destino);
                exit;
            }

            if (strlen($novaSenha) < 6) {
                flash_message('erro', 'A nova senha deve ter no mínimo 6 caracteres.', 'warning');
                header('Location: ' . $destino);
                exit;
            }

            if ($novaSenha === 'padrao@2026' || ($hashAtual !== '' && password_verify($novaSenha, $hashAtual))) {
                flash_message('erro', 'Escolha uma senha pessoal diferente da senha inicial e da senha anterior.', 'warning');
                header('Location: ' . $destino);
                exit;
            }

            if ($novaSenha !== $confirmaSenha) {
                flash_message('erro', 'A confirmação de senha não confere com a nova senha digitada.', 'warning');
                header('Location: ' . $destino);
                exit;
            }

            $novoHash = password_hash($novaSenha, PASSWORD_BCRYPT);
            $stmtUp = $pdo->prepare('UPDATE usuarios SET senha_hash = :h WHERE id = :id');
            $stmtUp->execute([':h' => $novoHash, ':id' => $user['id']]);

            AuditoriaService::log('SENHA_CUSTOMIZADA_ALTERADA', [
                'usuario_id' => $user['id'],
                'login'      => $user['login']
            ]);

            unset($_SESSION['senha_hash_conferido'], $_SESSION['troca_senha_obrigatoria']);
            session_regenerate_id(true);
            flash_message('sucesso', 'Sua senha foi alterada com sucesso!', 'success');
            header('Location: ' . $destino);
            exit;
        }

        if ($trocaObrigatoria) {
            require ROOT_PATH . '/views/auth/primeiro_acesso.php';
            return;
        }
        header('Location: /index.php?r=dashboard');
        exit;
    }

    /**
     * Endpoint desativado por diretriz de segurança institucional.
     * Redefinição de senhas é realizada exclusivamente pelo Administrador do Sistema / Setor de TI.
     */
    public function resetSenhaPublico(): void {
        flash_message('erro', 'Por diretriz de segurança, a redefinição de senhas é realizada exclusivamente pelo Administrador do Sistema. Entre em contato com o Setor de TI.', 'warning');
        header('Location: /index.php?r=login');
        exit;
    }
}
