<?php
declare(strict_types=1);
?>
</main>

<footer class="comara-footer">
    <div class="container-fluid px-4">
        <div class="row align-items-center">
            <div class="col-md-6 text-center text-md-start mb-2 mb-md-0">
                <strong>COMARA — Comissão de Aeroportos da Região Amazônica</strong><br>
                <small class="text-muted">Avenida Pedro Álvares Cabral, s/n - Souza, Belém - PA | Diretoria de Infraestrutura da Aeronáutica (DIRINFRA)</small>
            </div>
            <div class="col-md-6 text-center text-md-end">
                <small class="text-muted d-block d-md-inline mb-1 mb-md-0">Sistema Eletrônico de Eleição dos Padrões • Versão <?= APP_VERSION ?></small>
                <span class="mx-2 d-none d-md-inline text-muted">•</span>
                <a href="#" class="text-decoration-none text-muted small" data-bs-toggle="modal" data-bs-target="#modalLgpd">
                    <i class="bi bi-shield-check text-success me-1"></i>Privacidade &amp; LGPD
                </a>
            </div>
        </div>
    </div>
</footer>

<!-- Modal Institucional LGPD e Governança de Dados -->
<div class="modal fade" id="modalLgpd" tabindex="-1" aria-labelledby="modalLgpdLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalLgpdLabel">
                    <i class="bi bi-shield-check me-2"></i>Aviso de Privacidade e Conformidade LGPD
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body p-4 text-dark" style="font-size: 0.93rem; line-height: 1.6;">
                <div class="alert alert-primary d-flex align-items-center mb-4" role="alert">
                    <i class="bi bi-info-circle-fill fs-3 me-3 text-primary"></i>
                    <div>
                        <strong>Governança e Proteção de Dados (Lei nº 13.709/2018 - LGPD)</strong><br>
                        Este sistema foi desenvolvido segundo os preceitos de <em>Privacy by Design</em> e <em>Privacy by Default</em>, assegurando transparência, inviolabilidade do voto e integridade no tratamento de dados pessoais no âmbito da COMARA / COMAER.
                    </div>
                </div>

                <h6 class="fw-bold text-primary border-bottom pb-1 mb-2">
                    <i class="bi bi-bank me-2"></i>1. Controlador e Base Legal do Tratamento
                </h6>
                <p>
                    O tratamento de dados cadastrais e funcionais (SARAM, Nome, Posto/Graduação, Quadro, Especialidade, Setor e CPF) é conduzido pela <strong>Comissão de Aeroportos da Região Amazônica (COMARA)</strong> e pelo <strong>Comando da Aeronáutica (COMAER)</strong> com fundamento no <strong>Art. 7º, inciso III</strong> e no <strong>Art. 23 da Lei nº 13.709/2018 (LGPD)</strong> — tratamento realizado pela administração pública para cumprimento de obrigação legal e execução de políticas públicas regimentais de valorização do mérito institucional (eleição dos Padrões).
                </p>

                <h6 class="fw-bold text-primary border-bottom pb-1 mb-2 mt-4">
                    <i class="bi bi-diagram-3 me-2"></i>2. Princípio da Minimização e Finalidade (Art. 6º, III)
                </h6>
                <p>
                    A plataforma processa estritamente os dados essenciais para:
                </p>
                <ul class="mb-3">
                    <li>Identificação funcional inequívoca do eleitor e dos candidatos elegíveis;</li>
                    <li>Lançamento de notas pelo Chefe Imediato (Fase 1) e validação pelo Chefe de Divisão (Fase 2);</li>
                    <li>Garantia de quórum e controle de voto único no pleito democrático (Fase 3).</li>
                </ul>
                <p class="text-muted small">
                    <em>Nota: Nenhum dado de natureza sensível (conhecimento político, convicção religiosa, dados de saúde, genéticos ou filiação sindical) é coletado ou admitido na aplicação.</em>
                </p>

                <h6 class="fw-bold text-primary border-bottom pb-1 mb-2 mt-4">
                    <i class="bi bi-safe me-2"></i>3. Anonimização Criptográfica e Inviolabilidade do Voto
                </h6>
                <p>
                    Em consonância com as garantias do sigilo eleitoral e o Art. 12 da LGPD, a urna eletrônica utiliza <strong>desacoplamento criptográfico de via única</strong>:
                </p>
                <div class="bg-light border rounded p-3 mb-3">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <span class="badge bg-secondary mb-1">Registro de Comparecimento</span>
                            <div class="small">
                                O CPF do eleitor é transformado em <code>HASH SHA-256</code> irreversível na tabela <code>eleitores_ciclo</code>, registrando apenas que o militar/servidor compareceu à eleição para emissão do comprovante digital de votação.
                            </div>
                        </div>
                        <div class="col-md-6 border-start ps-md-3">
                            <span class="badge bg-success mb-1">Cédula Anonimizada</span>
                            <div class="small">
                                Os votos são gravados na tabela <code>urna_votos</code> <strong>sem qualquer vínculo</strong> de identificação, usuário, IP ou chave estrangeira com o eleitor. Marcas temporais são arredondadas para inviabilizar correlação cronológica.
                            </div>
                        </div>
                    </div>
                </div>

                <h6 class="fw-bold text-primary border-bottom pb-1 mb-2 mt-4">
                    <i class="bi bi-shield-lock me-2"></i>4. Segurança da Informação e Privacidade por Padrão
                </h6>
                <ul class="mb-3">
                    <li><strong>Credenciais Criptografadas:</strong> As senhas são armazenadas exclusivamente utilizando funções criptográficas de derivação com <code>BCRYPT</code> e salt dinâmico individual.</li>
                    <li><strong>Mascaramento Visual de Dados:</strong> Visualizações administrativas de documentos (CPF) aplicam ofuscação (<code>***.XXX.XXX-**</code>) para mitigar exposições involuntárias.</li>
                    <li><strong>Rastreabilidade de Auditoria:</strong> Ações administrativas críticas de transição de fases, justificativas e delegações são registradas em logs de auditoria imutáveis.</li>
                </ul>

                <div class="card bg-light border-0 p-3 mt-4 text-center">
                    <span class="text-muted small">
                        <i class="bi bi-laptop me-1 text-primary"></i>
                        <strong>Sistema Eletrônico de Eleição dos Padrões</strong> - Sistema desenvolvido pela equipe da STI da COMARA
                    </span>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">
                    <i class="bi bi-check2-circle me-1"></i>Entendido e Ciente
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap 5 Bundle JS local (necessario para modais, menus e popovers na intranet) -->
<script src="/assets/vendor/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
<!-- Scripts Customizados COMARA -->
<script src="/assets/js/main.js"></script>
</body>
</html>
