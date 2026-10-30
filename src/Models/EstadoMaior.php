<?php
declare(strict_types=1);
require_once __DIR__ . '/Efetivo.php';
require_once __DIR__ . '/HistoricoVencedores.php';
require_once __DIR__ . '/../Services/DesempateService.php';
require_once __DIR__ . '/../Services/OrdenacaoEstadoMaior.php';
require_once __DIR__ . '/../Services/AuditoriaService.php';

/** Encerramento da urna, corte, confirmações imutáveis e apuração 5–3–1. */
class EstadoMaior {
    public const CATEGORIAS = ['graduado','praca','sppf','sptf'];
    public const PESOS = [1=>5,2=>3,3=>1];

    public static function verificarEstrutura(): void {
        $pdo = Database::getConnection();
        foreach (['historico_vencedores','fase4_ciclos','fase4_decisoes_corte','fase4_avaliadores','fase4_confirmacoes','fase4_ordenacoes'] as $t) {
            if (!$pdo->query("SHOW TABLES LIKE '$t'")->fetch()) {
                throw new RuntimeException('Atualização do banco necessária. STI: execute php database/migrate_fase2.php e depois php database/migrate_fases3_4.php antes de continuar.');
            }
        }
        if (!$pdo->query("SHOW COLUMNS FROM efetivo LIKE 'data_inicio_servico'")->fetch()) {
            throw new RuntimeException('Atualização necessária: execute php database/migrate_fase2.php.');
        }
    }

    public static function usuario(int $id): array {
        $s = Database::getConnection()->prepare('SELECT u.*, e.posto, e.nome_guerra, e.ativo AS efetivo_ativo FROM usuarios u LEFT JOIN efetivo e ON e.id=u.efetivo_id WHERE u.id=?');
        $s->execute([$id]);
        $u=$s->fetch();
        if (!$u || !(int)$u['ativo']) throw new RuntimeException('Usuário inativo ou não autorizado.');
        return $u;
    }
    public static function exigirPresidencia(int $id): void {
        if (!in_array(self::usuario($id)['perfil'], [PERFIL_PRESIDENTE,PERFIL_ADMIN], true)) {
            throw new RuntimeException('Decisão restrita à Presidência ou ao Administrador autorizado.');
        }
    }
    public static function bloquearPleito(int $id, int $fase): array {
        $s=Database::getConnection()->prepare('SELECT * FROM pleitos WHERE id=? FOR UPDATE');
        $s->execute([$id]); $p=$s->fetch();
        if (!$p || (int)$p['fase_atual']!==$fase || $p['status']!=='em_andamento') {
            throw new RuntimeException('Operação indisponível nesta fase ou em pleito encerrado.');
        }
        return $p;
    }
    public static function ciclo(int $p): ?array {
        self::verificarEstrutura();
        $s=Database::getConnection()->prepare('SELECT * FROM fase4_ciclos WHERE pleito_id=?');
        $s->execute([$p]); $c=$s->fetch();
        if (!$c) return null;
        $c['categorias']=json_decode($c['categorias_json'],true,512,JSON_THROW_ON_ERROR);
        return $c;
    }
    public static function corte(array $candidatos): array {
        $r=DesempateService::ordenarCandidatos($candidatos)['ranking'];
        $fixos=array_column(array_slice($r,0,3),'candidato_id'); $empatados=[]; $vagas=0;
        if (count($r)>3 && DesempateService::comparar($r[2],$r[3])===0) {
            $fixos=[];
            foreach ($r as $c) {
                $cmp=DesempateService::comparar($c,$r[2]);
                if ($cmp<0) $fixos[]=(int)$c['candidato_id'];
                elseif ($cmp===0) $empatados[]=(int)$c['candidato_id'];
            }
            $vagas=3-count($fixos);
        }
        return ['candidatos'=>$r,'fixos'=>array_map('intval',$fixos),'empatados'=>$empatados,'vagas'=>$vagas,'excepcional'=>[]];
    }
    private static function dadosCandidato(int $p, string $cat, ?array $ids): array {
        $pdo=Database::getConnection();
        if ($ids===[]) return [];
        $sql='SELECT e.*, e.id AS candidato_id,
            (SELECT COUNT(*) FROM urna_votos v WHERE v.pleito_id=? AND v.categoria=? AND v.candidato_id=e.id) AS total_votos,
            (SELECT MAX(t.nota_tacf) FROM avaliacoes_tacf t WHERE t.pleito_id=? AND t.candidato_id=e.id) AS nota_tacf
            FROM efetivo e WHERE e.categoria=?';
        $args=[$p,$cat,$p,$cat];
        if ($ids!==null) { $sql.=' AND e.id IN ('.implode(',',array_fill(0,count($ids),'?')).')'; $args=array_merge($args,$ids); }
        else $sql.=' AND e.ativo=1';
        $sql.=' ORDER BY e.nome_guerra COLLATE utf8mb4_unicode_ci,e.id';
        $s=$pdo->prepare($sql); $s->execute($args);
        $dados=HistoricoVencedores::enriquecer($s->fetchAll(),$p);
        foreach ($dados as &$c) {
            if (in_array($cat,['sppf','sptf'],true)) $c['posto']='CIVIL '.strtoupper($cat);
            // CPF e senha nunca compõem o retrato eleitoral.
            unset($c['cpf'],$c['cpf_hash'],$c['telefone']);
        }
        return $dados;
    }
    /** Chamado dentro da transação de avanço: fecha a urna mesmo quando há corte pendente. */
    public static function preparar(int $p, int $autor): bool {
        $c=self::ciclo($p);
        if (!$c) {
            $categorias=[];
            foreach (self::CATEGORIAS as $cat) {
                $ids=array_map('intval',array_column(Efetivo::listarParaUrna($p,$cat),'id'));
                $categorias[$cat]=self::corte(self::dadosCandidato($p,$cat,$ids));
                if (!$ids) $categorias[$cat]['excepcional']=self::dadosCandidato($p,$cat,null);
            }
            Database::getConnection()->prepare('INSERT INTO fase4_ciclos(pleito_id,categorias_json,encerrado_por) VALUES(?,?,?)')
                ->execute([$p,json_encode($categorias,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$autor]);
        }
        foreach (self::situacao($p) as $cat) if ($cat['pendente']) return false;
        return true;
    }
    public static function situacao(int $p): array {
        $c=self::ciclo($p); if (!$c) return [];
        $s=Database::getConnection()->prepare('SELECT * FROM fase4_decisoes_corte WHERE pleito_id=?'); $s->execute([$p]);
        $decisoes=[]; foreach ($s->fetchAll() as $d) $decisoes[$d['categoria']]=$d;
        $cats=$c['categorias'];
        foreach ($cats as $cat=>&$d) {
            $d['decisao']=$decisoes[$cat]??null;
            $d['pendente']=$d['vagas']>0 && !$d['decisao'];
            $d['selecionados']=$d['fixos'];
            if ($d['decisao']) $d['selecionados']=array_merge($d['fixos'],json_decode($d['decisao']['candidatos_json'],true,512,JSON_THROW_ON_ERROR));
        }
        return $cats;
    }
    public static function decidirCorte(int $p,string $cat,array $ids,string $just,int $autor): void {
        self::verificarEstrutura(); $pdo=Database::getConnection(); $pdo->beginTransaction();
        try {
            self::bloquearPleito($p,3); self::exigirPresidencia($autor);
            $d=self::situacao($p)[$cat]??null; $just=trim($just);
            if (!$d || !$d['pendente']) throw new RuntimeException('Não há decisão de corte pendente nesta categoria.');
            $ids=array_map('intval',$ids);
            if (count($ids)!==(int)$d['vagas'] || count(array_unique($ids))!==count($ids) || array_diff($ids,$d['empatados'])) {
                throw new InvalidArgumentException('Selecione exatamente as vagas restantes, somente entre os empatados exibidos.');
            }
            if ($just==='') throw new InvalidArgumentException('A decisão exige justificativa fundamentada.');
            $pdo->prepare('INSERT INTO fase4_decisoes_corte(pleito_id,categoria,candidatos_json,justificativa,usuario_id) VALUES(?,?,?,?,?)')
                ->execute([$p,$cat,json_encode($ids),$just,$autor]);
            $pdo->commit();
            AuditoriaService::log('DECISAO_CORTE_FASE3',['pleito_id'=>$p,'categoria'=>$cat,'candidatos'=>$ids,'justificativa'=>$just],$autor);
        } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    }
    public static function abrir(int $p): void {
        $pdo=Database::getConnection();
        $s=$pdo->query("SELECT u.id,e.nome_guerra,e.posto FROM usuarios u JOIN efetivo e ON e.id=u.efetivo_id
            WHERE u.ativo=1 AND e.ativo=1 AND e.posto IN ('Cel','Ten Cel','Maj') ORDER BY e.nome_guerra");
        $avaliadores=$s->fetchAll();
        if (!$avaliadores) throw new RuntimeException('Cadastre contas ativas para os Oficiais Superiores antes de abrir a Fase 4.');
        $ins=$pdo->prepare('INSERT INTO fase4_avaliadores(pleito_id,usuario_id,nome_guerra,posto) VALUES(?,?,?,?)');
        foreach ($avaliadores as $u) $ins->execute([$p,$u['id'],$u['nome_guerra']??'',$u['posto']]);
        $pdo->prepare('UPDATE fase4_ciclos SET aberto_em=NOW() WHERE pleito_id=?')->execute([$p]);
    }
    public static function candidatos(int $p): array {
        $out=[];
        foreach (self::situacao($p) as $cat=>$d) {
            if ($d['pendente']) throw new RuntimeException('A Fase 4 está bloqueada: aguarde a decisão da Presidência sobre a terceira vaga.');
            $lista=array_values(array_filter($d['candidatos'],fn($c)=>in_array((int)$c['candidato_id'],$d['selecionados'],true)));
            // Ordenação da apresentação independente da votação, inclusive acentos decompostos.
            usort($lista, fn($a,$b)=>strcmp(self::chaveNome($a['nome_guerra']??''),self::chaveNome($b['nome_guerra']??'')) ?: ((int)$a['candidato_id']<=>(int)$b['candidato_id']));
            $out[$cat]=$lista;
        }
        return $out;
    }
    private static function chaveNome(string $s): string {
        $s=strtr($s,['Á'=>'a','À'=>'a','Â'=>'a','Ã'=>'a','Ä'=>'a','á'=>'a','à'=>'a','â'=>'a','ã'=>'a','ä'=>'a','É'=>'e','È'=>'e','Ê'=>'e','Ë'=>'e','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','Í'=>'i','Ì'=>'i','Î'=>'i','Ï'=>'i','í'=>'i','ì'=>'i','î'=>'i','ï'=>'i','Ó'=>'o','Ò'=>'o','Ô'=>'o','Õ'=>'o','Ö'=>'o','ó'=>'o','ò'=>'o','ô'=>'o','õ'=>'o','ö'=>'o','Ú'=>'u','Ù'=>'u','Û'=>'u','Ü'=>'u','ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u','Ç'=>'c','ç'=>'c']);
        return strtolower(preg_replace('/\p{M}/u','',$s));
    }
    public static function progresso(int $p): array {
        $s=Database::getConnection()->prepare('SELECT a.*, c.confirmado_em FROM fase4_avaliadores a LEFT JOIN fase4_confirmacoes c ON c.pleito_id=a.pleito_id AND c.usuario_id=a.usuario_id WHERE a.pleito_id=? ORDER BY a.nome_guerra');
        $s->execute([$p]); return $s->fetchAll();
    }
    public static function avaliacao(int $p,int $u): array {
        $s=Database::getConnection()->prepare('SELECT * FROM fase4_ordenacoes WHERE pleito_id=? AND usuario_id=? ORDER BY categoria,posicao');
        $s->execute([$p,$u]); $r=[];
        foreach ($s->fetchAll() as $i) $r[$i['categoria']][(int)$i['candidato_id']]=$i;
        return $r;
    }
    public static function confirmar(int $p,int $u,array $categorias): void {
        self::verificarEstrutura(); $pdo=Database::getConnection(); $pdo->beginTransaction();
        try {
            self::bloquearPleito($p,4); $user=self::usuario($u);
            if (!(int)$user['efetivo_ativo'] || !in_array($user['posto'],POSTOS_ESTADO_MAIOR,true)) throw new RuntimeException('Somente Coronéis, Tenentes-Coronéis e Majores ativos podem ordenar.');
            $roster=array_column(self::progresso($p),null,'usuario_id');
            if (!isset($roster[$u])) throw new RuntimeException('Oficial não consta entre os avaliadores habilitados deste pleito.');
            if ($roster[$u]['confirmado_em']) throw new RuntimeException('Avaliação já confirmada. Não é possível alterá-la.');
            $esperados=self::candidatos($p); $normal=[];
            if (array_diff(array_keys($categorias),self::CATEGORIAS)) throw new InvalidArgumentException('Categoria inválida.');
            foreach ($esperados as $cat=>$cands) {
                $itens=$categorias[$cat]??[];
                if (!is_array($itens)) throw new InvalidArgumentException('Ordenação inválida.');
                if (!$cands) { if ($itens) throw new InvalidArgumentException('Categoria vazia não aceita ordenação.'); continue; }
                $normal[$cat]=OrdenacaoEstadoMaior::validar(array_column($cands,'candidato_id'),$itens);
            }
            $pdo->prepare('INSERT INTO fase4_confirmacoes(pleito_id,usuario_id) VALUES(?,?)')->execute([$p,$u]);
            $ins=$pdo->prepare('INSERT INTO fase4_ordenacoes(pleito_id,usuario_id,categoria,candidato_id,posicao,justificativa) VALUES(?,?,?,?,?,?)');
            foreach ($normal as $cat=>$itens) foreach ($itens as $i) $ins->execute([$p,$u,$cat,$i['candidato_id'],$i['posicao'],$i['justificativa']]);
            $pdo->commit(); AuditoriaService::log('ORDENACAO_FASE4_CONFIRMADA',['pleito_id'=>$p],$u);
        } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    }
    public static function exigirConcluida(int $p): void {
        $c=self::ciclo($p);
        if (!$c || !$c['aberto_em']) throw new RuntimeException('Fase 4 não preparada. Encerre a votação e resolva os cortes antes de prosseguir.');
        $r=self::progresso($p);
        if (!$r) throw new RuntimeException('Não há avaliadores habilitados.');
        foreach ($r as $u) if (!$u['confirmado_em']) throw new RuntimeException('Aguarde a confirmação de todos os Oficiais Superiores habilitados.');
    }
    public static function compararResultado(array $a,array $b): int {
        return DesempateService::comparar($a,$b,'pontos_fase4') ?: ((int)$b['total_votos']<=>(int)$a['total_votos']);
    }
    public static function resultados(int $p): array {
        self::exigirConcluida($p);
        $s=Database::getConnection()->prepare('SELECT categoria,candidato_id,SUM(CASE posicao WHEN 1 THEN 5 WHEN 2 THEN 3 WHEN 3 THEN 1 ELSE 0 END) pontos FROM fase4_ordenacoes WHERE pleito_id=? GROUP BY categoria,candidato_id');
        $s->execute([$p]); $pontos=[]; foreach ($s->fetchAll() as $r) $pontos[$r['categoria']][$r['candidato_id']]=(int)$r['pontos'];
        $out=[]; $sit=self::situacao($p);
        foreach (self::candidatos($p) as $cat=>$lista) {
            foreach ($lista as &$c) $c['pontos_fase4']=$pontos[$cat][$c['candidato_id']]??0;
            unset($c); usort($lista,[self::class,'compararResultado']);
            $emp=[]; foreach ($lista as $c) { if (self::compararResultado($lista[0],$c)!==0) break; $emp[]=$c; }
            $excepcional=!$lista;
            // Nunca consultar todo o efetivo quando há um, dois ou três finalistas.
            $opcoes=$excepcional?$sit[$cat]['excepcional']:(count($emp)>1?$emp:[]);
            $out[$cat]=['ranking'=>$lista,'vencedor'=>count($emp)===1?$emp[0]:null,'requer_voto_de_minerva'=>$excepcional||count($emp)>1,
                'candidatos_empatados'=>$opcoes,'excepcional'=>$excepcional];
        }
        return $out;
    }
}
