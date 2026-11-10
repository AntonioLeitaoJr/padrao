<?php
declare(strict_types=1);
require_once ROOT_PATH . '/views/layouts/header.php';
?>

<div class="row justify-content-center my-5">
    <div class="col-md-7 col-lg-5">
        <section class="card card-comara shadow-sm border" aria-labelledby="primeiroAcessoTitulo">
            <div class="card-header bg-primary text-white py-3">
                <h2 class="h5 mb-0" id="primeiroAcessoTitulo"><i class="bi bi-shield-lock me-2"></i>Primeiro acesso — senha pessoal obrigatória</h2>
            </div>
            <div class="card-body p-4">
                <p>Para proteger a identidade do eleitor, é necessário substituir a senha inicial por uma senha pessoal antes de acessar as fases da votação.</p>
                <p class="text-muted small">O mesmo cadastro continuará disponível pelo SARAM (militares) ou matrícula/CPF (servidores civis). Essa alteração não cria outra conta.</p>
                <form method="POST" action="/index.php?r=alterar_senha">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="acao" value="alterar">
                    <div class="mb-3">
                        <label for="senhaAtualInicial" class="form-label fw-semibold">Senha atual</label>
                        <input type="password" class="form-control" id="senhaAtualInicial" name="senha_atual" autocomplete="current-password" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label for="novaSenhaInicial" class="form-label fw-semibold">Nova senha pessoal (mínimo 6 caracteres)</label>
                        <input type="password" class="form-control" id="novaSenhaInicial" name="nova_senha" autocomplete="new-password" minlength="6" required>
                    </div>
                    <div class="mb-3">
                        <label for="confirmaSenhaInicial" class="form-label fw-semibold">Confirme sua nova senha</label>
                        <input type="password" class="form-control" id="confirmaSenhaInicial" name="confirma_senha" autocomplete="new-password" minlength="6" required>
                    </div>
                    <div class="d-grid">
                        <button class="btn btn-primary fw-bold" type="submit">Definir senha e continuar</button>
                    </div>
                </form>
                <div class="mt-3 small text-muted">Não é permitido reutilizar a senha inicial nem manter a senha anterior. Se precisar de ajuda, contate o Administrador/STI.</div>
                <div class="mt-3 text-end"><a href="/index.php?r=logout" class="link-secondary">Sair do sistema</a></div>
            </div>
        </section>
    </div>
</div>

<?php require_once ROOT_PATH . '/views/layouts/footer.php'; ?>
