<?php
declare(strict_types=1);
require_once __DIR__ . '/EstadoMaior.php';

class Homologacao {
    public static function apurarResultados(int $p): array {
        $pdo=Database::getConnection();
        $s=$pdo->prepare('SELECT * FROM pleitos WHERE id=?'); $s->execute([$p]); $pleito=$s->fetch();
        if (!$pleito || (int)$pleito['fase_atual']!==5) throw new RuntimeException('A apuração final estará disponível na Fase 5.');
        // Homologações legadas permanecem consultáveis; nunca converter resultados antigos.
        if ($pleito['status']==='homologado') {
            $existe=$pdo->query("SHOW TABLES LIKE 'fase4_ciclos'")->fetch();
            $ciclo=null;
            if ($existe) { $s=$pdo->prepare('SELECT pleito_id FROM fase4_ciclos WHERE pleito_id=?'); $s->execute([$p]); $ciclo=$s->fetch(); }
            if (!$ciclo) return self::apurarLegado($p);
        }
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
                $r['ranking']=array_values(array_filter($r['ranking'],fn($c)=>(int)$c['candidato_id']!==$id));
                array_unshift($r['ranking'],$escolhido);
            }
        }
        unset($r);
        $s=$pdo->prepare('SELECT * FROM homologacao_pleito WHERE pleito_id=?'); $s->execute([$p]); $homol=$s->fetch();
        return ['categorias'=>$resultados,'homologado'=>(bool)$homol,'dados_homologacao'=>$homol?:null];
    }
    public static function registrarVotoMinerva(int $pleitoId,string $categoria,int $candidatoId,string $justificativa,int $presidenteUsuarioId): array {
        $pdo=Database::getConnection(); $pdo->beginTransaction();
        try {
            EstadoMaior::bloquearPleito($pleitoId,5); EstadoMaior::exigirPresidencia($presidenteUsuarioId);
            $r=EstadoMaior::resultados($pleitoId)[$categoria]??null;
            if (!$r || !$r['requer_voto_de_minerva'] || !in_array($candidatoId,array_map('intval',array_column($r['candidatos_empatados'],'candidato_id')),true)) {
                throw new RuntimeException('Escolha não autorizada: somente empatados finais ou o efetivo da categoria comprovadamente sem finalistas.');
            }
            $justificativa=trim($justificativa);
            if ($justificativa==='') throw new InvalidArgumentException('A decisão presidencial exige justificativa fundamentada.');
            $pdo->prepare('INSERT INTO voto_minerva_presidente(pleito_id,categoria,candidato_vencedor_id,justificativa,presidente_usuario_id,registrado_em)
                VALUES(?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE candidato_vencedor_id=VALUES(candidato_vencedor_id),justificativa=VALUES(justificativa),presidente_usuario_id=VALUES(presidente_usuario_id),registrado_em=NOW()')
                ->execute([$pleitoId,$categoria,$candidatoId,$justificativa,$presidenteUsuarioId]);
            $pdo->commit();
            AuditoriaService::log($r['excepcional']?'ESCOLHA_EXCEPCIONAL_CATEGORIA_VAZIA':'VOTO_MINERVA_REGISTRADO',
                ['pleito_id'=>$pleitoId,'categoria'=>$categoria,'candidato_id'=>$candidatoId,'justificativa'=>$justificativa],$presidenteUsuarioId);
            return ['sucesso'=>true,'mensagem'=>'Decisão presidencial gravada com fundamentação.'];
        } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); return ['sucesso'=>false,'mensagem'=>$e->getMessage()]; }
    }
    public static function homologarResultados(int $pleitoId,int $presidenteUsuarioId,string $ata): array {
        $pdo=Database::getConnection(); $pdo->beginTransaction();
        try {
            EstadoMaior::bloquearPleito($pleitoId,5); EstadoMaior::exigirPresidencia($presidenteUsuarioId);
            if (mb_strlen(trim($ata))<20) throw new InvalidArgumentException('A ata deve conter pelo menos 20 caracteres.');
            $apuracao=self::apurarResultados($pleitoId); $ids=[];
            foreach (EstadoMaior::CATEGORIAS as $cat) {
                $r=$apuracao['categorias'][$cat];
                if (!$r['vencedor']) throw new RuntimeException('Homologação bloqueada: falta definir o vencedor de '.CATEGORIAS_PADRAO[$cat]['nome'].'.');
                $ids[]=(int)$r['vencedor']['candidato_id'];
            }
            $pdo->prepare('INSERT INTO homologacao_pleito(pleito_id,presidente_usuario_id,vencedor_graduado_id,vencedor_praca_id,vencedor_sppf_id,vencedor_sptf_id,ata_homologacao,data_homologacao,bloqueado) VALUES(?,?,?,?,?,?,?,NOW(),1)')
                ->execute(array_merge([$pleitoId,$presidenteUsuarioId],$ids,[$ata]));
            $pdo->prepare('UPDATE pleitos SET status="homologado",homologado_em=NOW(),homologado_por=? WHERE id=?')->execute([$presidenteUsuarioId,$pleitoId]);
            $pdo->prepare('UPDATE efetivo SET indicacoes_anteriores_qtd=indicacoes_anteriores_qtd+1 WHERE id IN (?,?,?,?)')->execute($ids);
            $pdo->commit(); AuditoriaService::log('HOMOLOGACAO_OFICIAL_CONCLUIDA',['pleito_id'=>$pleitoId,'vencedores'=>$ids],$presidenteUsuarioId);
            return ['sucesso'=>true,'mensagem'=>'Pleito homologado com quatro vencedores e resultados bloqueados.'];
        } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); return ['sucesso'=>false,'mensagem'=>$e->getMessage()]; }
    }
    private static function apurarLegado(int $pleitoId): array {
        $pdo = Database::getConnection();
        $categorias = [CAT_GRADUADO, CAT_PRACA, CAT_SPPF, CAT_SPTF];
        $resultados = [];

        // Votos de minerva já registrados pelo presidente
        $stmtMin = $pdo->prepare('SELECT * FROM voto_minerva_presidente WHERE pleito_id = :p');
        $stmtMin->execute([':p' => $pleitoId]);
        $minervas = [];
        foreach ($stmtMin->fetchAll() as $vm) {
            $minervas[$vm['categoria']] = $vm;
        }

        foreach ($categorias as $cat) {
            // Contagem de votos na urna anônima
            $sql = "
                SELECT e.id as candidato_id, e.nome, e.nome_guerra, e.posto, e.quadro, e.especialidade,
                       e.setor, e.divisao_sigla, e.funcao, e.saram, e.foto_custom,
                       e.data_admissao_comara, e.data_inicio_servico,
                       COALESCE(MAX(t.nota_tacf), 0) as nota_tacf,
                       COUNT(u.id) as total_votos
                FROM indicados_consolidados i
                INNER JOIN efetivo e ON e.id = i.candidato_id
                LEFT JOIN avaliacoes_tacf t ON t.candidato_id = e.id AND t.pleito_id = :p
                LEFT JOIN urna_votos u ON u.candidato_id = e.id AND u.pleito_id = :p AND u.categoria = :cat
                WHERE i.pleito_id = :p 
                  AND i.categoria = :cat
                  AND i.status_validacao_superior = 'VALIDADO'
                GROUP BY e.id
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([':p' => $pleitoId, ':cat' => $cat]);
            $candidatos = $stmt->fetchAll();

            require_once __DIR__ . '/HistoricoVencedores.php';
            $candidatos = HistoricoVencedores::enriquecer($candidatos, $pleitoId);

            // Aplica ordenação e desempate automatizado
            $apuracao = DesempateService::ordenarCandidatos($candidatos, 'total_votos');
            $ranking = $apuracao['ranking'];
            $requerMinerva = $apuracao['requer_voto_de_minerva'];

            // Se o presidente já deu voto de minerva nesta categoria, define o vencedor escolhido
            $vencedor = null;
            if (isset($minervas[$cat])) {
                $candMinervaId = (int)$minervas[$cat]['candidato_vencedor_id'];
                foreach ($ranking as $idx => $c) {
                    if ((int)$c['candidato_id'] === $candMinervaId) {
                        $vencedor = $c;
                        // Move para o topo do ranking
                        unset($ranking[$idx]);
                        array_unshift($ranking, $vencedor);
                        $requerMinerva = false;
                        break;
                    }
                }
            } elseif (!empty($ranking) && !$requerMinerva) {
                $vencedor = $ranking[0];
            }

            $resultados[$cat] = [
                'categoria'              => CATEGORIAS_PADRAO[$cat],
                'ranking'                => $ranking,
                'vencedor'               => $vencedor,
                'requer_voto_de_minerva' => $requerMinerva,
                'candidatos_empatados'   => $apuracao['candidatos_empatados'],
                'minerva_registrado'     => $minervas[$cat] ?? null
            ];
        }

        // Verifica se já foi homologado
        $stmtHomol = $pdo->prepare('SELECT * FROM homologacao_pleito WHERE pleito_id = :p LIMIT 1');
        $stmtHomol->execute([':p' => $pleitoId]);
        $homol = $stmtHomol->fetch();

        return [
            'categorias'          => $resultados,
            'homologado'          => (bool)$homol,
            'dados_homologacao'   => $homol ?: null
        ];
    }


}
