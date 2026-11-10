<?php
declare(strict_types=1);

/**
 * Carga privada no MySQL configurado na instalação, SEM incluir CSV no GitHub.
 *
 * 1) Prévia: php database/importar_efetivo_privado.php /caminho/seguro/lista.csv
 * 2) Aplicar: php database/importar_efetivo_privado.php /caminho/seguro/lista.csv
 *     --aplicar --esperado=484 --sha256=<sha256 da prévia>
 *
 * Não usar em eleição aberta ou homologada. Backup SQL local é obrigatório.
 */
if (PHP_SAPI!=='cli') {
    http_response_code(403);
    exit('Uso exclusivo pelo terminal do servidor.');
}
require_once __DIR__.'/../src/Services/ImportacaoEfetivoService.php';

$path=$argv[1]??null;
if (!$path || str_starts_with($path,'--')) {
    fwrite(STDERR,"Uso: php database/importar_efetivo_privado.php /caminho/arquivo.csv [--aplicar --esperado=N --sha256=HASH]\n");
    exit(2);
}
$path=realpath($path);
if ($path===false || !is_file($path)) {
    fwrite(STDERR,"Arquivo CSV não encontrado.\n");
    exit(2);
}
$public=realpath(ROOT_PATH.'/public');
if ($public && str_starts_with($path,$public.DIRECTORY_SEPARATOR)) {
    fwrite(STDERR,"CSV não pode estar na pasta pública do servidor.\n");
    exit(2);
}
try {
    $dados=ImportacaoEfetivoService::analisar($path);
    $total=count($dados['linhas']);
    $extras=array_slice($argv,2);
    $aplicar=in_array('--aplicar',$extras,true);
    $quantidade=null; $hash=null;
    foreach ($extras as $arg) {
        if ($arg==='--aplicar') continue;
        if (preg_match('/^--esperado=(\d+)$/D',$arg,$m)) { $quantidade=(int)$m[1]; continue; }
        if (preg_match('/^--sha256=([a-fA-F0-9]{64})$/D',$arg,$m)) { $hash=strtolower($m[1]); continue; }
        throw new InvalidArgumentException('Argumento desconhecido. Nenhum dado alterado.');
    }
    echo "CSV validado: {$total} registros; {$dados['ativos']} ativos; {$dados['inativos']} inativos.\n";
    echo "SHA-256: {$dados['sha256']}\n";
    if (!$aplicar) {
        echo "PRÉVIA CONCLUÍDA. Nenhuma alteração no banco. Para aplicar, informe --aplicar --esperado={$total} --sha256={$dados['sha256']}.\n";
        exit(0);
    }
    if ($quantidade===null || $quantidade<=0 || $hash===null) {
        throw new InvalidArgumentException('Aplicação exige --esperado=N e --sha256=HASH.');
    }
    if (function_exists('set_time_limit')) @set_time_limit(0);
    $r=ImportacaoEfetivoService::importar($path,null,$quantidade,$hash);
    echo "CARGA CONCLUÍDA: {$r['total']} registros; novos {$r['inseridos']}; atualizados {$r['atualizados']}; ativos {$r['ativos']}; inativos {$r['inativos']}.\n";
    echo "Backup anterior: {$r['backup']}.\n";
} catch (Throwable $e) {
    fwrite(STDERR,"CARGA NÃO CONCLUÍDA: ".$e->getMessage()."\n");
    exit(1);
}
