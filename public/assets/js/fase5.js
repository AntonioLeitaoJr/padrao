'use strict';
// A homologação deve considerar somente decisões salvas e conferidas.
document.addEventListener('DOMContentLoaded', () => {
    const homologacao = document.getElementById('homologacao-fase5');
    if (!homologacao) return;
    let alterado = false;
    const marcar = () => {
        alterado = true;
        document.getElementById('aviso-fase5-nao-salva').hidden = false;
        homologacao.querySelector('button[type="submit"]').disabled = true;
    };
    document.querySelectorAll('.ordenacao-fase5').forEach(form => {
        form.addEventListener('input', marcar);
        form.addEventListener('change', marcar);
    });
    homologacao.addEventListener('submit', event => {
        if (alterado) event.preventDefault();
    });
});
