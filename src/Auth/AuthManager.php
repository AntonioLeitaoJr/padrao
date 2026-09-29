<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/ldap.php';
require_once __DIR__ . '/../Services/AuditoriaService.php';

/**
 * Gerenciador de Autenticação e Sessão
 * Integração Active Directory (LDAP) + Base Local + RBAC
 */
class AuthManager {

    public static function check(): bool {
        return !empty($_SESSION['user']['id']);
    }

    public static function user(): ?array {
        return $_SESSION['user'] ?? null;
    }

    public static function hasRole(string|array $roles): bool {
        if (!self::check()) {
            return false;
        }

        $userRole = $_SESSION['user']['perfil'] ?? '';

        // Administrador tem acesso a todas as áreas
        if ($userRole === PERFIL_ADMIN) {
            return true;
        }

        if (is_array($roles)) {
            return in_array($userRole, $roles, true);
        }

        return $userRole === $roles;
    }

    /**
     * Verifica se o usuário autenticado é Oficial da FAB ou Administrador.
     * Praças (Cb, S1, S2), Civis (CV), Sargentos (1S, 2S, 3S) e Suboficiais (SO) retornam FALSE.
     */
    public static function isOficial(?array $user = null): bool {
        if ($user === null) {
            if (!self::check()) {
                return false;
            }
            $user = self::user();
        }

        if (!$user) {
            return false;
        }

        // Administrador do sistema sempre tem acesso
        if (($user['perfil'] ?? '') === PERFIL_ADMIN) {
            return true;
        }

        $posto = trim((string)($user['posto'] ?? ''));
        if ($posto === '') {
            return false;
        }

        return in_array($posto, POSTOS_OFICIAIS, true);
    }

    /**
     * Verifica se o usuário autenticado tem prerrogativa para avaliar na Fase 1.
     * Regra: Exclusivamente Oficiais nos postos de Tenente ou Capitão (ou Administrador do sistema).
     */
    public static function isAvaliadorFase1(?array $user = null): bool {
        if ($user === null) {
            if (!self::check()) {
                return false;
            }
            $user = self::user();
        }

        if (!$user) {
            return false;
        }

        if (($user['perfil'] ?? '') === PERFIL_ADMIN) {
            return true;
        }

        $posto = trim((string)($user['posto'] ?? ''));
        if ($posto === '') {
            return false;
        }

        return in_array($posto, POSTOS_AVALIADORES_FASE1, true);
    }

    /**
     * Verifica se o usuário autenticado integra o Estado-Maior (Majores, Tenentes-Coronéis e Coronéis)
     * com prerrogativa de consulta das fichas e validação na Fase 4.
     */
    public static function isEstadoMaior(?array $user = null): bool {
        if ($user === null) {
            if (!self::check()) {
                return false;
            }
            $user = self::user();
        }

        if (!$user) {
            return false;
        }

        if (in_array(($user['perfil'] ?? ''), [PERFIL_ADMIN, PERFIL_PRESIDENTE, PERFIL_DIRECAO_SUPERIOR], true)) {
            return true;
        }

        $posto = trim((string)($user['posto'] ?? ''));
        return in_array($posto, POSTOS_ESTADO_MAIOR, true);
    }

    /**
     * Verifica se o usuário autenticado pode atuar como selecionador na Fase 2
     * (Chefe de Divisão ou Representante Unificado das Assessorias).
     */
    public static function isChefeOuRepresentanteFase2(?array $user = null): bool {
        if ($user === null) {
            if (!self::check()) {
                return false;
            }
            $user = self::user();
        }

        if (!$user) {
            return false;
        }

        if (in_array(($user['perfil'] ?? ''), [PERFIL_ADMIN, PERFIL_PRESIDENTE, PERFIL_CHEFE_DIV_ASSESS], true)) {
            return true;
        }

        if (!empty($user['orgao_chefe_sigla'])) {
            return true;
        }

        // Verifica se é o militar mais antigo das assessorias reunidas
        require_once ROOT_PATH . '/src/Models/Efetivo.php';
        $maisAntigo = Efetivo::getMilitarMaisAntigoAssessorias();
        if ($maisAntigo && (int)$maisAntigo['id'] === (int)($user['efetivo_id'] ?? 0)) {
            return true;
        }

        return false;
    }

    public static function login(string $username, string $password): array {
        $username = trim($username);
        if ($username === '' || $password === '') {
            return ['sucesso' => false, 'mensagem' => 'Informe o login e a senha.'];
        }

        $ldap = new LdapAuth();
        $authResult = $ldap->authenticate($username, $password);

        if (!$authResult) {
            AuditoriaService::log('LOGIN_FALHOU', ['login_tentado' => $username]);
            return ['sucesso' => false, 'mensagem' => 'Credenciais inválidas. Verifique seu login e senha.'];
        }

        $pdo = Database::getConnection();

        // Se veio do AD ou local, sincroniza usuário na base
        $loginClean = $authResult['login'];
        $authId = (int)($authResult['id'] ?? 0);
        $stmtUser = $pdo->prepare('
            SELECT u.*, e.nome, e.nome_guerra, e.posto, e.saram, e.cpf, e.setor, e.divisao_sigla, e.categoria
            FROM usuarios u
            LEFT JOIN efetivo e ON e.id = u.efetivo_id
            WHERE (u.id = :uid OR u.login = :login) AND u.ativo = 1
            LIMIT 1
        ');
        $stmtUser->execute([':uid' => $authId, ':login' => $loginClean]);
        $userRow = $stmtUser->fetch();

        // Se não existir localmente mas autenticou no AD, cria como ELEITOR
        if (!$userRow) {
            $stmtEfetivo = $pdo->prepare('SELECT id, nome, nome_guerra, posto, saram, cpf, setor, divisao_sigla, categoria FROM efetivo WHERE saram = :saram OR nome LIKE :nome LIMIT 1');
            $stmtEfetivo->execute([
                ':saram' => $authResult['saram'] ?? '',
                ':nome'  => '%' . ($authResult['nome'] ?? '') . '%'
            ]);
            $efetivoFound = $stmtEfetivo->fetch();

            $stmtIns = $pdo->prepare('
                INSERT INTO `usuarios` (`login`, `efetivo_id`, `perfil`, `ativo`)
                VALUES (:login, :ef, "ELEITOR", 1)
            ');
            $stmtIns->execute([
                ':login' => $loginClean,
                ':ef'    => $efetivoFound ? $efetivoFound['id'] : null
            ]);
            $newId = (int)$pdo->lastInsertId();

            $userRow = [
                'id'                => $newId,
                'login'             => $loginClean,
                'perfil'            => 'ELEITOR',
                'orgao_chefe_sigla' => null,
                'efetivo_id'        => $efetivoFound ? $efetivoFound['id'] : null,
                'nome'              => $efetivoFound['nome'] ?? ($authResult['nome'] ?? $loginClean),
                'nome_guerra'       => $efetivoFound['nome_guerra'] ?? $loginClean,
                'posto'             => $efetivoFound['posto'] ?? '',
                'saram'             => $efetivoFound['saram'] ?? '',
                'cpf'               => $efetivoFound['cpf'] ?? '',
                'setor'             => $efetivoFound['setor'] ?? '',
                'divisao_sigla'     => $efetivoFound['divisao_sigla'] ?? '',
                'categoria'         => $efetivoFound['categoria'] ?? ''
            ];
        }

        // Atualiza último login
        $pdo->prepare('UPDATE usuarios SET ultimo_login = NOW() WHERE id = :id')->execute([':id' => $userRow['id']]);

        // Regenera ID da sessão contra fixation
        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id'                => (int)$userRow['id'],
            'login'             => $userRow['login'],
            'perfil'            => $userRow['perfil'],
            'orgao_chefe_sigla' => $userRow['orgao_chefe_sigla'],
            'efetivo_id'        => $userRow['efetivo_id'] ? (int)$userRow['efetivo_id'] : null,
            'nome'              => $userRow['nome'] ?? $userRow['login'],
            'nome_guerra'       => $userRow['nome_guerra'] ?? $userRow['login'],
            'posto'             => $userRow['posto'] ?? '',
            'saram'             => $userRow['saram'] ?? '',
            'cpf'               => $userRow['cpf'] ?? '',
            'setor'             => $userRow['setor'] ?? '',
            'divisao_sigla'     => $userRow['divisao_sigla'] ?? '',
            'categoria'         => $userRow['categoria'] ?? ''
        ];

        AuditoriaService::log('LOGIN_SUCESSO', [
            'perfil' => $userRow['perfil'],
            'origem' => $authResult['origem'] ?? 'LOCAL'
        ], (int)$userRow['id'], $userRow['login']);

        return ['sucesso' => true, 'usuario' => $_SESSION['user']];
    }

    public static function logout(): void {
        if (self::check()) {
            AuditoriaService::log('LOGOUT', [], (int)$_SESSION['user']['id'], $_SESSION['user']['login']);
        }
        unset($_SESSION['user']);
        session_destroy();
    }
}
