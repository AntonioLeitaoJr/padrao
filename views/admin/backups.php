<?php
declare(strict_types=1);
require_once ROOT_PATH . '/views/layouts/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="h4 fw-bold text-primary mb-1">Rotinas de Backup e Contingência</h2>
        <div class="text-muted small">Backups incrementais diários e cópias integrais semanais para garantia de disponibilidade e imutabilidade.</div>
    </div>
    <div class="d-flex gap-2">
        <form method="POST" action="/index.php?r=admin/backups" class="d-inline">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="acao" value="gerar">
            <input type="hidden" name="tipo" value="diario">
            <button type="submit" class="btn btn-outline-primary fw-bold shadow-sm">
                <i class="bi bi-clock-history me-1"></i> Executar Backup Diário
            </button>
        </form>

        <form method="POST" action="/index.php?r=admin/backups" class="d-inline">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="acao" value="gerar">
            <input type="hidden" name="tipo" value="semanal">
            <button type="submit" class="btn btn-primary fw-bold shadow-sm">
                <i class="bi bi-cloud-arrow-down-fill me-1"></i> Executar Backup Semanal Completo
            </button>
        </form>
    </div>
</div>

<div class="card card-comara shadow-sm mb-4">
    <div class="card-comara-header">
        <span>Histórico de Arquivos de Backup Gerados</span>
        <span class="badge bg-secondary"><?= count($listaBackups) ?> arquivo(s)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Tipo de Rotina</th>
                        <th>Nome do Arquivo</th>
                        <th>Tamanho</th>
                        <th>Data e Hora de Geração</th>
                        <th class="text-end">Status / Arquivamento</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($listaBackups)): ?>
                        <tr><td colspan="5" class="text-center p-4 text-muted">Nenhum arquivo de backup gerado até o momento. Utilize os botões acima para executar.</td></tr>
                    <?php else: ?>
                        <?php foreach ($listaBackups as $b): ?>
                            <tr>
                                <td>
                                    <span class="badge <?= $b['tipo'] === 'semanal' ? 'bg-primary' : 'bg-secondary' ?> text-uppercase">
                                        <?= $b['tipo'] ?>
                                    </span>
                                </td>
                                <td><code><?= sanitize_output($b['nome']) ?></code></td>
                                <td><span class="badge bg-light text-dark border"><?= $b['tamanho'] ?></span></td>
                                <td><?= $b['data_hora'] ?></td>
                                <td class="text-end">
                                    <span class="text-success small fw-semibold"><i class="bi bi-shield-check"></i> Seguro no Storage</span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>


<!-- Zeramento operacional controlado: nunca apaga homologações ou a auditoria -->
<div class="card border-danger mb-4">
    <div class="card-header bg-danger-subtle text-danger fw-bold">
        <i class="bi bi-exclamation-octagon me-2"></i> Administração avançada — zerar a base operacional
    </div>
    <div class="card-body">
        <p class="small mb-2">Apaga cadastros do efetivo, contas não administrativas, chefias vinculadas, avaliações,
           votos, seleções e decisões não homologadas. Preserva a estrutura SQL, as divisões regimentais,
           os backups, as contas ADMIN e o histórico de auditoria. Mantém um pleito de referência na Fase 1,
           sem cronograma, para permitir nova configuração.</p>
        <div class="alert alert-warning py-2 small">
            <strong>Operação extraordinária.</strong> Não deve ser executada durante votação ou com usuários no sistema.
            A STI precisa suspender acessos e integrações, e realizar procedimento de manutenção.
            O sistema gera backup integral automaticamente antes da limpeza e bloqueia a operação
            se houver qualquer pleito homologado.
        </div>

        <?php if (!$resetHabilitado): ?>
            <p class="mb-0 text-muted small"><i class="bi bi-lock-fill me-1"></i>
            Função desativada. A STI pode liberá-la temporariamente usando
            <code>ALLOW_DATABASE_RESET=true</code> no ambiente do servidor.</p>
        <?php elseif ($erroResumoReset): ?>
            <div class="alert alert-danger"><?= sanitize_output($erroResumoReset) ?></div>
        <?php elseif ($resumoReset && $resumoReset['homologados'] > 0): ?>
            <div class="alert alert-danger mb-0"><strong>Bloqueado:</strong>
            há pleito homologado. A interface administrativa não permite apagar resultados oficiais.</div>
        <?php elseif ($resumoReset && $resumoReset['admins_ativos'] < 1): ?>
            <div class="alert alert-danger mb-0">Nenhuma conta ADMIN ativa com senha local para recuperação.</div>
        <?php elseif (!empty($preparoReset)): ?>
            <div class="alert alert-danger">
                <strong>Última confirmação:</strong> esta ação elimina registros operacionais.
                A confirmação vale por cinco minutos e apenas para o administrador atual.
            </div>
            <div class="row g-2 small mb-3">
                <div class="col-sm-4"><strong>Efetivo:</strong> <?= (int)($resumoReset['contagens']['efetivo'] ?? 0) ?></div>
                <div class="col-sm-4"><strong>Contas:</strong> <?= (int)($resumoReset['contagens']['usuarios'] ?? 0) ?></div>
                <div class="col-sm-4"><strong>Votos:</strong> <?= (int)($resumoReset['contagens']['urna_votos'] ?? 0) ?></div>
            </div>
            <form method="POST" action="/index.php?r=admin/zerar_banco"
                  onsubmit="return confirm('CONFIRMA o zeramento da base operacional? O sistema excluirá os cadastros e votos não homologados.');">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="acao" value="confirmar">
                <input type="hidden" name="token_reset" value="<?= sanitize_output($preparoReset['token']) ?>">
                <div class="mb-3">
                    <label class="form-label fw-semibold" for="resetSenhaAdmin">Sua senha atual de ADMIN</label>
                    <input class="form-control" id="resetSenhaAdmin" type="password" name="senha_admin"
                           autocomplete="current-password" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold" for="resetFrase">Digite exatamente: <code>ZERAR BASE COMARA</code></label>
                    <input class="form-control" id="resetFrase" type="text" name="confirmacao"
                           autocomplete="off" required>
                </div>
                <label class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="ciente" value="1" required>
                    <span class="form-check-label">Confirmo a operação extraordinária e que o servidor está em manutenção.</span>
                </label>
                <button type="submit" class="btn btn-danger fw-bold">
                    <i class="bi bi-trash3 me-1"></i> Confirmar zeramento operacional
                </button>
            </form>
        <?php else: ?>
            <p class="small">O primeiro passo apenas prepara uma confirmação temporária.
            Não apaga nenhum registro.</p>
            <form method="POST" action="/index.php?r=admin/zerar_banco">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="acao" value="preparar">
                <button type="submit" class="btn btn-outline-danger fw-bold">Preparar zeramento da base</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- Guia de Agendamento Automático no aaPanel (Debian 13) -->
<div class="card card-comara shadow-sm">
    <div class="card-comara-header bg-light">
        <span><i class="bi bi-terminal me-2"></i>Instruções para Agendamento no aaPanel (Cron Tab)</span>
    </div>
    <div class="card-body small text-muted">
        <p class="mb-2">No painel <strong>aaPanel -> Cron</strong> do servidor Debian 13, configure as duas tarefas automatizadas abaixo:</p>
        
        <div class="p-3 bg-dark text-light rounded font-monospace mb-3">
            # Backup Diário às 23:00 (Segunda a Domingo)<br>
            0 23 * * * /bin/bash /www/wwwroot/votacao.comara.intraer/scripts/backup_diario.sh > /dev/null 2>&1<br><br>
            # Backup Semanal Completo aos Domingos às 02:00<br>
            0 2 * * 0 /bin/bash /www/wwwroot/votacao.comara.intraer/scripts/backup_semanal.sh > /dev/null 2>&1
        </div>

        <p class="mb-0">Os scripts shell estão disponíveis na pasta <code>scripts/</code> do projeto com permissão de execução <code>chmod +x</code>.</p>
    </div>
</div>

<?php require_once ROOT_PATH . '/views/layouts/footer.php'; ?>
