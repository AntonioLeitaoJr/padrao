<?php
declare(strict_types=1);

/**
 * Testes estáticos de assets INTRAER: sem acesso a CDNs, sem banco de dados.
 * A verificação de entrega HTTP real deve ser feita também no aaPanel após deploy.
 */
$raiz=dirname(__DIR__);
$public=$raiz.'/public';
$header=file_get_contents($raiz.'/views/layouts/header.php');
$footer=file_get_contents($raiz.'/views/layouts/footer.php');
$checks=0;
function confereAsset(bool $cond,string $msg): void {
    global $checks;
    if (!$cond) throw new RuntimeException('FALHA: '.$msg);
    echo "OK: ".$msg."\n";
    $checks++;
}
confereAsset($header!==false && $footer!==false,'layouts compartilhados estão disponíveis');
$cssEsperado=[
    '/assets/vendor/bootstrap/5.3.3/css/bootstrap.min.css',
    '/assets/vendor/bootstrap-icons/1.11.3/bootstrap-icons.min.css',
    '/assets/css/style.css?v=20261111'
];
preg_match_all('~<link\b[^>]*\brel\s*=\s*["\x27]stylesheet["\x27][^>]*>~i',$header,$tags);
$encontrados=[];
foreach ($tags[0] as $tag) {
    if (!preg_match('~\bhref\s*=\s*["\x27]([^"\x27]+)["\x27]~i',$tag,$m)) {
        throw new RuntimeException('Folha de estilo sem href.');
    }
    $href=$m[1];
    confereAsset(str_starts_with($href,'/assets/'),'CSS local com caminho absoluto /assets/: '.$href);
    $path=$public.parse_url($href,PHP_URL_PATH);
    confereAsset(is_file($path) && filesize($path)>0,'CSS existe no pacote: '.$href);
    $encontrados[]=$href;
}
confereAsset($encontrados===$cssEsperado,'CSS na ordem: Bootstrap → Icons → estilo COMARA');
$js='/assets/vendor/bootstrap/5.3.3/js/bootstrap.bundle.min.js';
confereAsset(str_contains($footer,'src="'.$js.'"'),'Bootstrap JS local referenciado no footer');
confereAsset(is_file($public.$js) && filesize($public.$js)>0,'Bootstrap JS distribuído no projeto');
$icons=$public.'/assets/vendor/bootstrap-icons/1.11.3/bootstrap-icons.min.css';
$iconsCss=file_get_contents($icons);
preg_match_all('~url\(["\x27]?(fonts/[^"\x27\)]+)~',$iconsCss,$matches);
confereAsset(count($matches[1])===2,'Bootstrap Icons declara as fontes woff2 e woff');
foreach ($matches[1] as $font) {
    $relative=explode('?',$font,2)[0];
    $fontPath=dirname($icons).'/'.$relative;
    confereAsset(is_file($fontPath) && filesize($fontPath)>10000,'Fonte de ícones local: '.$relative);
}
foreach ([
    '/assets/vendor/bootstrap/5.3.3/LICENSE',
    '/assets/vendor/bootstrap-icons/1.11.3/LICENSE',
] as $license) confereAsset(is_file($public.$license),'licença de distribuição incluída');
foreach ([$header,$footer] as $html) {
    confereAsset(!preg_match('~<\s*(link|script)\b[^>]*\b(?:href|src)\s*=\s*["\x27](?:https?:)?//~i',$html),
        'layout não carrega CDN para estilos nem JavaScript');
}

// Auditoria de todas as páginas do projeto, não somente telas administrativas.
// Os estilos inline são permitidos; links CSS externos e @import remoto não.
$roots=['views','public','src','config'];
$arquivos=0;
foreach ($roots as $dir) {
    $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
        $raiz.'/'.$dir,FilesystemIterator::SKIP_DOTS
    ));
    foreach ($iterator as $file) {
        if (!in_array(strtolower(pathinfo($file->getFilename(),PATHINFO_EXTENSION)),
            ['php','html','htm','css','js'],true)) continue;
        $content=file_get_contents($file->getPathname());
        if ($content===false) throw new RuntimeException('Falha de leitura: '.$file->getPathname());
        $rel=substr($file->getPathname(),strlen($raiz)+1);
        // Referências na marcação: estilos externos são vedados.
        confereAsset(!preg_match(
            '~<link\b[^>]*(?:rel=["\x27]stylesheet["\x27][^>]*href|href=["\x27](?:https?:)?//[^"\x27]+\.css)[^>]*~i',
            $content
        ) || !preg_match('~<link\b[^>]*\b(?:href|src)\s*=\s*["\x27](?:https?:)?//~i',$content),
            'sem CSS CDN: '.$rel);
        // Importações/fontes via CSS remoto também devem ser locais.
        if (str_ends_with($rel,'.css')) {
            confereAsset(!preg_match('~@import\s+(?:url\()?["\x27]?(?:https?:)?//~i',$content)
                && !preg_match('~url\(["\x27]?(?:https?:)?//~i',$content),
                'sem @import/fontes CDN em '.$rel);
        }
        $arquivos++;
    }
}
confereAsset($arquivos>=30,'varredura completa de código e templates ('.$arquivos.' arquivos)');
$rewrite=file_get_contents($raiz.'/.htaccess');
confereAsset(str_contains($rewrite,'RewriteRule ^assets/(.+)$ public/assets/$1'),
    'Apache com DocumentRoot=raiz encaminha /assets/ para public/assets/');
echo "SUCESSO: ".$checks." verificações de assets offline.\n";
