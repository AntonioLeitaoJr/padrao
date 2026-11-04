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
        $s=$pdo->prepare('SELECT * FROM voto_minerva_presidente WHERE pleito_id=?'); $s->execute([$p]); $min=[];
        foreach ($s->fetchAll() as $m) $min[$m['categoria']]=$m;
        foreach ($resultados as $cat=>&$r) {
            $r['categoria']=CATEGORIAS_PADRAO[$cat]; $r['minerva_registrado']=$min[$cat]??null;
            if (isset($min[$cat])) {
                $id=(int)$min[$cat]['candidato_vencedor_id']; $escolhido=null;
                foreach ($r['candidatos_empatados'] as $c) if ((int)$c['candidato_id']===$id) $escolhido=$c;
                if (!$escolhido) throw new RuntimeException('Decisão presidencial incompatível com os candidatos habilitados. Solicite conferência administrativa.');
                $escolhido['pontos_fase4']=$escolhido['pontos_fase4']??0;
                $r['vencedor']=$escolhido; $r['requer_voto_de_minerva']=false;
                $r['criterio_resultado']=$r['excepcional']?'Escolha excepcional fundamentada da Presidência.':'Voto de minerva fundamentado entre os empatados finais.';
                $r['ranking']=array_values(array_filter($r['ranking'],fn($c)=>(int)$c['candidato_id']!==$id));
                array_unshift($r['ranking'],$escolhido);
            }
        }
        unset($r);
        $s=$pdo->prepare('SELECT * FROM homologacao_pleito WHERE pleito_id=?'); $s->execute([$p]); $homol=$s->fetch();
        return ['categorias'=>$resultados,'homologado'=>(bool)$homol,'dados_homologacao'=>$homol?:null];
    }
    public static function registrarVotoMinerva(int $pleitoId,string $categoria,int $candidatoId,string $justificativa,int $presidenteUsuarioId, ?string $versao = null): array {
        $pdo=Database::getConnection(); $pdo->beginTransaction();
        try {
            EstadoMaior::bloquearPleito($pleitoId,5); EstadoMaior::exigirPresidencia($presidenteUsuarioId);
            self::verificarEstrutura();
            self::exigirSemHomologacao($pleitoId);
            $r=EstadoMaior::resultados($pleitoId)[$categoria]??null;
            if (!$r || !$r['requer_voto_de_minerva'] || !in_array($candidatoId,array_map('intval',array_column($r['candidatos_empatados'],'candidato_id')),true)) {
                throw new RuntimeException('Escolha não autorizada: somente empatados finais ou o efetivo da categoria comprovadamente sem finalistas.');
            }
            $justificativa=preg_replace('/^\s+|\s+$/u','',$justificativa);
            if ($justificativa===null || $justificativa==='') throw new InvalidArgumentException('A decisão presidencial exige justificativa fundamentada.');
            $s=$pdo->prepare('SELECT * FROM voto_minerva_presidente WHERE pleito_id=? AND categoria=?');
            $s->execute([$pleitoId,$categoria]); $anterior=$s->fetch() ?: null;
            if ($anterior && (int)$anterior['candidato_vencedor_id']===$candidatoId && $anterior['justificativa']===$justificativa) {
                $pdo->commit(); return ['sucesso'=>true,'mensagem'=>'Esta decisão já está registrada.'];
            }
            if ($versao!==null && !hash_equals(self::versaoDecisao($anterior),$versao)) throw new RuntimeException('A decisão mudou em outra sessão. Recarregue e confira antes de salvar.');
            $pdo->prepare('INSERT INTO voto_minerva_presidente(pleito_id,categoria,candidato_vencedor_id,justificativa,presidente_usuario_id,registrado_em)
                VALUES(?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE candidato_vencedor_id=VALUES(candidato_vencedor_id),justificativa=VALUES(justificativa),presidente_usuario_id=VALUES(presidente_usuario_id),registrado_em=NOW()')
                ->execute([$pleitoId,$categoria,$candidatoId,$justificativa,$presidenteUsuarioId]);
            AuditoriaService::log($r['excepcional']?'ESCOLHA_EXCEPCIONAL_CATEGORIA_VAZIA':'VOTO_MINERVA_REGISTRADO',
                ['pleito_id'=>$pleitoId,'categoria'=>$categoria,'candidato_id'=>$candidatoId,'justificativa'=>$justificativa,'anterior'=>$anterior],$presidenteUsuarioId,null,true);
            $pdo->commit();
            return ['sucesso'=>true,'mensagem'=>'Decisão presidencial gravada com fundamentação.'];
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
        if (!Database::getConnection()->query("SHOW TABLES LIKE 'fase5_resultados'")->fetch()) throw new RuntimeException('Atualização necessária: STI, execute php database/migrate_fase5.php após as migrações da Fase 2 e das Fases 3–4.');
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
        $s=Database::getConnection()->prepare('SELECT o.*,a.nome_guerra AS avaliador_nome,a.posto AS avaliador_posto FROM fase4_ordenacoes o JOIN fase4_avaliadores a ON a.pleito_id=o.pleito_id AND a.usuario_id=o.usuario_id WHERE o.pleito_id=? ORDER BY a.nome_guerra,o.categoria,o.posicao');
        $s->execute([$p]); $avaliacoes=$s->fetchAll();
        return $avaliacoes;
    }
}
