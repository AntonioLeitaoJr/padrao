<?php
declare(strict_types=1);
require_once ROOT_PATH . '/views/layouts/header.php';
?>

<!-- Cabeçalho da Página -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h2 class="h4 fw-bold text-primary mb-1">
            <i class="bi bi-person-lines-fill me-2"></i> Atribuição de Chefias & Perfis Regimentais
        </h2>
        <div class="text-muted small">
            Configuração das 12 Chefias de Divisão e Assessoria (Fase 2), Avaliador TACF (Fase 1), Direção Superior (Fase 4) e Presidência (Fase 5).
        </div>
    </div>
    <div>
        <a href="/index.php?r=admin/efetivo" class="btn btn-outline-primary">
            <i class="bi bi-people me-1"></i> Ir para Gestão do Efetivo
        </a>
    </div>
</div>

<!-- Barra de Indicadores e Alertas -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card card-comara shadow-sm h-100 border-start border-primary border-4">
            <div class="card-body p-3">
                <div class="text-muted small fw-semibold text-uppercase">Divisões & Assessorias</div>
                <div class="h3 fw-bold text-primary my-1"><?= count($divisoes) ?></div>
                <div class="text-muted small">Órgãos com representação na Fase 2</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card card-comara shadow-sm h-100 border-start border-success border-4">
            <div class="card-body p-3">
                <div class="text-muted small fw-semibold text-uppercase">Efetivo com Chefe Direto</div>
                <div class="h3 fw-bold text-success my-1"><?= $statsChefesDiretos['com_chefe'] ?? 0 ?> <span class="h6 text-muted">/ <?= $statsChefesDiretos['total_efetivo'] ?? 0 ?></span></div>
                <div class="text-muted small">Militares subordinados vinculados</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card card-comara shadow-sm h-100 border-start <?= (($statsChefesDiretos['sem_chefe_avaliar'] ?? 0) > 0) ? 'border-warning' : 'border-info' ?> border-4">
            <div class="card-body p-3">
                <div class="text-muted small fw-semibold text-uppercase">Candidatos sem Chefe Direto</div>
                <div class="h3 fw-bold <?= (($statsChefesDiretos['sem_chefe_avaliar'] ?? 0) > 0) ? 'text-warning' : 'text-info' ?> my-1">
                    <?= $statsChefesDiretos['sem_chefe_avaliar'] ?? 0 ?>
                </div>
                <div class="text-muted small">
                    <?= (($statsChefesDiretos['sem_chefe_avaliar'] ?? 0) > 0) ? 'Graduados/Praças que precisam de chefe para Fase 1' : 'Todos os candidatos possuem chefe direto' ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Abas de Gestão de Chefias -->
<ul class="nav nav-tabs nav-tabs-comara mb-4" id="chefiasTab" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active fw-bold" id="divisoes-tab" data-bs-toggle="tab" data-bs-target="#divisoes-pane" type="button" role="tab">
            <i class="bi bi-diagram-3 me-2"></i> Chefes de Divisão & Assessoria (Fase 2)
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link fw-bold" id="especiais-tab" data-bs-toggle="tab" data-bs-target="#especiais-pane" type="button" role="tab">
            <i class="bi bi-shield-check me-2"></i> Administradores, RH, TACF & Direção Superior
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link fw-bold" id="setores-tab" data-bs-toggle="tab" data-bs-target="#setores-pane" type="button" role="tab">
            <i class="bi bi-link-45deg me-2"></i> Vinculação por Setor (Fase 1)
        </button>
    </li>
</ul>

<div class="tab-content" id="chefiasTabContent">

    <!-- ABA 1: Chefes das 12 Divisões e Assessorias (Fase 2) -->
    <div class="tab-pane fade show active" id="divisoes-pane" role="tabpanel">
        <div class="card card-comara shadow-sm mb-4">
            <div class="card-comara-header d-flex justify-content-between align-items-center">
                <span>
                    <i class="bi bi-diagram-3-fill me-2"></i> Atribuição Oficial dos 12 Chefes de Divisão e Assessoria
                </span>
                <span class="small text-white-50">Regulamentado pela Portaria de Eleição de Padrões</span>
            </div>
            <div class="card-body p-4">
                <p class="text-muted small mb-4">
                    Na <strong>Fase 2</strong>, cada um dos 12 Chefes de Divisão e Assessoria deve selecionar <strong>exatamente 6 candidatos</strong> em cada uma das 4 categorias. Selecione abaixo o Oficial ou Graduado responsável por cada divisão/assessoria:
                </p>

                <form method="POST" action="/index.php?r=admin/salvar_chefias">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="secao" value="chefes_divisao">

                    <div class="table-responsive mb-4">
                        <table class="table table-hover align-middle border">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 100px;">Sigla</th>
                                    <th>Divisão / Assessoria</th>
                                    <th>Tipo</th>
                                    <th>Chefe Atual Cadastrado</th>
                                    <th style="min-width: 320px;">Alterar / Atribuir Chefe Responsável</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($divisoes as $d): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-primary fs-6 fw-bold"><?= sanitize_output($d['sigla']) ?></span>
                                        </td>
                                        <td>
                                            <strong><?= sanitize_output($d['nome']) ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border"><?= sanitize_output($d['tipo']) ?></span>
                                        </td>
                                        <td>
                                            <?php if (!empty($d['nome_guerra'])): ?>
                                                <div class="d-flex align-items-center">
                                                    <img src="/index.php?r=foto&saram=<?= $d['saram'] ?>&id=<?= $d['efetivo_id'] ?>"
                                                         class="rounded-circle me-2"
                                                         style="width: 32px; height: 32px; object-fit: cover; border: 1px solid #ced4da;"
                                                         alt="Foto">
                                                    <div>
                                                        <span class="fw-bold text-dark"><?= sanitize_output($d['posto']) ?> <?= sanitize_output($d['nome_guerra']) ?></span><br>
                                                        <small class="text-muted">SARAM: <?= sanitize_output($d['saram']) ?></small>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle me-1"></i> Não atribuído</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <select name="chefe_divisao[<?= $d['sigla'] ?>]" class="form-select form-select-sm">
                                                <option value="">-- Manter / Selecionar Militar --</option>
                                                <?php foreach ($oficiais as $of): ?>
                                                    <option value="<?= $of['id'] ?>" <?= (!empty($d['efetivo_id']) && $d['efetivo_id'] == $of['id']) ? 'selected' : '' ?>>
                                                        <?= sanitize_output($of['posto']) ?> <?= sanitize_output($of['nome_guerra']) ?> (<?= sanitize_output($of['divisao_sigla']) ?> - <?= sanitize_output($of['nome']) ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex flex-wrap justify-content-between align-items-center bg-light p-3 rounded border">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="vincular_subordinados" id="checkVincularSub" value="1" checked>
                            <label class="form-check-label fw-semibold" for="checkVincularSub">
                                Vincular automaticamente todos os militares da divisão/assessoria ao chefe selecionado como Chefe Direto da Fase 1
                            </label>
                        </div>
                        <button type="submit" class="btn btn-primary fw-bold">
                            <i class="bi bi-check2-circle me-1"></i> Salvar Chefias de Divisão e Assessoria
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ABA 2: Papéis Regimentais Especiais (TACF, Direção Superior e Presidência) -->
    <div class="tab-pane fade" id="especiais-pane" role="tabpanel">
        <div class="row g-4">

            <!-- Responsável TACF (Educação Física) -->
            <div class="col-md-6">
                <div class="card card-comara shadow-sm h-100">
                    <div class="card-comara-header d-flex justify-content-between align-items-center">
                        <div><i class="bi bi-heart-pulse-fill me-2 text-danger"></i> Comissão de Avaliação do TACF</div>
                        <span class="badge bg-danger"><?= count($avaliadoresTacf) ?> Avaliador(es)</span>
                    </div>
                    <div class="card-body p-4">
                        <p class="text-muted small">
                            Os militares/civis cadastrados abaixo com perfil <strong>ED_FISICA</strong> possuem permissão para lançar e homologar as notas do Teste de Aptidão e Condicionamento Físico de todos os militares na Fase 1.
                        </p>

                        <div class="table-responsive mb-3" style="max-height: 240px; overflow-y: auto;">
                            <table class="table table-sm table-hover align-middle border mb-0">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th>Militar / Membro</th>
                                        <th>SARAM</th>
                                        <th class="text-end">Ação</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($avaliadoresTacf)): ?>
                                        <tr>
                                            <td colspan="3" class="text-center text-danger py-3">
                                                <i class="bi bi-exclamation-triangle me-1"></i> Nenhum avaliador de TACF cadastrado.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($avaliadoresTacf as $av): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <img src="/index.php?r=foto&saram=<?= $av['saram'] ?>&id=<?= $av['id'] ?>" class="rounded-circle me-2" style="width: 32px; height: 32px; object-fit: cover;" alt="Foto">
                                                        <div>
                                                            <strong class="d-block text-dark"><?= sanitize_output($av['posto']) ?> <?= sanitize_output($av['nome_guerra']) ?></strong>
                                                            <small class="text-muted"><?= sanitize_output($av['setor'] ?: $av['divisao_sigla']) ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><span class="badge bg-light text-dark border"><?= sanitize_output($av['saram']) ?></span></td>
                                                <td class="text-end">
                                                    <form method="POST" action="/index.php?r=admin/salvar_chefias" class="d-inline" onsubmit="return confirm('Remover <?= sanitize_output($av['nome_guerra']) ?> da Comissão de Avaliadores do TACF?');">
                                                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                                        <input type="hidden" name="secao" value="tacf_remover">
                                                        <input type="hidden" name="usuario_id" value="<?= $av['usuario_id'] ?>">
                                                        <button type="submit" class="btn btn-outline-danger btn-sm" title="Remover da comissão">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Formulário para Adicionar Novo Avaliador do TACF -->
                        <form method="POST" action="/index.php?r=admin/salvar_chefias" class="p-3 bg-light rounded border">
                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                            <input type="hidden" name="secao" value="tacf_adicionar">

                            <label class="form-label fw-semibold small mb-1">
                                <i class="bi bi-person-plus me-1 text-primary"></i> Cadastrar Novo Avaliador para o TACF:
                            </label>
                            <div class="input-group">
                                <select name="tacf_efetivo_id" class="form-select select2-enable" required>
                                    <option value="">Selecione o militar/avaliador...</option>
                                    <?php foreach ($todoEfetivo as $m): ?>
                                        <option value="<?= $m['id'] ?>">
                                            <?= sanitize_output($m['posto']) ?> <?= sanitize_output($m['nome_guerra']) ?> (<?= sanitize_output($m['setor'] ?: $m['divisao_sigla']) ?> - SARAM: <?= sanitize_output($m['saram']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-success fw-bold">
                                    <i class="bi bi-plus-lg me-1"></i> Adicionar
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Presidência da COMARA (Fase 5 / Minerva) -->
            <div class="col-md-6">
                <div class="card card-comara shadow-sm h-100">
                    <div class="card-comara-header">
                        <i class="bi bi-award-fill me-2 text-warning"></i> Presidência da COMARA (Fase 5 / Minerva)
                    </div>
                    <div class="card-body p-4">
                        <p class="text-muted small">
                            A <strong>Presidência</strong> da COMARA é responsável por desempatar pleitos na Fase 5 (Voto de Minerva regimental) e assinar a portaria definitiva de homologação dos 4 Padrões.
                        </p>

                        <div class="p-3 bg-light rounded border mb-3">
                            <div class="text-muted small fw-semibold mb-1">Presidente Atual:</div>
                            <?php if ($presidente): ?>
                                <div class="d-flex align-items-center">
                                    <img src="/index.php?r=foto&saram=<?= $presidente['saram'] ?>&id=<?= $presidente['id'] ?>" class="rounded-circle me-3" style="width: 48px; height: 48px; object-fit: cover;" alt="Foto">
                                    <div>
                                        <div class="fw-bold fs-6 text-dark"><?= sanitize_output($presidente['posto']) ?> <?= sanitize_output($presidente['nome_guerra']) ?></div>
                                        <small class="text-muted"><?= sanitize_output($presidente['nome']) ?> • <?= sanitize_output($presidente['funcao']) ?></small>
                                    </div>
                                </div>
                            <?php else: ?>
                                <span class="text-danger fw-bold"><i class="bi bi-exclamation-triangle me-1"></i> Nenhum Presidente atribuído</span>
                            <?php endif; ?>
                        </div>

                        <form method="POST" action="/index.php?r=admin/salvar_chefias">
                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                            <input type="hidden" name="secao" value="presidente">

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Selecionar Oficial da Presidência:</label>
                                <select name="presidente_efetivo_id" class="form-select" required>
                                    <option value="">Selecione o Oficial...</option>
                                    <?php foreach ($oficiais as $of): ?>
                                        <option value="<?= $of['id'] ?>" <?= ($presidente && $presidente['id'] == $of['id']) ? 'selected' : '' ?>>
                                            <?= sanitize_output($of['posto']) ?> <?= sanitize_output($of['nome_guerra']) ?> (<?= sanitize_output($of['setor']) ?> - SARAM: <?= sanitize_output($of['saram']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-warning fw-bold w-100">
                                <i class="bi bi-save me-1"></i> Atualizar Presidência
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Membros da Direção Superior (Fase 4) -->
            <div class="col-12">
                <div class="card card-comara shadow-sm">
                    <div class="card-comara-header">
                        <i class="bi bi-shield-lock-fill me-2 text-primary"></i> Membros da Direção Superior (Fase 4: Validação e Impugnações)
                    </div>
                    <div class="card-body p-4">
                        <p class="text-muted small mb-3">
                            Na <strong>Fase 4</strong>, os Oficiais Superiores com perfil <strong>DIRECAO_SUPERIOR</strong> têm acesso exclusivo para analisar pareceres de conduta ética, impugnar eventuais candidatos e consolidar a lista quádrupla para a Urna Eletrônica.
                        </p>

                        <form method="POST" action="/index.php?r=admin/salvar_chefias">
                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                            <input type="hidden" name="secao" value="direcao_superior">

                            <div class="row g-2 mb-4">
                                <?php
                                $membrosIdsAtuais = array_column($direcaoSuperior, 'id');
                                foreach ($oficiais as $of):
                                    if (!in_array($of['posto'], ['Cel', 'Ten Cel', 'Maj'], true)) continue;
                                    $marcado = in_array($of['id'], $membrosIdsAtuais, true);
                                ?>
                                    <div class="col-md-4">
                                        <div class="form-check p-2 border rounded bg-light">
                                            <input class="form-check-input ms-1" type="checkbox" name="membros_direcao[]" value="<?= $of['id'] ?>" id="ds_<?= $of['id'] ?>" <?= $marcado ? 'checked' : '' ?>>
                                            <label class="form-check-label ms-2 fw-semibold text-dark" for="ds_<?= $of['id'] ?>">
                                                <?= sanitize_output($of['posto']) ?> <?= sanitize_output($of['nome_guerra']) ?>
                                                <small class="text-muted d-block"><?= sanitize_output($of['divisao_sigla']) ?> - <?= sanitize_output($of['nome']) ?></small>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <button type="submit" class="btn btn-primary fw-bold">
                                <i class="bi bi-check2-circle me-1"></i> Salvar Composição da Direção Superior
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Administradores do Sistema (Acesso Total / Gestão Geral) -->
            <div class="col-12">
                <div class="card card-comara shadow-sm border-2 border-danger">
                    <div class="card-comara-header bg-danger text-white d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-shield-lock-fill me-2"></i> Administradores do Sistema (Acesso Total / Gestão Geral)</span>
                        <span class="badge bg-light text-danger fw-bold"><?= count($administradores) ?> Administrador(es) Ativo(s)</span>
                    </div>
                    <div class="card-body p-4">
                        <p class="text-muted small mb-3">
                            Usuários com perfil <strong>ADMIN</strong> possuem autoridade total no sistema: configuração de prazos, reinício do pleito, gestão do efetivo e candidatos, fotos, atribuição de chefias, reset de senhas e auditoria.
                        </p>

                        <!-- Tabela de Administradores Atuais -->
                        <div class="table-responsive mb-4">
                            <table class="table table-hover align-middle border mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 50px;">Foto</th>
                                        <th>Nome Completo & Guerra</th>
                                        <th>Posto / Função</th>
                                        <th>SARAM / Login</th>
                                        <th>Divisão / Setor</th>
                                        <th class="text-center">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($administradores as $adm): ?>
                                        <tr>
                                            <td>
                                                <?php if (!empty($adm['saram'])): ?>
                                                    <img src="/index.php?r=foto&saram=<?= $adm['saram'] ?>&id=<?= $adm['efetivo_id'] ?>" class="rounded-circle" style="width: 38px; height: 38px; object-fit: cover;" alt="Foto">
                                                <?php else: ?>
                                                    <div class="rounded-circle bg-danger text-white d-flex align-items-center justify-content-center fw-bold" style="width: 38px; height: 38px;">
                                                        AD
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="fw-bold text-dark"><?= sanitize_output($adm['nome_guerra'] ?: $adm['login']) ?></div>
                                                <small class="text-muted"><?= sanitize_output($adm['nome'] ?: 'Conta Principal de Sistema') ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-danger"><?= sanitize_output($adm['posto'] ?: 'ADMIN') ?></span>
                                            </td>
                                            <td>
                                                <code><?= sanitize_output($adm['saram'] ?: $adm['login']) ?></code>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark border"><?= sanitize_output($adm['divisao_sigla'] ?: 'COMARA') ?></span>
                                                <small class="text-muted"><?= sanitize_output($adm['setor'] ?: '') ?></small>
                                            </td>
                                            <td class="text-center">
                                                <form method="POST" action="/index.php?r=admin/resetar_senha" class="d-inline me-1" onsubmit="return confirm('Deseja realmente redefinir a senha de <?= sanitize_output($adm['nome_guerra'] ?: $adm['login']) ?> para padrao@2026?')">
                                                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                                    <input type="hidden" name="usuario_id" value="<?= $adm['usuario_id'] ?>">
                                                    <button type="submit" class="btn btn-outline-warning btn-sm" title="Resetar Senha para padrao@2026">
                                                        <i class="bi bi-key-fill"></i> Resetar
                                                    </button>
                                                </form>
                                                <?php if ($adm['login'] === 'admin'): ?>
                                                    <span class="badge bg-secondary" title="Conta raiz do sistema"><i class="bi bi-lock-fill me-1"></i> Principal</span>
                                                <?php else: ?>
                                                    <form method="POST" action="/index.php?r=admin/salvar_chefias" class="d-inline" onsubmit="return confirm('Deseja realmente revogar os privilégios de administrador deste integrante?')">
                                                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                                        <input type="hidden" name="secao" value="administrador_remover">
                                                        <input type="hidden" name="usuario_id" value="<?= $adm['usuario_id'] ?>">
                                                        <button type="submit" class="btn btn-outline-danger btn-sm" title="Revogar perfil de Administrador">
                                                            <i class="bi bi-person-dash me-1"></i> Revogar Admin
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Formulário para Conceder Permissão de Administrador a Qualquer Integrante -->
                        <div class="p-3 bg-light rounded border">
                            <h6 class="fw-bold text-dark mb-2"><i class="bi bi-person-plus-fill me-1 text-danger"></i> Conceder Função de Administrador a Outro Integrante:</h6>
                            <form method="POST" action="/index.php?r=admin/salvar_chefias" class="row g-2 align-items-end">
                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                <input type="hidden" name="secao" value="administrador_adicionar">

                                <div class="col-md-9">
                                    <label class="form-label small text-muted mb-1">Selecione qualquer militar ou servidor civil da COMARA:</label>
                                    <select name="admin_efetivo_id" class="form-select" required>
                                        <option value="">Selecione pelo Posto, Nome ou SARAM...</option>
                                        <?php 
                                        $admEfetivoIds = array_filter(array_column($administradores, 'efetivo_id'));
                                        foreach ($todoEfetivo as $m): 
                                            if (in_array($m['id'], $admEfetivoIds)) continue;
                                        ?>
                                            <option value="<?= $m['id'] ?>">
                                                <?= sanitize_output($m['posto']) ?> <?= sanitize_output($m['nome_guerra']) ?> — <?= sanitize_output($m['nome']) ?> (<?= sanitize_output($m['divisao_sigla']) ?> / <?= sanitize_output($m['setor']) ?> - SARAM: <?= sanitize_output($m['saram']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <button type="submit" class="btn btn-danger fw-bold w-100" onclick="return confirm('Confirma a concessão de privilégios totais de Administrador a este integrante?')">
                                        <i class="bi bi-shield-plus me-1"></i> Tornar Administrador
                                    </button>
                                </div>
                            </form>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Operadores de Recursos Humanos (RH - Gestão Exclusiva de Efetivo) -->
            <div class="col-12">
                <div class="card card-comara shadow-sm border-2 border-info">
                    <div class="card-comara-header bg-info text-white d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-person-badge-fill me-2"></i> Operadores de Recursos Humanos (RH - Cadastro de Efetivo)</span>
                        <span class="badge bg-light text-info fw-bold"><?= count($operadoresRh) ?> Operador(es) Ativo(s)</span>
                    </div>
                    <div class="card-body p-4">
                        <p class="text-muted small mb-3">
                            Usuários com perfil <strong>RH</strong> possuem acesso <strong>exclusivo</strong> ao módulo de cadastro, edição e importação/exportação do efetivo (<code>admin/efetivo</code>), sem permissões sobre prazos, fases, chefias, votos ou auditoria.
                        </p>

                        <!-- Tabela de Operadores de RH Atuais -->
                        <div class="table-responsive mb-4">
                            <table class="table table-hover align-middle border mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 50px;">Foto</th>
                                        <th>Nome Completo & Guerra</th>
                                        <th>Posto / Categoria</th>
                                        <th>SARAM / Login</th>
                                        <th>Divisão / Setor</th>
                                        <th class="text-center">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($operadoresRh)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-3">
                                                <i class="bi bi-info-circle me-1"></i> Nenhum operador de RH cadastrado no momento.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($operadoresRh as $rh): ?>
                                            <tr>
                                                <td>
                                                    <img src="/index.php?r=foto&saram=<?= $rh['saram'] ?>&id=<?= $rh['efetivo_id'] ?>" class="rounded-circle" style="width: 38px; height: 38px; object-fit: cover;" alt="Foto">
                                                </td>
                                                <td>
                                                    <div class="fw-bold text-dark"><?= sanitize_output($rh['nome_guerra'] ?: $rh['login']) ?></div>
                                                    <small class="text-muted"><?= sanitize_output($rh['nome']) ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info text-dark"><?= sanitize_output($rh['posto'] ?: 'CIVIL') ?></span>
                                                </td>
                                                <td>
                                                    <code><?= sanitize_output($rh['saram'] ?: $rh['login']) ?></code>
                                                </td>
                                                <td>
                                                    <span class="badge bg-light text-dark border"><?= sanitize_output($rh['divisao_sigla'] ?: 'COMARA') ?></span>
                                                    <small class="text-muted"><?= sanitize_output($rh['setor'] ?: '') ?></small>
                                                </td>
                                                <td class="text-center">
                                                    <form method="POST" action="/index.php?r=admin/salvar_chefias" class="d-inline" onsubmit="return confirm('Deseja realmente revogar o perfil de RH deste integrante?')">
                                                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                                        <input type="hidden" name="secao" value="rh_remover">
                                                        <input type="hidden" name="usuario_id" value="<?= $rh['usuario_id'] ?>">
                                                        <button type="submit" class="btn btn-outline-danger btn-sm" title="Revogar perfil de RH">
                                                            <i class="bi bi-person-dash me-1"></i> Revogar RH
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Formulário para Conceder Permissão de RH a um Integrante -->
                        <div class="p-3 bg-light rounded border">
                            <h6 class="fw-bold text-dark mb-2"><i class="bi bi-person-plus-fill me-1 text-info"></i> Conceder Função de RH a Outro Integrante:</h6>
                            <form method="POST" action="/index.php?r=admin/salvar_chefias" class="row g-2 align-items-end">
                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                <input type="hidden" name="secao" value="rh_adicionar">

                                <div class="col-md-9">
                                    <label class="form-label small text-muted mb-1">Selecione qualquer militar ou servidor civil da COMARA:</label>
                                    <select name="rh_efetivo_id" class="form-select select2-enable" required>
                                        <option value="">Selecione pelo Posto, Nome ou SARAM...</option>
                                        <?php 
                                        $rhEfetivoIds = array_filter(array_column($operadoresRh, 'efetivo_id'));
                                        foreach ($todoEfetivo as $m): 
                                            if (in_array($m['id'], $rhEfetivoIds)) continue;
                                        ?>
                                            <option value="<?= $m['id'] ?>">
                                                <?= sanitize_output($m['posto']) ?> <?= sanitize_output($m['nome_guerra']) ?> — <?= sanitize_output($m['nome']) ?> (<?= sanitize_output($m['divisao_sigla']) ?> / <?= sanitize_output($m['setor']) ?> - SARAM: <?= sanitize_output($m['saram']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <button type="submit" class="btn btn-info text-white fw-bold w-100" onclick="return confirm('Confirma a concessão de perfil de Recursos Humanos (RH) a este integrante?')">
                                        <i class="bi bi-person-check me-1"></i> Atribuir Perfil RH
                                    </button>
                                </div>
                            </form>
                        </div>

                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- ABA 3: Vinculação de Militares por Setor (Fase 1) -->
    <div class="tab-pane fade" id="setores-pane" role="tabpanel">
        <div class="card card-comara shadow-sm">
            <div class="card-comara-header">
                <i class="bi bi-link-45deg me-2"></i> Atribuição em Lote de Chefe Direto por Setor Específico
            </div>
            <div class="card-body p-4">
                <p class="text-muted small mb-4">
                    Utilize esta ferramenta para vincular todos os integrantes de uma seção ou oficina específica (ex: <code>DLMV</code>, <code>SDPJ</code>, <code>DEOF</code>, <code>DLAL</code>) a um Encarregado ou Chefe Imediato (Oficial ou Suboficial).
                </p>

                <form method="POST" action="/index.php?r=admin/salvar_chefias" class="row g-3 align-items-end mb-4">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="secao" value="vincular_setor">

                    <div class="col-md-5">
                        <label class="form-label fw-semibold">Setor / Subdivisão *</label>
                        <input type="text" name="setor" class="form-control text-uppercase" placeholder="EX: DLMV, SDPJ, DEOF, DAPM..." required>
                        <div class="form-text small">Digite a sigla exata do setor dos militares a serem vinculados.</div>
                    </div>

                    <div class="col-md-5">
                        <label class="form-label fw-semibold">Chefe Imediato Responsável *</label>
                        <select name="chefe_setor_id" class="form-select" required>
                            <option value="">Selecione o Chefe...</option>
                            <?php foreach ($oficiais as $of): ?>
                                <option value="<?= $of['id'] ?>">
                                    <?= sanitize_output($of['posto']) ?> <?= sanitize_output($of['nome_guerra']) ?> (<?= sanitize_output($of['divisao_sigla']) ?> - <?= sanitize_output($of['nome']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <button type="submit" class="btn btn-success fw-bold w-100">
                            <i class="bi bi-link me-1"></i> Vincular Setor
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>

<?php require_once ROOT_PATH . '/views/layouts/footer.php'; ?>
