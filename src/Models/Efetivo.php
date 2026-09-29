<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

/**
 * Modelo de Acesso aos Dados do Efetivo e Candidatos da COMARA
 */
class Efetivo {

    public static function getPorId(int $id): ?array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM efetivo WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $res = $stmt->fetch();
        return $res ?: null;
    }

    public static function getPorSaram(string $saram): ?array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM efetivo WHERE saram = :saram LIMIT 1');
        $stmt->execute([':saram' => $saram]);
        $res = $stmt->fetch();
        return $res ?: null;
    }

    /**
     * Retorna os militares diretamente subordinados a um chefe direto
     * ou militares cuja avaliação foi delegada a este oficial (caso de chefe em missão)
     */
    public static function getSubordinadosDiretos(int $chefeEfetivoId, int $pleitoId): array {
        $pdo = Database::getConnection();
        $sql = "
            SELECT e.*, 
                   f.id as ficha_id, f.is_nob, f.media_calculada, f.updated_at as avaliado_em,
                   t.nota_tacf,
                   del.id as delegacao_id, del.oficial_delegado_id, del.motivo as delegacao_motivo,
                   del_of.posto as delegado_posto, del_of.nome_guerra as delegado_guerra,
                   orig_ch.posto as chefe_orig_posto, orig_ch.nome_guerra as chefe_orig_guerra,
                   IF(del.oficial_delegado_id = :chefe, 1, 0) as sou_o_delegado
            FROM efetivo e
            LEFT JOIN delegacoes_fase1 del ON del.candidato_id = e.id AND del.pleito_id = :p
            LEFT JOIN efetivo del_of ON del_of.id = del.oficial_delegado_id
            LEFT JOIN efetivo orig_ch ON orig_ch.id = e.chefe_direto_id
            LEFT JOIN fichas_indicacao f ON f.candidato_id = e.id AND f.pleito_id = :p AND f.tipo_avaliacao = 'OBRIGATORIA_CHEFE'
            LEFT JOIN avaliacoes_tacf t ON t.candidato_id = e.id AND t.pleito_id = :p
            WHERE (e.chefe_direto_id = :chefe OR del.oficial_delegado_id = :chefe) 
              AND e.ativo = 1 
              AND e.categoria IN ('graduado', 'praca', 'sppf', 'sptf')
            ORDER BY sou_o_delegado ASC, e.categoria, e.posto, e.nome
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':chefe' => $chefeEfetivoId, ':p' => $pleitoId]);
        return $stmt->fetchAll();
    }

    /**
     * Verifica se o oficial autenticado é o substituto delegado para avaliar determinado candidato
     */
    public static function isOficialDelegado(int $pleitoId, int $candidatoId, int $oficialEfetivoId): bool {
        if ($oficialEfetivoId <= 0) {
            return false;
        }
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            SELECT id FROM delegacoes_fase1 
            WHERE pleito_id = :p AND candidato_id = :c AND oficial_delegado_id = :o 
            LIMIT 1
        ');
        $stmt->execute([':p' => $pleitoId, ':c' => $candidatoId, ':o' => $oficialEfetivoId]);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * Lista todos os oficiais ativos aptos a receber delegações de avaliação
     */
    public static function listarOficiais(): array {
        $pdo = Database::getConnection();
        $postosIn = "'" . implode("','", POSTOS_OFICIAIS) . "'";
        $sql = "
            SELECT id, posto, nome, nome_guerra, saram, setor, divisao_sigla
            FROM efetivo
            WHERE ativo = 1 AND posto IN ($postosIn)
            ORDER BY FIELD(posto, 'Ten-Brig', 'Maj-Brig', 'Brig', 'Cel', 'Ten Cel', 'Maj', 'Cap', '1º Ten', '1° Ten', '1S Ten', '1 Ten', '2º Ten', '2° Ten', '2S Ten', '2 Ten'), nome_guerra
        ";
        return $pdo->query($sql)->fetchAll();
    }

    /**
     * Salva ou atualiza delegação de avaliação da Fase 1 (Oficial em Missão)
     */
    public static function salvarDelegacao(int $pleitoId, int $candidatoId, int $oficialDelegadoId, int $designadoPorId, string $motivo): array {
        $candidato = self::getPorId($candidatoId);
        if (!$candidato) {
            return ['sucesso' => false, 'mensagem' => 'Candidato não encontrado.'];
        }

        $oficial = self::getPorId($oficialDelegadoId);
        if (!$oficial || !in_array($oficial['posto'], POSTOS_OFICIAIS, true)) {
            return ['sucesso' => false, 'mensagem' => 'O substituto selecionado deve ser um Oficial ativo da Aeronáutica.'];
        }

        $motivoLimpo = trim($motivo);
        if (mb_strlen($motivoLimpo) < 3) {
            return ['sucesso' => false, 'mensagem' => 'Informe o motivo da substituição/delegação (ex: Chefe em Missão, Afastamento, Férias).'];
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            INSERT INTO delegacoes_fase1 (pleito_id, candidato_id, oficial_delegado_id, designado_por_id, motivo)
            VALUES (:p, :c, :o, :u, :m)
            ON DUPLICATE KEY UPDATE 
                oficial_delegado_id = VALUES(oficial_delegado_id),
                designado_por_id    = VALUES(designado_por_id),
                motivo              = VALUES(motivo),
                created_at          = CURRENT_TIMESTAMP
        ');
        $stmt->execute([
            ':p' => $pleitoId,
            ':c' => $candidatoId,
            ':o' => $oficialDelegadoId,
            ':u' => $designadoPorId,
            ':m' => $motivoLimpo
        ]);

        return [
            'sucesso'  => true,
            'mensagem' => "Avaliação de {$candidato['posto']} {$candidato['nome_guerra']} delegada com sucesso ao {$oficial['posto']} {$oficial['nome_guerra']}."
        ];
    }

    /**
     * Remove delegação de avaliação da Fase 1
     */
    public static function removerDelegacao(int $pleitoId, int $candidatoId): bool {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('DELETE FROM delegacoes_fase1 WHERE pleito_id = :p AND candidato_id = :c');
        return $stmt->execute([':p' => $pleitoId, ':c' => $candidatoId]);
    }


    /**
     * Lista todos os candidatos elegíveis para a Fase 1 com status de avaliação
     */
    public static function listarCandidatosFase1(int $pleitoId, ?string $categoria = null): array {
        $pdo = Database::getConnection();
        $sql = "
            SELECT e.*, 
                   MAX(t.nota_tacf) as nota_tacf,
                   COUNT(f.id) as total_avaliacoes,
                   COALESCE(AVG(f.media_calculada), 0) as media_geral_fase1
            FROM efetivo e
            LEFT JOIN avaliacoes_tacf t ON t.candidato_id = e.id AND t.pleito_id = :p
            LEFT JOIN fichas_indicacao f ON f.candidato_id = e.id AND f.pleito_id = :p
            WHERE e.ativo = 1 AND e.categoria != 'ineligivel'
        ";
        $params = [':p' => $pleitoId];

        if ($categoria && isset(CATEGORIAS_PADRAO[$categoria])) {
            $sql .= " AND e.categoria = :cat";
            $params[':cat'] = $categoria;
        }

        $sql .= " GROUP BY e.id ORDER BY media_geral_fase1 DESC, e.posto, e.nome";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Lista candidatos com suas médias para a Fase 2 (Seleção pelos Chefes de Divisão e Assessoria)
     */
    public static function listarParaSelecaoFase2(int $pleitoId, string $categoria, string $orgaoSigla): array {
        $pdo = Database::getConnection();
        $sql = "
            SELECT e.*, 
                   MAX(t.nota_tacf) as nota_tacf,
                   COALESCE(AVG(f.media_calculada), 0) as media_fase1,
                   COUNT(DISTINCT f.id) as qtd_fichas,
                   MAX(COALESCE(f.is_nob, 0)) as foi_nob,
                   MAX(IF(s.id IS NOT NULL, 1, 0)) as selecionado_pelo_orgao
            FROM efetivo e
            LEFT JOIN avaliacoes_tacf t ON t.candidato_id = e.id AND t.pleito_id = :p
            LEFT JOIN fichas_indicacao f ON f.candidato_id = e.id AND f.pleito_id = :p
            LEFT JOIN selecao_fase2 s ON s.candidato_id = e.id AND s.pleito_id = :p AND s.orgao_sigla = :orgao
            WHERE e.ativo = 1 AND e.categoria = :cat
            GROUP BY e.id
            ORDER BY media_fase1 DESC, nota_tacf DESC, e.nome
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':p' => $pleitoId, ':cat' => $categoria, ':orgao' => $orgaoSigla]);
        return $stmt->fetchAll();
    }

    /**
     * Retorna os candidatos indicados aptos para compor a cédula da Urna na Fase 3
     */
    public static function listarParaUrna(int $pleitoId, string $categoria): array {
        $pdo = Database::getConnection();
        $sql = "
            SELECT e.*, i.media_fase1, i.total_indicacoes_orgaos
            FROM indicados_consolidados i
            INNER JOIN efetivo e ON e.id = i.candidato_id
            WHERE i.pleito_id = :p 
              AND i.categoria = :cat
              AND i.status_validacao_superior = 'VALIDADO'
            ORDER BY e.posto, e.nome_guerra
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':p' => $pleitoId, ':cat' => $categoria]);
        return $stmt->fetchAll();
    }

    /**
     * Atualiza foto manual do militar ou civil enviada pelo administrador
     */
    public static function atualizarFotoManual(int $id, string $filename): bool {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('UPDATE efetivo SET foto_custom = :f WHERE id = :id');
        return $stmt->execute([':f' => $filename, ':id' => $id]);
    }

    /**
     * Retorna contagens de efetivo agrupadas por categoria
     */
    public static function obterTotaisPorCategoria(): array {
        $pdo = Database::getConnection();
        $totais = $pdo->query('
            SELECT categoria, COUNT(*) as qtd 
            FROM efetivo 
            WHERE ativo = 1 
            GROUP BY categoria
        ')->fetchAll(PDO::FETCH_KEY_PAIR);

        $totalGeral = (int)$pdo->query('SELECT COUNT(*) FROM efetivo WHERE ativo = 1')->fetchColumn();

        return [
            CAT_GRADUADO   => (int)($totais[CAT_GRADUADO] ?? 0),
            CAT_PRACA      => (int)($totais[CAT_PRACA] ?? 0),
            CAT_SPPF       => (int)($totais[CAT_SPPF] ?? 0),
            CAT_SPTF       => (int)($totais[CAT_SPTF] ?? 0),
            CAT_INELIGIVEL => (int)($totais[CAT_INELIGIVEL] ?? 0),
            'total'        => $totalGeral
        ];
    }

    /**
     * Listagem paginada para o painel de administração do efetivo
     */
    public static function listarPaginado(
        int $limit = 25,
        int $offset = 0,
        ?string $termo = null,
        ?string $divisao = null,
        ?string $categoria = null,
        ?string $status = null
    ): array {
        $pdo = Database::getConnection();
        $where = [];
        $params = [];

        if ($termo !== null && trim($termo) !== '') {
            $where[] = '(e.nome LIKE :q OR e.nome_guerra LIKE :q OR e.saram LIKE :q OR e.cpf LIKE :q)';
            $params[':q'] = '%' . trim($termo) . '%';
        }

        if ($divisao !== null && $divisao !== '' && $divisao !== 'todas') {
            $where[] = 'e.divisao_sigla = :div';
            $params[':div'] = $divisao;
        }

        if ($categoria !== null && $categoria !== '' && $categoria !== 'todas') {
            $where[] = 'e.categoria = :cat';
            $params[':cat'] = $categoria;
        }

        if ($status !== null && $status !== '' && $status !== 'todos') {
            $where[] = 'e.ativo = :status';
            $params[':status'] = ($status === 'inativo') ? 0 : 1;
        }

        $whereSql = !empty($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

        $sql = "
            SELECT e.*,
                   u.id as usuario_id, u.login as usuario_login, u.perfil as usuario_perfil,
                   c.posto as chefe_posto, c.nome_guerra as chefe_nome_guerra
            FROM efetivo e
            LEFT JOIN usuarios u ON u.efetivo_id = e.id
            LEFT JOIN efetivo c ON c.id = e.chefe_direto_id
            {$whereSql}
            ORDER BY e.ativo DESC, e.posto, e.nome
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Contagem total de registros filtrados para paginação
     */
    public static function contarTotal(
        ?string $termo = null,
        ?string $divisao = null,
        ?string $categoria = null,
        ?string $status = null
    ): int {
        $pdo = Database::getConnection();
        $where = [];
        $params = [];

        if ($termo !== null && trim($termo) !== '') {
            $where[] = '(e.nome LIKE :q OR e.nome_guerra LIKE :q OR e.saram LIKE :q OR e.cpf LIKE :q)';
            $params[':q'] = '%' . trim($termo) . '%';
        }

        if ($divisao !== null && $divisao !== '' && $divisao !== 'todas') {
            $where[] = 'e.divisao_sigla = :div';
            $params[':div'] = $divisao;
        }

        if ($categoria !== null && $categoria !== '' && $categoria !== 'todas') {
            $where[] = 'e.categoria = :cat';
            $params[':cat'] = $categoria;
        }

        if ($status !== null && $status !== '' && $status !== 'todos') {
            $where[] = 'e.ativo = :status';
            $params[':status'] = ($status === 'inativo') ? 0 : 1;
        }

        $whereSql = !empty($where) ? ('WHERE ' . implode(' AND ', $where)) : '';
        $sql = "SELECT COUNT(*) FROM efetivo e {$whereSql}";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Lista todas as siglas distintas de divisões/assessorias registradas
     */
    public static function listarDivisoes(): array {
        $pdo = Database::getConnection();
        $stmt = $pdo->query('
            SELECT DISTINCT divisao_sigla 
            FROM efetivo 
            WHERE divisao_sigla IS NOT NULL AND divisao_sigla != "" 
            ORDER BY divisao_sigla
        ');
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Lista militares aptos a exercer funções de chefia (Oficiais e Suboficiais)
     */
    public static function listarOficiaisEChefes(): array {
        $pdo = Database::getConnection();
        $sql = "
            SELECT e.id, e.saram, e.nome, e.nome_guerra, e.posto, e.quadro, e.divisao_sigla, e.setor,
                   u.id as usuario_id, u.perfil, u.orgao_chefe_sigla
            FROM efetivo e
            LEFT JOIN usuarios u ON u.efetivo_id = e.id
            WHERE e.ativo = 1 AND e.posto IN ('Cel', 'Ten Cel', 'Maj', 'Cap', '1º Ten', '2º Ten')
            ORDER BY 
                CASE e.posto
                    WHEN 'Cel' THEN 1
                    WHEN 'Ten Cel' THEN 2
                    WHEN 'Maj' THEN 3
                    WHEN 'Cap' THEN 4
                    WHEN '1º Ten' THEN 5
                    WHEN '2º Ten' THEN 6
                    ELSE 7
                END,
                e.nome
        ";
        return $pdo->query($sql)->fetchAll();
    }

    /**
     * Salva ou atualiza um militar/servidor civil e sua conta de acesso
     */
    public static function salvarOuAtualizar(array $dados, ?string $fotoCustom = null): int {
        $pdo = Database::getConnection();

        $id = !empty($dados['id']) ? (int)$dados['id'] : null;
        $saram = trim($dados['saram'] ?? '');
        $cpf = preg_replace('/\D/', '', $dados['cpf'] ?? '');
        $cpfFormatado = strlen($cpf) === 11 
            ? vsprintf('%s%s%s.%s%s%s.%s%s%s-%s%s', str_split($cpf))
            : trim($dados['cpf'] ?? '');
        $cpfHash = $cpf ? hash('sha256', $cpf) : null;

        $nome = trim(mb_strtoupper($dados['nome'] ?? ''));
        $nomeGuerra = trim(mb_strtoupper($dados['nome_guerra'] ?? ''));
        $posto = trim($dados['posto'] ?? '');
        $quadro = trim(mb_strtoupper($dados['quadro'] ?? ''));
        $esp = trim(mb_strtoupper($dados['especialidade'] ?? ''));
        $setor = trim(mb_strtoupper($dados['setor'] ?? ''));
        $divisao = trim(mb_strtoupper($dados['divisao_sigla'] ?? ''));
        $categoria = trim($dados['categoria'] ?? 'ineligivel');
        $vinculo = trim($dados['tipo_vinculo'] ?? 'MILITAR_CARREIRA');
        $chefeDiretoId = !empty($dados['chefe_direto_id']) ? (int)$dados['chefe_direto_id'] : null;
        $telefone = trim($dados['telefone'] ?? '');
        $funcao = trim($dados['funcao'] ?? ($posto . ' - ' . $setor));
        $ativo = isset($dados['ativo']) ? (int)$dados['ativo'] : 1;

        if ($id) {
            // Update
            $sql = "
                UPDATE efetivo SET
                    saram = :saram, cpf = :cpf, " . ($cpfHash ? "cpf_hash = :cpf_hash," : "") . "
                    nome = :nome, nome_guerra = :nome_guerra,
                    posto = :posto, quadro = :quadro, especialidade = :esp,
                    setor = :setor, divisao_sigla = :div, funcao = :funcao,
                    categoria = :cat, tipo_vinculo = :vinculo,
                    chefe_direto_id = :chefe, telefone = :tel, ativo = :ativo
                    " . ($fotoCustom ? ", foto_custom = :foto" : "") . "
                WHERE id = :id
            ";
            $params = [
                ':saram'       => $saram ?: null,
                ':cpf'         => $cpfFormatado ?: null,
                ':nome'        => $nome,
                ':nome_guerra' => $nomeGuerra,
                ':posto'       => $posto,
                ':quadro'      => $quadro,
                ':esp'         => $esp,
                ':setor'       => $setor,
                ':div'         => $divisao,
                ':funcao'      => $funcao,
                ':cat'         => $categoria,
                ':vinculo'     => $vinculo,
                ':chefe'       => $chefeDiretoId,
                ':tel'         => $telefone,
                ':ativo'       => $ativo,
                ':id'          => $id
            ];
            if ($cpfHash) {
                $params[':cpf_hash'] = $cpfHash;
            }
            if ($fotoCustom) {
                $params[':foto'] = $fotoCustom;
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            // Atualiza usuário vinculado se existir
            $loginEsperado = $saram ?: $cpf;
            if ($loginEsperado) {
                $pdo->prepare('UPDATE usuarios SET login = :login, ativo = :ativo WHERE efetivo_id = :id')
                    ->execute([':login' => $loginEsperado, ':ativo' => $ativo, ':id' => $id]);
            }

            return $id;
        } else {
            // Insert
            $sql = "
                INSERT INTO efetivo (
                    saram, cpf, cpf_hash, nome, nome_guerra,
                    posto, quadro, especialidade, setor, divisao_sigla,
                    funcao, categoria, tipo_vinculo, chefe_direto_id,
                    telefone, ativo" . ($fotoCustom ? ", foto_custom" : "") . "
                ) VALUES (
                    :saram, :cpf, :cpf_hash, :nome, :nome_guerra,
                    :posto, :quadro, :esp, :setor, :div,
                    :funcao, :cat, :vinculo, :chefe,
                    :tel, :ativo" . ($fotoCustom ? ", :foto" : "") . "
                )
            ";
            $params = [
                ':saram'       => $saram ?: null,
                ':cpf'         => $cpfFormatado ?: null,
                ':cpf_hash'    => $cpfHash,
                ':nome'        => $nome,
                ':nome_guerra' => $nomeGuerra,
                ':posto'       => $posto,
                ':quadro'      => $quadro,
                ':esp'         => $esp,
                ':setor'       => $setor,
                ':div'         => $divisao,
                ':funcao'      => $funcao,
                ':cat'         => $categoria,
                ':vinculo'     => $vinculo,
                ':chefe'       => $chefeDiretoId,
                ':tel'         => $telefone,
                ':ativo'       => $ativo
            ];
            if ($fotoCustom) {
                $params[':foto'] = $fotoCustom;
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $novoId = (int)$pdo->lastInsertId();

            // Cria conta de acesso
            $login = $saram ?: ($cpf ?: 'user_' . $novoId);
            $senhaPadraoHash = password_hash('padrao@2026', PASSWORD_BCRYPT);
            $perfil = 'ELEITOR';
            if ($categoria === 'ineligivel' && in_array($posto, ['Cel', 'Ten Cel'], true)) {
                $perfil = 'DIRECAO_SUPERIOR';
            }

            $stmtU = $pdo->prepare('
                INSERT INTO usuarios (login, senha_hash, efetivo_id, perfil, ativo)
                VALUES (:l, :s, :ef, :p, :at)
                ON DUPLICATE KEY UPDATE efetivo_id = VALUES(efetivo_id), ativo = VALUES(ativo)
            ');
            $stmtU->execute([
                ':l'  => $login,
                ':s'  => $senhaPadraoHash,
                ':ef' => $novoId,
                ':p'  => $perfil,
                ':at' => $ativo
            ]);

            return $novoId;
        }
    }

    /**
     * Alterna status ativo/inativo de um membro e de seu usuário
     */
    public static function toggleAtivo(int $id): bool {
        $pdo = Database::getConnection();
        $atual = (int)$pdo->query("SELECT ativo FROM efetivo WHERE id = {$id}")->fetchColumn();
        $novo = ($atual === 1) ? 0 : 1;

        $pdo->prepare('UPDATE efetivo SET ativo = :n WHERE id = :id')->execute([':n' => $novo, ':id' => $id]);
        $pdo->prepare('UPDATE usuarios SET ativo = :n WHERE efetivo_id = :id')->execute([':n' => $novo, ':id' => $id]);
        return ($novo === 1);
    }

    /**
     * Vincula todos os militares subordinados de uma divisão ao seu chefe correspondente
     */
    public static function vincularChefeDiretoEmLote(string $divisaoSigla, int $chefeEfetivoId): int {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            UPDATE efetivo 
            SET chefe_direto_id = :chefe 
            WHERE divisao_sigla = :sigla AND id != :chefe
        ');
        $stmt->execute([':chefe' => $chefeEfetivoId, ':sigla' => $divisaoSigla]);
        return $stmt->rowCount();
    }

    /**
     * Vincula militares de um setor específico a um chefe direto
     */
    public static function vincularChefeDiretoPorSetor(string $setor, int $chefeEfetivoId): int {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            UPDATE efetivo 
            SET chefe_direto_id = :chefe 
            WHERE setor = :setor AND id != :chefe
        ');
        $stmt->execute([':chefe' => $chefeEfetivoId, ':setor' => $setor]);
        return $stmt->rowCount();
    }
}

