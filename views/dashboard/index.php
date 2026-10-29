<?php
declare(strict_types=1);
require_once ROOT_PATH . '/views/layouts/header.php';

$pleito = $dados['pleito'];
$faseAtual = $pleito ? (int)$pleito['fase_atual'] : 1;
?>

<!-- Banner do Pleito Atual -->
<div class="card card-comara shadow-sm mb-4">
    <div class="card-body p-4">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge bg-primary text-uppercase px-3 py-2 fs-6">Pleito Oficial <?= $pleito['ano'] ?? date('Y') ?></span>
                    <span class="fase-badge atual fs-6">
                        <i class="bi bi-clock-history me-1"></i> Fase Atual: <?= $faseAtual ?> — <?= FASES_PROCESSO[$faseAtual]['nome'] ?>
                    </span>
                    <span class="badge bg-secondary"><?= strtoupper($pleito['status'] ?? 'EM ANDAMENTO') ?></span>
                </div>
                <h2 class="h4 fw-bold text-primary mb-1"><?= sanitize_output($pleito['titulo'] ?? 'Eleição dos Padrões COMARA') ?></h2>
                <p class="text-muted mb-0 small">
                    <?= FASES_PROCESSO[$faseAtual]['descricao'] ?>
                </p>
            </div>
            <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                <?php if ($dados['votou_fase3']): ?>
                    <div class="alert alert-success d-inline-block text-start mb-0 py-2 px-3 border-success shadow-sm">
                        <div class="fw-bold"><i class="bi bi-check2-circle text-success me-1"></i> Voto Registrado</div>
                        <small class="text-muted d-block">Comprovante: <code><?= sanitize_output($dados['comprovante_voto']) ?></code></small>
                    </div>
                <?php elseif ($faseAtual === FASE_3): ?>
                    <a href="/index.php?r=fase3" class="btn btn-success btn-lg fw-bold shadow">
                        <i class="bi bi-box-seam me-2"></i> Votar Agora na Urna
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Linha do Tempo das 5 Fases Regimentais -->
        <hr class="my-4">
        <div class="row text-center g-2">
            <?php foreach (FASES_PROCESSO as $numFase => $info): ?>
                <?php
                $classeCard = 'bg-light text-muted border';
                $icone = 'bi-circle';
                if ($numFase < $faseAtual) {
                    $classeCard = 'bg-success-subtle text-success border-success';
                    $icone = 'bi-check-circle-fill';
                } elseif ($numFase === $faseAtual) {
                    $classeCard = 'bg-primary text-white border-primary shadow-sm';
                    $icone = 'bi-arrow-right-circle-fill';
                }
                ?>
                <div class="col">
                    <div class="p-2 rounded <?= $classeCard ?> h-100">
                        <div class="fw-bold small mb-1">
                            <i class="bi <?= $icone ?> me-1"></i> Fase <?= $numFase ?>
                        </div>
                        <div style="font-size: 0.75rem;" class="text-truncate" title="<?= $info['nome'] ?>">
                            <?= $info['nome'] ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Alertas Específicos por Perfil -->
<?php if (AuthManager::isOficial() && !empty($dados['subordinados'])): ?>
    <?php
    $pendentesSub = array_filter($dados['subordinados'], fn($s) => empty($s['ficha_id']));
    ?>
    <?php if (!empty($pendentesSub)): ?>
        <div class="alert alert-warning shadow-sm border-warning d-flex align-items-center justify-content-between p-3 mb-4">
            <div>
                <h5 class="alert-heading fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>Avaliações de Subordinados Pendentes (Fase 1)</h5>
                <p class="mb-0 small">
                    Você possui <strong><?= count($pendentesSub) ?></strong> militar(es) diretamente subordinado(s) aguardando o preenchimento obrigatório da Ficha de Indicação. 
                    <span class="text-danger fw-semibold">O avanço para a Fase 2 está condicionado à conclusão de todas as avaliações obrigatórias.</span>
                </p>
            </div>
            <a href="/index.php?r=fase1" class="btn btn-warning fw-bold text-nowrap ms-3">
                <i class="bi bi-pencil-square me-1"></i> Avaliar Subordinados
            </a>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php if (AuthManager::hasRole([PERFIL_ED_FISICA, PERFIL_ADMIN]) && $dados['tacf_pendentes'] > 0): ?>
    <div class="alert alert-info shadow-sm border-info d-flex align-items-center justify-content-between p-3 mb-4">
        <div>
            <h5 class="alert-heading fw-bold mb-1"><i class="bi bi-activity text-info me-2"></i>Avaliações do TACF Pendentes</h5>
            <p class="mb-0 small">
                Existem <strong><?= $dados['tacf_pendentes'] ?></strong> militares candidatos sem lançamento de nota do Teste de Aptidão do Condicionamento Físico.
            </p>
        </div>
        <a href="/index.php?r=fase1" class="btn btn-info text-white fw-bold text-nowrap ms-3">
            <i class="bi bi-clipboard2-check me-1"></i> Lançar Notas TACF
        </a>
    </div>
<?php endif; ?>

<?php if ($dados['progresso_fase2'] && !$dados['progresso_fase2']['concluido'] && $faseAtual === FASE_2): ?>
    <div class="alert alert-primary shadow-sm border-primary d-flex align-items-center justify-content-between p-3 mb-4">
        <div>
            <h5 class="alert-heading fw-bold mb-1"><i class="bi bi-diagram-3-fill text-primary me-2"></i>Seleção de Candidatos pela sua Chefia (Fase 2)</h5>
            <p class="mb-0 small">
                Seu órgão (<strong><?= sanitize_output($dados['orgao_fase2']) ?></strong>) ainda não concluiu a indicação obrigatória de um candidato por categoria com concorrentes disponíveis.
            </p>
        </div>
        <a href="/index.php?r=fase2" class="btn btn-primary fw-bold text-nowrap ms-3">
            <i class="bi bi-check2-square me-1"></i> Concluir Seleção
        </a>
    </div>
<?php endif; ?>

<!-- Estatísticas Gerais das Categorias -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card card-comara p-3 border-start border-4 border-primary">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small fw-bold text-uppercase">Graduado Padrão</div>
                    <div class="h3 fw-bold mb-0 text-primary"><?= $dados['metricas']['total_graduado'] ?? 0 ?></div>
                    <small class="text-muted">Suboficiais e Sargentos</small>
                </div>
                <div class="fs-1 text-primary opacity-50"><i class="bi bi-person-badge"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card card-comara p-3 border-start border-4 border-success">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small fw-bold text-uppercase">Praça Padrão</div>
                    <div class="h3 fw-bold mb-0 text-success"><?= $dados['metricas']['total_praca'] ?? 0 ?></div>
                    <small class="text-muted">Cabos e Soldados</small>
                </div>
                <div class="fs-1 text-success opacity-50"><i class="bi bi-person-lines-fill"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card card-comara p-3 border-start border-4 border-info">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small fw-bold text-uppercase">SPPF Padrão</div>
                    <div class="h3 fw-bold mb-0 text-info"><?= $dados['metricas']['total_sppf'] ?? 0 ?></div>
                    <small class="text-muted">Servidores Permanentes</small>
                </div>
                <div class="fs-1 text-info opacity-50"><i class="bi bi-briefcase-fill"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card card-comara p-3 border-start border-4 border-warning">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small fw-bold text-uppercase">SPTF Padrão</div>
                    <div class="h3 fw-bold mb-0 text-warning"><?= $dados['metricas']['total_sptf'] ?? 0 ?></div>
                    <small class="text-muted">Servidores Temporários (Lei 8.745)</small>
                </div>
                <div class="fs-1 text-warning opacity-50"><i class="bi bi-clock-history"></i></div>
            </div>
        </div>
    </div>
</div>

<!-- Painel Informativo sobre Regras e Integridade -->
<div class="row g-4">
    <div class="col-lg-6">
        <div class="card card-comara h-100">
            <div class="card-comara-header">
                <span><i class="bi bi-shield-shaded me-2 text-primary"></i>Garantias de Sigilo e Segurança</span>
            </div>
            <div class="card-body">
                <ul class="list-unstyled mb-0">
                    <li class="mb-3 d-flex align-items-start">
                        <i class="bi bi-check-circle-fill text-success fs-5 me-2 mt-n1"></i>
                        <div>
                            <strong>Urna Eletrônica Cega:</strong>
                            <div class="text-muted small">O registro do voto e o controle do eleitor são gravados em tabelas desacopladas sem chaves de ligação, garantindo sigilo absoluto contra qualquer correlação matemática.</div>
                        </div>
                    </li>
                    <li class="mb-3 d-flex align-items-start">
                        <i class="bi bi-check-circle-fill text-success fs-5 me-2 mt-n1"></i>
                        <div>
                            <strong>Unicidade por CPF:</strong>
                            <div class="text-muted small">O sistema obriga a validação dos dígitos verificadores do CPF e impede estritamente qualquer tentativa de voto duplicado no ciclo eleitoral.</div>
                        </div>
                    </li>
                    <li class="d-flex align-items-start">
                        <i class="bi bi-check-circle-fill text-success fs-5 me-2 mt-n1"></i>
                        <div>
                            <strong>Auditoria e Integridade Perene:</strong>
                            <div class="text-muted small">Todas as ações (avaliações, seleções, exclusões e homologações) são assinadas com hash encadeado e armazenadas para retenção mínima de 5 anos.</div>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card card-comara h-100">
            <div class="card-comara-header">
                <span><i class="bi bi-sort-numeric-down me-2 text-warning"></i>Critérios Regimentais de Desempate</span>
            </div>
            <div class="card-body">
                <ol class="small text-muted ps-3 mb-0">
                    <li class="mb-2"><strong>1º Critério:</strong> Candidatos que não venceram nos dois anos anteriores ao pleito possuem prioridade sobre os vencedores desse período.</li>
                    <li class="mb-2"><strong>2º Critério:</strong> Em caso de persistência, prevalece o candidato mais antigo na instituição COMARA (data de apresentação/admissão).</li>
                    <li class="mb-2"><strong>3º Critério:</strong> Persistindo o empate, prevalece o candidato com maior tempo de serviço militar ou civil.</li>
                    <li class="mb-2"><strong>4º Critério:</strong> Maior nota na Avaliação do TACF lançada pelo setor de Educação Física.</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<?php require_once ROOT_PATH . '/views/layouts/footer.php'; ?>
