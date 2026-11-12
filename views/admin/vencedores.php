<?php require ROOT_PATH . '/views/layouts/header.php'; ?>
<h2 class="h4">Vencedores dos dois anos anteriores</h2>
<p>Para o pleito de <?= $anoPleito ?>, registre os quatro vencedores de <?= $anoPleito - 2 ?> e de <?= $anoPleito - 1 ?>. Ter sido apenas candidato não conta como vitória.</p>
<p class="text-muted">Selecione o vencedor na lista, que inclui integrantes inativos. Caso ele não conste no cadastro da COMARA, escolha <strong>Vencedor não encontrado — informar manualmente</strong> e registre seu nome para preservar o histórico, sem incluí-lo no efetivo atual. Os registros homologados no sistema são preenchidos automaticamente.</p>
<form method="post" action="/index.php?r=admin/vencedores">
<input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
<?php foreach ([$anoPleito - 2, $anoPleito - 1] as $ano): ?>
<fieldset class="card card-body mb-3"><legend class="h5">Pleito <?= $ano ?></legend>
<div class="row g-3">
<?php foreach (CATEGORIAS_PADRAO as $cat => $info): ?>
<?php
    $registro = $historico[$ano][$cat] ?? [];
    $nomeManual = (string)($registro['nome_manual'] ?? '');
    $manualSelecionado = $nomeManual !== '' && empty($registro['candidato_id']);
    $idCampo = "v-{$ano}-{$cat}";
    $idNome = "vm-{$ano}-{$cat}";
?>
<div class="col-md-6">
    <label class="form-label" for="<?= $idCampo ?>"><?= sanitize_output($info['nome']) ?></label>
    <select required class="form-select" id="<?= $idCampo ?>" name="vencedores[<?= $ano ?>][<?= $cat ?>]" data-historico-select data-historico-alvo="<?= $idNome ?>">
        <option value="">Selecione o vencedor</option>
        <?php foreach ($pessoas as $p): if ($p['categoria'] !== $cat) continue; ?>
        <option value="<?= (int)$p['id'] ?>" <?= !$manualSelecionado && (int)($registro['candidato_id'] ?? 0) === (int)$p['id'] ? 'selected' : '' ?>><?= sanitize_output($p['posto'] . ' ' . $p['nome_guerra'] . ' — ' . $p['nome'] . (!$p['ativo'] ? ' (inativo)' : '')) ?></option>
        <?php endforeach; ?>
        <option value="__manual__" <?= $manualSelecionado ? 'selected' : '' ?>>Vencedor não encontrado — informar manualmente</option>
    </select>
    <div id="<?= $idNome ?>" class="mt-2 <?= $manualSelecionado ? '' : 'd-none' ?>" data-historico-manual>
        <label class="form-label" for="<?= $idNome ?>-nome">Nome do vencedor de <?= $ano ?></label>
        <input type="text" class="form-control" id="<?= $idNome ?>-nome"
               name="nomes_manuais[<?= $ano ?>][<?= $cat ?>]"
               value="<?= sanitize_output($nomeManual) ?>" minlength="3" maxlength="255"
               placeholder="Informe o nome completo do vencedor"
               <?= $manualSelecionado ? 'required' : 'disabled' ?>>
        <div class="form-text">Registro histórico somente. Se a pessoa estiver cadastrada, selecione-a na lista para que a vitória seja considerada nos critérios de desempate.</div>
    </div>
</div>
<?php endforeach; ?>
</div></fieldset>
<?php endforeach; ?>
<?php if (in_array((int)$pleito['fase_atual'], [FASE_1, FASE_2], true)): ?>
<button class="btn btn-primary" type="submit">Salvar vencedores anteriores</button>
<?php endif; ?>
<a class="btn btn-outline-secondary" href="/index.php?r=admin/efetivo">Gestão do Efetivo</a>
</form>
<script>
document.querySelectorAll('[data-historico-select]').forEach(function (selecao) {
    var alvo = document.getElementById(selecao.dataset.historicoAlvo);
    if (!alvo) return;
    var nome = alvo.querySelector('input[name^="nomes_manuais"]');
    if (!nome) return;
    function atualizar() {
        var manual = selecao.value === '__manual__';
        alvo.classList.toggle('d-none', !manual);
        nome.disabled = !manual;
        nome.required = manual;
    }
    selecao.addEventListener('change', atualizar);
    atualizar();
});
</script>
<?php require ROOT_PATH . '/views/layouts/footer.php'; ?>
