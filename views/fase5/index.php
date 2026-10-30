<?php
declare(strict_types=1);
require_once ROOT_PATH . '/views/layouts/header.php';

$homologado = $apuracao['homologado'];
$dadosHomol = $apuracao['dados_homologacao'];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="h4 fw-bold text-primary mb-1">Fase 5: Apuração, Voto de Minerva e Homologação Oficial</h2>
        <div class="text-muted small">Soma das ordenações do Estado-Maior (5–3–1), quatro critérios, votos da Fase 3 e decisão presidencial quando necessária.</div>
    </div>
    <div>
        <?php if ($homologado): ?>
            <span class="badge bg-success p-2 fs-6"><i class="bi bi-lock-fill me-1"></i> Pleito Oficialmente Homologado</span>
        <?php else: ?>
            <span class="badge bg-warning text-dark p-2 fs-6"><i class="bi bi-clock-history me-1"></i> Aguardando Homologação</span>
        <?php endif; ?>
    </div>
</div>

<!-- Cards dos 4 Vencedores Proclamados -->
<div class="row g-3 mb-4">
    <?php foreach (CATEGORIAS_PADRAO as $catKey => $catInfo): ?>
        <?php
        $dadosCat = $apuracao['categorias'][$catKey] ?? null;
        $vencedor = $dadosCat['vencedor'] ?? null;
        ?>
        <div class="col-md-6 col-xl-3">
            <div class="card card-comara h-100 shadow-sm border-2 <?= $vencedor ? 'border-primary' : 'border-warning' ?>">
                <div class="card-header bg-light py-2 text-center">
                    <span class="badge bg-primary text-uppercase"><?= $catInfo['sigla'] ?></span>
                    <strong class="d-block text-primary mt-1"><?= $catInfo['nome'] ?></strong>
                </div>
                <div class="card-body text-center p-3">
                    <?php if ($vencedor): ?>
                        <div class="position-relative d-inline-block mb-2">
                            <img src="/index.php?r=foto&saram=<?= $vencedor['saram'] ?>&id=<?= $vencedor['candidato_id'] ?>" 
                                 class="cand-photo border border-3 border-primary shadow" alt="Foto">
                            <span class="position-absolute bottom-0 start-50 translate-middle-x badge bg-success fs-6">
                                <i class="bi bi-trophy-fill text-warning"></i> 1º LUGAR
                            </span>
                        </div>
                        <h5 class="fw-bold text-dark mb-0 mt-2">
                            <?= sanitize_output($vencedor['posto']) ?> <?= sanitize_output($vencedor['nome_guerra']) ?>
                        </h5>
                        <small class="text-muted d-block text-truncate"><?= sanitize_output($vencedor['nome']) ?></small>
                        <div class="small text-secondary mb-2"><?= sanitize_output($vencedor['setor']) ?> (<?= sanitize_output($vencedor['divisao_sigla']) ?>)</div>
                        
                        <div class="p-2 bg-light rounded border">
                            <div class="h4 fw-bold text-primary mb-0"><?= $vencedor['pontos_fase4'] ?? $vencedor['total_votos'] ?></div>
                            <small class="text-muted text-uppercase fw-semibold" style="font-size: 0.7rem;">Pontos do Estado-Maior</small>
                        </div>

                        <?php if ($dadosCat['minerva_registrado']): ?>
                            <span class="badge bg-warning text-dark mt-2 d-block py-1">
                                <i class="bi bi-pen-fill me-1"></i> <?= !empty($dadosCat['excepcional']) ? 'Escolha excepcional fundamentada' : 'Decidido por Voto de Minerva' ?>
                            </span>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="py-4 text-warning">
                            <i class="bi bi-exclamation-triangle fs-1 d-block mb-2"></i>
                            <strong>Decisão pendente</strong>
                            <div class="small text-muted">Aguardando Voto de Minerva do Sr. Presidente.</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Detalhamento da Apuração e Ranking por Categoria -->
<?php foreach (CATEGORIAS_PADRAO as $catKey => $catInfo): ?>
    <?php
    $dadosCat = $apuracao['categorias'][$catKey] ?? null;
    $ranking = $dadosCat['ranking'] ?? [];
    $requerMinerva = $dadosCat['requer_voto_de_minerva'] ?? false;
    $candsEmpatados = $dadosCat['candidatos_empatados'] ?? [];
    ?>
    <div class="card card-comara shadow-sm mb-4">
        <div class="card-comara-header">
            <span class="fs-5 text-primary fw-bold">Resultado consolidado — <?= $catInfo['nome'] ?></span>
            <span class="badge bg-secondary"><?= count($ranking) ?> Candidatos Classificados</span>
        </div>
        <div class="card-body p-0">
            
            <?php if ($requerMinerva && !$homologado): ?>
                <!-- Alerta e Módulo de Voto de Minerva do Presidente -->
                <div class="p-3 bg-warning-subtle border-bottom border-warning">
                    <div class="d-flex align-items-center mb-2">
                        <i class="bi bi-exclamation-triangle-fill text-warning fs-3 me-2"></i>
                        <div>
                            <strong class="text-dark fs-6"><?= !empty($dadosCat['excepcional']) ? 'Exceção: categoria sem finalistas' : 'Empate persistente no resultado final' ?></strong>
                            <div class="small text-muted"><?= !empty($dadosCat['excepcional']) ? 'Somente esta categoria ficou sem representantes. A Presidência pode escolher entre os integrantes ativos desta categoria registrados no encerramento da urna. Nenhuma outra categoria tem sua lista ampliada.' : 'Persistiu empate após pontos 5–3–1, os quatro critérios e os votos da Fase 3. Somente os empatados abaixo podem ser escolhidos.' ?></div>
                        </div>
                    </div>

                    <?php if (AuthManager::hasRole([PERFIL_PRESIDENTE, PERFIL_ADMIN])): ?>
                        <form onsubmit="return confirm('Confirmar esta decisão fundamentada?')" method="POST" action="/index.php?r=fase5/minerva" class="card p-3 bg-white border mt-2">
                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                            <input type="hidden" name="categoria" value="<?= $catKey ?>">

                            <div class="fw-bold text-primary mb-2">Selecione o candidato a ser desempatado pelo Sr. Presidente:</div>
                            <div class="row g-2 mb-3">
                                <?php foreach ($candsEmpatados as $ce): ?>
                                    <div class="col-md-6">
                                        <div class="p-2 border rounded d-flex align-items-center bg-light">
                                            <input class="form-check-input me-3 fs-5" type="radio" 
                                                   name="candidato_id" id="min_<?= $catKey ?>_<?= $ce['candidato_id'] ?>" 
                                                   value="<?= $ce['candidato_id'] ?>" required>
                                            <img src="/index.php?r=foto&saram=<?= $ce['saram'] ?>&id=<?= $ce['candidato_id'] ?>" class="cand-photo-sm me-2" alt="Foto">
                                            <div>
                                                <label class="form-check-label fw-bold d-block" for="min_<?= $catKey ?>_<?= $ce['candidato_id'] ?>">
                                                    <?= sanitize_output($ce['posto']) ?> <?= sanitize_output($ce['nome_guerra']) ?>
                                                </label>
                                                <small class="text-muted"><?= sanitize_output($ce['nome']) ?> (<?= $ce['total_votos'] ?> votos)</small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold small">Justificativa Regimental do Voto de Minerva <span class="text-danger">*</span></label>
                                <textarea class="form-control" name="justificativa" rows="2" required placeholder="Fundamente a decisão do Voto de Minerva..."></textarea>
                            </div>

                            <div>
                                <button type="submit" class="btn btn-warning fw-bold">
                                    <i class="bi bi-pen-fill me-1"></i> Proferir Voto de Minerva Oficial
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="badge bg-danger p-2">Aguardando decisão e assinatura do Sr. Presidente da COMARA.</div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 70px;" class="text-center">Classificação</th>
                            <th style="width: 60px;">Foto</th>
                            <th>Candidato / SARAM</th>
                            <th>Posto / Especialidade</th>
                            <th>Setor</th>
                            <th class="text-center">Pontos Fase 4 / Votos Fase 3</th>
                            <th>Critério 1 (Vitórias nos 2 anos anteriores)</th>
                            <th>Critério 2 (COMARA)</th>
                            <th>Critério 3 (Serviço)</th>
                            <th>Critério 4 (TACF)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ranking as $pos => $cand): ?>
                            <tr class="<?= $pos === 0 ? 'table-success fw-semibold' : '' ?>">
                                <td class="text-center">
                                    <?php if ($pos === 0): ?>
                                        <span class="badge bg-success fs-6"><i class="bi bi-award-fill"></i> 1º Lugar</span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-dark border"><?= $pos + 1 ?>º</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <img src="/index.php?r=foto&saram=<?= $cand['saram'] ?>&id=<?= $cand['candidato_id'] ?>" class="cand-photo-sm" alt="Foto">
                                </td>
                                <td>
                                    <strong><?= sanitize_output($cand['posto']) ?> <?= sanitize_output($cand['nome_guerra']) ?></strong><br>
                                    <small class="text-muted"><?= sanitize_output($cand['nome']) ?></small>
                                </td>
                                <td><?= sanitize_output($cand['quadro']) ?> - <?= sanitize_output($cand['especialidade']) ?></td>
                                <td><?= sanitize_output($cand['setor']) ?> (<?= sanitize_output($cand['divisao_sigla']) ?>)</td>
                                <td class="text-center">
                                    <span class="badge bg-primary fs-5 px-3"><?= $cand['pontos_fase4'] ?? '—' ?> / <?= $cand['total_votos'] ?></span>
                                </td>
                                <td>
                                    <small class="badge <?= (int)$cand['vitorias_ultimos_dois_anos'] === 0 ? 'bg-success' : 'bg-secondary' ?>">
                                        <?= (int)$cand['vitorias_ultimos_dois_anos'] === 0 ? 'Não venceu nos 2 anos anteriores' : $cand['vitorias_ultimos_dois_anos'] . ' vitória(s) nos 2 anos anteriores' ?>
                                    </small>
                                </td>
                                <td><small class="text-muted"><?= $cand['data_admissao_comara'] ? date('d/m/Y', strtotime($cand['data_admissao_comara'])) : '--' ?></small></td>
                                <td><small class="text-muted"><?= $cand['data_inicio_servico'] ? date('d/m/Y', strtotime($cand['data_inicio_servico'])) : '--' ?></small></td>
                                <td>
                                    <?php if ($cand['nota_tacf']): ?>
                                        <span class="badge bg-info text-dark"><?= number_format((float)$cand['nota_tacf'], 2, ',', '.') ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">--</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<!-- Painel de Homologação Presidencial Definitiva -->
<div class="card card-comara shadow-lg border-2 border-primary mb-4">
    <div class="card-comara-header bg-primary text-white">
        <span class="fs-5 fw-bold"><i class="bi bi-patch-check-fill me-2"></i>Ato de Homologação Oficial do Pleito</span>
        <span class="badge bg-light text-primary">Prerrogativa do Sr. Presidente</span>
    </div>
    <div class="card-body p-4">
        <?php if ($homologado): ?>
            <div class="alert alert-success d-flex align-items-center p-3 mb-3">
                <i class="bi bi-lock-fill fs-2 me-3"></i>
                <div>
                    <h5 class="alert-heading fw-bold mb-1">Pleito Homologado com Sucesso!</h5>
                    <p class="mb-0 small">Os resultados foram proclamados e permanentemente bloqueados para alterações. Os relatórios oficiais estão disponíveis para afixação e publicação.</p>
                </div>
            </div>

            <div class="p-3 bg-light rounded border mb-3">
                <strong class="d-block text-dark mb-1">Ata Oficial de Homologação Registrada:</strong>
                <div class="text-muted font-monospace small" style="white-space: pre-wrap;"><?= sanitize_output($dadosHomol['ata_homologacao'] ?? '') ?></div>
                <div class="small text-secondary mt-2 border-top pt-2">
                    Homologado em: <strong><?= date('d/m/Y H:i:s', strtotime($dadosHomol['data_homologacao'])) ?></strong>
                </div>
            </div>
        <?php elseif (AuthManager::hasRole([PERFIL_PRESIDENTE, PERFIL_ADMIN])): ?>
            <form method="POST" action="/index.php?r=fase5/homologar">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                <p class="text-muted small">
                    Ao homologar este pleito, o Sr. Presidente confirma e proclama os padrões eleitos das 4 categorias regimentais. 
                    <strong class="text-danger">A homologação bloqueia permanentemente a edição de notas, votos e resultados.</strong>
                </p>

                <div class="mb-3">
                    <label for="ata_homologacao" class="form-label fw-bold">Texto da Ata de Homologação Oficial <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="ata_homologacao" name="ata_homologacao" rows="4" required minlength="20"
                              placeholder="HOMOLOGO, no uso das atribuições que me são conferidas pelo Regulamento da COMARA (ROCA 21-55), a eleição dos Padrões Militar e Civil do ano corrente..."></textarea>
                </div>

                <div class="text-end">
                    <button type="submit" class="btn btn-success btn-lg fw-bold px-4 shadow">
                        <i class="bi bi-shield-lock-fill me-2"></i> HOMOLOGAR E BLOQUEAR RESULTADOS
                    </button>
                </div>
            </form>
        <?php else: ?>
            <div class="alert alert-secondary mb-0">
                Aguardando assinatura da Ata de Homologação pelo Sr. Presidente da COMARA.
            </div>
        <?php endif; ?>

        <!-- Links Diretos para os Relatórios Padronizados -->
        <hr class="my-4">
        <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <span class="small text-muted fw-bold">Documentos e Publicações Oficiais:</span>
            <div class="d-flex gap-2">
                <?php if (AuthManager::hasRole(PERFIL_ADMIN)): ?>
                    <a href="/index.php?r=relatorios/placa" class="btn btn-warning fw-bold text-dark shadow-sm">
                        <i class="bi bi-trophy-fill me-1"></i> Placa Alusiva (Hall do Comando)
                    </a>
                <?php endif; ?>
                <a href="/index.php?r=relatorios/site" class="btn btn-primary fw-bold shadow-sm">
                    <i class="bi bi-globe me-1"></i> Publicação Web (Site COMARA)
                </a>
                <a href="/index.php?r=relatorios/estatisticas" class="btn btn-outline-secondary fw-bold">
                    <i class="bi bi-file-earmark-bar-graph me-1"></i> Relatório Estatístico Completo
                </a>
            </div>
        </div>

    </div>
</div>

<?php require_once ROOT_PATH . '/views/layouts/footer.php'; ?>
