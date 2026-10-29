<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Services/DesempateService.php';
function verificar(bool $ok, string $nome): void {
    if (!$ok) throw new RuntimeException($nome);
    echo "OK: $nome\n";
}
function candidato(int $id, float $nota = 4): array {
    return ['id' => $id, 'nome_guerra' => sprintf('N%03d', $id), 'media_fase1' => $nota,
        'qtd_fichas' => 1, 'vitorias_ultimos_dois_anos' => 0,
        'data_admissao_comara' => '2020-01-01', 'data_inicio_servico' => '2000-01-01', 'nota_tacf' => 4];
}
verificar(DesempateService::classificarFase2([]) === [], 'grupo vazio');
verificar(count(DesempateService::classificarFase2([candidato(1)])) === 1, 'único candidato');
foreach ([2 => 2, 5 => 2, 10 => 2, 11 => 3, 21 => 5] as $total => $esperado) {
    $lista = [];
    for ($i = 1; $i <= $total; $i++) $lista[] = candidato($i, 5 - $i / 100);
    verificar(count(DesempateService::classificarFase2($lista)) === $esperado, "20% com mínimo dois e teto: $total");
}
$lista = [candidato(1, 5), candidato(2, 4), candidato(3, 4), candidato(4, 3)];
verificar(count(DesempateService::classificarFase2($lista)) === 3, 'todos os empatados no corte');
$a = candidato(1); $b = candidato(2);
$b['vitorias_ultimos_dois_anos'] = 1;
$b['data_admissao_comara'] = '1990-01-01';
verificar(DesempateService::comparar($a, $b, 'media_fase1') < 0, 'vitória anterior tem precedência sobre antiguidade');
$b['vitorias_ultimos_dois_anos'] = 0;
verificar(DesempateService::comparar($a, $b, 'media_fase1') > 0, 'antiguidade COMARA');
$b['data_admissao_comara'] = $a['data_admissao_comara'];
$b['data_inicio_servico'] = '1995-01-01'; $a['nota_tacf'] = 5;
verificar(DesempateService::comparar($a, $b, 'media_fase1') > 0, 'tempo de serviço antes do TACF');
$b['data_inicio_servico'] = $a['data_inicio_servico'];
verificar(DesempateService::comparar($a, $b, 'media_fase1') < 0, 'TACF como quarto critério');
$b = candidato(2); $a = candidato(1);
$a['indicacoes_anteriores_qtd'] = 99;
$a['data_vinculo_om'] = '1990-01-01';
verificar(DesempateService::comparar($a, $b, 'media_fase1') === 0, 'campos antigos não afetam desempate');
$a['qtd_fichas'] = 0;
verificar(count(DesempateService::classificarFase2([$a, $b])) === 1, 'não classificados sem ficha válida');
$a = candidato(1, 0);
verificar(count(DesempateService::classificarFase2([$a])) === 1, 'nota zero válida não é NOB');
$apuracao = DesempateService::ordenarCandidatos([candidato(1), candidato(2), candidato(3), candidato(4)], 'media_fase1');
verificar(count($apuracao['candidatos_empatados']) === 4 && $apuracao['requer_voto_de_minerva'], 'minerva preservada e todos os empatados identificados');
echo "Todos os testes passaram.\n";
