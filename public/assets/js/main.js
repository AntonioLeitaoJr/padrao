/**
 * Scripts Interativos do Sistema de Votação dos Padrões COMARA
 */

document.addEventListener('DOMContentLoaded', function () {

    // 1. MÁSCARA E VALIDAÇÃO DE CPF (URNA FASE 3)
    const cpfInput = document.getElementById('cpfVotacao');
    if (cpfInput) {
        cpfInput.addEventListener('input', function (e) {
            let v = e.target.value.replace(/\D/g, '');
            if (v.length > 11) v = v.substring(0, 11);
            if (v.length > 9) {
                v = v.replace(/(\d{3})(\d{3})(\d{3})(\d{1,2})/, '$1.$2.$3-$4');
            } else if (v.length > 6) {
                v = v.replace(/(\d{3})(\d{3})(\d{1,3})/, '$1.$2.$3');
            } else if (v.length > 3) {
                v = v.replace(/(\d{3})(\d{1,3})/, '$1.$2');
            }
            e.target.value = v;
        });
    }

    // Fase 2: um selecionado por grupo com candidatos; zero em grupos vazios.
    const formFase2 = document.getElementById('formFase2');
    if (formFase2) {
        const atualizar = () => {
            let valido = true;
            ['graduado', 'praca', 'sppf', 'sptf'].forEach(cat => {
                const radios = formFase2.querySelectorAll(`input[name="selecao[${cat}]"]`);
                const total = Array.from(radios).filter(r => r.checked).length;
                const esperado = radios.length ? 1 : 0;
                const badge = document.getElementById(`badge-count-${cat}`);
                badge.textContent = esperado ? `${total} / 1 selecionado` : 'Sem candidatos';
                badge.className = total === esperado ? 'badge bg-success fs-6' : 'badge bg-warning text-dark fs-6';
                if (total !== esperado) valido = false;
            });
            document.getElementById('btnSalvarFase2').disabled = !valido || formFase2.dataset.editavel !== '1';
        };
        formFase2.addEventListener('change', atualizar);
        atualizar();
    }
    const fichaFase2 = document.getElementById('fichaFase2');
    if (fichaFase2) fichaFase2.addEventListener('show.bs.modal', event => {
        const botao = event.relatedTarget;
        document.getElementById('fichaFase2Nome').textContent = botao.dataset.nome;
        document.getElementById('fichaFase2Posto').textContent = botao.dataset.posto;
        document.getElementById('fichaFase2Setor').textContent = botao.dataset.setor;
        document.getElementById('fichaFase2Foto').src = botao.dataset.foto;
    });

    // 3. SELEÇÃO VISUAL NA URNA (FASE 3)
    const urnaForm = document.getElementById('formUrna');
    if (urnaForm) {
        urnaForm.querySelectorAll('.urna-card-cand').forEach(card => {
            card.addEventListener('click', function () {
                const radio = this.querySelector('input[type="radio"]');
                if (radio) {
                    radio.checked = true;
                    const cat = radio.name;
                    urnaForm.querySelectorAll(`input[name="${cat}"]`).forEach(r => {
                        const parent = r.closest('.urna-card-cand');
                        if (parent) parent.classList.remove('selected');
                    });
                    this.classList.add('selected');
                }
            });
        });
    }

    // 4. PREENCHIMENTO RÁPIDO DO MODAL DE TACF
    const modalTacf = document.getElementById('modalTacf');
    if (modalTacf) {
        modalTacf.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const candId = button.getAttribute('data-candidato-id');
            const candNome = button.getAttribute('data-candidato-nome');
            const candNota = button.getAttribute('data-candidato-nota');

            document.getElementById('tacf_candidato_id').value = candId;
            document.getElementById('tacf_candidato_nome').textContent = candNome;
            document.getElementById('tacf_nota').value = candNota || '5.00';
        });
    }

    // 5. MODAL DE INABILITAÇÃO FASE 4
    const modalExclusao = document.getElementById('modalExclusao');
    if (modalExclusao) {
        modalExclusao.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const candId = button.getAttribute('data-candidato-id');
            const candNome = button.getAttribute('data-candidato-nome');

            document.getElementById('excluir_candidato_id').value = candId;
            document.getElementById('excluir_candidato_nome').textContent = candNome;
        });
    }

    // 6. MODAL DE DELEGAÇÃO DE OFICIAL EM MISSÃO (FASE 1)
    const modalDelegar = document.getElementById('modalDelegarOficial');
    if (modalDelegar) {
        modalDelegar.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            if (!button) return;
            const candId = button.getAttribute('data-candidato-id');
            const candNome = button.getAttribute('data-candidato-nome');
            const candSub = button.getAttribute('data-candidato-sub');
            const motivo = button.getAttribute('data-delegacao-motivo') || '';
            const delegadoId = button.getAttribute('data-delegado-id') || '';

            const elId = document.getElementById('delegar_candidato_id');
            const elNome = document.getElementById('delegar_candidato_nome');
            const elSub = document.getElementById('delegar_candidato_sub');
            const elMotivo = document.getElementById('delegar_motivo');
            const elOficial = document.getElementById('delegar_oficial_id');

            if (elId) elId.value = candId || '';
            if (elNome) elNome.textContent = candNome || '--';
            if (elSub) elSub.textContent = candSub || '';
            if (elMotivo) elMotivo.value = motivo;
            if (elOficial) elOficial.value = delegadoId || '';
        });
    }

    // 7. INICIALIZAÇÃO DE POPOVERS E TOOLTIPS (BOOTSTRAP 5)
    const popoverTriggerList = document.querySelectorAll('[data-bs-toggle="popover"]');
    [...popoverTriggerList].forEach(el => {
        new bootstrap.Popover(el, {
            html: true,
            sanitize: false
        });
    });

    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    [...tooltipTriggerList].forEach(el => new bootstrap.Tooltip(el));
});
