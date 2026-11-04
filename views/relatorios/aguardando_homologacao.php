<?php
require ROOT_PATH.'/views/layouts/header.php';
?>
<div class="alert alert-info">Os vencedores do pleito atual serão divulgados somente após a homologação. A apuração final da Presidência estará disponível na Fase 5.</div>
<h2 class="h5">Consultar eleições homologadas</h2>
<ul><?php foreach ($historicos as $h): ?><li><a href="/index.php?r=<?= sanitize_output($rotaHistorico) ?>&amp;pleito_id=<?= (int)$h['id'] ?>"><?= (int)$h['ano'] ?> — <?= sanitize_output($h['titulo']) ?></a></li><?php endforeach; ?></ul>
<?php if (!$historicos): ?><p>Nenhuma homologação disponível.</p><?php endif; ?>
<?php require ROOT_PATH.'/views/layouts/footer.php'; ?>
