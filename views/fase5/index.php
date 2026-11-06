<?php
declare(strict_types=1);
require_once ROOT_PATH . '/views/layouts/header.php';

$homologado = $apuracao['homologado'];
$dadosHomol = $apuracao['dados_homologacao'];
$podeHomologar = count(array_filter($apuracao['categorias'],fn($c)=>!empty($c['vencedor'])))===4;
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="h4 fw-bold text-primary mb-1">Fase 5: Ordenação Presidencial e Homologação Oficial</h2>
        <div class="text-muted small">A Presidência define o 1º, 2º e 3º lugares, pode excluir finalistas e incluir integrantes ativos do mesmo grupo, independentemente das fases anteriores.</div>
    </div>
    <div>
        <?php if ($homologado): ?>
            <span class="badge bg-success p-2 fs-6"><i class="bi bi-lock-fill me-1"></i> Pleito Oficialmente Homologado</span>
        <?php else: ?>
            <span class="badge bg-warning text-dark p-2 fs-6"><i class="bi bi-clock-history me-1"></i> Aguardando Homologação</span>
        <?php endif; ?>
    </div>
</div>

<form method="GET" class="mb-3">
    <input type="hidden" name="r" value="fase5">
    <label for="consultaPleito">Consultar homologação anterior:</label>
    <select name="pleito_id" id="consultaPleito" required>
        <?php foreach ($historicos as $h): ?><option value="<?= (int)$h['id'] ?>" <?= (int)$pleito['id']===(int)$h['id']?'selected':'' ?>><?= (int)$h['ano'] ?> — <?= sanitize_output($h['titulo']) ?></option><?php endforeach; ?>
    </select><button class="btn btn-outline-primary btn-sm">Consultar</button>
    <a href="/index.php?r=fase5">Pleito atual</a>
</form>
<?php if (!empty($apuracao['legado'])): ?><div class="alert alert-info">Homologação anterior: vencedores e ata originais preservados, sem recálculo. Dados de identificação vêm do cadastro atual; pontuações históricas não são presumidas.</div><?php endif; ?>
<!-- Vencedores definidos; proclamados somente após homologação -->
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
                            <img src="/index.php?r=foto&saram=<?= rawurlencode((string)$vencedor['saram']) ?>&id=<?= $vencedor['candidato_id'] ?>" 
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
                            <div class="h4 fw-bold text-primary mb-0"><?= $vencedor['pontos_fase4'] ?? '—' ?></div>
                            <small class="text-muted text-uppercase fw-semibold" style="font-size: 0.7rem;">Pontos da Fase 4</small><div class="small">Votos da Fase 3: <?= $vencedor['total_votos'] ?? '—' ?></div>
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
                            <div class="small text-muted">Aguardando definição expressa da classificação pela Presidência.</div>
                        </div>
                    <?php endif; ?>
                    <div class="small mt-2"><?= sanitize_output($dadosCat['criterio_resultado'] ?? '') ?></div>
                    <?php if (!empty($dadosCat['minerva_registrado'])): ?><div class="small text-start mt-2"><strong>Fundamentação registrada:</strong> <?= sanitize_output($dadosCat['minerva_registrado']['justificativa']) ?><br>Autor (usuário): <?= (int)$dadosCat['minerva_registrado']['presidente_usuario_id'] ?> · <?= sanitize_output($dadosCat['minerva_registrado']['registrado_em']) ?></div><?php endif; ?>
                    <?php if (!$homologado): ?><div class="small text-warning-emphasis">Resultado provisório — não homologado.</div><?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Detalhamento da Apuração e Ranking por Categoria -->
<div class="alert alert-info small">
    <strong>Comparação das etapas:</strong> a classificação popular considera apenas os votos de todos os candidatos da categoria na Fase 3.
    A ordenação final da Fase 4 considera os pontos 5–3–1, os quatro critérios e os votos da Fase 3, como referência para a Presidência.
    Empatados compartilham posição (ex.: 1º, 1º, 3º). A decisão presidencial não altera essas posições.
    Posições ausentes em homologações anteriores são indicadas como não registradas.
</div>
<?php foreach (CATEGORIAS_PADRAO as $catKey => $catInfo): ?>
    <?php
    $dadosCat = $apuracao['categorias'][$catKey] ?? null;
    $ranking = $dadosCat['ranking'] ?? [];
    $requerMinerva = empty($dadosCat['decisao_presidencial']) && !$homologado;
    $editar=!$homologado && AuthManager::hasRole([PERFIL_PRESIDENTE,PERFIL_ADMIN]);
    $decisao=$dadosCat['decisao_presidencial']??null;
    $linhas=$ranking;
    if (!$homologado) {
        $linhas=array_values(array_column(array_merge($dadosCat['referencia_fase4']??[], $ranking, $dadosCat['excluidos']??[]),null,'candidato_id'));
    }
    $posicoes=[];
    if ($decisao) foreach ($ranking as $i=>$c) $posicoes[(int)$c['candidato_id']]=(string)($i+1);
    foreach ($dadosCat['excluidos']??[] as $c) $posicoes[(int)$c['candidato_id']]='excluir';
    $opcoes=$editar?Homologacao::opcoesCategoria((int)$pleito['id'],$catKey):[];
    $candsEmpatados = $dadosCat['candidatos_empatados'] ?? [];
    ?>
    <div class="card card-comara shadow-sm mb-4">
        <div class="card-comara-header">
            <span class="fs-5 text-primary fw-bold">Ordenação presidencial — <?= $catInfo['nome'] ?></span>
            <span class="badge bg-secondary"><?= count($ranking) ?> Candidatos na lista</span>
        </div>
        <div class="card-body p-0">
            
            <?php if ($editar): ?>
            <form class="ordenacao-fase5" method="POST" action="/index.php?r=fase5/ordenar" onsubmit="return confirm('Salvar a ordenação, inclusões e exclusões desta categoria?');">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="pleito_id" value="<?= (int)$pleito['id'] ?>">
                <input type="hidden" name="categoria" value="<?= $catKey ?>">
                <input type="hidden" name="versao" value="<?= Homologacao::versaoDecisao($decisao) ?>">
                <p class="p-3 mb-0">Defina a posição de cada candidato ou escolha <strong>Excluir nesta fase</strong>. Não há classificação automática. Use até três posições, sem repetição ou lacunas; ao menos o 1º lugar é obrigatório.</p>
            <?php endif; ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 70px;" class="text-center">Resultado da Fase 5</th>
                            <th style="width: 60px;">Foto</th>
                            <th>Candidato / SARAM</th>
                            <th>Posto / Especialidade</th>
                            <th>Setor</th>
                            <th class="text-center">Classificação popular — Fase 3</th>
                            <th class="text-center">Ordenação final — Fase 4</th>
                            <th>Critério 1 (Vitórias nos 2 anos anteriores)</th>
                            <th>Critério 2 (COMARA)</th>
                            <th>Critério 3 (Serviço)</th>
                            <th>Critério 4 (TACF)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($linhas as $pos => $cand): ?>
                            <tr class="<?= (int)($dadosCat['vencedor']['candidato_id']??0)===(int)$cand['candidato_id'] ? 'table-success fw-semibold' : '' ?>">
                                <td class="text-center">
                                    <?php if ($editar): ?>
                                        <input type="hidden" name="escolhas[<?= $pos ?>][candidato_id]" value="<?= (int)$cand['candidato_id'] ?>">
                                        <select class="form-select" style="min-width:160px" name="escolhas[<?= $pos ?>][posicao]" aria-label="Resultado da Fase 5 para <?= sanitize_output($cand['nome_guerra']) ?>" required>
                                            <option value="">Selecione...</option>
                                            <?php foreach (['1'=>'1º lugar','2'=>'2º lugar','3'=>'3º lugar','excluir'=>'Excluir nesta fase'] as $valor=>$rotulo): ?>
                                            <option value="<?= $valor ?>" <?= ($posicoes[(int)$cand['candidato_id']]??'')===(string)$valor?'selected':'' ?>><?= $rotulo ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php else: ?>
                                        <span class="badge bg-light text-dark border"><?= $homologado?($pos+1).'º':sanitize_output($posicoes[(int)$cand['candidato_id']]??'Pendente') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <img src="/index.php?r=foto&saram=<?= rawurlencode((string)$cand['saram']) ?>&id=<?= $cand['candidato_id'] ?>" class="cand-photo-sm" alt="Foto">
                                </td>
                                <td>
                                    <strong><?= sanitize_output($cand['posto']) ?> <?= sanitize_output($cand['nome_guerra']) ?></strong><br>
                                    <small class="text-muted"><?= sanitize_output($cand['nome']) ?></small><?php if (!empty($cand['incluido_presidencia'])): ?><div class="small text-primary">Incluído pela Presidência</div><?php endif; ?>
                                </td>
                                <td><?= sanitize_output($cand['quadro']) ?> - <?= sanitize_output($cand['especialidade']) ?></td>
                                <td><?= sanitize_output($cand['setor']) ?> (<?= sanitize_output($cand['divisao_sigla']) ?>)</td>
                                <td class="text-center">
                                    <?php if (isset($cand['participou_fase3']) && !$cand['participou_fase3']): ?>Não participou
                                    <?php elseif (isset($cand['posicao_fase3'])): ?>
                                        <strong><?= (int)$cand['posicao_fase3'] ?>º<?= !empty($cand['empate_fase3']) ? ' (empatado)' : '' ?></strong>
                                    <?php else: ?>Posição não registrada<?php endif; ?>
                                    <?php if (($cand['participou_fase3']??true)): ?><div class="small"><?= isset($cand['total_votos']) ? (int)$cand['total_votos'].' votos' : 'Votos não registrados' ?></div><?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if (isset($cand['participou_fase4']) && !$cand['participou_fase4']): ?>Não participou
                                    <?php elseif (isset($cand['posicao_fase4'])): ?>
                                        <strong><?= (int)$cand['posicao_fase4'] ?>º<?= !empty($cand['empate_fase4']) ? ' (empatado)' : '' ?></strong>
                                    <?php else: ?>Posição não registrada<?php endif; ?>
                                    <?php if (($cand['participou_fase4']??true)): ?><div class="small"><?= isset($cand['pontos_fase4']) ? (int)$cand['pontos_fase4'].' pontos' : 'Pontos não registrados' ?></div><?php endif; ?>
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
            <?php if ($editar): ?>
                <div class="p-3 border-top">
                    <h3 class="h6">Incluir integrante deste grupo</h3>
                    <p class="small">Qualquer integrante ativo do grupo pode ser escolhido, mesmo sem participação na Fase 1. Preencha apenas as inclusões desejadas e sua posição; deixe as demais linhas vazias.</p>
                    <?php for ($extra=0;$extra<3;$extra++): $indice=count($linhas)+$extra; ?>
                    <div class="row g-2 mb-2">
                        <div class="col-md-9"><select class="form-select" name="escolhas[<?= $indice ?>][candidato_id]" aria-label="Integrante adicional <?= $extra+1 ?> de <?= sanitize_output($catInfo['nome']) ?>">
                            <option value="">Não incluir</option>
                            <?php foreach ($opcoes as $op): if (in_array((int)$op['candidato_id'],array_map('intval',array_column($linhas,'candidato_id')),true)) continue; ?>
                            <option value="<?= (int)$op['candidato_id'] ?>"><?= sanitize_output($op['posto'].' '.$op['nome_guerra'].' — '.$op['nome'].' — '.$op['saram'].' — '.$op['setor']) ?></option>
                            <?php endforeach; ?>
                        </select></div>
                        <div class="col-md-3"><select class="form-select" name="escolhas[<?= $indice ?>][posicao]" aria-label="Posição do integrante adicional <?= $extra+1 ?>"><option value="">Posição...</option><option value="1">1º lugar</option><option value="2">2º lugar</option><option value="3">3º lugar</option></select></div>
                    </div>
                    <?php endfor; ?>
                    <label class="form-label" for="just_<?= $catKey ?>">Fundamentação da ordenação, inclusões e exclusões</label>
                    <textarea class="form-control mb-2" id="just_<?= $catKey ?>" name="justificativa" required rows="3"><?= sanitize_output($decisao['justificativa']??'') ?></textarea>
                    <label class="d-block mb-2"><input type="checkbox" name="confirmar" value="1" required> Confirmo esta decisão. Poderei revisá-la até homologar.</label>
                    <button class="btn btn-primary">Salvar ordenação desta categoria</button>
                </div>
            </form>
            <?php elseif ($decisao): ?><div class="p-3"><strong>Fundamentação:</strong> <?= sanitize_output($decisao['justificativa']) ?></div><?php endif; ?>
            <?php if ($homologado && !empty($dadosCat['excluidos'])): ?>
            <div class="p-3 border-top"><strong>Excluídos na Fase 5:</strong> <?php foreach ($dadosCat['excluidos'] as $ex): ?><?= sanitize_output($ex['posto'].' '.$ex['nome_guerra']) ?>; <?php endforeach; ?></div>
            <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>

<?php if (AuthManager::hasRole([PERFIL_PRESIDENTE, PERFIL_ADMIN])): ?>
<details class="card p-3 mb-4"><summary>Avaliações individuais da Fase 4 — consulta restrita à Presidência e ADMIN</summary>
<p class="small">Consulta identificada das ordenações e justificativas; não representa a votação secreta da Fase 3.</p>
<div class="table-responsive"><table class="table"><thead><tr><th>Avaliador</th><th>Categoria</th><th>Candidato</th><th>Posição</th><th>Justificativa</th></tr></thead><tbody>
<?php foreach ($avaliacoes as $a):
    $candidatoNome='Candidato #'.(int)$a['candidato_id'];
    foreach (array_merge($apuracao['categorias'][$a['categoria']]['referencia_fase4']??[], $apuracao['categorias'][$a['categoria']]['ranking']) as $c) if ((int)$c['candidato_id']===(int)$a['candidato_id']) $candidatoNome=$c['posto'].' '.$c['nome_guerra'];
?>
<tr><td><?= sanitize_output($a['avaliador_posto'].' '.$a['avaliador_nome']) ?></td><td><?= sanitize_output($a['categoria']) ?></td><td><?= sanitize_output($candidatoNome) ?></td><td><?= (int)$a['posicao'] ?>º</td><td><?= sanitize_output($a['justificativa']) ?></td></tr>
<?php endforeach; ?>
</tbody></table></div></details>
<?php endif; ?>

<div id="aviso-fase5-nao-salva" class="alert alert-warning" hidden>Há alterações não salvas. Salve a ordenação de cada categoria alterada antes de homologar.</div>
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
            <?php if (!$podeHomologar): ?><div class="alert alert-warning">Homologação bloqueada: defina um vencedor válido em cada uma das quatro categorias.</div><?php endif; ?>
            <form id="homologacao-fase5" method="POST" action="/index.php?r=fase5/homologar" onsubmit="return confirm('Homologar definitivamente os quatro vencedores? Não será possível alterar ou reiniciar esta eleição.');">
                <input type="hidden" name="pleito_id" value="<?= (int)$pleito['id'] ?>">
                <input type="hidden" name="versao" value="<?= Homologacao::versaoApuracao($apuracao) ?>">
                <label class="mb-3"><input type="checkbox" name="confirmar" value="1" required> Conferi a ordenação, as inclusões, exclusões e os quatro vencedores; confirmo a homologação definitiva.</label>
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
                    <button type="submit" class="btn btn-success btn-lg fw-bold px-4 shadow" <?= $podeHomologar?'':'disabled' ?>>
                        <i class="bi bi-shield-lock-fill me-2"></i> HOMOLOGAR E BLOQUEAR RESULTADOS
                    </button>
                </div>
            </form>
        <?php else: ?>
            <div class="alert alert-secondary mb-0">
                Aguardando assinatura da Ata de Homologação pelo Sr. Presidente da COMARA.
            </div>
        <?php endif; ?>

        <?php if ($homologado): ?>
        <!-- Links Diretos para os Relatórios Padronizados -->
        <hr class="my-4">
        <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <span class="small text-muted fw-bold">Documentos e Publicações Oficiais:</span>
            <div class="d-flex gap-2">
                <?php if (AuthManager::hasRole(PERFIL_ADMIN)): ?>
                    <a href="/index.php?r=relatorios/placa&amp;pleito_id=<?= (int)$pleito['id'] ?>" class="btn btn-warning fw-bold text-dark shadow-sm">
                        <i class="bi bi-trophy-fill me-1"></i> Placa Alusiva (Hall do Comando)
                    </a>
                <?php endif; ?>
                <a href="/index.php?r=relatorios/site&amp;pleito_id=<?= (int)$pleito['id'] ?>" class="btn btn-primary fw-bold shadow-sm">
                    <i class="bi bi-globe me-1"></i> Publicação Web (Site COMARA)
                </a>
                <a href="/index.php?r=relatorios/estatisticas" class="btn btn-outline-secondary fw-bold">
                    <i class="bi bi-file-earmark-bar-graph me-1"></i> Relatório Estatístico Completo
                </a>
            </div>
        </div>

        <?php endif; ?>
    </div>
</div>

<script src="/assets/js/fase5.js" defer></script>
<?php require_once ROOT_PATH . '/views/layouts/footer.php'; ?>
