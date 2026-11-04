<?php
declare(strict_types=1);
require_once ROOT_PATH . '/views/layouts/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="/index.php?r=fase5" class="btn btn-outline-secondary btn-sm mb-2">
            <i class="bi bi-arrow-left"></i> Voltar à Fase 5
        </a>
        <h3 class="h4 fw-bold text-primary mb-0">Publicação Oficial no Portal COMARA (www2.comara.intraer)</h3>
    </div>
    <div>
        <span class="badge bg-success p-2 fs-6"><i class="bi bi-calendar-check me-1"></i> Vigência: 01/Jan a 31/Dez de <?= $pleito['ano'] + 1 ?></span>
    </div>
</div>

<div class="card card-comara shadow-sm mb-4">
    <div class="card-comara-header">
        <span><i class="bi bi-code-slash me-2"></i>Módulo de Integração com o Portal Joomla da COMARA</span>
        <span class="badge bg-primary">Automático 365 Dias</span>
    </div>
    <div class="card-body">
        <p class="small text-muted">
            Este módulo gera o conteúdo responsivo a ser incorporado diretamente na página inicial do portal <code>www2.comara.intraer</code>. 
            O regulamento determina a manutenção ininterrupta da galeria dos Padrões eleitos durante todo o ano subsequente ao pleito.
        </p>

        <!-- Preview do Widget do Site Oficial -->
        <div class="border rounded p-4 bg-light shadow-sm">
            <div class="d-flex align-items-center justify-content-between border-bottom pb-3 mb-4">
                <div class="d-flex align-items-center">
                    <img src="/assets/img/comara_brasao.png" alt="COMARA" style="width: 50px; height: auto;" class="me-3">
                    <div>
                        <h4 class="mb-0 fw-bold text-primary text-uppercase">Padrões COMARA <?= $pleito['ano'] ?></h4>
                        <small class="text-muted">Galeria de Destaques Funcionais e Militares</small>
                    </div>
                </div>
                <span class="badge bg-primary">Gestão <?= $pleito['ano'] + 1 ?></span>
            </div>

            <div class="row g-3">
                <?php foreach (CATEGORIAS_PADRAO as $catKey => $catInfo): ?>
                    <?php
                    $dadosCat = $apuracao['categorias'][$catKey] ?? null;
                    $v = $dadosCat['vencedor'] ?? null;
                    ?>
                    <div class="col-md-6 col-lg-3">
                        <div class="card h-100 border-0 shadow-sm text-center p-3 bg-white">
                            <span class="badge bg-light text-primary border mb-2 text-uppercase fw-bold" style="font-size: 0.7rem;">
                                <?= $catInfo['nome'] ?>
                            </span>

                            <div class="my-2">
                                <?php if ($v): ?>
                                    <img src="/index.php?r=foto&saram=<?= $v['saram'] ?>&id=<?= $v['candidato_id'] ?>" 
                                         class="cand-photo mx-auto border-3 border-primary" alt="Foto">
                                <?php else: ?>
                                    <div class="cand-photo mx-auto d-flex align-items-center justify-content-center bg-light text-muted">
                                        Pendente
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if ($v): ?>
                                <h6 class="fw-bold text-primary mb-1 mt-1">
                                    <?= sanitize_output($v['posto']) ?> <?= sanitize_output($v['nome_guerra']) ?>
                                </h6>
                                <div class="small text-muted text-truncate mb-1"><?= sanitize_output($v['nome']) ?></div>
                                <div class="badge bg-secondary-subtle text-secondary mb-2">
                                    <?= sanitize_output($v['setor']) ?> • <?= sanitize_output($v['divisao_sigla']) ?>
                                </div>
                                <div class="small text-muted" style="font-size: 0.72rem;">
                                    <?php if (isset($v['pontos_fase4'])): ?>
                                        <?= (int)$v['pontos_fase4'] ?> pontos no Estado-Maior • <?= (int)$v['total_votos'] ?> votos do efetivo
                                    <?php else: ?>
                                        Eleito com <?= (int)$v['total_votos'] ?> votos populares
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Código para Incorporação (Embed Snippet) -->
        <div class="mt-4">
            <label class="form-label fw-bold small text-muted">Código HTML para Incorporação no Portal Joomla / INTRAER:</label>
            <div class="input-group">
                <textarea class="form-control font-monospace small bg-white" rows="2" readonly id="embedCode">&lt;iframe src="http://votacao.comara.intraer/index.php?r=relatorios/site" width="100%" height="450" frameborder="0" style="border:none; overflow:hidden;" scrolling="no"&gt;&lt;/iframe&gt;</textarea>
                <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText(document.getElementById('embedCode').value); alert('Código copiado para a área de transferência!');">
                    <i class="bi bi-clipboard"></i> Copiar Código
                </button>
            </div>
        </div>

    </div>
</div>

<?php require_once ROOT_PATH . '/views/layouts/footer.php'; ?>
