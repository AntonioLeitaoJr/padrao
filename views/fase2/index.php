<?php
declare(strict_types=1);
require_once ROOT_PATH . '/views/layouts/header.php';

$todosOrgaosFase2 = array_merge(
    ORGAOS_SELECAO_FASE2['DIVISOES'],
    ORGAOS_SELECAO_FASE2['ASSESSORIAS']
);
$nomeOrgaoAtual = $todosOrgaosFase2[$orgaoSigla] ?? $orgaoSigla;
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2 class="h4 fw-bold text-primary mb-1">Fase 2: Seleção por Divisões e Assessorias</h2>
        <div class="text-muted small">
            Seleção regimental de <strong>1 candidato por categoria</strong> (entre os 20% com melhores notas da Fase 1) por cada Divisão e pelas Assessorias unificadas, formando os 6 selecionados para a Urna da Fase 3.
        </div>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="badge bg-primary p-2 fs-6">
            <i class="bi bi-building me-1"></i> <?= sanitize_output($orgaoSigla) ?> — <?= sanitize_output($nomeOrgaoAtual) ?>
        </span>
        <?php if (AuthManager::hasRole(PERFIL_ADMIN)): ?>
            <form method="GET" action="/index.php" class="d-inline-flex gap-1">
                <input type="hidden" name="r" value="fase2">
                <select name="orgao" class="form-select form-select-sm" onchange="this.form.submit()">
                    <optgroup label="Divisões (5 Selecionadores)">
                        <?php foreach (ORGAOS_SELECAO_FASE2['DIVISOES'] as $s => $n): ?>
                            <option value="<?= $s ?>" <?= $orgaoSigla === $s ? 'selected' : '' ?>><?= $s ?> - <?= $n ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                    <optgroup label="Assessorias (1 Representante Unificado)">
                        <?php foreach (ORGAOS_SELECAO_FASE2['ASSESSORIAS'] as $s => $n): ?>
                            <option value="<?= $s ?>" <?= $orgaoSigla === $s ? 'selected' : '' ?>><?= $s ?> - <?= $n ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                </select>
            </form>
        <?php endif; ?>
    </div>
</div>

<form id="formFase2" method="POST" action="/index.php?r=fase2">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="orgao" value="<?= sanitize_output($orgaoSigla) ?>">

    <!-- Regra de Seleção de 1 por Categoria -->
    <div class="alert alert-info d-flex align-items-center justify-content-between shadow-sm py-2 px-3 mb-4">
        <div>
            <i class="bi bi-info-circle-fill fs-5 me-2 text-primary"></i>
            <strong>Regra Regimental Obrigatória:</strong> Escolha <strong>exatamente 1 candidato</strong> em cada categoria para representar <strong><?= sanitize_output($nomeOrgaoAtual) ?></strong> na votação geral da Fase 3.
            <div class="small text-muted mt-1">Estão listados apenas os <strong>20% com melhores notas da Fase 1</strong> de sua unidade, exibidos em ordem alfabética.</div>
        </div>
        <div class="text-end">
            <button type="submit" id="btnSalvarFase2" class="btn btn-primary fw-bold px-4 shadow-sm">
                <i class="bi bi-send-check-fill me-1"></i> Confirmar Seleção Oficial
            </button>
        </div>
    </div>

    <!-- Navegação pelas 4 Categorias Oficiais -->
    <ul class="nav nav-pills nav-fill mb-3 gap-2" id="catFase2Tabs" role="tablist">
        <?php foreach (CATEGORIAS_PADRAO as $catKey => $catInfo): ?>
            <?php 
            $qtdSel = count($selecionadosAtuais[$catKey] ?? []); 
            $candsDisponiveis = count($candidatosPorCategoria[$catKey] ?? []);
            ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= ($catKey === CAT_GRADUADO) ? 'active' : '' ?> p-3 text-start border shadow-sm"
                        id="tab-btn-<?= $catKey ?>" data-bs-toggle="tab" data-bs-target="#tab-cat-<?= $catKey ?>" type="button">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fw-bold"><?= $catInfo['nome'] ?></span>
                        <span id="badge-count-<?= $catKey ?>" class="badge <?= ($qtdSel >= 1 || $candsDisponiveis === 0) ? 'bg-success' : 'bg-warning text-dark' ?> fs-6">
                            <?= $qtdSel ?> / 1 selecionado
                        </span>
                    </div>
                    <small class="text-muted d-block mt-1">
                        <?= $candsDisponiveis ?> concorrente(s) no top 20%
                    </small>
                </button>
            </li>
        <?php endforeach; ?>
    </ul>

    <!-- Conteúdo das Categorias -->
    <div class="tab-content">
        <?php foreach (CATEGORIAS_PADRAO as $catKey => $catInfo): ?>
            <div class="tab-pane fade <?= ($catKey === CAT_GRADUADO) ? 'show active' : '' ?>" id="tab-cat-<?= $catKey ?>" role="tabpanel">
                <div class="card card-comara shadow-sm">
                    <div class="card-comara-header d-flex justify-content-between align-items-center">
                        <div>
                            <span><?= $catInfo['nome'] ?> — 20% Melhores Notas da Fase 1 (Ordem Alfabética)</span>
                            <div class="small text-muted">Selecione 1 integrante de <?= sanitize_output($nomeOrgaoAtual) ?> para compor a urna</div>
                        </div>
                        <span class="badge bg-light text-dark border">
                            Total disponível: <?= count($candidatosPorCategoria[$catKey] ?? []) ?>
                        </span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 70px;" class="text-center">Escolha</th>
                                        <th style="width: 60px;">Foto</th>
                                        <th>Candidato</th>
                                        <th>Posto / Especialidade</th>
                                        <th>Setor / Lotação</th>
                                        <th>Fichas F1</th>
                                        <th>Nota TACF</th>
                                        <th>Média Geral Fase 1</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $cands = $candidatosPorCategoria[$catKey] ?? []; ?>
                                    <?php if (empty($cands)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center p-4 text-muted">
                                                <i class="bi bi-info-circle fs-4 d-block mb-1 text-secondary"></i>
                                                Nenhum candidato apto nesta categoria para <?= sanitize_output($nomeOrgaoAtual) ?>.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($cands as $c): ?>
                                            <?php 
                                            $isNob = ((int)($c['foi_nob'] ?? 0) === 1 && (float)$c['media_fase1'] == 0);
                                            $isSelected = in_array((int)$c['id'], array_map('intval', $selecionadosAtuais[$catKey] ?? []), true); 
                                            ?>
                                            <tr class="<?= $isSelected ? 'table-primary' : ($isNob ? 'text-muted opacity-75' : '') ?>">
                                                <td class="text-center">
                                                    <?php if ($isNob): ?>
                                                        <span class="badge bg-secondary" title="Militar marcado como Não Observado (NOB) na Fase 1">
                                                            <i class="bi bi-eye-slash"></i>
                                                        </span>
                                                    <?php else: ?>
                                                        <input type="radio" class="form-check-input fs-4 cursor-pointer radio-cand-<?= $catKey ?>"
                                                               name="selecao[<?= $catKey ?>]" value="<?= $c['id'] ?>"
                                                               <?= $isSelected ? 'checked' : '' ?>
                                                               onchange="atualizarContagemFase2('<?= $catKey ?>')">
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <img src="/index.php?r=foto&saram=<?= $c['saram'] ?>&id=<?= $c['id'] ?>" class="cand-photo-sm" alt="Foto">
                                                </td>
                                                <td>
                                                    <strong><?= sanitize_output($c['nome_guerra']) ?></strong><br>
                                                    <small class="text-muted"><?= sanitize_output($c['nome']) ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-light text-dark border"><?= sanitize_output($c['posto']) ?></span>
                                                    <small class="text-muted d-block"><?= sanitize_output($c['quadro']) ?> - <?= sanitize_output($c['especialidade']) ?></small>
                                                </td>
                                                <td><?= sanitize_output($c['setor']) ?> (<?= sanitize_output($c['divisao_sigla']) ?>)</td>
                                                <td>
                                                    <span class="badge bg-light text-dark border"><?= $c['qtd_fichas'] ?> ficha(s)</span>
                                                </td>
                                                <td>
                                                    <?php if ($c['nota_tacf']): ?>
                                                        <span class="badge bg-info text-dark"><?= number_format((float)$c['nota_tacf'], 2, ',', '.') ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">--</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($isNob): ?>
                                                        <span class="badge bg-secondary fs-6" title="Não Observado na Fase 1">NOB</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-primary fs-6"><?= number_format((float)$c['media_fase1'], 2, ',', '.') ?></span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</form>

<!-- Painel de Progresso das Divisões e Assessorias (6 Selecionadores) -->
<div class="card card-comara mt-4 shadow-sm">
    <div class="card-comara-header">
        <span><i class="bi bi-bar-chart-steps me-2"></i>Status de Seleção dos 6 Órgãos da COMARA (Fase 2 &rarr; Fase 3)</span>
        <span class="small text-muted">Acompanhamento dos 6 indicados por categoria</span>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <?php foreach ($progressoGeral as $sig => $pInfo): ?>
                <div class="col-md-4 col-lg-2">
                    <div class="border rounded p-2 bg-light h-100 shadow-sm text-center">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <strong class="text-primary"><?= $sig ?></strong>
                            <?php if ($pInfo['concluido']): ?>
                                <span class="badge bg-success"><i class="bi bi-check-lg"></i> Pronto</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split"></i> Pendente</span>
                            <?php endif; ?>
                        </div>
                        <small class="text-muted d-block text-truncate mb-2" title="<?= $pInfo['nome'] ?>"><?= $pInfo['nome'] ?></small>
                        <div class="text-muted" style="font-size: 0.72rem;">
                            Grad: <?= $pInfo['contagens'][CAT_GRADUADO] ?? 0 ?>/1 | 
                            Praça: <?= $pInfo['contagens'][CAT_PRACA] ?? 0 ?>/1
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
function atualizarContagemFase2(catKey) {
    const radios = document.querySelectorAll('.radio-cand-' + catKey);
    let marcado = false;
    radios.forEach(r => {
        if (r.checked) marcado = true;
    });

    const badge = document.getElementById('badge-count-' + catKey);
    if (badge) {
        if (marcado) {
            badge.className = 'badge bg-success fs-6';
            badge.innerText = '1 / 1 selecionado';
        } else {
            badge.className = 'badge bg-warning text-dark fs-6';
            badge.innerText = '0 / 1 selecionado';
        }
    }
}
</script>

<?php require_once ROOT_PATH . '/views/layouts/footer.php'; ?>
