<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/AuditoriaService.php';

/** Limpeza operacional assistida: mantém estrutura, auditoria, divisões e contas ADMIN. */
final class ZeramentoService {
    private const FILHAS = [
        'fase5_resultados', 'fase5_decisoes', 'fase4_ordenacoes',
        'fase4_confirmacoes', 'fase4_avaliadores', 'fase4_decisoes_corte',
        'fase4_composicao', 'fase4_ciclos', 'voto_minerva_presidente',
        'homologacao_pleito', 'eleitores_ciclo', 'urna_votos',
        'indicados_consolidados', 'selecao_fase2', 'fichas_indicacao',
        'avaliacoes_tacf', 'delegacoes_fase1', 'historico_fases_pleito',
        'historico_vencedores'
    ];
    private const PRESERVADAS = ['usuarios', 'efetivo', 'pleitos', 'divisoes_assessorias', 'logs_auditoria'];

    public static function habilitado(): bool {
        return env('ALLOW_DATABASE_RESET', false) === true;
    }

    private static function q(string $t): string {
        if (!preg_match('/^[a-z_0-9]+$/D', $t)) throw new RuntimeException('Nome de tabela inválido.');
        return chr(96).$t.chr(96);
    }

    private static function existentes(PDO $pdo): array {
        $linhas = $pdo->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM);
        $tabelas = [];
        foreach ($linhas as $linha) {
            if (($linha[1] ?? '') !== 'BASE TABLE') {
                throw new RuntimeException('Há objetos de banco não suportados. Consulte a STI.');
            }
            $tabelas[] = $linha[0];
        }
        $permitidas = array_merge(self::FILHAS, self::PRESERVADAS);
        foreach ($tabelas as $t) {
            if (!in_array($t, $permitidas, true)) {
                throw new RuntimeException('Há tabelas adicionais; zeramento bloqueado até revisão da STI.');
            }
        }
        foreach (self::PRESERVADAS as $t) {
            if (!in_array($t, $tabelas, true)) throw new RuntimeException('Esquema obrigatório incompleto.');
        }
        return $tabelas;
    }

    public static function resumo(): array {
        $pdo = Database::getConnection();
        $tabelas = self::existentes($pdo);
        $contagens = [];
        foreach ($tabelas as $t) {
            $contagens[$t] = (int)$pdo->query('SELECT COUNT(*) FROM '.self::q($t))->fetchColumn();
        }
        $homologados = (int)$pdo->query("SELECT COUNT(*) FROM pleitos WHERE status='homologado' OR homologado_em IS NOT NULL")->fetchColumn();
        foreach (['homologacao_pleito', 'fase5_resultados'] as $tab) {
            if (in_array($tab, $tabelas, true)) {
                $homologados += (int)$pdo->query('SELECT COUNT(*) FROM '.self::q($tab))->fetchColumn();
            }
        }
        $admins = (int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE perfil='ADMIN' AND ativo=1 AND senha_hash IS NOT NULL")->fetchColumn();
        $pleito = $pdo->query('SELECT id,ano,titulo FROM pleitos ORDER BY ano DESC,id DESC LIMIT 1')->fetch() ?: null;
        $dados = [$contagens,$homologados,$admins,$pleito];
        return [
            'contagens'=>$contagens,'homologados'=>$homologados,
            'admins_ativos'=>$admins,'pleito_referencia'=>$pleito,
            'fingerprint'=>hash('sha256',json_encode($dados,JSON_THROW_ON_ERROR))
        ];
    }

    public static function executar(int $adminId,string $fingerprint): array {
        if (!self::habilitado()) throw new RuntimeException('Zeramento bloqueado pela configuração da STI.');
        $pdo = Database::getConnection();
        if ((int)$pdo->query("SELECT GET_LOCK('comara_reset_operacional_v1',0)")->fetchColumn() !== 1) {
            throw new RuntimeException('Outra operação de zeramento já está em andamento.');
        }
        try {
            $q = $pdo->prepare("SELECT id FROM usuarios WHERE id=? AND perfil='ADMIN' AND ativo=1 AND senha_hash IS NOT NULL");
            $q->execute([$adminId]);
            if (!$q->fetch()) throw new RuntimeException('Conta ADMIN inválida ou inativa.');
            $antes = self::resumo();
            if (!hash_equals($fingerprint,$antes['fingerprint'])) {
                throw new RuntimeException('Os dados mudaram desde a confirmação. Refaça a preparação.');
            }
            if ($antes['homologados'] !== 0) {
                throw new RuntimeException('Existe pleito homologado. O resultado oficial não pode ser apagado.');
            }
            if ($antes['admins_ativos'] < 1) throw new RuntimeException('Nenhuma conta ADMIN recuperável.');
            // Backup obrigatório fora da transação de limpeza, antes de qualquer exclusão.
            $backup = self::backupPreZeramento($pdo);
            $pdo->beginTransaction();
            try {
                $pdo->query('SELECT id FROM pleitos ORDER BY id FOR UPDATE')->fetchAll();
                $agora = self::resumo();
                if (!hash_equals($fingerprint,$agora['fingerprint']) || $agora['homologados'] !== 0) {
                    throw new RuntimeException('O pleito foi alterado; nenhuma exclusão foi realizada.');
                }
                $presentes = array_keys($agora['contagens']);
                foreach (self::FILHAS as $t) {
                    if (in_array($t,$presentes,true)) $pdo->exec('DELETE FROM '.self::q($t));
                }
                $pdo->exec("DELETE FROM usuarios WHERE perfil<>'ADMIN'");
                $pdo->exec("UPDATE usuarios SET efetivo_id=NULL,orgao_chefe_sigla=NULL WHERE perfil='ADMIN'");
                $pdo->exec('UPDATE divisoes_assessorias SET chefe_usuario_id=NULL');
                $pdo->exec('DELETE FROM efetivo');
                $pleitoId = (int)($antes['pleito_referencia']['id'] ?? 0);
                if ($pleitoId) {
                    $pdo->prepare('DELETE FROM pleitos WHERE id<>?')->execute([$pleitoId]);
                    $pdo->prepare("UPDATE pleitos SET fase_atual=1,status='planejamento',
                        fase1_inicio=NULL,fase1_fim=NULL,fase2_inicio=NULL,fase2_fim=NULL,
                        fase3_inicio=NULL,fase3_fim=NULL,fase4_inicio=NULL,fase4_fim=NULL,
                        fase5_inicio=NULL,fase5_fim=NULL,homologado_em=NULL,homologado_por=NULL
                        WHERE id=?")->execute([$pleitoId]);
                } else {
                    $ano=(int)date('Y');
                    $pdo->prepare("INSERT INTO pleitos(ano,titulo,fase_atual,status) VALUES(?,?,1,'planejamento')")
                        ->execute([$ano,'Eleição dos Padrões COMARA '.$ano]);
                }
                AuditoriaService::log('ZERAMENTO_BASE_OPERACIONAL',[
                    'backup'=>$backup['arquivo'],'sha256'=>$backup['sha256'],
                    'contagens_anteriores'=>$antes['contagens'],
                    'pleito_referencia'=>$pleitoId,
                    'preservado'=>'contas ADMIN, divisões, estrutura e auditoria'
                ],$adminId,null,true);
                $pdo->commit();
                return $backup;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
        } finally {
            $pdo->query("SELECT RELEASE_LOCK('comara_reset_operacional_v1')");
        }
    }

    private static function backupPreZeramento(PDO $pdo): array {
        $dir=ROOT_PATH.'/backups/reset';
        if (!is_dir($dir) && !@mkdir($dir,0700,true) && !is_dir($dir)) {
            throw new RuntimeException('Diretório de backup protegido indisponível.');
        }
        @chmod($dir,0700);
        $nome='antes_zeramento_'.date('Ymd_His').'_'.bin2hex(random_bytes(6)).'.sql.gz';
        $path=$dir.'/'.$nome;
        $tabelas=self::existentes($pdo);
        $gz=@gzopen($path,'wb9');
        if (!$gz) throw new RuntimeException('Falha ao criar backup obrigatório.');
        @chmod($path,0600);
        $ok=false;
        try {
            $write=static function(string $str) use ($gz): void {
                if (gzwrite($gz,$str)!==strlen($str)) throw new RuntimeException('Backup interrompido por falha de gravação.');
            };
            $write("-- BACKUP ANTES DO ZERAMENTO COMARA\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n");
            foreach ($tabelas as $t) {
                $quoted=self::q($t);
                $ddl=$pdo->query('SHOW CREATE TABLE '.$quoted)->fetch(PDO::FETCH_ASSOC);
                if (!$ddl || empty($ddl['Create Table'])) throw new RuntimeException('Estrutura de tabela não pôde ser copiada.');
                $write("\nDROP TABLE IF EXISTS ".$quoted.";\n".$ddl['Create Table'].";\n");
                $cols=$pdo->query('SHOW FULL COLUMNS FROM '.$quoted)->fetchAll(PDO::FETCH_ASSOC);
                $normais=[];
                foreach ($cols as $c) {
                    if (stripos((string)($c['Extra']??''),'GENERATED')===false) $normais[]=$c['Field'];
                }
                if (!$normais) continue;
                $selected=implode(',',array_map([self::class,'q'],$normais));
                $stmt=$pdo->query('SELECT '.$selected.' FROM '.$quoted);
                while ($row=$stmt->fetch(PDO::FETCH_NUM)) {
                    $values=array_map(static fn($v)=>$v===null?'NULL':$pdo->quote((string)$v),$row);
                    $write('INSERT INTO '.$quoted.' ('.$selected.') VALUES ('.implode(',',$values).");\n");
                }
                $stmt->closeCursor();
            }
            $write("\nSET FOREIGN_KEY_CHECKS=1;\n");
            $ok=true;
        } finally {
            gzclose($gz);
            if (!$ok) @unlink($path);
        }
        $size=@filesize($path);
        $sha=@hash_file('sha256',$path);
        if (!$size || !$sha) {
            @unlink($path);
            throw new RuntimeException('Backup incompleto; zeramento cancelado.');
        }
        return ['arquivo'=>$nome,'sha256'=>$sha,'tamanho_bytes'=>$size,'caminho'=>$path];
    }
}
