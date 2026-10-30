<?php
declare(strict_types=1);
require_once ROOT_PATH . '/views/layouts/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="h4 fw-bold text-primary mb-1">Gestão de Prazos e Controle das Fases do Pleito</h2>
        <div class="text-muted small">Configuração de datas, horários de início e encerramento e controle regimental de transição de etapas.</div>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="badge bg-primary p-2 fs-6">Pleito <?= $pleito['ano'] ?></span>
        <button type="button" class="btn btn-outline-danger btn-sm fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalReiniciarPleito" title="Reiniciar este pleito eleitoral">
            <i class="bi bi-arrow-counterclockwise me-1"></i> Reiniciar Pleito
        </button>
    </div>
</div>

<div class="row g-4">
    <!-- Formulário de Prazos -->
    <div class="col-lg-8">
        <form method="POST" action="/index.php?r=admin/pleito" class="card card-comara shadow-sm">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <div class="card-comara-header">
                <span><i class="bi bi-calendar-event me-2"></i>Cronograma das 5 Fases Regimentais</span>
                <span class="badge bg-secondary">Outubro / Novembro</span>
            </div>

            <div class="card-body p-4">
                <div class="mb-4">
                    <label for="titulo" class="form-label fw-bold">Título do Pleito Oficial</label>
                    <input type="text" class="form-control form-control-lg fw-bold text-primary" id="titulo" name="titulo" 
                           value="<?= sanitize_output($pleito['titulo']) ?>" required>
                </div>

                <?php
                $fasesConfig = [
                    1 => ['nome' => 'Fase 1: Avaliações dos Chefes Diretos & TACF', 'ini' => 'fase1_inicio', 'fim' => 'fase1_fim'],
                    2 => ['nome' => 'Fase 2: Seleção pelas Chefias de Divisão e Assessoria', 'ini' => 'fase2_inicio', 'fim' => 'fase2_fim'],
                    3 => ['nome' => 'Fase 3: Votação Geral do Efetivo (Urna Turno Único)', 'ini' => 'fase3_inicio', 'fim' => 'fase3_fim'],
                    4 => ['nome' => 'Fase 4: Validação pela Direção Superior', 'ini' => 'fase4_inicio', 'fim' => 'fase4_fim'],
                    5 => ['nome' => 'Fase 5: Homologação dos Resultados e Desempate', 'ini' => 'fase5_inicio', 'fim' => 'fase5_fim'],
                ];
                ?>

                <?php foreach ($fasesConfig as $num => $f): ?>
                    <div class="p-3 border rounded mb-3 bg-light">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <strong class="text-dark"><i class="bi bi-clock me-1 text-primary"></i> <?= $f['nome'] ?></strong>
                            <?php if ((int)$pleito['fase_atual'] === $num): ?>
                                <span class="badge bg-primary">Fase em Curso</span>
                            <?php elseif ((int)$pleito['fase_atual'] > $num): ?>
                                <span class="badge bg-success">Concluída</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Aguardando</span>
                            <?php endif; ?>
                        </div>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label small text-muted">Início (Data e Hora)</label>
                                <input type="datetime-local" class="form-control" name="<?= $f['ini'] ?>"
                                       value="<?= $pleito[$f['ini']] ? date('Y-m-d\TH:i', strtotime($pleito[$f['ini']])) : '' ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small text-muted">Encerramento (Data e Hora)</label>
                                <input type="datetime-local" class="form-control" name="<?= $f['fim'] ?>"
                                       value="<?= $pleito[$f['fim']] ? date('Y-m-d\TH:i', strtotime($pleito[$f['fim']])) : '' ?>">
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="text-end mt-4">
                    <button type="submit" class="btn btn-primary fw-bold px-4">
                        <i class="bi bi-save me-1"></i> Salvar Alterações de Cronograma
                    </button>
                </div>
            </div>
        </form>
    </div>

    <?php if ((int)$pleito['fase_atual'] >= 3): ?>
    <div class="alert alert-info">
        Ao encerrar a Fase 3, a urna será fechada definitivamente. Empates no corte bloqueiam a Fase 4 até decisão fundamentada da Presidência.
        <a href="/index.php?r=fase4">Consultar corte e confirmações do Estado-Maior</a>.
        A Fase 5 exige a confirmação de todos os avaliadores habilitados.
    </div>
    <?php endif; ?>
    <!-- Painel de Avanço de Fase e Bloqueios -->
    <div class="col-lg-4">
        <div class="card card-comara shadow-sm border-2 border-primary mb-4">
            <div class="card-comara-header bg-primary text-white">
                <span><i class="bi bi-forward-fill me-2"></i>Controle de Transição de Fase</span>
            </div>
            <div class="card-body p-4 text-center">
                <div class="mb-3">
                    <div class="small text-muted text-uppercase fw-bold">Etapa Atual do Sistema:</div>
                    <div class="display-6 fw-bold text-primary">Fase <?= $pleito['fase_atual'] ?></div>
                    <div class="fw-semibold text-dark"><?= FASES_PROCESSO[$pleito['fase_atual']]['nome'] ?></div>
                </div>

                <hr>

                <?php if ((int)$pleito['fase_atual'] === FASE_1): ?>
                    <div class="text-start mb-3">
                        <label class="small text-muted fw-bold d-block mb-1">Verificação Prévia de Avaliações:</label>
                        <?php if ($verificacaoFase1['pode_avancar']): ?>
                            <div class="badge bg-success p-2 d-block text-start">
                                <i class="bi bi-check-circle-fill me-1"></i> 100% das avaliações obrigatórias concluídas.
                            </div>
                        <?php else: ?>
                            <div class="badge bg-warning text-dark p-2 d-block text-start">
                                <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= $verificacaoFase1['total_pendentes'] ?> avaliação(ões) pendente(s) de chefes diretos.
                            </div>
                            <small class="text-muted d-block mt-1">O avanço com pendências exigirá o registro formal de justificativa.</small>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ((int)$pleito['fase_atual'] < 5): ?>
                    <?php if ((int)$pleito['fase_atual'] === FASE_1 && !$verificacaoFase1['pode_avancar']): ?>
                        <button type="button" class="btn btn-warning btn-lg w-100 fw-bold shadow-sm mb-2" data-bs-toggle="modal" data-bs-target="#modalAvancarComPendencias">
                            <i class="bi bi-arrow-right-circle-fill me-1"></i> Avançar para Fase 2 (com Justificativa)
                        </button>
                    <?php else: ?>
                        <form method="POST" action="/index.php?r=admin/avancar_fase" onsubmit="return confirm('Confirma o avanço oficial do pleito para a próxima fase regimental?');">
                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                            <button type="submit" class="btn btn-warning btn-lg w-100 fw-bold shadow-sm mb-2">
                                <i class="bi bi-arrow-right-circle-fill me-1"></i> Avançar para a Fase <?= (int)$pleito['fase_atual'] + 1 ?>
                            </button>
                        </form>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="alert alert-info small mb-2">
                        O pleito está na Fase 5. Utilize o painel de Homologação para a proclamação oficial definitiva dos vencedores.
                    </div>
                <?php endif; ?>

                <?php if ((int)$pleito['fase_atual'] > 1): ?>
                    <button type="button" class="btn btn-outline-warning w-100 fw-bold shadow-sm mb-2" data-bs-toggle="modal" data-bs-target="#modalVoltarFase">
                        <i class="bi bi-arrow-left-circle me-1"></i> Voltar para a Fase <?= (int)$pleito['fase_atual'] - 1 ?>
                    </button>
                <?php endif; ?>

                <div class="mt-2 pt-2 border-top">
                    <button type="button" class="btn btn-outline-danger w-100 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalReiniciarPleito">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Reiniciar Pleito
                    </button>
                    <div class="small text-muted text-center mt-1" style="font-size: 0.75rem;">
                        Retorna o pleito para a Fase 1 e permite zerar votos e avaliações.
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-comara shadow-sm">
            <div class="card-comara-header">
                <span>Informações do Servidor</span>
            </div>
            <div class="card-body small text-muted">
                <div><strong>Ambiente:</strong> Debian 13 / aaPanel</div>
                <div><strong>PHP Version:</strong> <?= phpversion() ?></div>
                <div><strong>Banco de Dados:</strong> MySQL (UTF8MB4)</div>
                <div><strong>Timezone Oficial:</strong> <?= date_default_timezone_get() ?></div>
                <div><strong>Hora do Sistema:</strong> <?= date('d/m/Y H:i:s') ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Histórico e Auditoria de Transições de Fases -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card card-comara shadow-sm">
            <div class="card-comara-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-journal-text me-2 text-primary"></i>Histórico e Auditoria de Transições de Fases</span>
                <span class="badge bg-secondary"><?= count($historicoFases ?? []) ?> Registro(s)</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($historicoFases)): ?>
                    <div class="p-4 text-center text-muted">
                        <i class="bi bi-info-circle fs-3 d-block mb-2 text-secondary"></i>
                        Nenhuma transição de fase registrada para o pleito atual até o momento.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-striped align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Data / Horário</th>
                                    <th>Transição</th>
                                    <th>Etapa (De &rarr; Para)</th>
                                    <th>Pendências no Avanço</th>
                                    <th>Justificativa / Motivo Registrado</th>
                                    <th>Administrador Responsável</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($historicoFases as $hf): ?>
                                    <tr>
                                        <td>
                                            <i class="bi bi-clock me-1 text-muted"></i>
                                            <?= date('d/m/Y H:i:s', strtotime($hf['created_at'])) ?>
                                        </td>
                                        <td>
                                            <?php if ($hf['tipo_transicao'] === 'AVANCO'): ?>
                                                <span class="badge bg-success"><i class="bi bi-arrow-right-circle me-1"></i> AVANÇO</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark"><i class="bi bi-arrow-left-circle me-1"></i> RETORNO</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong>Fase <?= $hf['fase_de'] ?></strong>
                                            <i class="bi bi-arrow-right mx-1 text-muted"></i>
                                            <strong class="text-primary">Fase <?= $hf['fase_para'] ?></strong>
                                        </td>
                                        <td>
                                            <?php if ((int)$hf['teve_pendencias'] === 1): ?>
                                                <span class="badge bg-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i> <?= $hf['total_pendencias'] ?> Pendência(s)</span>
                                            <?php else: ?>
                                                <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i> Sem Pendências</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($hf['justificativa'])): ?>
                                                <span class="small fst-italic text-dark">"<?= sanitize_output($hf['justificativa']) ?>"</span>
                                            <?php else: ?>
                                                <span class="small text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="fw-semibold"><?= sanitize_output($hf['usuario_nome'] ?: 'Administrador') ?></span>
                                            <small class="text-muted d-block">(<?= sanitize_output($hf['usuario_login'] ?: 'admin') ?>)</small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>


<!-- Modal de Reinicialização do Pleito (Exclusivo Administrador) -->
<div class="modal fade" id="modalReiniciarPleito" tabindex="-1" aria-labelledby="modalReiniciarPleitoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold" id="modalReiniciarPleitoLabel">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> Reiniciar Pleito Eleitoral
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form method="POST" action="/index.php?r=admin/reiniciar_pleito">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                <div class="modal-body p-4">
                    <div class="alert alert-danger border-danger-subtle mb-3">
                        <div class="fw-bold mb-1"><i class="bi bi-shield-exclamation me-1"></i> Atenção - Ação de Gestão do Pleito</div>
                        <div class="small">
                            Esta operação retornará o pleito <strong><?= sanitize_output($pleito['titulo']) ?></strong> para a <strong>Fase 1 (Avaliações dos Chefes Diretos & TACF)</strong> e redefinirá o status para <strong>Em Andamento</strong>.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-dark mb-2">Selecione o Modo de Reinicialização:</label>

                        <div class="form-check p-3 border rounded mb-2 bg-light">
                            <input class="form-check-input" type="radio" name="limpar_dados" id="limpar_tudo" value="1" checked>
                            <label class="form-check-label" for="limpar_tudo">
                                <strong class="text-danger d-block">Reset Completo (Recomendado para novo ciclo)</strong>
                                <span class="small text-muted">
                                    Zera os votos da Urna (Fase 3), seleções das divisões (Fase 2), fichas de indicação dos chefes (Fase 1), corte e ordenações do Estado-Maior (Fase 4), homologação e atas (Fase 5).
                                </span>
                            </label>

                            <div class="form-check mt-2 pt-2 border-top">
                                <input class="form-check-input" type="checkbox" name="limpar_tacf" id="limpar_tacf" value="1">
                                <label class="form-check-label small text-secondary" for="limpar_tacf">
                                    Limpar também notas do TACF (Educação Física / DAEF)
                                </label>
                            </div>
                        </div>

                        <div class="form-check p-3 border rounded bg-light">
                            <input class="form-check-input" type="radio" name="limpar_dados" id="apenas_fase" value="0">
                            <label class="form-check-label" for="apenas_fase">
                                <strong class="text-primary d-block">Apenas Regressar para Fase 1</strong>
                                <span class="small text-muted">
                                    Mantém os votos e avaliações existentes. Esta opção fica bloqueada após o encerramento da urna no novo fluxo.
                                </span>
                            </label>
                        </div>
                    </div>

                    <div class="alert alert-warning py-2 px-3 small mb-3">
                        <i class="bi bi-info-circle me-1"></i> <strong>Nota:</strong> O cadastro do Efetivo, fotos, chefias vinculadas e usuários permanecem <strong>100% preservados</strong>.
                    </div>

                    <div class="form-check mb-0">
                        <input class="form-check-input" type="checkbox" id="confirma_reiniciar" required>
                        <label class="form-check-label small fw-semibold text-danger" for="confirma_reiniciar">
                            Confirmo que desejo reiniciar o pleito eleitoral da COMARA.
                        </label>
                    </div>
                </div>

                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger btn-sm fw-bold shadow-sm">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Confirmar e Reiniciar Pleito
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal de Avanço com Pendências (Justificativa Obrigatória) -->
<div class="modal fade" id="modalAvancarComPendencias" tabindex="-1" aria-labelledby="modalAvancarComPendenciasLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title fw-bold" id="modalAvancarComPendenciasLabel">
                    <i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i> Avançar com Pendências Regimentais
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form method="POST" action="/index.php?r=admin/avancar_fase">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <div class="modal-body p-4">
                    <div class="alert alert-danger py-2 px-3 small mb-3">
                        <i class="bi bi-shield-exclamation me-1"></i>
                        Existem <strong><?= $verificacaoFase1['total_pendentes'] ?? 0 ?> avaliação(ões) pendente(s)</strong> de chefes diretos na Fase 1.
                    </div>

                    <p class="text-muted small">
                        Como <strong>Administrador Geral</strong>, você possui autorização para avançar para a próxima fase mesmo havendo pendências. No entanto, o sistema exige que seja registrada uma <strong>justificativa formal em texto</strong>, que ficará gravada perenemente na auditoria oficial do pleito.
                    </p>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark small">Justificativa Formal do Administrador <span class="text-danger">*</span>:</label>
                        <textarea name="justificativa_pendencias" class="form-control" rows="4" placeholder="Ex: Avanço determinado em virtude do término do prazo e autorização do Comando..." required minlength="5"></textarea>
                        <div class="form-text small">Mínimo de 5 caracteres. Este texto será auditado e exibido no histórico de fases.</div>
                    </div>

                    <div class="form-check mb-0">
                        <input class="form-check-input" type="checkbox" id="confirma_avanco_pendencia" required>
                        <label class="form-check-label small fw-semibold text-dark" for="confirma_avanco_pendencia">
                            Estou ciente de que esta ação e justificativa ficarão registradas sob meu usuário.
                        </label>
                    </div>
                </div>

                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning btn-sm fw-bold shadow-sm">
                        <i class="bi bi-arrow-right-circle-fill me-1"></i> Confirmar Avanço com Justificativa
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal de Retorno de Fase -->
<div class="modal fade" id="modalVoltarFase" tabindex="-1" aria-labelledby="modalVoltarFaseLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title fw-bold" id="modalVoltarFaseLabel">
                    <i class="bi bi-arrow-left-circle-fill me-2"></i> Retornar Etapa do Pleito
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form method="POST" action="/index.php?r=admin/voltar_fase">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <div class="modal-body p-4">
                    <div class="alert alert-warning py-2 px-3 small mb-3">
                        <i class="bi bi-info-circle me-1"></i>
                        O pleito regredirá da <strong>Fase <?= $pleito['fase_atual'] ?></strong> para a <strong>Fase <?= max(1, (int)$pleito['fase_atual'] - 1) ?> (<?= FASES_PROCESSO[max(1, (int)$pleito['fase_atual'] - 1)]['nome'] ?>)</strong>.
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark small">Motivo do Retorno (Opcional / Justificativa):</label>
                        <textarea name="motivo_retorno" class="form-control" rows="3" placeholder="Ex: Reabertura solicitada pelo Comando para inclusão de avaliações complementares..."></textarea>
                    </div>

                    <div class="form-check mb-0">
                        <input class="form-check-input" type="checkbox" id="confirma_voltar_fase" required>
                        <label class="form-check-label small fw-semibold text-dark" for="confirma_voltar_fase">
                            Confirmo o retorno do pleito para a fase anterior.
                        </label>
                    </div>
                </div>

                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning btn-sm fw-bold shadow-sm">
                        <i class="bi bi-arrow-left-circle me-1"></i> Confirmar Retorno de Fase
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once ROOT_PATH . '/views/layouts/footer.php'; ?>
