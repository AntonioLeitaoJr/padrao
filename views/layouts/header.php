<?php
declare(strict_types=1);

$userLogado = AuthManager::user();
$rotaAtual = $_GET['r'] ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> — COMARA</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Estilos Customizados COMARA -->
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>

<!-- Cabeçalho Oficial INTRAER / COMARA -->
<header class="comara-header">
    <div class="container-fluid px-4">
        <div class="d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center">
                <img src="/assets/img/comara_brasao.png" alt="Brasão COMARA" class="comara-crest me-3">
                <div>
                    <div class="text-uppercase small tracking-wide opacity-75" style="letter-spacing: 1.5px; font-size: 0.75rem;">Comando da Aeronáutica • DIRINFRA</div>
                    <h1 class="h4 mb-0 fw-bold text-white text-uppercase" style="letter-spacing: 0.5px;">Comissão de Aeroportos da Região Amazônica</h1>
                    <div class="small text-light opacity-90 fw-semibold">Sistema Eletrônico de Eleição dos Padrões (Militar e Civil)</div>
                </div>
            </div>
            <div class="d-none d-md-flex align-items-center gap-3">
                <div class="text-end">
                    <div class="intraer-logo">INTRAER</div>
                    <div class="small text-white-50" style="font-size: 0.7rem;">Ambiente Seguro • Rede Corporativa</div>
                </div>
            </div>
        </div>
    </div>
</header>

<!-- Barra de Navegação -->
<nav class="comara-nav">
    <div class="container-fluid px-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between py-1">
            <ul class="nav nav-pills gap-1">
                <li class="nav-item">
                    <a class="nav-link <?= ($rotaAtual === 'dashboard') ? 'active' : '' ?>" href="/index.php?r=dashboard">
                        <i class="bi bi-speedometer2 me-1"></i> Painel Geral
                    </a>
                </li>

                <?php if (AuthManager::check()): ?>
                    <?php if (AuthManager::isAvaliadorFase1() || AuthManager::hasRole([PERFIL_ED_FISICA, PERFIL_ADMIN])): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= (str_starts_with($rotaAtual, 'fase1')) ? 'active' : '' ?>" href="/index.php?r=fase1">
                                <i class="bi bi-file-earmark-check me-1"></i> Fase 1: Avaliações
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if (AuthManager::hasRole(PERFIL_RH) && !AuthManager::hasRole(PERFIL_ADMIN)): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= (str_starts_with($rotaAtual, 'admin/efetivo')) ? 'active' : '' ?>" href="/index.php?r=admin/efetivo">
                                <i class="bi bi-people-fill me-1 text-primary"></i> <strong>Cadastro de Usuários (RH)</strong>
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if (AuthManager::hasRole([PERFIL_CHEFE_DIV_ASSESS, PERFIL_ADMIN, PERFIL_PRESIDENTE])): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= (str_starts_with($rotaAtual, 'fase2')) ? 'active' : '' ?>" href="/index.php?r=fase2">
                                <i class="bi bi-diagram-3 me-1"></i> Fase 2: Divisões & Assessorias
                            </a>
                        </li>
                    <?php endif; ?>

                    <li class="nav-item">
                        <a class="nav-link <?= (str_starts_with($rotaAtual, 'fase3')) ? 'active' : '' ?>" href="/index.php?r=fase3">
                            <i class="bi bi-box-seam me-1 text-primary"></i> <strong>Fase 3: Urna Geral</strong>
                        </a>
                    </li>

                    <?php if (AuthManager::hasRole([PERFIL_DIRECAO_SUPERIOR, PERFIL_PRESIDENTE, PERFIL_ADMIN])): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= (str_starts_with($rotaAtual, 'fase4')) ? 'active' : '' ?>" href="/index.php?r=fase4">
                                <i class="bi bi-shield-check me-1"></i> Fase 4: Direção Superior
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if (AuthManager::hasRole([PERFIL_PRESIDENTE, PERFIL_ADMIN])): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= (str_starts_with($rotaAtual, 'fase5')) ? 'active' : '' ?>" href="/index.php?r=fase5">
                                <i class="bi bi-award me-1"></i> Fase 5: Homologação
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if (AuthManager::hasRole(PERFIL_ADMIN)): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= ($rotaAtual === 'relatorios/placa') ? 'active' : '' ?>" href="/index.php?r=relatorios/placa">
                                <i class="bi bi-trophy me-1 text-warning"></i> Placa Alusiva
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if (AuthManager::hasRole(PERFIL_ADMIN)): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle <?= (str_starts_with($rotaAtual, 'admin')) ? 'active' : '' ?>" data-bs-toggle="dropdown" href="#" role="button">
                                <i class="bi bi-gear-fill me-1"></i> Gestão do Pleito
                            </a>
                            <ul class="dropdown-menu shadow-sm">
                                <li><a class="dropdown-item" href="/index.php?r=admin/pleito"><i class="bi bi-calendar2-range me-2 text-primary"></i> Prazos e Fases</a></li>
                                <li><a class="dropdown-item" href="/index.php?r=admin/efetivo"><i class="bi bi-people-fill me-2 text-primary"></i> Gestão do Efetivo & Candidatos</a></li>
                                <li><a class="dropdown-item" href="/index.php?r=admin/chefias"><i class="bi bi-person-lines-fill me-2 text-primary"></i> Atribuição de Chefias & Perfis</a></li>
                                <li><a class="dropdown-item" href="/index.php?r=admin/fotos"><i class="bi bi-camera me-2 text-primary"></i> Gestão de Fotografias</a></li>
                                <li><a class="dropdown-item" href="/index.php?r=admin/logs"><i class="bi bi-journal-text me-2 text-secondary"></i> Logs de Auditoria (5 Anos)</a></li>
                                <li><a class="dropdown-item" href="/index.php?r=admin/backups"><i class="bi bi-database-check me-2 text-secondary"></i> Backups Incrementais</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="/index.php?r=relatorios/site"><i class="bi bi-globe me-2"></i> Publicação no Site Oficial</a></li>
                                <li><a class="dropdown-item" href="/index.php?r=relatorios/estatisticas"><i class="bi bi-bar-chart me-2"></i> Estatísticas e Participação</a></li>
                            </ul>
                        </li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>

            <div class="d-flex align-items-center gap-2 mt-2 mt-md-0">
                <?php if (AuthManager::check()): ?>
                    <span class="badge bg-light text-dark border px-2 py-1">
                        <i class="bi bi-person-badge me-1"></i>
                        <?= sanitize_output($userLogado['posto']) ?> <?= sanitize_output($userLogado['nome_guerra']) ?>
                        <span class="badge bg-primary ms-1"><?= sanitize_output($userLogado['perfil']) ?></span>
                    </span>
                    <button type="button" class="btn btn-outline-primary btn-sm bg-white" data-bs-toggle="modal" data-bs-target="#modalAlterarSenha" title="Alterar ou Redefinir Senha de Acesso">
                        <i class="bi bi-key-fill me-1 text-warning"></i> Senha
                    </button>
                    <a href="/index.php?r=logout" class="btn btn-outline-danger btn-sm">
                        <i class="bi bi-box-arrow-right"></i> Sair
                    </a>
                <?php else: ?>
                    <a href="/index.php?r=login" class="btn btn-primary btn-sm">
                        <i class="bi bi-lock-fill me-1"></i> Autenticar
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<?php if (AuthManager::check()): ?>
<!-- Modal de Alteração e Reset de Senha (Disponível para qualquer usuário autenticado) -->
<div class="modal fade" id="modalAlterarSenha" tabindex="-1" aria-labelledby="modalAlterarSenhaLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold" id="modalAlterarSenhaLabel">
                    <i class="bi bi-shield-lock me-2"></i> Segurança da Conta - Senha de Acesso
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-primary border-primary-subtle py-2 px-3 small mb-3">
                    <i class="bi bi-shield-check text-primary me-1"></i>
                    <strong>Segurança da Informação:</strong> No primeiro acesso ao sistema, é obrigatório definir uma nova senha pessoal para assegurar o sigilo do seu voto.
                </div>

                <form method="POST" action="/index.php?r=alterar_senha">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="acao" value="alterar">

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Senha Atual</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-lock text-muted"></i></span>
                            <input type="password" name="senha_atual" class="form-control" placeholder="Digite sua senha atual" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nova Senha (mínimo 6 caracteres)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-shield-lock text-muted"></i></span>
                            <input type="password" name="nova_senha" minlength="6" class="form-control" placeholder="Digite a nova senha" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Confirmar Nova Senha</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-shield-lock-fill text-muted"></i></span>
                            <input type="password" name="confirma_senha" minlength="6" class="form-control" placeholder="Repita a nova senha" required>
                        </div>
                    </div>

                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-primary fw-bold shadow-sm">
                            <i class="bi bi-check-lg me-1"></i> Salvar Nova Senha
                        </button>
                    </div>
                </form>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Mensagens Flash -->
<div class="container-fluid px-4 mt-3">
    <?php if ($sucesso = flash_message('sucesso')): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> <?= sanitize_output($sucesso['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if ($erro = flash_message('erro')): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= sanitize_output($erro['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
</div>

<!-- Conteúdo Principal -->
<main class="container-fluid px-4 py-3 flex-grow-1">
