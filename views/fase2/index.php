<?php
declare(strict_types=1);
require_once ROOT_PATH . '/views/layouts/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2 class="h4 fw-bold text-primary mb-1">Fase 2: Seleção pelas Chefias de Divisão e Assessoria</h2>
        <div class="text-muted small">Indicação mandatória de exatamente 6 candidatos por categoria com base nas médias da Fase 1.</div>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="badge bg-primary p-2 fs-6">Órgão Selecionador: <?= sanitize_output($orgaoSigla) ?></span>
        <?php if (AuthManager::hasRole(PERFIL_ADMIN)): ?>
            <form method="GET" action="/index.php" class="d-inline-flex gap-1">
                <input type="hidden" name="r" value="fase2">
                <select name="orgao" class="form-select form-select-sm" onchange="this.form.submit()">
                    <optgroup label="Divisões">
                        <?php foreach (ORGAOS_SELECAO_FASE2['DIVISOES'] as $s => $n): ?>
                            <option value="<?= $s ?>" <?= $orgaoSigla === $s ? 'selected' : '' ?>><?= $s ?> - <?= $n ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                    <optgroup label="Assessorias">
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

    <!-- Regra dos 6 Candidatos por Categoria -->
    <div class="alert alert-info d-flex align-items-center justify-content-between shadow-sm py-2 px-3 mb-4">
        <div>
            <i class="bi bi-info-circle-fill fs-5 me-2 text-primary"></i>
            <strong>Regra Regimental Obrigatória:</strong> Cada chefe de divisão e assessoria deve marcar <strong>exatamente 6 candidatos</strong> em cada categoria para habilitar o envio oficial.
        </div>
        <div class="text-end">
            <button type="submit" id="btnSalvarFase2" class="btn btn-secondary fw-bold px-4 shadow-sm" disabled>
                <i class="bi bi-send-check-fill me-1"></i> Confirmar Seleção dos 24 Indicados
            </button>
        </div>
    </div>

    <!-- Navegação pelas 4 Categorias Oficiais -->
    <ul class="nav nav-pills nav-fill mb-3 gap-2" id="catFase2Tabs" role="tablist">
        <?php foreach (CATEGORIAS_PADRAO as $catKey => $catInfo): ?>
            <?php $qtdSel = count($selecionadosAtuais[$catKey] ?? []); ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= ($catKey === CAT_GRADUADO) ? 'active' : '' ?> p-3 text-start border shadow-sm"
                        id="tab-btn-<?= $catKey ?>" data-bs-toggle="tab" data-bs-target="#tab-cat-<?= $catKey ?>" type="button">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fw-bold"><?= $catInfo['nome'] ?></span>
                        <span id="badge-count-<?= $catKey ?>" class="badge <?= $qtdSel === 6 ? 'bg-success' : 'bg-warning text-dark' ?> fs-6">
                            <?= $qtdSel ?> / 6 selecionados
                        </span>
                    </div>
                    <small class="text-muted d-block mt-1"><?= $catInfo['descricao'] ?></small>
                </button>
            </li>
        <?php endforeach; ?>
    </ul>

    <!-- Conteúdo das Categorias -->
    <div class="tab-content">
        <?php foreach (CATEGORIAS_PADRAO as $catKey => $catInfo): ?>
            <div class="tab-pane fade <?= ($catKey === CAT_GRADUADO) ? 'show active' : '' ?>" id="tab-cat-<?= $catKey ?>" role="tabpanel">
                <div class="card card-comara">
                    <div class="card-comara-header">
                        <span>Candidatos Inscritos — <?= $catInfo['nome'] ?> (Ordenados por Média Aritmética)</span>
                        <small class="text-muted">Marque exatamente 6 caixas de seleção</small>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 50px;" class="text-center">Selecionar</th>
                                        <th style="width: 60px;">Foto</th>
                                        <th>Candidato</th>
                                        <th>Posto / Quadro / Esp</th>
                                        <th>Setor</th>
                                        <th>Avaliações Fase 1</th>
                                        <th>Nota TACF</th>
                                        <th>Média Geral Fase 1</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $cands = $candidatosPorCategoria[$catKey] ?? []; ?>
                                    <?php if (empty($cands)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center p-4 text-muted">Nenhum candidato localizado nesta categoria.</td>
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
                                                        <input type="checkbox" class="form-check-input fs-5"
                                                               name="selecao[<?= $catKey ?>][]" value="<?= $c['id'] ?>"
                                                               <?= $isSelected ? 'checked' : '' ?>>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <img src="/index.php?r=foto&saram=<?= $c['saram'] ?>&id=<?= $c['id'] ?>" class="cand-photo-sm" alt="Foto">
                                                </td>
                                                <td>
                                                    <strong><?= sanitize_output($c['nome_guerra']) ?></strong><br>
                                                    <small class="text-muted"><?= sanitize_output($c['nome']) ?></small>
                                                </td>
                                                <td><?= sanitize_output($c['posto']) ?> - <?= sanitize_output($c['quadro']) ?> (<?= sanitize_output($c['especialidade']) ?>)</td>
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

<!-- Painel de Progresso das Divisões e Assessorias -->
<div class="card card-comara mt-4">
    <div class="card-comara-header">
        <span><i class="bi bi-bar-chart-steps me-2"></i>Status Geral de Seleção dos Órgãos da COMARA</span>
        <span class="small text-muted">Acompanhamento em tempo real</span>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <?php foreach ($progressoGeral as $sig => $pInfo): ?>
                <div class="col-md-3">
                    <div class="border rounded p-2 bg-light">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <strong class="text-primary"><?= $sig ?></strong>
                            <?php if ($pInfo['concluido']): ?>
                                <span class="badge bg-success"><i class="bi bi-check-lg"></i> Concluído (24/24)</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split"></i> Incompleto</span>
                            <?php endif; ?>
                        </div>
                        <small class="text-muted d-block text-truncate" title="<?= $pInfo['nome'] ?>"><?= $pInfo['nome'] ?></small>
                        <div class="mt-2 text-muted" style="font-size: 0.72rem;">
                            Grad: <?= $pInfo['contagens'][CAT_GRADUADO] ?? 0 ?>/6 | 
                            Praça: <?= $pInfo['contagens'][CAT_PRACA] ?? 0 ?>/6 | 
                            SPPF: <?= $pInfo['contagens'][CAT_SPPF] ?? 0 ?>/6 | 
                            SPTF: <?= $pInfo['contagens'][CAT_SPTF] ?? 0 ?>/6
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php require_once ROOT_PATH . '/views/layouts/footer.php'; ?>
