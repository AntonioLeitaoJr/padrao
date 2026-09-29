<?php
declare(strict_types=1);
require_once ROOT_PATH . '/views/layouts/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2 class="h4 fw-bold text-primary mb-1">Fase 4: Estado-Maior</h2>
        <div class="text-muted small">Apreciação pelos Oficiais Superiores da COMARA (Majores, Tenentes-Coronéis e Coronéis). Clique no nome do candidato para visualizar o resumo detalhado da ficha.</div>
    </div>
    <div>
        <span class="badge bg-primary p-2 fs-6"><i class="bi bi-shield-shaded me-1"></i> Estado-Maior COMARA</span>
    </div>
</div>

<div class="alert alert-secondary d-flex align-items-center shadow-sm p-3 mb-4">
    <i class="bi bi-info-circle-fill text-primary fs-4 me-3"></i>
    <div class="small">
        <strong>Instruções do Estado-Maior:</strong> Clique no <strong>nome de qualquer candidato</strong> para abrir o resumo completo da ficha de avaliação da Fase 1, serviço de escala e TACF. 
        Caso haja inabilitação por impedimento legal ou regimental, <strong>é obrigatório registrar a fundamentação textual detalhada</strong>.
    </div>
</div>

<!-- Tabs pelas 4 Categorias -->
<ul class="nav nav-pills mb-3 gap-2" id="fase4Tabs" role="tablist">
    <?php foreach (CATEGORIAS_PADRAO as $catKey => $catInfo): ?>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= ($catKey === CAT_GRADUADO) ? 'active' : '' ?> fw-bold border shadow-sm"
                    id="tab4-btn-<?= $catKey ?>" data-bs-toggle="tab" data-bs-target="#tab4-cat-<?= $catKey ?>" type="button">
                <?= $catInfo['nome'] ?>
                <span class="badge bg-secondary ms-1"><?= count($porCategoria[$catKey] ?? []) ?></span>
            </button>
        </li>
    <?php endforeach; ?>
</ul>

<div class="tab-content">
    <?php foreach (CATEGORIAS_PADRAO as $catKey => $catInfo): ?>
        <div class="tab-pane fade <?= ($catKey === CAT_GRADUADO) ? 'show active' : '' ?>" id="tab4-cat-<?= $catKey ?>" role="tabpanel">
            <div class="card card-comara shadow-sm">
                <div class="card-comara-header d-flex justify-content-between align-items-center">
                    <span>Candidatos Classificados — <?= $catInfo['nome'] ?></span>
                    <small class="text-muted"><i class="bi bi-cursor-fill me-1"></i>Clique no nome para abrir o popup da ficha</small>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 70px;">Foto</th>
                                    <th>Candidato (Clique para ver ficha)</th>
                                    <th>Posto / Especialidade</th>
                                    <th>Setor / Divisão</th>
                                    <th>Antiguidade COMARA</th>
                                    <th>Indicações</th>
                                    <th>Média F1 / TACF</th>
                                    <th>Status Regimental</th>
                                    <th class="text-end">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $cands = $porCategoria[$catKey] ?? []; ?>
                                <?php if (empty($cands)): ?>
                                    <tr><td colspan="9" class="text-center p-4 text-muted">Nenhum candidato consolidado nesta categoria.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($cands as $c): ?>
                                        <?php $isExcluido = ($c['status_validacao_superior'] === 'EXCLUIDO'); ?>
                                        <tr class="<?= $isExcluido ? 'table-danger' : '' ?>">
                                            <td>
                                                <img src="/index.php?r=foto&saram=<?= $c['saram'] ?>&id=<?= $c['candidato_id'] ?>" 
                                                     class="cand-photo-sm cursor-pointer shadow-sm" alt="Foto"
                                                     onclick="abrirResumoFicha(<?= $c['candidato_id'] ?>)"
                                                     title="Ver ficha do candidato">
                                            </td>
                                            <td>
                                                <a href="javascript:void(0)" onclick="abrirResumoFicha(<?= $c['candidato_id'] ?>)" 
                                                   class="text-decoration-none fw-bold text-primary fs-6 d-inline-flex align-items-center"
                                                   title="Clique para abrir o resumo completo da ficha">
                                                    <?= sanitize_output($c['posto']) ?> <?= sanitize_output($c['nome_guerra']) ?>
                                                    <i class="bi bi-file-earmark-person-fill text-info ms-1 small"></i>
                                                </a>
                                                <br>
                                                <small class="text-muted"><?= sanitize_output($c['nome']) ?></small><br>
                                                <small class="text-secondary">SARAM: <?= sanitize_output($c['saram'] ?: 'Civil') ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark border"><?= sanitize_output($c['posto']) ?></span>
                                                <small class="text-muted d-block"><?= sanitize_output($c['quadro']) ?> - <?= sanitize_output($c['especialidade']) ?></small>
                                            </td>
                                            <td><?= sanitize_output($c['setor']) ?> (<?= sanitize_output($c['divisao_sigla']) ?>)</td>
                                            <td>
                                                <small class="d-block text-muted">COMARA: <strong><?= $c['data_admissao_comara'] ? date('d/m/Y', strtotime($c['data_admissao_comara'])) : '--' ?></strong></small>
                                                <small class="d-block text-muted">OM: <strong><?= $c['data_vinculo_om'] ? date('d/m/Y', strtotime($c['data_vinculo_om'])) : '--' ?></strong></small>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge <?= (int)$c['indicacoes_anteriores_qtd'] === 0 ? 'bg-success' : 'bg-warning text-dark' ?>">
                                                    <?= (int)$c['indicacoes_anteriores_qtd'] === 0 ? 'Nunca Indicado' : $c['indicacoes_anteriores_qtd'] . ' pleito(s)' ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="small">Média F1: <strong class="text-primary"><?= number_format((float)$c['media_fase1'], 2, ',', '.') ?></strong></div>
                                                <?php if ($c['nota_tacf']): ?>
                                                    <div class="small">TACF: <strong class="text-success"><?= number_format((float)$c['nota_tacf'], 2, ',', '.') ?></strong></div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($isExcluido): ?>
                                                    <span class="badge bg-danger fs-6 mb-1">INABILITADO</span>
                                                    <div class="small text-danger" style="max-width: 220px;" title="<?= sanitize_output($c['justificativa_exclusao']) ?>">
                                                        <em>"<?= sanitize_output($c['justificativa_exclusao']) ?>"</em>
                                                    </div>
                                                    <small class="text-muted d-block">Por: <?= sanitize_output($c['excluidor_login'] ?: 'Oficial') ?></small>
                                                <?php else: ?>
                                                    <span class="badge bg-success fs-6"><i class="bi bi-check-circle-fill me-1"></i> HABILITADO</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end text-nowrap">
                                                <button type="button" class="btn btn-sm btn-outline-info me-1" 
                                                        onclick="abrirResumoFicha(<?= $c['candidato_id'] ?>)" title="Ver resumo da ficha">
                                                    <i class="bi bi-eye-fill"></i> Ficha
                                                </button>
                                                <?php if ($isExcluido): ?>
                                                    <form method="POST" action="/index.php?r=fase4/reverter" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                                        <input type="hidden" name="candidato_id" value="<?= $c['candidato_id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-success fw-bold">
                                                            <i class="bi bi-arrow-counterclockwise"></i> Reabilitar
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-sm btn-outline-danger fw-bold"
                                                            data-bs-toggle="modal" data-bs-target="#modalExclusao"
                                                            data-candidato-id="<?= $c['candidato_id'] ?>"
                                                            data-candidato-nome="<?= sanitize_output($c['posto'] . ' ' . $c['nome_guerra']) ?>">
                                                        <i class="bi bi-x-circle me-1"></i> Inabilitar
                                                    </button>
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

<!-- POPUP DE RESUMO DA FICHA DE AVALIAÇÃO (ESTADO-MAIOR) -->
<div class="modal fade" id="modalResumoFicha" tabindex="-1" aria-labelledby="modalResumoFichaLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold" id="modalResumoFichaLabel">
                    <i class="bi bi-file-earmark-person-fill me-2"></i> Resumo da Ficha de Indicação — Estado-Maior
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body p-4" style="max-height: calc(100vh - 200px); overflow-y: auto;">
                <div id="resumo_loading" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Carregando...</span>
                    </div>
                    <div class="text-muted fw-bold mt-2">Carregando ficha de indicação...</div>
                </div>

                <div id="resumo_conteudo" style="display: none;">
                    <!-- Cabeçalho do Integrante -->
                    <div class="card border mb-3 shadow-sm bg-light">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center">
                                <img id="resumo_foto" src="" class="rounded-circle shadow-sm me-3 border border-2 border-primary" style="width: 76px; height: 76px; object-fit: cover;" alt="Foto">
                                <div class="flex-grow-1">
                                    <div class="h5 fw-bold text-primary mb-1" id="resumo_posto_nome_guerra">--</div>
                                    <div class="text-dark small mb-1" id="resumo_nome_completo">--</div>
                                    <div class="d-flex flex-wrap gap-2 small">
                                        <span class="badge bg-light text-dark border">SARAM: <strong id="resumo_saram">--</strong></span>
                                        <span class="badge bg-light text-dark border">Setor: <strong id="resumo_setor">--</strong></span>
                                        <span class="badge bg-light text-dark border">Divisão: <strong id="resumo_divisao">--</strong></span>
                                        <span class="badge bg-secondary" id="resumo_categoria">--</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Situação NOB ou Observado -->
                    <div id="resumo_alerta_nob" class="alert alert-secondary py-2 px-3 mb-3 d-flex align-items-center" style="display: none;">
                        <i class="bi bi-eye-slash-fill fs-4 text-secondary me-2"></i>
                        <div>
                            <strong>Não Observado (NOB):</strong> O integrante foi registrado como NOB pelo chefe direto (sem atribuição de notas de desempenho).
                        </div>
                    </div>

                    <!-- Informações Regimentais e Físicas -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <div class="card border shadow-sm p-3 h-100">
                                <div class="text-muted small fw-bold text-uppercase mb-1"><i class="bi bi-clock-history me-1"></i> Carreira & Tempo de Serviço</div>
                                <div class="small mb-1">Tempo na COMARA: <strong id="resumo_tempo_comara">--</strong></div>
                                <div class="small mb-1">Tempo na OM: <strong id="resumo_tempo_om">--</strong></div>
                                <div class="small">Indicações Anteriores: <strong id="resumo_indicacoes">--</strong></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border shadow-sm p-3 h-100">
                                <div class="text-muted small fw-bold text-uppercase mb-1"><i class="bi bi-heart-pulse-fill me-1 text-danger"></i> Condição Física & Escala</div>
                                <div class="small mb-1">Nota TACF: <strong id="resumo_tacf" class="text-success">--</strong></div>
                                <div class="small">Serviço de Escala (1 a 5): <strong id="resumo_escala" class="text-primary">--</strong></div>
                            </div>
                        </div>
                    </div>

                    <!-- Critérios Regimentais de Desempenho (1 a 5) -->
                    <div id="resumo_bloco_notas" class="card border mb-3 shadow-sm">
                        <div class="card-header bg-white fw-bold text-primary">
                            <i class="bi bi-list-check me-2"></i> Notas dos Critérios Regimentais (Fase 1)
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-sm table-striped align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Critério de Desempenho</th>
                                        <th class="text-center" style="width: 120px;">Nota Atribuída</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Eficiência e eficácia no desempenho de atribuições</td>
                                        <td class="text-center fw-bold" id="resumo_crit_eficiencia">--</td>
                                    </tr>
                                    <tr>
                                        <td>Nível de conhecimento e interesse na especialidade</td>
                                        <td class="text-center fw-bold" id="resumo_crit_conhecimento">--</td>
                                    </tr>
                                    <tr>
                                        <td>Iniciativa, criatividade e adaptabilidade</td>
                                        <td class="text-center fw-bold" id="resumo_crit_iniciativa">--</td>
                                    </tr>
                                    <tr>
                                        <td>Conduta militar / ética profissional</td>
                                        <td class="text-center fw-bold" id="resumo_crit_conduta">--</td>
                                    </tr>
                                    <tr>
                                        <td>Apresentação pessoal e porte</td>
                                        <td class="text-center fw-bold" id="resumo_crit_apresentacao">--</td>
                                    </tr>
                                    <tr>
                                        <td>Relacionamento interpessoal e espírito de cooperação</td>
                                        <td class="text-center fw-bold" id="resumo_crit_relacionamento">--</td>
                                    </tr>
                                    <tr>
                                        <td>Liderança, ascendência funcional e responsabilidade</td>
                                        <td class="text-center fw-bold" id="resumo_crit_lideranca">--</td>
                                    </tr>
                                    <tr class="table-primary fw-bold">
                                        <td class="text-end">MÉDIA GERAL CALCULADA:</td>
                                        <td class="text-center text-primary fs-6" id="resumo_media_geral">--</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Observações e Avaliador -->
                    <div class="card border mb-3 shadow-sm bg-light p-3">
                        <div class="d-flex justify-content-between small text-muted mb-2">
                            <span>Avaliador Responsável: <strong id="resumo_avaliador_nome">--</strong></span>
                            <span>Data da Avaliação: <strong id="resumo_avaliado_em">--</strong></span>
                        </div>
                        <div class="small">
                            <span class="fw-bold text-dark">Observações sobre Conduta / Fichas:</span>
                            <div id="resumo_conduta_texto" class="mt-1 p-2 bg-white rounded border fst-italic">Nenhuma observação registrada.</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary px-4 fw-bold" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Inabilitação com Justificativa Textual Obrigatória -->
<div class="modal fade" id="modalExclusao" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content shadow-lg border-0">
            <form method="POST" action="/index.php?r=fase4/excluir">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="candidato_id" id="excluir_candidato_id">

                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill me-2"></i>Inabilitar Candidato Regimentalmente</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">Candidato Selecionado:</label>
                        <div class="fs-5 fw-bold text-dark" id="excluir_candidato_nome">--</div>
                    </div>

                    <div class="alert alert-warning small">
                        <strong>Aviso de Auditoria:</strong> A inabilitação de um candidato nesta fase exige motivação legal/regimental explícita. O texto digitado será permanentemente gravado com seu usuário e data/hora.
                    </div>

                    <div class="mb-3">
                        <label for="justificativa_exclusao" class="form-label fw-bold">Justificativa Textual Obrigatória <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="justificativa_exclusao" name="justificativa" rows="4" required minlength="15"
                                  placeholder="Fundamente o impedimento regimental (ex: Punição disciplinar recente, não atendimento ao tempo mínimo de OM, incompatibilidade regimental)..."></textarea>
                        <div class="form-text">Mínimo de 15 caracteres fundamentando o motivo da exclusão.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger fw-bold"><i class="bi bi-slash-circle me-1"></i> Confirmar Inabilitação</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let modalResumoInstancia = null;

function abrirResumoFicha(candidatoId) {
    const modalEl = document.getElementById('modalResumoFicha');
    if (!modalResumoInstancia) {
        modalResumoInstancia = new bootstrap.Modal(modalEl);
    }

    const loading = document.getElementById('resumo_loading');
    const conteudo = document.getElementById('resumo_conteudo');
    loading.style.display = 'block';
    conteudo.style.display = 'none';

    modalResumoInstancia.show();

    fetch('/index.php?r=fase4/resumo_ficha&id=' + candidatoId)
        .then(r => r.json())
        .then(res => {
            if (!res.sucesso) {
                alert(res.mensagem || 'Erro ao carregar resumo da ficha.');
                modalResumoInstancia.hide();
                return;
            }

            const d = res.dados;
            const c = d.candidato;
            const f = d.ficha;
            const t = d.tacf;

            // Dados Básicos
            document.getElementById('resumo_foto').src = d.foto_url;
            document.getElementById('resumo_posto_nome_guerra').innerText = (c.posto || '') + ' ' + (c.nome_guerra || '');
            document.getElementById('resumo_nome_completo').innerText = c.nome || '';
            document.getElementById('resumo_saram').innerText = c.saram || 'Civil';
            document.getElementById('resumo_setor').innerText = c.setor || '--';
            document.getElementById('resumo_divisao').innerText = c.divisao_sigla || '--';
            document.getElementById('resumo_categoria').innerText = (c.categoria || '').toUpperCase();

            // Tempos e Indicações
            document.getElementById('resumo_tempo_comara').innerText = c.data_admissao_comara ? formatarDataBR(c.data_admissao_comara) : '--';
            document.getElementById('resumo_tempo_om').innerText = c.data_vinculo_om ? formatarDataBR(c.data_vinculo_om) : '--';
            document.getElementById('resumo_indicacoes').innerText = (parseInt(c.indicacoes_anteriores_qtd) || 0) + ' vez(es)';

            // TACF e Escala
            if (t && t.nota_tacf) {
                document.getElementById('resumo_tacf').innerText = parseFloat(t.nota_tacf).toFixed(2) + (t.conceito ? ' (' + t.conceito + ')' : '');
            } else {
                document.getElementById('resumo_tacf').innerText = 'Não lançado / Dispensado';
            }

            // Ficha de Avaliação
            const alertaNob = document.getElementById('resumo_alerta_nob');
            const blocoNotas = document.getElementById('resumo_bloco_notas');

            if (f) {
                document.getElementById('resumo_avaliador_nome').innerText = (f.avaliador_posto ? f.avaliador_posto + ' ' : '') + (f.avaliador_guerra || f.avaliador_login || 'Chefe');
                document.getElementById('resumo_avaliado_em').innerText = f.updated_at ? formatarDataHoraBR(f.updated_at) : '--';
                document.getElementById('resumo_conduta_texto').innerText = f.conduta_fichas || 'Nenhuma observação registrada.';

                if (parseInt(f.is_nob) === 1) {
                    alertaNob.style.display = 'flex';
                    blocoNotas.style.display = 'none';
                    document.getElementById('resumo_escala').innerText = 'Não Observado (NOB)';
                } else {
                    alertaNob.style.display = 'none';
                    blocoNotas.style.display = 'block';
                    document.getElementById('resumo_escala').innerText = f.servico_escala ? f.servico_escala + ' / 5' : 'Não se aplica (Civil)';

                    document.getElementById('resumo_crit_eficiencia').innerText = f.eficiencia_eficacia ? f.eficiencia_eficacia + ' / 5' : '--';
                    document.getElementById('resumo_crit_conhecimento').innerText = f.conhecimento_especialidade ? f.conhecimento_especialidade + ' / 5' : '--';
                    document.getElementById('resumo_crit_iniciativa').innerText = f.iniciativa_adaptabilidade ? f.iniciativa_adaptabilidade + ' / 5' : '--';
                    document.getElementById('resumo_crit_conduta').innerText = f.conduta_pontuacao ? f.conduta_pontuacao + ' / 5' : '--';
                    document.getElementById('resumo_crit_apresentacao').innerText = f.apresentacao_pessoal ? f.apresentacao_pessoal + ' / 5' : '--';
                    document.getElementById('resumo_crit_relacionamento').innerText = f.relacionamento_trabalho ? f.relacionamento_trabalho + ' / 5' : '--';
                    document.getElementById('resumo_crit_lideranca').innerText = f.lideranca ? f.lideranca + ' / 5' : '--';
                    document.getElementById('resumo_media_geral').innerText = f.media_calculada ? parseFloat(f.media_calculada).toFixed(2) : '--';
                }
            } else {
                alertaNob.style.display = 'none';
                blocoNotas.style.display = 'none';
                document.getElementById('resumo_escala').innerText = '--';
                document.getElementById('resumo_avaliador_nome').innerText = 'Não avaliado';
                document.getElementById('resumo_avaliado_em').innerText = '--';
                document.getElementById('resumo_conduta_texto').innerText = 'Ficha de indicação da Fase 1 não localizada.';
            }

            loading.style.display = 'none';
            conteudo.style.display = 'block';
        })
        .catch(err => {
            console.error(err);
            alert('Falha ao comunicar com o servidor.');
            modalResumoInstancia.hide();
        });
}

function formatarDataBR(dataStr) {
    if (!dataStr) return '--';
    const partes = dataStr.split('-');
    if (partes.length === 3) {
        return partes[2].substring(0, 2) + '/' + partes[1] + '/' + partes[0];
    }
    return dataStr;
}

function formatarDataHoraBR(dtStr) {
    if (!dtStr) return '--';
    const d = new Date(dtStr.replace(' ', 'T'));
    if (isNaN(d.getTime())) return dtStr;
    return d.toLocaleDateString('pt-BR') + ' às ' + d.toLocaleTimeString('pt-BR', {hour: '2-digit', minute:'2-digit'});
}

// Configuração do modal de exclusão
document.addEventListener('DOMContentLoaded', function () {
    const modalExclusao = document.getElementById('modalExclusao');
    if (modalExclusao) {
        modalExclusao.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            if (!button) return;
            document.getElementById('excluir_candidato_id').value = button.getAttribute('data-candidato-id') || '';
            document.getElementById('excluir_candidato_nome').innerText = button.getAttribute('data-candidato-nome') || '';
            document.getElementById('justificativa_exclusao').value = '';
        });
    }
});
</script>

<?php require_once ROOT_PATH . '/views/layouts/footer.php'; ?>
