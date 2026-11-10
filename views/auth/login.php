<?php
declare(strict_types=1);
require_once ROOT_PATH . '/views/layouts/header.php';
?>

<div class="row justify-content-center my-5">
    <div class="col-md-5 col-lg-4">
        <div class="card card-comara shadow-sm border">
            <div class="card-comara-header text-center justify-content-center py-4 bg-light border-bottom">
                <div class="text-center">
                    <img src="/assets/img/comara_brasao.png" alt="COMARA" style="width: 80px; height: auto;" class="mb-2">
                    <h5 class="mb-1 text-primary fw-bold">Eleição dos Padrões</h5>
                    <div class="text-muted small">Autenticação do Efetivo COMARA</div>
                </div>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="/index.php?r=login">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                    <div class="mb-3">
                        <label for="login" class="form-label fw-semibold">SARAM (militar) ou matrícula / CPF (civil)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-person-fill text-muted"></i></span>
                            <input type="text" class="form-control" id="login" name="login" required autofocus placeholder="Digite seu SARAM, matrícula ou CPF">
                        </div>
                        <div class="form-text">Para servidores civis, informe preferencialmente sua matrícula funcional. Caso não a saiba, utilize o CPF.</div>
                    </div>

                    <div class="mb-4">
                        <label for="senha" class="form-label fw-semibold">Senha</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-shield-lock-fill text-muted"></i></span>
                            <input type="password" class="form-control" id="senha" name="senha" required placeholder="Digite sua senha">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold shadow-sm">
                        <i class="bi bi-box-arrow-in-right me-2"></i> Entrar
                    </button>
                </form>

                <div class="text-center mt-3 pt-3 border-top">
                    <button type="button" 
                            id="btnSuporteSenha"
                            class="btn btn-sm btn-link text-decoration-none text-muted mb-1"
                            data-bs-toggle="popover" 
                            data-bs-placement="bottom"
                            data-bs-trigger="focus"
                            data-bs-html="true"
                            data-bs-custom-class="popover-comara shadow"
                            title="&lt;div class='fw-bold text-primary'&gt;&lt;i class='bi bi-shield-lock-fill me-1'&gt;&lt;/i&gt; Suporte de Senha &amp; Primeiro Acesso&lt;/div&gt;"
                            data-bs-content="&lt;div class='p-1 text-dark'&gt;
                                &lt;p class='small mb-2'&gt;
                                    &lt;strong&gt;Esqueceu sua senha ou precisa redefinir?&lt;/strong&gt;&lt;br&gt;
                                    Por motivos de segurança, a redefinição de senhas é realizada &lt;strong&gt;exclusivamente pelo Administrador do Sistema&lt;/strong&gt;. Favor entrar em contato com o &lt;strong&gt;Setor de TI (STI / COMARA)&lt;/strong&gt; para que seu acesso seja restabelecido.
                                &lt;/p&gt;
                                &lt;div class='alert alert-warning py-2 px-2 small mb-0 border-0 bg-warning-subtle text-dark'&gt;
                                    &lt;i class='bi bi-exclamation-triangle-fill text-warning me-1'&gt;&lt;/i&gt;
                                    &lt;strong&gt;Atenção ao Primeiro Acesso:&lt;/strong&gt; No &lt;strong&gt;primeiro acesso&lt;/strong&gt; com a senha inicial institucional, será &lt;strong&gt;obrigatória a definição de uma senha pessoal&lt;/strong&gt; antes de entrar nas fases do pleito.
                                &lt;/div&gt;
                            &lt;/div&gt;">
                        <i class="bi bi-question-circle-fill text-primary me-1"></i> Esqueceu a senha ou primeiro acesso?
                    </button>
                    <div>
                        <a href="#" class="btn btn-sm btn-link text-decoration-none text-muted small" data-bs-toggle="modal" data-bs-target="#modalLgpd">
                            <i class="bi bi-shield-check text-success me-1"></i> Aviso de Privacidade &amp; Conformidade LGPD
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl, {
            html: true,
            sanitize: false
        });
    });
});
</script>

<?php require_once ROOT_PATH . '/views/layouts/footer.php'; ?>
