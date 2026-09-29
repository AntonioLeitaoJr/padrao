<?php
declare(strict_types=1);
require_once ROOT_PATH . '/views/layouts/header.php';
?>

<!-- Cabeçalho da Página -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h2 class="h4 fw-bold text-primary mb-1">
            <i class="bi bi-people-fill me-2"></i> Gestão do Efetivo e Candidatos da COMARA
        </h2>
        <div class="text-muted small">
            Cadastro completo dos 443 militares e servidores civis, segmentação automática por categoria e gerenciamento de permissões de voto.
        </div>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <button type="button" class="btn btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#modalCadastrarEfetivo" onclick="abrirModalCadastro()">
            <i class="bi bi-person-plus-fill me-1"></i> Cadastrar Integrante
        </button>
        <button type="button" class="btn btn-outline-success fw-bold" data-bs-toggle="modal" data-bs-target="#modalImportarCsv">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i> Importar CSV
        </button>
        <a href="/index.php?r=admin/baixar_modelo_csv" class="btn btn-outline-secondary" title="Baixar modelo em branco formatado para Excel">
            <i class="bi bi-download me-1"></i> Modelo CSV
        </a>
        <a href="/index.php?r=admin/baixar_efetivo_csv" class="btn btn-outline-primary" title="Exportar todos os registros cadastrados">
            <i class="bi bi-cloud-arrow-down me-1"></i> Exportar Atual
        </a>
    </div>
</div>

<!-- Cards de Resumo por Categoria -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card card-comara h-100 shadow-sm border-start border-primary border-4">
            <div class="card-body p-3">
                <div class="text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem;">Total Efetivo</div>
                <div class="h3 fw-bold text-dark my-1"><?= $totais['total'] ?></div>
                <div class="text-muted small" style="font-size: 0.72rem;">Militares e Civis</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card card-comara h-100 shadow-sm border-start border-primary border-4">
            <div class="card-body p-3">
                <div class="text-primary small fw-semibold text-uppercase" style="font-size: 0.72rem;">Graduado Padrão</div>
                <div class="h3 fw-bold text-primary my-1"><?= $totais[CAT_GRADUADO] ?></div>
                <div class="text-muted small" style="font-size: 0.72rem;">Suboficiais e Sargentos</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card card-comara h-100 shadow-sm border-start border-success border-4">
            <div class="card-body p-3">
                <div class="text-success small fw-semibold text-uppercase" style="font-size: 0.72rem;">Praça Padrão</div>
                <div class="h3 fw-bold text-success my-1"><?= $totais[CAT_PRACA] ?></div>
                <div class="text-muted small" style="font-size: 0.72rem;">Cabos e Soldados</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card card-comara h-100 shadow-sm border-start border-info border-4">
            <div class="card-body p-3">
                <div class="text-info small fw-semibold text-uppercase" style="font-size: 0.72rem;">Civil Permanente</div>
                <div class="h3 fw-bold text-info my-1"><?= $totais[CAT_SPPF] ?></div>
                <div class="text-muted small" style="font-size: 0.72rem;">Quadro Efetivo SPPF</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card card-comara h-100 shadow-sm border-start border-warning border-4">
            <div class="card-body p-3">
                <div class="text-warning small fw-semibold text-uppercase" style="font-size: 0.72rem;">Civil Temporário</div>
                <div class="h3 fw-bold text-warning my-1"><?= $totais[CAT_SPTF] ?></div>
                <div class="text-muted small" style="font-size: 0.72rem;">Lei 8.745/93 SPTF</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card card-comara h-100 shadow-sm border-start border-secondary border-4">
            <div class="card-body p-3">
                <div class="text-secondary small fw-semibold text-uppercase" style="font-size: 0.72rem;">Inelegíveis</div>
                <div class="h3 fw-bold text-secondary my-1"><?= $totais[CAT_INELIGIVEL] ?></div>
                <div class="text-muted small" style="font-size: 0.72rem;">Oficiais e Chefias</div>
            </div>
        </div>
    </div>
</div>

<!-- Filtros e Barra de Pesquisa -->
<div class="card card-comara shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="GET" action="/index.php" class="row g-2 align-items-center">
            <input type="hidden" name="r" value="admin/efetivo">

            <div class="col-md-3">
                <label class="form-label small text-muted mb-1 fw-semibold">Categoria Oficial</label>
                <select name="cat" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="todas" <?= ($categoria === 'todas') ? 'selected' : '' ?>>Todas as Categorias</option>
                    <option value="graduado" <?= ($categoria === 'graduado') ? 'selected' : '' ?>>Graduado Padrão (SO/Sgt)</option>
                    <option value="praca" <?= ($categoria === 'praca') ? 'selected' : '' ?>>Praça Padrão (Cb/Sd)</option>
                    <option value="sppf" <?= ($categoria === 'sppf') ? 'selected' : '' ?>>Servidor Civil Permanente (SPPF)</option>
                    <option value="sptf" <?= ($categoria === 'sptf') ? 'selected' : '' ?>>Servidor Civil Temporário (SPTF)</option>
                    <option value="ineligivel" <?= ($categoria === 'ineligivel') ? 'selected' : '' ?>>Inelegíveis (Oficiais/Chefias)</option>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label small text-muted mb-1 fw-semibold">Divisão / Órgão</label>
                <select name="div" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="todas" <?= ($divisao === 'todas') ? 'selected' : '' ?>>Todas as Divisões</option>
                    <?php foreach ($divisoes as $d): ?>
                        <option value="<?= $d ?>" <?= ($divisao === $d) ? 'selected' : '' ?>><?= $d ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label small text-muted mb-1 fw-semibold">Status de Cadastro</label>
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="ativo" <?= ($status === 'ativo') ? 'selected' : '' ?>>Apenas Ativos</option>
                    <option value="inativo" <?= ($status === 'inativo') ? 'selected' : '' ?>>Apenas Inativos</option>
                    <option value="todos" <?= ($status === 'todos') ? 'selected' : '' ?>>Todos os Registros</option>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label small text-muted mb-1 fw-semibold">Busca por Nome ou SARAM</label>
                <input type="text" name="q" class="form-control form-control-sm" placeholder="Nome, Guerra, SARAM ou CPF..." value="<?= sanitize_output($termo) ?>">
            </div>

            <div class="col-md-2 d-flex align-items-end pt-3">
                <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold">
                    <i class="bi bi-search me-1"></i> Filtrar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Tabela de Registros -->
<div class="card card-comara shadow-sm mb-4">
    <div class="card-comara-header d-flex justify-content-between align-items-center">
        <span>
            <i class="bi bi-table me-2"></i> Integrantes do Efetivo 
            <span class="badge bg-light text-primary ms-2"><?= $totalRegistros ?> registros localizados</span>
        </span>
        <span class="small text-white-50">Página <?= $pagina ?> de <?= $totalPaginas ?></span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 55px;" class="text-center">Foto</th>
                        <th style="width: 100px;">Posto / Grad</th>
                        <th>Nome Completo & Guerra</th>
                        <th>SARAM / CPF</th>
                        <th>Divisão / Setor</th>
                        <th>Categoria Padrão</th>
                        <th>Chefe Direto</th>
                        <th class="text-center">Status</th>
                        <th class="text-end" style="min-width: 130px;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($efetivo)): ?>
                        <tr>
                            <td colspan="9" class="text-center p-5 text-muted">
                                <i class="bi bi-search display-6 d-block mb-2 text-muted"></i>
                                Nenhum militar ou servidor encontrado com os filtros selecionados.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($efetivo as $item): ?>
                            <tr class="<?= ($item['ativo'] == 0) ? 'table-secondary opacity-75' : '' ?>">
                                <td class="text-center">
                                    <img src="/index.php?r=foto&saram=<?= $item['saram'] ?>&id=<?= $item['id'] ?>"
                                         class="rounded-circle"
                                         style="width: 40px; height: 40px; object-fit: cover; border: 2px solid #ced4da;"
                                         alt="Foto">
                                </td>
                                <td>
                                    <span class="fw-bold text-primary"><?= sanitize_output($item['posto']) ?></span><br>
                                    <small class="text-muted"><?= sanitize_output($item['quadro']) ?> - <?= sanitize_output($item['especialidade']) ?></small>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark"><?= sanitize_output($item['nome_guerra']) ?></div>
                                    <small class="text-muted"><?= sanitize_output($item['nome']) ?></small>
                                </td>
                                <td>
                                    <code><?= sanitize_output($item['saram'] ?: 'S/SARAM') ?></code><br>
                                    <small class="text-muted" title="Protegido por LGPD (Art. 6º, III)"><i class="bi bi-shield-lock me-1"></i><?= mascarar_cpf($item['cpf']) ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border fw-bold"><?= sanitize_output($item['divisao_sigla']) ?></span>
                                    <small class="text-muted ms-1"><?= sanitize_output($item['setor']) ?></small>
                                </td>
                                <td>
                                    <?php
                                    switch ($item['categoria']) {
                                        case CAT_GRADUADO:
                                            echo '<span class="badge bg-primary">GRADUADO</span>';
                                            break;
                                        case CAT_PRACA:
                                            echo '<span class="badge bg-success">PRAÇA</span>';
                                            break;
                                        case CAT_SPPF:
                                            echo '<span class="badge bg-info text-dark">CIVIL SPPF</span>';
                                            break;
                                        case CAT_SPTF:
                                            echo '<span class="badge bg-warning text-dark">CIVIL SPTF</span>';
                                            break;
                                        default:
                                            echo '<span class="badge bg-secondary">INELEGÍVEL</span>';
                                            break;
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php if (!empty($item['chefe_nome_guerra'])): ?>
                                        <span class="small fw-semibold text-dark">
                                            <i class="bi bi-person-check me-1 text-success"></i>
                                            <?= sanitize_output($item['chefe_posto']) ?> <?= sanitize_output($item['chefe_nome_guerra']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted fst-italic small">Não vinculado</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($item['ativo'] == 1): ?>
                                        <span class="badge bg-success-subtle text-success border border-success">Ativo</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger">Inativo</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-primary me-1"
                                            title="Editar Dados e Categoria"
                                            onclick='abrirModalEdicao(<?= json_encode($item, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                                        <i class="bi bi-pencil-square"></i>
                                    </button>

                                    <?php if (AuthManager::hasRole(PERFIL_ADMIN)): ?>
                                        <form method="POST" action="/index.php?r=admin/resetar_senha" class="d-inline me-1" onsubmit="return confirm('Deseja realmente redefinir a senha de <?= sanitize_output($item['nome_guerra']) ?> para padrao@2026?')">
                                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                            <input type="hidden" name="efetivo_id" value="<?= $item['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-warning" title="Resetar Senha para padrao@2026">
                                                <i class="bi bi-key"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <form method="POST" action="/index.php?r=admin/toggle_ativo_efetivo" class="d-inline" onsubmit="return confirm('Deseja realmente alterar o status deste integrante?')">
                                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                        <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                        <button type="submit" class="btn btn-sm <?= ($item['ativo'] == 1) ? 'btn-outline-danger' : 'btn-outline-success' ?>"
                                                title="<?= ($item['ativo'] == 1) ? 'Inativar Integrante' : 'Ativar Integrante' ?>">
                                            <i class="bi <?= ($item['ativo'] == 1) ? 'bi-person-slash' : 'bi-person-check' ?>"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Paginação -->
    <?php if ($totalPaginas > 1): ?>
        <div class="card-footer bg-white d-flex justify-content-between align-items-center py-3">
            <span class="text-muted small">
                Exibindo <?= count($efetivo) ?> de <?= $totalRegistros ?> militares/servidores
            </span>
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?= ($pagina <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link" href="/index.php?r=admin/efetivo&cat=<?= urlencode($categoria) ?>&div=<?= urlencode($divisao) ?>&status=<?= urlencode($status) ?>&q=<?= urlencode($termo) ?>&p=<?= $pagina - 1 ?>">&laquo; Anterior</a>
                </li>
                <?php
                $ini = max(1, $pagina - 2);
                $fim = min($totalPaginas, $pagina + 2);
                for ($p = $ini; $p <= $fim; $p++):
                ?>
                    <li class="page-item <?= ($pagina === $p) ? 'active' : '' ?>">
                        <a class="page-link" href="/index.php?r=admin/efetivo&cat=<?= urlencode($categoria) ?>&div=<?= urlencode($divisao) ?>&status=<?= urlencode($status) ?>&q=<?= urlencode($termo) ?>&p=<?= $p ?>"><?= $p ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= ($pagina >= $totalPaginas) ? 'disabled' : '' ?>">
                    <a class="page-link" href="/index.php?r=admin/efetivo&cat=<?= urlencode($categoria) ?>&div=<?= urlencode($divisao) ?>&status=<?= urlencode($status) ?>&q=<?= urlencode($termo) ?>&p=<?= $pagina + 1 ?>">Próxima &raquo;</a>
                </li>
            </ul>
        </div>
    <?php endif; ?>
</div>

<!-- MODAL: Cadastrar / Editar Integrante -->
<div class="modal fade" id="modalCadastrarEfetivo" tabindex="-1" aria-labelledby="modalCadastrarEfetivoLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="/index.php?r=admin/salvar_efetivo" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="id" id="campo_id" value="">

                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold" id="modalCadastrarEfetivoLabel">
                        <i class="bi bi-person-fill-gear me-2"></i> Cadastrar Integrante do Efetivo
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Nome Completo *</label>
                            <input type="text" name="nome" id="campo_nome" class="form-control" required placeholder="EX: JOÃO CARLOS DA SILVA">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Nome de Guerra *</label>
                            <input type="text" name="nome_guerra" id="campo_nome_guerra" class="form-control" required placeholder="EX: SILVA">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">SARAM (Militar) ou Matrícula</label>
                            <input type="text" name="saram" id="campo_saram" class="form-control" placeholder="7 dígitos (militar)">
                            <div class="form-text small">Deixe em branco para servidor civil sem SARAM.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">CPF *</label>
                            <input type="text" name="cpf" id="campo_cpf" class="form-control" placeholder="000.000.000-00">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Telefone / Ramal</label>
                            <input type="text" name="telefone" id="campo_telefone" class="form-control" placeholder="Ex: (91) 98888-0000">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Posto / Graduação *</label>
                            <select name="posto" id="campo_posto" class="form-select" required onchange="ajustarCategoriaAutomaticamente(this.value)">
                                <option value="">Selecione...</option>
                                <optgroup label="Oficiais Superiores (Inelegíveis)">
                                    <option value="Cel">Cel</option>
                                    <option value="Ten Cel">Ten Cel</option>
                                    <option value="Maj">Maj</option>
                                </optgroup>
                                <optgroup label="Oficiais Intermediários/Subalternos (Inelegíveis)">
                                    <option value="Cap">Cap</option>
                                    <option value="1º Ten">1º Ten</option>
                                    <option value="2º Ten">2º Ten</option>
                                </optgroup>
                                <optgroup label="Graduados (Concorrem a Graduado Padrão)">
                                    <option value="SO">SO</option>
                                    <option value="1S">1S</option>
                                    <option value="2S">2S</option>
                                    <option value="3S">3S</option>
                                </optgroup>
                                <optgroup label="Praças (Concorrem a Praça Padrão)">
                                    <option value="Cb">Cb</option>
                                    <option value="S1">S1</option>
                                    <option value="S2">S2</option>
                                </optgroup>
                                <optgroup label="Servidores Civis">
                                    <option value="CV">CV (Civil)</option>
                                </optgroup>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Quadro</label>
                            <input type="text" name="quadro" id="campo_quadro" class="form-control" placeholder="Ex: QSS, QCBCON, CIVIL">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Especialidade</label>
                            <input type="text" name="especialidade" id="campo_especialidade" class="form-control" placeholder="Ex: SAD, TMC, BMA, ADM">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Divisão / Sigla *</label>
                            <input type="text" name="divisao_sigla" id="campo_divisao_sigla" class="form-control text-uppercase" required placeholder="Ex: DA, DL, DE, DPC">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Setor Específico</label>
                            <input type="text" name="setor" id="campo_setor" class="form-control text-uppercase" placeholder="Ex: DAPM, DLMV, SDPJ">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Categoria Oficial no Pleito *</label>
                            <select name="categoria" id="campo_categoria" class="form-select" required>
                                <option value="graduado">Graduado Padrão (SO/Sgt)</option>
                                <option value="praca">Praça Padrão (Cb/Sd)</option>
                                <option value="sppf">Servidor Civil Permanente (SPPF)</option>
                                <option value="sptf">Servidor Civil Temporário (SPTF)</option>
                                <option value="ineligivel">Inelegível (Oficial / Chefia)</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Tipo de Vínculo *</label>
                            <select name="tipo_vinculo" id="campo_tipo_vinculo" class="form-select" required>
                                <option value="MILITAR_CARREIRA">Militar de Carreira</option>
                                <option value="MILITAR_TEMPORARIO">Militar Temporário</option>
                                <option value="CIVIL_PERMANENTE">Civil Permanente</option>
                                <option value="CIVIL_TEMPORARIO">Civil Temporário (Lei 8.745/93)</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Chefe Direto Imediato (Fase 1)</label>
                            <select name="chefe_direto_id" id="campo_chefe_direto_id" class="form-select">
                                <option value="">Sem Chefe Direto Definido</option>
                                <?php foreach ($potenciaisChefes as $c): ?>
                                    <option value="<?= $c['id'] ?>">
                                        <?= sanitize_output($c['posto']) ?> <?= sanitize_output($c['nome_guerra']) ?> (<?= sanitize_output($c['divisao_sigla']) ?> - <?= sanitize_output($c['nome']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Foto Manual (Opcional - JPG/PNG)</label>
                            <input type="file" name="foto" class="form-control" accept="image/jpeg,image/png,image/webp">
                            <div class="form-text small">Caso não envie foto, o sistema consultará automaticamente a API do SIGPES.</div>
                        </div>

                        <?php if (AuthManager::hasRole(PERFIL_ADMIN)): ?>
                            <div class="col-md-6">
                                <input type="hidden" name="is_admin_enviado" value="1">
                                <div class="p-2 border rounded bg-light mt-2 mb-1">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="is_admin" id="campo_is_admin" value="1">
                                        <label class="form-check-label fw-bold text-danger" for="campo_is_admin">
                                            <i class="bi bi-shield-lock me-1"></i> Perfil Administrador (ADMIN)
                                        </label>
                                        <div class="small text-muted" style="font-size: 0.75rem;">Acesso total de gestão, prazos e auditoria.</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <input type="hidden" name="is_rh_enviado" value="1">
                                <div class="p-2 border rounded bg-light mt-2 mb-1">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="is_rh" id="campo_is_rh" value="1">
                                        <label class="form-check-label fw-bold text-primary" for="campo_is_rh">
                                            <i class="bi bi-person-gear me-1"></i> Perfil Recursos Humanos (RH)
                                        </label>
                                        <div class="small text-muted" style="font-size: 0.75rem;">Acesso exclusivo ao cadastro de usuários.</div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="col-12">
                            <div class="form-check form-switch mt-1">
                                <input class="form-check-input" type="checkbox" name="ativo" id="campo_ativo" value="1" checked>
                                <label class="form-check-label fw-bold" for="campo_ativo">Militar / Servidor Ativo (Habilitado para o pleito)</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary fw-bold">
                        <i class="bi bi-check-circle me-1"></i> Salvar Registro
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: Importar Lista Completa em CSV -->
<div class="modal fade" id="modalImportarCsv" tabindex="-1" aria-labelledby="modalImportarCsvLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="/index.php?r=admin/importar_csv" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title fw-bold" id="modalImportarCsvLabel">
                        <i class="bi bi-file-earmark-arrow-up me-2"></i> Importar Lista Completa do Efetivo (CSV)
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="alert alert-info">
                        <h6 class="fw-bold mb-1"><i class="bi bi-info-circle-fill me-1"></i> Instruções de Carga em Lote</h6>
                        <ul class="mb-0 small">
                            <li>O arquivo deve estar no formato <strong>.CSV</strong> (separado por ponto-e-vírgula <code>;</code> ou vírgula <code>,</code>).</li>
                            <li>Colunas obrigatórias no cabeçalho: <code>SARAM;CPF;NOME;NOME_GUERRA;POSTO;QUADRO;ESPECIALIDADE;SETOR;DIVISAO;CATEGORIA;VINCULO;TELEFONE</code>.</li>
                            <li>Registros já existentes serão automaticamente atualizados pelo SARAM ou CPF. Novos registros serão inseridos e receberão senha de acesso padrão <code>padrao@2026</code>.</li>
                        </ul>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Selecione o arquivo CSV *</label>
                        <input type="file" name="arquivo_csv" class="form-control form-control-lg" accept=".csv" required>
                    </div>

                    <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded border">
                        <div>
                            <strong>Precisa de um modelo pronto?</strong><br>
                            <small class="text-muted">Baixe o modelo com cabeçalhos pré-configurados para preencher no Excel ou LibreOffice.</small>
                        </div>
                        <a href="/index.php?r=admin/baixar_modelo_csv" class="btn btn-outline-primary btn-sm fw-bold">
                            <i class="bi bi-download me-1"></i> Baixar Modelo CSV
                        </a>
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    <button type="submit" class="btn btn-success fw-bold">
                        <i class="bi bi-cloud-upload me-1"></i> Processar e Importar Efetivo
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function abrirModalCadastro() {
    document.getElementById('modalCadastrarEfetivoLabel').innerHTML = '<i class="bi bi-person-plus-fill me-2"></i> Cadastrar Integrante do Efetivo';
    document.getElementById('campo_id').value = '';
    document.getElementById('campo_nome').value = '';
    document.getElementById('campo_nome_guerra').value = '';
    document.getElementById('campo_saram').value = '';
    document.getElementById('campo_cpf').value = '';
    document.getElementById('campo_telefone').value = '';
    document.getElementById('campo_posto').value = '';
    document.getElementById('campo_quadro').value = '';
    document.getElementById('campo_especialidade').value = '';
    document.getElementById('campo_divisao_sigla').value = '';
    document.getElementById('campo_setor').value = '';
    document.getElementById('campo_categoria').value = 'graduado';
    document.getElementById('campo_tipo_vinculo').value = 'MILITAR_CARREIRA';
    document.getElementById('campo_chefe_direto_id').value = '';
    if (document.getElementById('campo_is_admin')) document.getElementById('campo_is_admin').checked = false;
    if (document.getElementById('campo_is_rh')) document.getElementById('campo_is_rh').checked = false;
    document.getElementById('campo_ativo').checked = true;
}

function abrirModalEdicao(item) {
    document.getElementById('modalCadastrarEfetivoLabel').innerHTML = '<i class="bi bi-pencil-square me-2"></i> Editar Integrante: ' + item.posto + ' ' + item.nome_guerra;
    document.getElementById('campo_id').value = item.id || '';
    document.getElementById('campo_nome').value = item.nome || '';
    document.getElementById('campo_nome_guerra').value = item.nome_guerra || '';
    document.getElementById('campo_saram').value = item.saram || '';
    document.getElementById('campo_cpf').value = item.cpf || '';
    document.getElementById('campo_telefone').value = item.telefone || '';
    document.getElementById('campo_posto').value = item.posto || '';
    document.getElementById('campo_quadro').value = item.quadro || '';
    document.getElementById('campo_especialidade').value = item.especialidade || '';
    document.getElementById('campo_divisao_sigla').value = item.divisao_sigla || '';
    document.getElementById('campo_setor').value = item.setor || '';
    document.getElementById('campo_categoria').value = item.categoria || 'graduado';
    document.getElementById('campo_tipo_vinculo').value = item.tipo_vinculo || 'MILITAR_CARREIRA';
    document.getElementById('campo_chefe_direto_id').value = item.chefe_direto_id || '';
    if (document.getElementById('campo_is_admin')) document.getElementById('campo_is_admin').checked = (item.usuario_perfil === 'ADMIN');
    if (document.getElementById('campo_is_rh')) document.getElementById('campo_is_rh').checked = (item.usuario_perfil === 'RH');
    document.getElementById('campo_ativo').checked = (item.ativo == 1);

    const modal = new bootstrap.Modal(document.getElementById('modalCadastrarEfetivo'));
    modal.show();
}

function ajustarCategoriaAutomaticamente(posto) {
    const catSelect = document.getElementById('campo_categoria');
    const vincSelect = document.getElementById('campo_tipo_vinculo');

    if (['Cel', 'Ten Cel', 'Maj', 'Cap', '1º Ten', '2º Ten'].includes(posto)) {
        catSelect.value = 'ineligivel';
        vincSelect.value = 'MILITAR_CARREIRA';
    } else if (['SO', '1S', '2S', '3S'].includes(posto)) {
        catSelect.value = 'graduado';
        vincSelect.value = 'MILITAR_CARREIRA';
    } else if (['Cb', 'S1', 'S2'].includes(posto)) {
        catSelect.value = 'praca';
        vincSelect.value = 'MILITAR_CARREIRA';
    } else if (posto === 'CV') {
        const saram = document.getElementById('campo_saram').value.trim();
        if (saram.startsWith('85')) {
            catSelect.value = 'sptf';
            vincSelect.value = 'CIVIL_TEMPORARIO';
        } else {
            catSelect.value = 'sppf';
            vincSelect.value = 'CIVIL_PERMANENTE';
        }
    }
}
</script>

<?php require_once ROOT_PATH . '/views/layouts/footer.php'; ?>
