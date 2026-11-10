<?php
declare(strict_types=1);

require_once __DIR__.'/../../config/config.php';
require_once __DIR__.'/../../config/database.php';
require_once __DIR__.'/../Models/Efetivo.php';
require_once __DIR__.'/BackupService.php';
require_once __DIR__.'/AuditoriaService.php';

/**
 * Importação integral e atômica do CSV do efetivo: uma linha inválida cancela
 * todas as alterações. O arquivo CSV permanece fora do repositório.
 */
final class ImportacaoEfetivoService {
    private const COLUNAS = [
        'SARAM','CPF','NOME','NOME_GUERRA','POSTO','QUADRO','ESPECIALIDADE',
        'SETOR','DIVISAO','CATEGORIA','VINCULO','TELEFONE'
    ];
    private const CATEGORIAS = ['graduado','praca','sppf','sptf','ineligivel'];
    private const VINCULOS = ['MILITAR_CARREIRA','MILITAR_TEMPORARIO','CIVIL_PERMANENTE','CIVIL_TEMPORARIO'];

    /** @return array{linhas:array,ativos:int,inativos:int,sha256:string} */
    public static function analisar(string $arquivo): array {
        if (!is_file($arquivo) || !is_readable($arquivo)) {
            throw new InvalidArgumentException('CSV não encontrado ou sem permissão de leitura.');
        }
        $tamanho=filesize($arquivo);
        if ($tamanho===false || $tamanho<=0 || $tamanho>10*1024*1024) {
            throw new InvalidArgumentException('CSV vazio ou maior que o limite de 10 MB.');
        }
        $fp=fopen($arquivo,'rb');
        if (!$fp) throw new InvalidArgumentException('Não foi possível abrir o CSV.');
        try {
            $primeira=fgets($fp);
            if ($primeira===false) throw new InvalidArgumentException('CSV sem cabeçalho.');
            $primeira=preg_replace('/^\xEF\xBB\xBF/','',$primeira);
            $delimitador=substr_count($primeira,';')>substr_count($primeira,',') ? ';' : ',';
            rewind($fp);
            $cabecalho=fgetcsv($fp,0,$delimitador,'"','\\');
            if (!is_array($cabecalho)) throw new InvalidArgumentException('CSV sem cabeçalho válido.');
            $mapa=[];
            foreach ($cabecalho as $i=>$nome) {
                $nome=mb_strtoupper(trim(preg_replace('/^\xEF\xBB\xBF/','',(string)$nome)));
                if ($nome==='') throw new InvalidArgumentException('CSV possui coluna sem identificação.');
                if (array_key_exists($nome,$mapa)) throw new InvalidArgumentException('CSV possui coluna duplicada.');
                $mapa[$nome]=$i;
            }
            foreach (self::COLUNAS as $col) {
                if (!isset($mapa[$col])) throw new InvalidArgumentException('CSV sem coluna obrigatória: '.$col.'.');
            }
            $linhas=[]; $cpfs=[]; $sarams=[]; $ativos=0; $inativos=0; $numero=1;
            while (($cols=fgetcsv($fp,0,$delimitador,'"','\\'))!==false) {
                $numero++;
                if (count($cols)===1 && trim((string)$cols[0])==='') continue;
                if (count($cols)!==count($cabecalho)) {
                    throw new InvalidArgumentException('Linha '.$numero.': quantidade de colunas diferente do cabeçalho.');
                }
                $g=static fn(string $ch): string => isset($mapa[$ch]) ? trim((string)$cols[$mapa[$ch]]) : '';
                $saram=$g('SARAM');
                $cpf=preg_replace('/\D/','',$g('CPF'));
                $nome=$g('NOME');
                $nomeGuerra=$g('NOME_GUERRA');
                $posto=$g('POSTO');
                $categoria=mb_strtolower($g('CATEGORIA'));
                $vinculo=mb_strtoupper($g('VINCULO'));
                $ativoBruto=array_key_exists('ATIVO',$mapa) ? $g('ATIVO') : '1';
                if ($saram==='' && $cpf==='') throw new InvalidArgumentException('Linha '.$numero.': falta SARAM/matrícula ou CPF.');
                if (strlen($saram)>20) throw new InvalidArgumentException('Linha '.$numero.': SARAM/matrícula ultrapassa 20 caracteres.');
                if ($cpf!=='' && strlen($cpf)!==11) throw new InvalidArgumentException('Linha '.$numero.': CPF deve conter 11 dígitos.');
                if ($nome==='' || $posto==='') throw new InvalidArgumentException('Linha '.$numero.': nome ou posto em branco.');
                if (!in_array($categoria,self::CATEGORIAS,true)) throw new InvalidArgumentException('Linha '.$numero.': categoria inválida.');
                if (!in_array($vinculo,self::VINCULOS,true)) throw new InvalidArgumentException('Linha '.$numero.': vínculo inválido.');
                if (!in_array($ativoBruto,['0','1'],true)) throw new InvalidArgumentException('Linha '.$numero.': ATIVO deve ser 0 ou 1.');
                if ($saram!=='') {
                    if (isset($sarams[$saram])) throw new InvalidArgumentException('Linha '.$numero.': SARAM/matrícula duplicado no arquivo.');
                    $sarams[$saram]=true;
                }
                if ($cpf!=='') {
                    if (isset($cpfs[$cpf])) throw new InvalidArgumentException('Linha '.$numero.': CPF duplicado no arquivo.');
                    $cpfs[$cpf]=true;
                }
                $ativo=(int)$ativoBruto;
                if ($ativo) $ativos++; else $inativos++;
                $linhas[]=[
                    'numero'=>$numero,
                    'dados'=>[
                        'saram'=>$saram, 'cpf'=>$cpf, 'nome'=>$nome,
                        'nome_guerra'=>$nomeGuerra!=='' ? $nomeGuerra : $nome,
                        'posto'=>$posto,
                        'quadro'=>$g('QUADRO'),'especialidade'=>$g('ESPECIALIDADE'),
                        'setor'=>$g('SETOR'),'divisao_sigla'=>$g('DIVISAO'),
                        'categoria'=>$categoria,'tipo_vinculo'=>$vinculo,
                        'telefone'=>$g('TELEFONE'),'ativo'=>$ativo
                    ]
                ];
            }
            if (!$linhas) throw new InvalidArgumentException('CSV não contém registros de efetivo.');
            return [
                'linhas'=>$linhas,'ativos'=>$ativos,'inativos'=>$inativos,
                'sha256'=>hash_file('sha256',$arquivo)
            ];
        } finally {
            fclose($fp);
        }
    }

    /**
     * Importação via CSV enviado na UI ou arquivo local controlado pela STI.
     * Não apaga registros ausentes do arquivo nem altera perfis já existentes.
     * @return array{total:int,inseridos:int,atualizados:int,ativos:int,inativos:int,backup:string}
     */
    public static function importar(
        string $arquivo, ?int $usuarioId=null, ?int $quantidadeEsperada=null,
        ?string $shaEsperado=null
    ): array {
        $analise=self::analisar($arquivo);
        $total=count($analise['linhas']);
        if ($quantidadeEsperada!==null && $total!==$quantidadeEsperada) {
            throw new InvalidArgumentException('CSV contém '.$total.' registros, diferente da quantidade esperada.');
        }
        if ($shaEsperado!==null && !hash_equals(strtolower($shaEsperado),$analise['sha256'])) {
            throw new InvalidArgumentException('SHA-256 do CSV não confere. Nenhum registro foi modificado.');
        }

        $pdo=Database::getConnection();
        Efetivo::bloquearCadastro();
        try {
            // Impede importação que altere pleito já votado ou homologado.
            $pleitos=$pdo->query('SELECT id,status,fase_atual FROM pleitos')->fetchAll();
            foreach ($pleitos as $p) {
                if ($p['status']==='homologado'
                    || ($p['status']==='em_andamento' && (int)$p['fase_atual']>=3)) {
                    throw new RuntimeException('Importação bloqueada: há pleito em votação ou homologado. Consulte a administração.');
                }
            }
            // Backup obrigatório antes de iniciar qualquer inclusão/atualização.
            $backup=BackupService::gerarBackup('diario',$usuarioId);
            $caminho=$backup['caminho']??'';
            if (!is_string($caminho) || !is_file($caminho) || filesize($caminho)===0) {
                throw new RuntimeException('Backup pré-importação não verificado; a carga foi cancelada.');
            }
            $inseridos=0; $atualizados=0;
            $pdo->beginTransaction();
            try {
                $pdo->query('SELECT id FROM pleitos ORDER BY id FOR UPDATE')->fetchAll();
                $pleitos=$pdo->query('SELECT status,fase_atual FROM pleitos')->fetchAll();
                foreach ($pleitos as $p) {
                    if ($p['status']==='homologado'
                        || ($p['status']==='em_andamento' && (int)$p['fase_atual']>=3)) {
                        throw new RuntimeException('Pleito avançou durante a importação. Carga cancelada.');
                    }
                }
                $qSaram=$pdo->prepare('SELECT id FROM efetivo WHERE TRIM(saram)=? ORDER BY id');
                $qCpf=$pdo->prepare('SELECT id FROM efetivo WHERE cpf_hash=? ORDER BY id');
                $qIdentidade=$pdo->prepare('SELECT saram FROM efetivo WHERE id=?');
                foreach ($analise['linhas'] as $linha) {
                    $d=$linha['dados']; $n=$linha['numero'];
                    $porSaram=[]; $porCpf=[];
                    if ($d['saram']!=='') {
                        $qSaram->execute([$d['saram']]);
                        $porSaram=$qSaram->fetchAll(PDO::FETCH_COLUMN);
                    }
                    if ($d['cpf']!=='') {
                        $qCpf->execute([hash('sha256',$d['cpf'])]);
                        $porCpf=$qCpf->fetchAll(PDO::FETCH_COLUMN);
                    }
                    if (count($porSaram)>1 || count($porCpf)>1) {
                        throw new InvalidArgumentException('Linha '.$n.': identificador duplicado na base atual.');
                    }
                    $idSaram=$porSaram[0]??null;
                    $idCpf=$porCpf[0]??null;
                    if ($idSaram && $idCpf && (int)$idSaram!==(int)$idCpf) {
                        throw new InvalidArgumentException('Linha '.$n.': SARAM e CPF correspondem a cadastros diferentes.');
                    }
                    $existente=$idSaram?:$idCpf;
                    if ($existente && !$idSaram && $d['saram']!=='') {
                        $qIdentidade->execute([(int)$existente]);
                        $saramAtual=trim((string)$qIdentidade->fetchColumn());
                        if ($saramAtual!=='' && $saramAtual!==$d['saram']) {
                            throw new InvalidArgumentException('Linha '.$n.': CPF pertence a matrícula diferente na base.');
                        }
                    }
                    if ($existente) $d['id']=(int)$existente;
                    try {
                        Efetivo::salvarOuAtualizar($d);
                    } catch (Throwable $e) {
                        if ($e instanceof InvalidArgumentException) {
                            throw new InvalidArgumentException('Linha '.$n.': '.$e->getMessage(),0,$e);
                        }
                        throw new RuntimeException('Linha '.$n.': falha ao gravar registro. Carga cancelada.',0,$e);
                    }
                    if ($existente) $atualizados++; else $inseridos++;
                }
                if ($inseridos+$atualizados!==$total) {
                    throw new RuntimeException('Conferência final de quantidade falhou.');
                }
                AuditoriaService::log('IMPORTACAO_CSV_EFETIVO',[
                    'total'=>$total,'novos'=>$inseridos,'atualizados'=>$atualizados,
                    'ativos'=>$analise['ativos'],'inativos'=>$analise['inativos'],
                    'sha256_csv'=>$analise['sha256'],
                    'backup_pre_importacao'=>basename($caminho)
                ],$usuarioId,null,true);
                $pdo->commit();
                return [
                    'total'=>$total,'inseridos'=>$inseridos,'atualizados'=>$atualizados,
                    'ativos'=>$analise['ativos'],'inativos'=>$analise['inativos'],
                    'backup'=>basename($caminho)
                ];
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
        } finally {
            Efetivo::liberarCadastro();
        }
    }
}
