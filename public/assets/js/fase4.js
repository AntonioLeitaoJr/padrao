'use strict';
(() => {
    const form = document.getElementById('formOrdenacao');
    if (!form) return;
    const segmenter = typeof Intl.Segmenter === 'function' ? new Intl.Segmenter('pt-BR', {granularity: 'grapheme'}) : null;
    const count = text => segmenter ? [...segmenter.segment(text.trim())].length : Array.from(text.trim().normalize('NFC')).length;
    form.querySelectorAll('.justificativa-fase4').forEach(input => {
        const validate = () => {
            const n = count(input.value);
            const label = document.getElementById(input.getAttribute('aria-describedby'));
            label.textContent = `${n}/150 caracteres`;
            label.classList.toggle('text-danger', n > 150);
            input.setCustomValidity(n === 0 ? 'Informe uma justificativa para este candidato.' : n > 150 ? 'Use no máximo 150 caracteres.' : '');
        };
        input.addEventListener('input', validate); validate();
    });
    form.querySelectorAll('[data-categoria]').forEach(section => {
        const selects = [...section.querySelectorAll('select')];
        const validate = () => selects.forEach(select => {
            const repeated = select.value && selects.filter(x => x.value === select.value).length > 1;
            select.setCustomValidity(repeated ? 'Cada posição deve ser atribuída a um único candidato.' : '');
        });
        selects.forEach(select => select.addEventListener('change', validate)); validate();
    });
    form.addEventListener('submit', event => {
        if (!form.reportValidity() || !window.confirm('Confirmar definitivamente todas as ordenações e justificativas? Após a gravação não será possível alterar.')) event.preventDefault();
    });
})();
