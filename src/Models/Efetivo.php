<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

/**
 * Modelo de Acesso aos Dados do Efetivo e Candidatos da COMARA
 */
class Efetivo {
    public static function orgaoSelecaoUsuario(int $usuarioId): ?string {
        $pdo = Database::getConnection();
        $divisoes = "'" . implode("','", array_keys(ORGAOS_SELECAO_FASE2['DIVISOES'])) . "'";
        $stmt = $pdo->prepare("SELECT d.sigla FROM divisoes_assessorias d
            JOIN usuarios u ON u.id = d.chefe_usuario_id
            JOIN efetivo e ON e.id = u.efetivo_id
            WHERE d.ativo = 1 AND u.ativo = 1 AND e.ativo = 1
              AND d.sigla IN ($divisoes) AND u.id = :u ORDER BY d.sigla LIMIT 1");
        $stmt->execute([':u' => $usuarioId]);
        $divisao = $stmt->fetchColumn();
        if ($divisao) return $divisao;
        $representante = self::getMilitarMaisAntigoAssessorias();
        return $representante && (int)$representante['usuario_id'] === $usuarioId ? 'ASSESSORIAS' : null;
    }


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
                   f.id as ficha_id, f.is_nob, f.status as ficha_status, f.media_calculada, f.updated_at as avaliado_em,
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
     * Retorna o militar ativo mais antigo de todas as assessorias reunidas
     * (AINT, ACI, AGOV, APOG, SCS, AJUR, SIJ) para atuar como representante único na Fase 2.
     */
    public static function getMilitarMaisAntigoAssessorias(): ?array {
        $pdo = Database::getConnection();
        $assessorias = SIGLAS_ASSESSORIAS;
        $in = "'" . implode("','", $assessorias) . "'";
        $divisoes = "'" . implode("','", array_keys(ORGAOS_SELECAO_FASE2['DIVISOES'])) . "'";
        $oficiais = "'" . implode("','", POSTOS_OFICIAIS) . "'";
        $sql = "
            SELECT DISTINCT e.*, u.id AS usuario_id
            FROM divisoes_assessorias d
            JOIN usuarios u ON u.id = d.chefe_usuario_id AND u.ativo = 1
            JOIN efetivo e ON e.id = u.efetivo_id
            WHERE d.ativo = 1 AND e.ativo = 1 AND d.sigla IN ($in)
              AND e.posto IN ($oficiais)
              AND NOT EXISTS (SELECT 1 FROM divisoes_assessorias dv
                  WHERE dv.ativo = 1 AND dv.sigla IN ($divisoes) AND dv.chefe_usuario_id = u.id)
            ORDER BY 
                CASE e.posto
                    WHEN 'Ten-Brig' THEN 1
                    WHEN 'Maj-Brig' THEN 2
                    WHEN 'Brig' THEN 3
                    WHEN 'Cel' THEN 4
                    WHEN 'Ten Cel' THEN 5
                    WHEN 'Maj' THEN 6
                    WHEN 'Cap' THEN 7
                    WHEN '1º Ten' THEN 8
                    WHEN '1° Ten' THEN 8
                    WHEN '1S Ten' THEN 8
                    WHEN '1 Ten' THEN 8
                    WHEN '2º Ten' THEN 9
                    WHEN '2° Ten' THEN 9
                    WHEN '2S Ten' THEN 9
                    WHEN '2 Ten' THEN 9
                    WHEN 'Asp' THEN 10
                    WHEN 'SO' THEN 11
                    WHEN '1S' THEN 12
                    WHEN '2S' THEN 13
                    WHEN '3S' THEN 14
                    WHEN 'CB' THEN 15
                    WHEN 'Cb' THEN 15
                    WHEN 'S1' THEN 16
                    WHEN 'S2' THEN 17
                    ELSE 99
                END,
                (e.data_admissao_comara IS NULL) ASC, e.data_admissao_comara ASC,
                e.id ASC
            LIMIT 1
        ";
        $res = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    /**
     * Lista candidatos com suas médias para a Fase 2 (Seleção pelos Chefes de Divisão e Assessoria)
     * Regra regimental atualizada:
     * 1. Filtra candidatos da respectiva divisão ou das assessorias unificadas.
     * 2. Exibe apenas os 20% das melhores notas da Fase 1.
     * 3. Exibe a listagem final em ordem alfabética por nome de guerra.
     */
    public static function listarParaSelecaoFase2(int $pleitoId, string $categoria, string $orgaoSigla): array {
        $pdo = Database::getConnection();
        
        $whereOrgao = "";
        $params = [':p' => $pleitoId, ':cat' => $categoria, ':orgao' => $orgaoSigla];

        if ($orgaoSigla === 'ASSESSORIAS') {
            $inAss = "'" . implode("','", SIGLAS_ASSESSORIAS) . "'";
            $whereOrgao = " AND e.divisao_sigla IN ($inAss)";
        } else {
            $whereOrgao = " AND e.divisao_sigla = :orgao_filtro";
            $params[':orgao_filtro'] = $orgaoSigla;
        }

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
                AND f.status = 'concluido' AND f.is_nob = 0
            LEFT JOIN selecao_fase2 s ON s.candidato_id = e.id AND s.pleito_id = :p AND s.orgao_sigla = :orgao
            WHERE e.ativo = 1 AND e.categoria = :cat {$whereOrgao}
            GROUP BY e.id
            ORDER BY e.id
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $todos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        require_once __DIR__ . '/../Services/DesempateService.php';
        require_once __DIR__ . '/HistoricoVencedores.php';
        $todos = HistoricoVencedores::enriquecer($todos, $pleitoId);
        return DesempateService::classificarFase2($todos);
    }

    /**
     * Retorna o resumo completo da ficha do candidato para o popup da Fase 4 (Estado-Maior)
     */
    public static function getResumoFichaCandidato(int $pleitoId, int $candidatoId): ?array {
        $candidato = self::getPorId($candidatoId);
        if (!$candidato) {
            return null;
        }

        $pdo = Database::getConnection();

        // Busca a ficha de indicação da Fase 1 (preferencialmente a obrigatória do chefe, ou a mais recente)
        $stmtF = $pdo->prepare("
            SELECT f.*, 
                   u.login as avaliador_login,
                   COALESCE(av_ef.nome_guerra, u.login) as avaliador_guerra,
                   av_ef.posto as avaliador_posto
            FROM fichas_indicacao f
            LEFT JOIN usuarios u ON u.id = f.avaliador_id
            LEFT JOIN efetivo av_ef ON av_ef.id = u.efetivo_id
            WHERE f.pleito_id = :p AND f.candidato_id = :c
            ORDER BY FIELD(f.tipo_avaliacao, 'OBRIGATORIA_CHEFE', 'VOLUNTARIA'), f.updated_at DESC
            LIMIT 1
        ");
        $stmtF->execute([':p' => $pleitoId, ':c' => $candidatoId]);
        $ficha = $stmtF->fetch(PDO::FETCH_ASSOC) ?: null;

        // TACF
        $stmtT = $pdo->prepare("SELECT * FROM avaliacoes_tacf WHERE pleito_id = :p AND candidato_id = :c LIMIT 1");
        $stmtT->execute([':p' => $pleitoId, ':c' => $candidatoId]);
        $tacf = $stmtT->fetch(PDO::FETCH_ASSOC) ?: null;

        // Status na consolidação / validação da Fase 4
        $stmtC = $pdo->prepare("
            SELECT status_validacao_superior, justificativa_exclusao, total_indicacoes_orgaos, media_fase1
            FROM indicados_consolidados 
            WHERE pleito_id = :p AND candidato_id = :c 
            LIMIT 1
        ");
        $stmtC->execute([':p' => $pleitoId, ':c' => $candidatoId]);
        $consolidado = $stmtC->fetch(PDO::FETCH_ASSOC) ?: null;

        return [
            'candidato'   => $candidato,
            'ficha'       => $ficha,
            'tacf'        => $tacf,
            'consolidado' => $consolidado,
            'foto_url'    => '/index.php?r=foto&saram=' . urlencode((string)$candidato['saram']) . '&id=' . $candidato['id']
        ];
    }

    /**
     * Retorna os candidatos indicados aptos para compor a cédula da Urna na Fase 3
     */
    public static function listarParaUrna(int $pleitoId, string $categoria): array {
        $pdo = Database::getConnection();
        // A cédula usa somente seleções explícitas, inclusive após retorno de fase.
        $stmt = $pdo->prepare("SELECT e.id, e.saram, e.nome_guerra, e.posto, e.setor,
                   e.categoria, e.divisao_sigla, s.orgao_sigla,
                   i.status_validacao_superior
            FROM selecao_fase2 s
            JOIN efetivo e ON e.id = s.candidato_id
            LEFT JOIN indicados_consolidados i ON i.pleito_id = s.pleito_id
                AND i.candidato_id = s.candidato_id AND i.categoria = s.categoria
            WHERE s.pleito_id = :p AND s.categoria = :cat
            ORDER BY e.nome_guerra COLLATE utf8mb4_unicode_ci, e.id");
        $stmt->execute([':p' => $pleitoId, ':cat' => $categoria]);
        $candidatos = [];
        $unidades = [];
        $ids = [];
        foreach ($stmt->fetchAll() as $c) {
            $unidade = in_array($c['divisao_sigla'], SIGLAS_ASSESSORIAS, true)
                ? 'ASSESSORIAS' : $c['divisao_sigla'];
            $permitidas = array_merge(ORGAOS_SELECAO_FASE2['DIVISOES'], ORGAOS_SELECAO_FASE2['ASSESSORIAS']);
            if (!isset($permitidas[$unidade]) || $unidade !== $c['orgao_sigla']
                || $c['categoria'] !== $categoria || isset($unidades[$unidade])
                || isset($ids[$c['id']]) || $c['status_validacao_superior'] === null) {
                throw new RuntimeException('Seleções inconsistentes na Fase 2. Solicite à Administração a conferência dos representantes e da consolidação antes de votar.');
            }
            $unidades[$unidade] = true;
            $ids[$c['id']] = true;
            if ($c['status_validacao_superior'] !== 'VALIDADO') continue;
            if (in_array($categoria, [CAT_SPPF, CAT_SPTF], true)) {
                $c['posto'] = 'CIVIL ' . strtoupper($categoria);
            }
            // Não carregar notas, histórico, nome completo ou classificação na cédula.
            $candidatos[] = array_intersect_key($c, array_flip(['id', 'saram', 'nome_guerra', 'posto', 'setor']));
        }
        return $candidatos;
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

        $datas = [];
        foreach (['data_admissao_comara', 'data_inicio_servico'] as $campo) {
            if (!array_key_exists($campo, $dados)) continue;
            $valor = trim((string)$dados[$campo]);
            $dt = $valor !== '' ? DateTimeImmutable::createFromFormat('!Y-m-d', $valor) : null;
            if ($valor !== '' && (!$dt || $dt->format('Y-m-d') !== $valor || $valor > date('Y-m-d'))) {
                throw new InvalidArgumentException('Data de apresentação ou início do serviço inválida.');
            }
            $datas[$campo] = $valor !== '' ? $valor : null;
        }
        $sqlDatas = '';
        $paramsDatas = [];
        foreach ($datas as $campo => $valor) {
            $sqlDatas .= ", $campo = :$campo";
            $paramsDatas[":$campo"] = $valor;
        }

        if ($id) {
            // Update
            $sql = "
                UPDATE efetivo SET
                    saram = :saram, cpf = :cpf, " . ($cpfHash ? "cpf_hash = :cpf_hash," : "") . "
                    nome = :nome, nome_guerra = :nome_guerra,
                    posto = :posto, quadro = :quadro, especialidade = :esp,
                    setor = :setor, divisao_sigla = :div, funcao = :funcao,
                    categoria = :cat, tipo_vinculo = :vinculo,
                    chefe_direto_id = :chefe, telefone = :tel, ativo = :ativo {$sqlDatas}
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
            $stmt->execute(array_merge($params, $paramsDatas));

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
            if ($sqlDatas !== '') {
                $pdo->prepare('UPDATE efetivo SET ' . substr($sqlDatas, 2) . ' WHERE id = :id')
                    ->execute(array_merge($paramsDatas, [':id' => $novoId]));
            }

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

