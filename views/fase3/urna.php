<?php
declare(strict_types=1);
require_once ROOT_PATH . '/views/layouts/header.php';
?>

<div class="row justify-content-center">
    <div class="col-xl-11">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h2 class="h4 fw-bold text-primary mb-1">Fase 3: Votação Geral do Efetivo</h2>
                <div class="text-muted small">Turno único • Urna eletrônica cega • Sigilo absoluto garantido por desacoplamento criptográfico.</div>
            </div>
            <div>
                <span class="badge bg-success fs-6 p-2"><i class="bi bi-shield-lock-fill me-1"></i> Urna Criptografada</span>
            </div>
        </div>

        <?php if ($jaVotou): ?>
            <!-- Tela de Eleitor que Já Votou -->
            <div class="card card-comara text-center py-5 shadow-sm">
                <div class="card-body">
                    <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                    <h3 class="fw-bold mt-3 text-dark">Seu voto já foi registrado neste ciclo eleitoral!</h3>
                    <p class="text-muted max-w-600 mx-auto">
                        Conforme as diretrizes regimentais da COMARA, cada integrante do efetivo pode registrar apenas um voto por ano por CPF.
                    </p>
                    <div class="p-3 bg-light rounded d-inline-block border my-3">
                        <span class="text-muted small d-block">Código Autenticador do seu Comprovante:</span>
                        <code class="fs-4 fw-bold text-primary"><?= sanitize_output($registroVoto['comprovante']) ?></code>
                        <div class="small text-muted mt-1">Registrado em: <?= date('d/m/Y H:i', strtotime($registroVoto['data_voto'])) ?></div>
                    </div>
                    <div>
                        <a href="/index.php?r=fase3/comprovante" class="btn btn-outline-primary fw-bold mt-2">
                            <i class="bi bi-printer-fill me-1"></i> Imprimir 2ª Via do Comprovante
                        </a>
                    </div>
                </div>
            </div>
        <?php elseif (!empty($erroUrna)): ?>
            <div class="alert alert-warning" role="alert"><?= sanitize_output($erroUrna) ?></div>
        <?php elseif (!$faseAberta): ?>
            <!-- Votação Não Aberta -->
            <div class="card card-comara text-center py-5 shadow-sm">
                <div class="card-body">
                    <i class="bi bi-clock-history text-warning" style="font-size: 3.5rem;"></i>
                    <h3 class="fw-bold mt-3 text-dark">A Urna Geral ainda não está aberta para votação</h3>
                    <p class="text-muted max-w-600 mx-auto">
                        A Fase 3 (Votação Geral do Efetivo) será liberada após a conclusão da seleção pelas Divisões e Assessorias (Fase 2).
                        Acompanhe o cronograma regimental no Painel Geral.
                    </p>
                    <a href="/index.php?r=dashboard" class="btn btn-primary fw-bold">Voltar ao Painel</a>
                </div>
            </div>
        <?php else: ?>
            <!-- Cédula da Urna Eletrônica -->
            <form id="formUrna" method="POST" action="/index.php?r=fase3/votar">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                <!-- Caixa de Identificação e Validação de CPF -->
                <div class="card card-comara shadow-sm mb-4 border-primary">
                    <div class="card-comara-header bg-primary text-white">
                        <span><i class="bi bi-person-vcard me-2"></i>Identificação do Eleitor e Validação de Unicidade</span>
                        <span class="badge bg-light text-primary">Obrigatório</span>
                    </div>
                    <div class="card-body p-4 bg-light">
                        <div class="row align-items-center">
                            <div class="col-md-7">
                                <label for="cpfVotacao" class="form-label fw-bold text-dark fs-5">
                                    Digite o seu CPF para habilitar a cédula <span class="text-danger">*</span>
                                </label>
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text bg-white"><i class="bi bi-shield-lock text-primary"></i></span>
                                    <input type="text" class="form-control fw-bold" id="cpfVotacao" name="cpf" 
                                           placeholder="000.000.000-00" maxlength="14" required
                                           value="<?= sanitize_output($userLogado['cpf'] ?? '') ?>">
                                </div>
                                <div class="form-text text-muted">
                                    <i class="bi bi-info-circle me-1"></i> O CPF é validado pelos dígitos verificadores oficiais e impede votos duplicados. Seu voto é gravado anonimamente.
                                </div>
                            </div>
                            <div class="col-md-5 mt-3 mt-md-0 border-start ps-md-4">
                                <div class="small text-muted fw-bold">Eleitor Autenticado:</div>
                                <div class="fs-5 fw-bold text-primary"><?= sanitize_output($userLogado['posto']) ?> <?= sanitize_output($userLogado['nome_guerra']) ?></div>
                                <div class="small text-muted"><?= sanitize_output($userLogado['nome']) ?></div>
                                <div class="small text-muted"><?= sanitize_output($userLogado['setor']) ?> (<?= sanitize_output($userLogado['divisao_sigla']) ?>)</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cédula com as 4 Categorias -->
                <?php foreach (CATEGORIAS_PADRAO as $catKey => $catInfo): ?>
                    <?php $cands = $candidatosUrna[$catKey] ?? []; ?>
                    <div class="card card-comara shadow-sm mb-4">
                        <div class="card-comara-header">
                            <div>
                                <span class="fs-5 fw-bold text-primary"><?= $catInfo['nome'] ?></span>
                                <small class="text-muted d-block"><?= $catInfo['descricao'] ?></small>
                            </div>
                            <span class="badge bg-secondary"><?= count($cands) ?> Candidatos Indicados</span>
                        </div>
                        <div class="card-body p-4">
                            <?php if (empty($cands)): ?>
                                <div class="alert alert-warning mb-0">Nenhum candidato indicado disponível nesta categoria.</div>
                            <?php else: ?>
                                <div class="row g-3">
                                    <?php foreach ($cands as $cand): ?>
                                        <div class="col-md-4 col-lg-3">
                                            <div class="card card-comara cand-card urna-card-cand h-100 p-3 text-center cursor-pointer shadow-sm position-relative">
                                                <div class="position-absolute top-0 end-0 m-2">
                                                    <input class="form-check-input fs-4" type="radio" 
                                                           name="voto_<?= $catKey ?>" 
                                                           value="<?= $cand['id'] ?>" required>
                                                </div>
                                                <img src="/index.php?r=foto&saram=<?= $cand['saram'] ?>&id=<?= $cand['id'] ?>" 
                                                     class="cand-photo mx-auto mb-2" alt="Foto">
                                                <h6 class="fw-bold text-primary mb-1">
                                                    <?= sanitize_output($cand['posto']) ?> <?= sanitize_output($cand['nome_guerra']) ?>
                                                </h6>
                                                <div class="small text-secondary mb-1">
                                                    <?= sanitize_output($cand['setor']) ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Botão de Envio do Voto -->
                <div class="card card-comara shadow p-4 text-center bg-light">
                    <h5 class="fw-bold text-dark mb-2">Pronto para confirmar seu voto?</h5>
                    <p class="text-muted small mb-3">
                        Revise suas escolhas. Após a confirmação, o voto será depositado de forma irreversível e sigilosa na urna.
                    </p>
                    <div>
                        <button type="submit" class="btn btn-success btn-lg fw-bold px-5 py-3 shadow">
                            <i class="bi bi-check2-circle me-2"></i> CONFIRMAR E REGISTRAR VOTO
                        </button>
                    </div>
                </div>
            </form>
        <?php endif; ?>

    </div>
</div>

<?php require_once ROOT_PATH . '/views/layouts/footer.php'; ?>
