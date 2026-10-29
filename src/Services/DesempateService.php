<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/config.php';

class DesempateService {
    public static function comparar(array $a, array $b, string $campo = 'total_votos'): int {
        $cmp = (float)($b[$campo] ?? 0) <=> (float)($a[$campo] ?? 0);
        if ($cmp) return $cmp;
        $cmp = ((int)($a['vitorias_ultimos_dois_anos'] ?? 0) > 0)
            <=> ((int)($b['vitorias_ultimos_dois_anos'] ?? 0) > 0);
        if ($cmp) return $cmp;
        foreach (['data_admissao_comara', 'data_inicio_servico'] as $data) {
            $cmp = self::dataOrdenacao($a[$data] ?? null) <=> self::dataOrdenacao($b[$data] ?? null);
            if ($cmp) return $cmp;
        }
        return (float)($b['nota_tacf'] ?? 0) <=> (float)($a['nota_tacf'] ?? 0);
    }

    private static function dataOrdenacao(?string $data): int {
        $timestamp = $data ? strtotime($data) : false;
        return $timestamp === false ? PHP_INT_MAX : $timestamp;
    }

    public static function ordenarCandidatos(array $candidatos, string $campoPontuacao = 'total_votos'): array {
        usort($candidatos, fn($a, $b) => self::comparar($a, $b, $campoPontuacao));
        $empatados = [];
        foreach ($candidatos as $c) {
            if (self::comparar($candidatos[0], $c, $campoPontuacao) !== 0) break;
            $empatados[] = $c;
        }
        return ['ranking' => $candidatos, 'requer_voto_de_minerva' => count($empatados) > 1,
            'candidatos_empatados' => count($empatados) > 1 ? $empatados : []];
    }

    public static function classificarFase2(array $candidatos): array {
        // Somente fichas concluídas e observadas geram candidatos classificados.
        $candidatos = array_values(array_filter($candidatos, fn($c) => (int)($c['qtd_fichas'] ?? 0) > 0));
        $ranking = self::ordenarCandidatos($candidatos, 'media_fase1')['ranking'];
        $limite = min(count($ranking), max(2, (int)ceil(count($ranking) * 0.20)));
        $lista = array_slice($ranking, 0, $limite);
        for ($i = $limite; $i < count($ranking); $i++) {
            if (self::comparar($ranking[$limite - 1], $ranking[$i], 'media_fase1') !== 0) break;
            $lista[] = $ranking[$i];
        }
        usort($lista, fn($a, $b) => strcasecmp((string)$a['nome_guerra'], (string)$b['nome_guerra']));
        return $lista;
    }

    public static function explicarDesempate(array $vencedor, array $segundo): string {
        if (empty($vencedor['vitorias_ultimos_dois_anos']) && !empty($segundo['vitorias_ultimos_dois_anos'])) {
            return 'Critério 1: Preferência a quem não venceu nos dois anos anteriores ao pleito.';
        }
        if (self::dataOrdenacao($vencedor['data_admissao_comara'] ?? null) !== self::dataOrdenacao($segundo['data_admissao_comara'] ?? null)) {
            return 'Critério 2: Maior antiguidade na COMARA (apresentação/admissão).';
        }
        if (self::dataOrdenacao($vencedor['data_inicio_servico'] ?? null) !== self::dataOrdenacao($segundo['data_inicio_servico'] ?? null)) {
            return 'Critério 3: Maior tempo de serviço militar ou civil.';
        }
        if ((float)($vencedor['nota_tacf'] ?? 0) !== (float)($segundo['nota_tacf'] ?? 0)) {
            return 'Critério 4: Maior nota na avaliação do TACF.';
        }
        return 'Empate persistente após os quatro critérios.';
    }
}
