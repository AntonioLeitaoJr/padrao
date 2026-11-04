<?php
declare(strict_types=1);

/** Validação independente; não define classificados, consolidação ou prazo de edição. */
class OrdenacaoEstadoMaior {
    public static function validar(array $candidatosEsperados, array $itens): array {
        if (!$candidatosEsperados || count($itens) !== count($candidatosEsperados)) {
            throw new InvalidArgumentException('Ordene todos os candidatos da categoria.');
        }
        $esperados = array_map('intval', $candidatosEsperados);
        if (count(array_unique($esperados)) !== count($esperados) || min($esperados) <= 0) {
            throw new InvalidArgumentException('Relação de candidatos inválida.');
        }
        $ids = $posicoes = $resultado = [];
        foreach ($itens as $item) {
            if (!is_array($item)) throw new InvalidArgumentException('Ordenação inválida.');
            $id = filter_var($item['candidato_id'] ?? null, FILTER_VALIDATE_INT);
            $posicao = filter_var($item['posicao'] ?? null, FILTER_VALIDATE_INT);
            if ($id === false || !in_array($id, $esperados, true) || isset($ids[$id])
                || $posicao === false || $posicao < 1 || $posicao > count($esperados)
                || isset($posicoes[$posicao])) {
                throw new InvalidArgumentException('Não repita candidatos ou posições; ordene todos os candidatos disponíveis.');
            }
            $texto = $item['justificativa'] ?? null;
            if (!is_string($texto) || !preg_match('//u', $texto)) {
                throw new InvalidArgumentException('Informe uma justificativa válida em português.');
            }
            $texto = preg_replace('/^\s+|\s+$/u', '', $texto);
            // Grafemas: acentos decompostos também contam como uma letra.
            $quantidade = preg_match_all('/\X/u', $texto);
            if ($quantidade < 1 || $quantidade > 150) {
                throw new InvalidArgumentException('Cada justificativa é obrigatória e deve conter no máximo 150 caracteres.');
            }
            $ids[$id] = $posicoes[$posicao] = true;
            $resultado[] = ['candidato_id' => $id, 'posicao' => $posicao, 'justificativa' => $texto];
        }
        usort($resultado, fn($a, $b) => $a['posicao'] <=> $b['posicao']);
        return $resultado;
    }
}
