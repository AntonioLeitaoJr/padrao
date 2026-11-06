<?php
declare(strict_types=1);
require_once __DIR__ . '/EstadoMaior.php';

class Homologacao {
    public static function apurarResultados(int $p): array {
        $pdo=Database::getConnection(); $propria=!$pdo->inTransaction();
        if ($propria) $pdo->beginTransaction();
        try {
            // Uma leitura não mistura decisão provisória com homologação concorrente.
            $s=$pdo->prepare('SELECT id FROM pleitos WHERE id=? FOR UPDATE'); $s->execute([$p]);
            $resultado=self::apurarInterno($p);
            if ($propria) $pdo->commit();
            return $resultado;
        } catch (Throwable $e) { if ($propria && $pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    }
    private static function apurarInterno(int $p): array {
        $pdo=Database::getConnection();
        $s=$pdo->prepare('SELECT * FROM pleitos WHERE id=?'); $s->execute([$p]); $pleito=$s->fetch();
        if (!$pleito || (int)$pleito['fase_atual']!==5) throw new RuntimeException('A apuração final estará disponível na Fase 5.');
        $s=$pdo->prepare('SELECT * FROM homologacao_pleito WHERE pleito_id=?'); $s->execute([$p]); $homol=$s->fetch();
        if ($homol) {
            if ($pleito['status']!=='homologado') throw new RuntimeException('Status incompatível com a homologação registrada. Solicite conferência institucional; os registros serão preservados.');
            return self::consultarHomologado($homol);
        }
        if ($pleito['status']==='homologado') throw new RuntimeException('Ata de homologação ausente. Solicite conferência institucional; nenhum resultado será recalculado.');
        $resultados=EstadoMaior::resultados($p);
        self::verificarEstrutura();
        $s=$pdo->prepare('SELECT * FROM fase5_decisoes WHERE pleito_id=?'); $s->execute([$p]);
        $decisoes=[]; foreach ($s->fetchAll() as $d) $decisoes[$d['categoria']]=$d;
        foreach ($resultados as $cat=>&$r) {
            $r['categoria']=CATEGORIAS_PADRAO[$cat]; $r['referencia_fase4']=$r['ranking'];
            $r['decisao_presidencial']=$decisoes[$cat]??null;
            $r['minerva_registrado']=null; $r['vencedor']=null; $r['excluidos']=[];
            $r['criterio_resultado']='Aguardando ordenação expressa da Presidência.';
            if (isset($decisoes[$cat])) {
                $d=json_decode($decisoes[$cat]['decisao_json'],true,512,JSON_THROW_ON_ERROR);
                $r['ranking']=$d['ranking']; $r['excluidos']=$d['excluidos'];
                $r['vencedor']=$r['ranking'][0]??null;
                $r['criterio_resultado']='Ordenação fundamentada da Presidência.';
            }
        }
        unset($r);
        return ['categorias'=>$resultados,'homologado'=>false,'dados_homologacao'=>null];
    }
    public static function registrarVotoMinerva(int $p,string $cat,int $id,string $just,int $autor,?string $versao=null): array {
        return ['sucesso'=>false,'mensagem'=>'Utilize a ordenação presidencial e defina expressamente as posições da categoria.'];
    }
    public static function opcoesCategoria(int $p,string $cat): array {
        if (!in_array($cat,EstadoMaior::CATEGORIAS,true)) throw new InvalidArgumentException('Categoria inválida.');
        return EstadoMaior::opcoesPresidenciais($p,$cat);
    }
    public static function salvarOrdenacao(int $p,string $cat,array $escolhas,string $just,int $autor,string $versao): array {
        $pdo=Database::getConnection(); $pdo->beginTransaction();
        try {
            EstadoMaior::bloquearPleito($p,5); EstadoMaior::exigirPresidencia($autor);
            self::verificarEstrutura(); self::exigirSemHomologacao($p);
            $r=self::apurarInterno($p)['categorias'][$cat]??null;
            if (!$r) throw new InvalidArgumentException('Categoria inválida.');
            if (!hash_equals(self::versaoDecisao($r['decisao_presidencial']),$versao)) throw new RuntimeException('A decisão mudou em outra sessão. Recarregue antes de salvar.');
            $just=preg_replace('/^\s+|\s+$/u','',$just);
            if ($just===null || $just==='') throw new InvalidArgumentException('Fundamente a ordenação, inclusões e exclusões desta categoria.');
            $opcoes=array_column(self::opcoesCategoria($p,$cat),null,'candidato_id');
            $referencias=array_column($r['referencia_fase4'],null,'candidato_id');
            $ranking=[]; $excluidos=[]; $vistos=[];
            foreach ($escolhas as $item) {
                if (!is_array($item) || !is_scalar($item['candidato_id']??null) || !ctype_digit((string)$item['candidato_id']) || (int)$item['candidato_id']<1 || !is_scalar($item['posicao']??null)) throw new InvalidArgumentException('Escolha malformada.');
                $id=(int)$item['candidato_id']; $pos=(string)$item['posicao'];
                if (isset($vistos[$id])) throw new InvalidArgumentException('Candidato repetido.');
                $vistos[$id]=true;
                if ($pos==='excluir') {
                    $antigos=array_column(array_merge($r['ranking'],$r['excluidos']),null,'candidato_id');
                    if (!isset($referencias[$id]) && !isset($opcoes[$id]) && !isset($antigos[$id])) throw new InvalidArgumentException('Candidato fora da categoria.');
                    $excluidos[]=$referencias[$id]??$opcoes[$id]??$antigos[$id]; continue;
                }
                if (!in_array($pos,['1','2','3'],true) || isset($ranking[(int)$pos])) throw new InvalidArgumentException('Defina posições de 1º a 3º, sem repetição, ou exclua o candidato.');
                if (!isset($opcoes[$id])) throw new InvalidArgumentException('Selecione somente integrantes ativos da mesma categoria.');
                $c=$referencias[$id]??$opcoes[$id]; $c['posicao_fase5']=(int)$pos;
                $c['incluido_presidencia']=!isset($referencias[$id]); $ranking[(int)$pos]=$c;
            }
            foreach ($referencias as $id=>$c) if (!isset($vistos[$id])) throw new InvalidArgumentException('Ordene ou exclua expressamente cada finalista da Fase 4.');
            ksort($ranking);
            if (!$ranking || array_keys($ranking)!==range(1,count($ranking))) throw new InvalidArgumentException('Defina ao menos o 1º colocado, sem lacunas na classificação.');
            $d=['ranking'=>array_values($ranking),'excluidos'=>$excluidos];
            $pdo->prepare('INSERT INTO fase5_decisoes(pleito_id,categoria,decisao_json,justificativa,usuario_id) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE decisao_json=VALUES(decisao_json),justificativa=VALUES(justificativa),usuario_id=VALUES(usuario_id),registrado_em=NOW()')->execute([$p,$cat,json_encode($d,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$just,$autor]);
            AuditoriaService::log('ORDENACAO_PRESIDENCIAL_FASE5',['pleito_id'=>$p,'categoria'=>$cat,'anterior'=>$r['decisao_presidencial'],'decisao'=>$d,'justificativa'=>$just],$autor,null,true);
            $pdo->commit(); return ['sucesso'=>true,'mensagem'=>'Ordenação presidencial salva. Confira todas as categorias antes de homologar.'];
        } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); return ['sucesso'=>false,'mensagem'=>self::mensagemErro($e)]; }
    }
    public static function homologarResultados(int $pleitoId,int $presidenteUsuarioId,string $ata, ?string $versao = null): array {
        $pdo=Database::getConnection(); $pdo->beginTransaction();
        try {
            EstadoMaior::bloquearPleito($pleitoId,5); EstadoMaior::exigirPresidencia($presidenteUsuarioId);
            self::verificarEstrutura();
            self::exigirSemHomologacao($pleitoId);
            $ata=preg_replace('/^\s+|\s+$/u','',$ata);
            if ($ata===null || mb_strlen($ata)<20) throw new InvalidArgumentException('A ata deve conter pelo menos 20 caracteres.');
            $apuracao=self::apurarResultados($pleitoId); $ids=[];
            if ($versao!==null && !hash_equals(self::versaoApuracao($apuracao),$versao)) throw new RuntimeException('A apuração mudou. Recarregue e confira os quatro vencedores antes de homologar.');
            foreach (EstadoMaior::CATEGORIAS as $cat) {
                $r=$apuracao['categorias'][$cat];
                if (!$r['vencedor']) throw new RuntimeException('Homologação bloqueada: falta definir o vencedor de '.CATEGORIAS_PADRAO[$cat]['nome'].'.');
                $ativos=array_map('intval',array_column(self::opcoesCategoria($pleitoId,$cat),'candidato_id'));
                foreach ($r['ranking'] as $c) if (!in_array((int)$c['candidato_id'],$ativos,true)) throw new RuntimeException('Há integrante inativo ou de outra categoria na ordenação. Revise a decisão antes de homologar.');
                $ids[]=(int)$r['vencedor']['candidato_id'];
            }
            $pdo->prepare('INSERT INTO homologacao_pleito(pleito_id,presidente_usuario_id,vencedor_graduado_id,vencedor_praca_id,vencedor_sppf_id,vencedor_sptf_id,ata_homologacao,data_homologacao,bloqueado) VALUES(?,?,?,?,?,?,?,NOW(),1)')
                ->execute(array_merge([$pleitoId,$presidenteUsuarioId],$ids,[$ata]));
            $pdo->prepare('INSERT INTO fase5_resultados(pleito_id,resultado_json) VALUES(?,?)')->execute([$pleitoId,json_encode($apuracao['categorias'],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
            $pdo->prepare('UPDATE pleitos SET status="homologado",homologado_em=NOW(),homologado_por=? WHERE id=?')->execute([$presidenteUsuarioId,$pleitoId]);
            $pdo->prepare('UPDATE efetivo SET indicacoes_anteriores_qtd=indicacoes_anteriores_qtd+1 WHERE id IN (?,?,?,?)')->execute($ids);
            AuditoriaService::log('HOMOLOGACAO_OFICIAL_CONCLUIDA',['pleito_id'=>$pleitoId,'vencedores'=>$ids],$presidenteUsuarioId,null,true);
            $pdo->commit();
            return ['sucesso'=>true,'mensagem'=>'Pleito homologado com quatro vencedores e resultados bloqueados.'];
        } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); return ['sucesso'=>false,'mensagem'=>self::mensagemErro($e)]; }
    }
    public static function versaoDecisao(?array $decisao): string {
        return hash('sha256',json_encode($decisao,JSON_THROW_ON_ERROR));
    }
    public static function versaoApuracao(array $apuracao): string {
        return hash('sha256',json_encode($apuracao['categorias'],JSON_THROW_ON_ERROR));
    }
    private static function mensagemErro(Throwable $e): string {
        error_log('[FASE5] '.$e->getMessage());
        return $e instanceof PDOException ? 'Falha na gravação. Nenhuma alteração foi concluída. Solicite suporte ao STI.' : $e->getMessage();
    }
    private static function verificarEstrutura(): void {
        EstadoMaior::verificarEstrutura();
        if (!Database::getConnection()->query("SHOW TABLES LIKE 'fase5_resultados'")->fetch() || !Database::getConnection()->query("SHOW TABLES LIKE 'fase5_decisoes'")->fetch()) throw new RuntimeException('Atualização necessária: STI, execute php database/migrate_fase5.php após as migrações da Fase 2 e das Fases 3–4.');
    }
    private static function exigirSemHomologacao(int $p): void {
        $s=Database::getConnection()->prepare('SELECT pleito_id FROM homologacao_pleito WHERE pleito_id=?'); $s->execute([$p]);
        if ($s->fetch()) throw new RuntimeException('Homologação já registrada; alterações bloqueadas.');
    }
    private static function consultarHomologado(array $h): array {
        $pdo=Database::getConnection(); $cats=null;
        if ($pdo->query("SHOW TABLES LIKE 'fase5_resultados'")->fetch()) {
            $s=$pdo->prepare('SELECT resultado_json FROM fase5_resultados WHERE pleito_id=?'); $s->execute([$h['pleito_id']]);
            $json=$s->fetchColumn();
            if ($json!==false) $cats=json_decode($json,true,512,JSON_THROW_ON_ERROR);
        }
        $legado=$cats===null;
        if ($legado) {
            // IDs e ata oficiais são a fonte. Não reaplicar critérios a registros antigos.
            $cats=[];
            foreach (EstadoMaior::CATEGORIAS as $cat) {
                $id=(int)$h['vencedor_'.$cat.'_id'];
                $s=$pdo->prepare('SELECT id AS candidato_id,nome,nome_guerra,posto,setor,divisao_sigla,saram,quadro,especialidade FROM efetivo WHERE id=?'); $s->execute([$id]);
                $v=$s->fetch();
                if (!$v) throw new RuntimeException('Vencedor da ata não encontrado. Solicite conferência institucional; resultado preservado sem recálculo.');
                $cats[$cat]=['categoria'=>CATEGORIAS_PADRAO[$cat],'vencedor'=>$v,'ranking'=>[],
                    'requer_voto_de_minerva'=>false,'candidatos_empatados'=>[],'minerva_registrado'=>null,'excepcional'=>false,
                    'criterio_resultado'=>'Vencedor registrado na homologação original. Pontuações e dados históricos não reconstruídos.'];
            }
        }
        foreach (EstadoMaior::CATEGORIAS as $cat) {
            if ((int)($cats[$cat]['vencedor']['candidato_id']??0)!==(int)$h['vencedor_'.$cat.'_id']) throw new RuntimeException('Resultado arquivado incompatível com a ata. Solicite conferência institucional.');
        }
        return ['categorias'=>$cats,'homologado'=>true,'dados_homologacao'=>$h,'legado'=>$legado];
    }
    public static function avaliacoesIndividuais(int $p,int $usuario): array {
        EstadoMaior::exigirPresidencia($usuario);
        $s=Database::getConnection()->prepare('SELECT fase_atual FROM pleitos WHERE id=?'); $s->execute([$p]);
        if ((int)$s->fetchColumn()!==5) throw new RuntimeException('Consulta individual disponível à Presidência na Fase 5.');
        if (!Database::getConnection()->query("SHOW TABLES LIKE 'fase4_ordenacoes'")->fetch()) return [];
        $s=Database::getConnection()->prepare('SELECT o.*,a.nome_guerra AS avaliador_nome,a.posto AS avaliador_posto FROM fase4_ordenacoes o JOIN fase4_composicao m ON m.pleito_id=o.pleito_id AND m.usuario_id=o.usuario_id AND m.habilitado=1 JOIN fase4_avaliadores a ON a.pleito_id=o.pleito_id AND a.usuario_id=o.usuario_id WHERE o.pleito_id=? ORDER BY a.nome_guerra,o.categoria,o.posicao');
        $s->execute([$p]); $avaliacoes=$s->fetchAll();
        return $avaliacoes;
    }
}
