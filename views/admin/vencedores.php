<?php require ROOT_PATH . '/views/layouts/header.php'; ?>
<h2 class="h4">Vencedores dos dois anos anteriores</h2>
<p>Para o pleito de <?= $anoPleito ?>, registre os quatro vencedores de <?= $anoPleito - 2 ?> e de <?= $anoPleito - 1 ?>. Ter sido apenas candidato não conta como vitória.</p>
<p class="text-muted">Se um vencedor ainda não estiver no cadastro, inclua-o em Gestão do Efetivo, inclusive como inativo quando já tiver deixado a COMARA. Os registros homologados no sistema são preenchidos automaticamente.</p>
<form method="post" action="/index.php?r=admin/vencedores">
<input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
<?php foreach ([$anoPleito - 2, $anoPleito - 1] as $ano): ?>
<fieldset class="card card-body mb-3"><legend class="h5">Pleito <?= $ano ?></legend>
<div class="row g-3">
<?php foreach (CATEGORIAS_PADRAO as $cat => $info): ?>
<div class="col-md-6"><label class="form-label" for="v-<?= $ano ?>-<?= $cat ?>"><?= sanitize_output($info['nome']) ?></label>
<select required class="form-select" id="v-<?= $ano ?>-<?= $cat ?>" name="vencedores[<?= $ano ?>][<?= $cat ?>]">
<option value="">Selecione o vencedor</option>
<?php foreach ($pessoas as $p): if ($p['categoria'] !== $cat) continue; ?>
<option value="<?= (int)$p['id'] ?>" <?= (int)($historico[$ano][$cat]['candidato_id'] ?? 0) === (int)$p['id'] ? 'selected' : '' ?>><?= sanitize_output($p['posto'] . ' ' . $p['nome_guerra'] . ' — ' . $p['nome'] . (!$p['ativo'] ? ' (inativo)' : '')) ?></option>
<?php endforeach; ?>
</select></div>
<?php endforeach; ?>
</div></fieldset>
<?php endforeach; ?>
<?php if (in_array((int)$pleito['fase_atual'], [FASE_1, FASE_2], true)): ?>
<button class="btn btn-primary" type="submit">Salvar vencedores anteriores</button>
<?php endif; ?>
<a class="btn btn-outline-secondary" href="/index.php?r=admin/efetivo">Gestão do Efetivo</a>
</form>
<?php require ROOT_PATH . '/views/layouts/footer.php'; ?>
