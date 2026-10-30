<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Services/OrdenacaoEstadoMaior.php';
$n = 0;
function check(bool $ok, string $msg): void { global $n; if (!$ok) throw new RuntimeException($msg); $n++; echo "OK: $msg\n"; }
function rejeita(array $itens, string $msg): void {
    try { OrdenacaoEstadoMaior::validar([11,22,33], $itens); }
    catch (InvalidArgumentException $e) { check(true, $msg); return; }
    throw new RuntimeException($msg);
}
$itens = [
    ['candidato_id'=>22,'posicao'=>2,'justificativa'=>'Dedicação à missão.'],
    ['candidato_id'=>11,'posicao'=>1,'justificativa'=>'Ótima atuação.'],
    ['candidato_id'=>33,'posicao'=>3,'justificativa'=>'Cooperação constante.']
];
check(array_column(OrdenacaoEstadoMaior::validar([11,22,33],$itens),'candidato_id') === [11,22,33], 'ordenação completa');
$x=$itens; $x[0]['justificativa']=str_repeat('á',150);
check(strlen(OrdenacaoEstadoMaior::validar([11,22,33],$x)[1]['justificativa'])===300, '150 letras acentuadas');
$x[0]['justificativa']=str_repeat("a\u{0301}",150);
check(count(OrdenacaoEstadoMaior::validar([11,22,33],$x))===3, 'acentos decompostos contam por grafema');
$x[0]['justificativa']=str_repeat('á',151); rejeita($x,'rejeita 151 caracteres');
$x=$itens; $x[0]['justificativa']=" \t\n\u{00a0}"; rejeita($x,'rejeita justificativa vazia');
$x=$itens; $x[0]['candidato_id']=11; rejeita($x,'rejeita candidato repetido');
$x=$itens; $x[0]['posicao']=1; rejeita($x,'rejeita posição repetida');
$x=$itens; $x[0]['posicao']=4; rejeita($x,'rejeita posição fora do intervalo');
$x=$itens; $x[0]['candidato_id']=44; rejeita($x,'rejeita candidato estranho');
$x=$itens; array_pop($x); rejeita($x,'rejeita ordenação incompleta');
$x=$itens; $x[0]['justificativa']="\xff"; rejeita($x,'rejeita UTF-8 inválido');
echo "$n verificações aprovadas. Validador ainda não conectado à Fase 4.\n";
