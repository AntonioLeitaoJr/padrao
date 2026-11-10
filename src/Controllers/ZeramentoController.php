<?php
declare(strict_types=1);

require_once __DIR__.'/../../config/config.php';
require_once __DIR__.'/../Middleware/AuthMiddleware.php';
require_once __DIR__.'/../Middleware/RoleMiddleware.php';
require_once __DIR__.'/../Services/ZeramentoService.php';

/** Confirmação em duas etapas para limpeza operacional administrada pela STI. */
final class ZeramentoController {
    public function executar(): void {
        AuthMiddleware::handle();
        RoleMiddleware::handle(PERFIL_ADMIN);
        $destino='/index.php?r=admin/backups';
        if (($_SERVER['REQUEST_METHOD']??'GET')!=='POST') {
            http_response_code(405);
            header('Location: '.$destino);
            exit;
        }
        try {
            if (!verify_csrf_token($_POST['csrf_token']??'')) throw new RuntimeException('Token CSRF inválido.');
            if (!ZeramentoService::habilitado()) throw new RuntimeException('Funcionalidade não liberada pela STI.');
            $user=AuthManager::user();
            $adminId=(int)($user['id']??0);
            $acao=(string)($_POST['acao']??'');
            if ($acao==='preparar') {
                $resumo=ZeramentoService::resumo();
                if ($resumo['homologados']!==0) {
                    throw new RuntimeException('Há pleito homologado; o zeramento não pode ser executado.');
                }
                if ($resumo['admins_ativos']<1) throw new RuntimeException('Não existe conta ADMIN ativa e recuperável.');
                $_SESSION['zeramento_preparo']=[
                    'token'=>bin2hex(random_bytes(32)),
                    'usuario_id'=>$adminId,
                    'expira'=>time()+300,
                    'fingerprint'=>$resumo['fingerprint']
                ];
                flash_message('sucesso','Preparação concluída. Confira os dados e confirme dentro de cinco minutos.','warning');
            } elseif ($acao==='confirmar') {
                $preparo=$_SESSION['zeramento_preparo']??null;
                unset($_SESSION['zeramento_preparo']);
                if (!is_array($preparo)
                    || (int)$preparo['usuario_id']!==$adminId
                    || (int)$preparo['expira']<time()
                    || !hash_equals((string)$preparo['token'],(string)($_POST['token_reset']??''))) {
                    throw new RuntimeException('Confirmação expirada ou inválida. Prepare a operação novamente.');
                }
                if ((string)($_POST['confirmacao']??'')!=='ZERAR BASE COMARA'
                    || (string)($_POST['ciente']??'')!=='1') {
                    throw new RuntimeException('Confirmação explícita não fornecida.');
                }
                $senha=(string)($_POST['senha_admin']??'');
                $pdo=Database::getConnection();
                $stmt=$pdo->prepare("SELECT senha_hash FROM usuarios WHERE id=? AND perfil='ADMIN' AND ativo=1 LIMIT 1");
                $stmt->execute([$adminId]);
                $hash=(string)$stmt->fetchColumn();
                if ($hash==='' || !password_verify($senha,$hash)) {
                    throw new RuntimeException('Senha de administrador incorreta.');
                }
                $result=ZeramentoService::executar($adminId,(string)$preparo['fingerprint']);
                // Obriga autenticar-se novamente, pois vínculos de efetivo foram apagados.
                $_SESSION=[];
                session_regenerate_id(true);
                flash_message('sucesso','Base operacional zerada. Backup protegido: '.$result['arquivo'].'. Faça login novamente.','success');
                header('Location: /index.php?r=login');
                exit;
            } else {
                throw new RuntimeException('Ação de zeramento desconhecida.');
            }
        } catch (Throwable $e) {
            error_log('[ZERAMENTO] '.$e->getMessage());
            $mensagem=$e instanceof RuntimeException ? $e->getMessage() : 'Falha ao zerar a base. Nenhuma limpeza deve ser presumida; consulte a STI.';
            flash_message('erro',$mensagem,'danger');
        }
        header('Location: '.$destino);
        exit;
    }
}
