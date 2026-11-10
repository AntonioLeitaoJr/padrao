<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

/**
 * Módulo de Autenticação Active Directory (LDAP) da COMARA / INTRAER
 */
class LdapAuth {
    private string $host;
    private int $port;
    private string $domain;
    private string $baseDn;
    private bool $useSsl;
    private bool $localFallback;

    public function __construct() {
        $this->host          = (string)env('LDAP_HOST', 'ldap.comara.intraer');
        $this->port          = (int)env('LDAP_PORT', 389);
        $this->domain        = (string)env('LDAP_DOMAIN', 'intraer');
        $this->baseDn        = (string)env('LDAP_BASE_DN', 'DC=intraer,DC=fab,DC=mil,DC=br');
        $this->useSsl        = (bool)env('LDAP_USE_SSL', false);
        $this->localFallback = (bool)env('LDAP_LOCAL_FALLBACK', true); // Habilitado por padrão para contingência
    }

    /**
     * Tenta autenticar usuário no Active Directory.
     * Retorna array com dados do militar se sucesso, ou false se falha.
     */
    public function authenticate(string $username, string $password): array|false {
        $username = trim($username);
        if ($username === '' || $password === '') {
            return false;
        }

        // 1. Prioridade para contas locais com senha definida (admin, chefias, contas de contingência)
        if ($this->localFallback) {
            $localResult = $this->authenticateLocal($username, $password);
            if ($localResult !== false) {
                return $localResult;
            }
        }

        // 2. Se não autenticou localmente, tenta validação no Active Directory da INTRAER
        if (!function_exists('ldap_connect')) {
            return false;
        }

        $ldapConn = @ldap_connect($this->host, $this->port);
        if (!$ldapConn) {
            return false;
        }

        ldap_set_option($ldapConn, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($ldapConn, LDAP_OPT_REFERRALS, 0);
        ldap_set_option($ldapConn, LDAP_OPT_NETWORK_TIMEOUT, 3);

        $userPrincipal = strpos($username, '@') !== false ? $username : "{$username}@{$this->domain}";

        // Tenta bind com as credenciais do usuário
        $bind = @ldap_bind($ldapConn, $userPrincipal, $password);

        if ($bind) {
            // Busca atributos do militar/civil no AD
            $filter = "(|(sAMAccountName={$username})(userPrincipalName={$userPrincipal}))";
            $search = @ldap_search($ldapConn, $this->baseDn, $filter, [
                'cn', 'mail', 'telephonenumber', 'department', 'title', 'sAMAccountName'
            ]);

            $userData = [
                'login'     => $username,
                'origem'    => 'ACTIVE_DIRECTORY',
                'nome'      => $username,
                'email'     => "{$username}@intraer",
                'setor'     => '',
                'funcao'    => ''
            ];

            if ($search) {
                $entries = ldap_get_entries($ldapConn, $search);
                if ($entries['count'] > 0) {
                    $entry = $entries[0];
                    $userData['nome']   = $entry['cn'][0] ?? $username;
                    $userData['email']  = $entry['mail'][0] ?? "{$username}@intraer";
                    $userData['setor']  = $entry['department'][0] ?? '';
                    $userData['funcao'] = $entry['title'][0] ?? '';
                }
            }

            @ldap_unbind($ldapConn);
            return $userData;
        }

        @ldap_unbind($ldapConn);
        return false;
    }

    /**
     * Validação contra a base local de usuários para contingência e administradores
     */
    private function authenticateLocal(string $username, string $password): array|false {
        try {
            $pdo = Database::getConnection();
            $usernameTrim = trim($username);
            // A matrícula civil utiliza o mesmo campo efetivo.saram, sem criar outra conta.
            // Para CPF, aceitamos somente dígitos e sua pontuação habitual.
            $cpfLimpo = preg_match('/^[0-9.\-]+$/D', $usernameTrim)
                ? preg_replace('/\D/', '', $usernameTrim) : '';
            $cpfFmt = strlen($cpfLimpo) === 11 
                ? vsprintf('%s%s%s.%s%s%s.%s%s%s-%s%s', str_split($cpfLimpo))
                : '';
            $cpfHash = (strlen($cpfLimpo) === 11) ? hash('sha256', $cpfLimpo) : '';

            $sql = '
                SELECT u.id, u.login, u.senha_hash, u.perfil, u.efetivo_id, u.ativo,
                       e.nome, e.nome_guerra, e.posto, e.saram, e.cpf, e.setor, e.divisao_sigla
                FROM usuarios u
                LEFT JOIN efetivo e ON e.id = u.efetivo_id
                WHERE (
                    u.login = :login
                    OR TRIM(e.saram) = :login
                    ' . ($cpfLimpo ? 'OR u.login = :cpf_limpo' : '') . '
                    ' . ($cpfFmt ? 'OR e.cpf = :cpf_fmt OR e.cpf = :cpf_limpo' : '') . '
                    ' . ($cpfHash ? 'OR e.cpf_hash = :cpf_hash' : '') . '
                ) AND u.ativo = 1
                LIMIT 2
            ';
            // Mais de uma conta com o mesmo identificador impede login ambíguo.

            $params = [':login' => $usernameTrim];
            if ($cpfLimpo) {
                $params[':cpf_limpo'] = $cpfLimpo;
            }
            if ($cpfFmt) {
                $params[':cpf_fmt'] = $cpfFmt;
            }
            if ($cpfHash) {
                $params[':cpf_hash'] = $cpfHash;
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $usuarios = $stmt->fetchAll();
            if (count($usuarios) !== 1) {
                if (count($usuarios) > 1) error_log('[LOCAL AUTH] Identificador associado a mais de uma conta ativa.');
                return false;
            }
            $user = $usuarios[0];

            if (!empty($user['senha_hash']) && password_verify($password, $user['senha_hash'])) {
                return [
                    'id'            => (int)$user['id'],
                    'login'         => $user['login'],
                    'origem'        => 'LOCAL_DATABASE',
                    'perfil'        => $user['perfil'],
                    'efetivo_id'    => $user['efetivo_id'] ? (int)$user['efetivo_id'] : null,
                    'nome'          => $user['nome'] ?: $user['login'],
                    'nome_guerra'   => $user['nome_guerra'] ?: $user['login'],
                    'posto'         => $user['posto'] ?: '',
                    'saram'         => $user['saram'] ?: '',
                    'cpf'           => $user['cpf'] ?: '',
                    'setor'         => $user['setor'] ?: '',
                    'divisao_sigla' => $user['divisao_sigla'] ?: ''
                ];
            }
        } catch (\Throwable $e) {
            error_log('[LOCAL AUTH ERROR] ' . $e->getMessage());
        }

        return false;
    }
}
