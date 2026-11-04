<?php
declare(strict_types=1);
require ROOT_PATH.'/views/layouts/header.php';
?>
<h2 class="h4 text-primary">Fase 4: Ordenação pelo Estado-Maior</h2>
<p>Coronéis, Tenentes-Coronéis e Majores ordenam individualmente cada categoria. Primeiro: <strong>5 pontos</strong>; segundo: <strong>3</strong>; terceiro: <strong>1</strong>.</p>
<?php if ($erro): ?>
<div class="alert alert-warning" role="alert"><?= sanitize_output($erro) ?></div>
<?php elseif (!$ciclo['aberto_em']): ?>
<div class="alert alert-warning" role="alert"><strong>Urna encerrada; Fase 4 bloqueada.</strong> A Presidência deve resolver os empates no corte. Após as decisões, a Administração deve avançar novamente a fase.</div>
<?php foreach ($situacao as $cat=>$d): if (!$d['pendente']) continue; ?>
<section class="card card-body mb-3">
<h3 class="h5"><?= sanitize_output(CATEGORIAS_PADRAO[$cat]['nome']) ?> — decisão da terceira vaga</h3>
<p>Empate persistente após os quatro critérios. Restam <?= (int)$d['vagas'] ?> vaga(s). Só os empatados no limite podem ser escolhidos.</p>
<?php if (AuthManager::hasRole([PERFIL_PRESIDENTE,PERFIL_ADMIN])): ?>
<form method="post" action="/index.php?r=fase4/decidir_corte" onsubmit="return confirm('Registrar definitivamente esta decisão fundamentada?')">
<input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
<input type="hidden" name="categoria" value="<?= sanitize_output($cat) ?>">
<?php foreach ($d['candidatos'] as $c): if (!in_array((int)$c['candidato_id'],$d['empatados'],true)) continue; ?>
<label class="d-block mb-2"><input type="checkbox" name="candidatos[]" value="<?= (int)$c['candidato_id'] ?>"> <?= sanitize_output($c['posto'].' '.$c['nome_guerra']) ?> — <?= (int)$c['total_votos'] ?> votos</label>
<?php endforeach; ?>
<label class="form-label">Fundamentação obrigatória<textarea class="form-control" name="justificativa" required rows="3"></textarea></label>
<label class="d-block"><input type="checkbox" name="confirmacao" value="1" required> Confiro os candidatos e confirmo esta decisão.</label>
<button class="btn btn-warning mt-2">Confirmar decisão do corte</button>
</form>
<?php else: ?><p>Aguardando decisão fundamentada da Presidência.</p><?php endif; ?>
</section>
<?php endforeach; ?>
<?php else: ?>
<?php if ($confirmado): ?><div class="alert alert-success">Avaliação confirmada e gravada. Consulta somente; não é possível alterar posições ou justificativas.</div>
<?php elseif (!$podeConfirmar): ?><div class="alert alert-info">Consulta somente. A confirmação exige Oficial Superior habilitado e Fase 4 aberta.</div><?php endif; ?>
<form method="post" action="/index.php?r=fase4/confirmar" id="formOrdenacao">
<input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
<?php foreach ($candidatos as $cat=>$lista): ?>
<section class="card card-body mb-3" data-categoria="<?= sanitize_output($cat) ?>">
<h3 class="h5"><?= sanitize_output(CATEGORIAS_PADRAO[$cat]['nome']) ?></h3>
<?php if (!$lista): ?><p>Sem representantes. A escolha excepcional desta categoria ocorrerá na Fase 5, exclusivamente pela Presidência.</p><?php endif; ?>
<?php foreach ($lista as $c):
$id=(int)$c['candidato_id']; $item=$avaliacao[$cat][$id]??($rascunho[$cat][$id]??[]);
$pos=is_scalar($item['posicao']??null)?(int)$item['posicao']:0;
$just=is_string($item['justificativa']??null)?$item['justificativa']:'';
?>
<div class="row border-top py-3 align-items-center">
<div class="col-md-4"><img class="cand-photo-sm" alt="Foto" src="/index.php?r=foto&amp;saram=<?= urlencode((string)$c['saram']) ?>&amp;id=<?= $id ?>">
<strong><?= sanitize_output($c['posto'].' '.$c['nome_guerra']) ?></strong><div><?= sanitize_output($c['setor']??'') ?></div></div>
<div class="col-md-2"><label class="form-label" for="pos_<?= $id ?>">Posição</label>
<input type="hidden" name="categorias[<?= $cat ?>][<?= $id ?>][candidato_id]" value="<?= $id ?>">
<select class="form-select" id="pos_<?= $id ?>" name="categorias[<?= $cat ?>][<?= $id ?>][posicao]" required <?= !$podeConfirmar?'disabled':'' ?>>
<option value="">Escolha</option>
<?php for ($i=1;$i<=count($lista);$i++): ?><option value="<?= $i ?>" <?= $pos===$i?'selected':'' ?>><?= $i ?>º — <?= EstadoMaior::PESOS[$i] ?> pontos</option><?php endfor; ?>
</select></div>
<div class="col-md-6"><label class="form-label" for="just_<?= $id ?>">Justificativa individual</label>
<textarea class="form-control justificativa-fase4" id="just_<?= $id ?>" name="categorias[<?= $cat ?>][<?= $id ?>][justificativa]" rows="2" required aria-describedby="cont_<?= $id ?>" <?= !$podeConfirmar?'readonly':'' ?>><?= sanitize_output($just) ?></textarea>
<small id="cont_<?= $id ?>" class="contador" aria-live="polite">0/150 caracteres</small></div>
</div>
<?php endforeach; ?>
</section>
<?php endforeach; ?>
<?php if ($podeConfirmar): ?>
<label class="d-block mb-2"><input type="checkbox" name="confirmacao" value="1" required> Revisei todas as categorias, posições e justificativas. Entendo que a confirmação é definitiva.</label>
<button class="btn btn-success" id="confirmarOrdenacao">Confirmar definitivamente as ordenações</button>
<?php endif; ?>
</form>
<?php if (AuthManager::hasRole([PERFIL_ADMIN,PERFIL_PRESIDENTE])): ?>
<details class="mt-4"><summary>Progresso dos Oficiais Superiores habilitados</summary><ul>
<?php foreach ($progresso as $a): ?><li><?= sanitize_output($a['posto'].' '.$a['nome_guerra']) ?>: <?= $a['confirmado_em']?'confirmado':'pendente' ?></li><?php endforeach; ?>
</ul></details>
<?php endif; ?>
<script src="/assets/js/fase4.js"></script>
<?php endif; ?>
<?php require ROOT_PATH.'/views/layouts/footer.php'; ?>
