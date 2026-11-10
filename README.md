# Sistema de Eleição dos Padrões COMARA

**Comissão de Aeroportos da Região Amazônica (COMARA)**  
Sistema Web Seguro para Condução do Processo de Escolha dos Militares e Servidores Civis Padrão.

---

## 📌 Visão Geral do Sistema

O sistema informatiza todo o rito regimental da COMARA para seleção dos destaques anuais nas 4 categorias oficiais:
1. **Graduado Padrão** (Suboficiais e Sargentos)
2. **Praça Padrão** (Cabos e Soldados)
3. **Servidor Público Federal Permanente (SPPF)**
4. **Servidor Público Temporário / Terceirizado (SPTF)**

O processo é organizado em cinco fases:

- **Fase 1 — Avaliação e TACF:** Tenentes e Capitães avaliam os candidatos; o Setor de Educação Física lança o TACF.
- **Fase 2 — Seleção pelas chefias:** cada uma das cinco divisões (DPC, DA, DL, DE e DS) e a representação conjunta de ACI, AGOV, AINT, AJUR, APOG, SCS e SIJ escolhe **um candidato por categoria disponível** entre os 20% mais bem pontuados de sua unidade.
- **Fase 3 — Votação geral:** o efetivo vota nos selecionados da Fase 2, com até seis candidatos por categoria, conforme a disponibilidade de cada unidade.
- **Fase 4 — Ordenação individual pelo Estado-Maior:** Coronéis, Tenentes-Coronéis e Majores ordenam até três finalistas por categoria, com justificativas e pesos 5–3–1.
- **Fase 5 — Ordenação presidencial, inclusão, exclusão e homologação**, com decisão expressa em cada categoria.

### Regras da Fase 2

O corte de 20% é arredondado para cima e inclui pelo menos dois candidatos quando disponíveis. Havendo apenas um, ele fica disponível; sem candidatos, o grupo fica sem representante. Empates no corte são resolvidos, nesta ordem, pela preferência a quem não venceu nos dois anos anteriores ao pleito, maior antiguidade na COMARA, maior tempo de serviço militar ou civil e maior nota do TACF. Persistindo o empate após os quatro critérios, todos os empatados na última vaga entram.

O representante das sete assessorias e seções é o chefe militar mais antigo entre elas que não acumule chefia de divisão. Se necessário, a busca continua pelos seguintes na antiguidade. A escolha depende do cadastro de chefias, não apenas da lotação.

O clique no nome exibe foto, nome de guerra, setor e posto/graduação; para civis, **CIVIL SPPF** ou **CIVIL SPTF**. A seleção permanece nos controles existentes. O painel geral apresenta quatro critérios; a ordenação final da Fase 5 cabe à Presidência.

Administrador e RH registram em **Vencedores anteriores** um vencedor por categoria e ano dos dois anos anteriores ao pleito ativo (2024 e 2025 para 2026). São vencedores finais, não todos os indicados. Consulte as regras completas, os dados necessários e a migração em [Atualização da Fase 2](docs/ATUALIZACAO_FASE2.md).

---

## 🚀 Funcionalidades Principais

- **Autenticação Flexível e Segura**:
  - Militares acessam pelo **SARAM**; civis (SPPF/SPTF) podem utilizar **matrícula funcional** (identificador preferencial divulgado) **ou CPF** com ou sem pontuação, na mesma conta. Identificadores ambíguos são recusados sem vinculação arbitrária.
  - Autenticação corporativa integrada ao **Active Directory / LDAP da INTRAER** com fallback local seguro para contingência.
  - Troca de senha **obrigatória antes do uso das fases** para contas locais que ainda tenham a senha inicial, inclusive após reset pela STI. Senhas alteradas anteriormente não são solicitadas novamente; acessos validados exclusivamente no AD seguem a política corporativa. A nova senha deve ter pelo menos seis caracteres e não pode repetir a inicial/anterior. Redefinição restrita ao **Administrador / STI**.
- **Gestão Completa de Efetivo e Administradores**:
  - Base inicial oficial com **443 membros** já cadastrados com postos, quadros, especialidades e lotações.
  - O Administrador pode conceder ou revogar o perfil de **Administrador (`ADMIN`)** para qualquer membro do efetivo com 1 clique.
  - Integração com a API do **SIGPES CCARJ** para download automático das fotografias oficiais em alta resolução.
- **Controle Total do Pleito**:
  - Painel de controle de fases com transição assistida.
  - Botão de **Reinicialização do Pleito** com dupla confirmação e registro em log de auditoria.
  - Apuração em tempo real com gráficos, cálculo automático de empates e emissão de ata regimental pronta para publicação em Boletim Interno.
- **Backups e Auditoria**:
  - Scripts prontos para backups diários incrementais e backups semanais integrais com retenção obrigatória de 5 anos.
  - Trilha de auditoria completa em conformidade com as normas da FAB.

---

## 🛠️ Requisitos Técnicos

- **PHP**: 8.2 ou 8.3 (extensões: `pdo_mysql`, `curl`, `mbstring`, `gd`, `zip`, `ldap`)
- **Banco de Dados**: MySQL 8.0+ ou MariaDB 10.6+
- **Servidor Web**: Apache 2.4+ (com `mod_rewrite`) ou Nginx 1.20+
- **Ambiente de Hospedagem**: aaPanel no Debian 12/13, Servidores Linux dedicados ou Docker.

---

## ⚡ Instalação Rápida Local (Docker)

Se você utiliza Docker no Windows, Linux ou macOS:

1. Clone o repositório:
   ```bash
   git clone https://github.com/fortunatolf-tech/padrao.git
   cd padrao
   ```

2. Suba os contêineres:
   ```bash
   docker compose up -d
   ```

3. Execute o instalador automático do banco de dados:
   ```bash
   docker exec -it votacao_comara_app php /var/www/html/database/instalar.php
   docker exec -it votacao_comara_app php /var/www/html/database/migrate_fase2.php
   docker exec -it votacao_comara_app php /var/www/html/database/migrate_fases3_4.php
   docker exec -it votacao_comara_app php /var/www/html/database/migrate_fase5.php
   ```

4. Acesse no navegador:
   - URL: `http://localhost:8080`
   - Login inicial: `admin`, SARAM (militar) ou matrícula/CPF (civil)
   - Senha padrão: `padrao@2026`

---

## 🌐 Deploy em Produção (aaPanel / Debian)

Consulte o manual completo com comandos passo a passo em:  
👉 **[DEPLOY_TUTORIAL.md](DEPLOY_TUTORIAL.md)**

Para o regulamento militar e manual de atribuições por função (Chefe Imediato, Chefe de Divisão, Presidente, Eleitor e STI), consulte a:  
👉 **[Norma Padrão de Ação (NPA COMARA)](docs/NPA_ELEICAO_PADROES_COMARA.md)**

Resumo dos comandos no servidor Linux / aaPanel:
```bash
cd /www/wwwroot/padrao
cp .env.example .env
# Edite as credenciais do banco no .env:
nano .env
# Em instalação nova, carregue a base e aplique a migração:
php database/instalar.php
php database/migrate_fase2.php
php database/migrate_fases3_4.php
php database/migrate_fase5.php
```

Em uma instalação existente, faça backup e execute as migrações da Fase 2, das Fases 3–4 e da Fase 5, nessa ordem após publicar a versão. Não reexecute a carga inicial para atualizar um banco em uso. Antes de liberar a seleção, confira os vencedores anteriores, as datas de apresentação/admissão e início de serviço e o cadastro de chefias.

---

## 🔐 Credenciais Padrão Iniciais

| Usuário / Login | Senha Inicial | Perfil | Descrição |
| :--- | :--- | :--- | :--- |
| `admin` | `padrao@2026` | `ADMIN` | Administrador Geral do Sistema |
| SARAM do militar | `padrao@2026` | `ELEITOR` / `CHEFE` | Militares do efetivo cadastrado |
| Matrícula ou CPF do civil | `padrao@2026` | `ELEITOR` | SPPF/SPTF: dois identificadores, mesma conta |

> **IMPORTANTE**: Para contas locais ainda com a senha inicial, o sistema exige a troca antes de acessar as fases, inclusive para o `admin`. Quem já usa senha pessoal não precisa trocá-la novamente. No acesso corporativo pelo LDAP, prevalece a política de senhas do AD.

---

## 📂 Estrutura do Projeto

```
padrao/
├── config/             # Configurações do sistema, constantes, conexão BD e LDAP
│   ├── config.php      # Loader do .env e variáveis globais
│   ├── database.php    # Conexão PDO Singleton
│   └── ldap.php        # Autenticação Active Directory e fallback local
├── database/           # Scripts SQL e migrações
│   ├── carga_inicial_443.sql # Dump oficial com 443 membros e estrutura
│   └── instalar.php    # Instalador automático CLI / Web
├── docker/             # Dockerfile e configurações de contêineres
├── public/             # Raiz pública do servidor web
│   ├── css/            # Estilos personalizados FAB / COMARA
│   ├── js/             # Scripts interativos (Fase 1, Fase 2, Urna Fase 3)
│   └── uploads/        # Diretório de fotos dos candidatos
├── scripts/            # Scripts de backup e manutenção (Cron)
│   ├── backup_diario.sh
│   └── backup_semanal.sh
├── src/                # Controladores, Modelos, Middleware e Serviços
│   ├── Auth/           # Gerenciador de Sessão e Criptografia
│   ├── Controllers/    # Lógica de negócio das telas e APIs
│   ├── Middleware/     # Proteção de rotas e perfis
│   ├── Models/         # Interação com o banco de dados
│   └── Services/       # Integração SIGPES CCARJ e regras especiais
└── views/              # Interfaces do usuário em PHP / Bootstrap 5
```

---

## 📜 Licença e Propriedade

Desenvolvido para uso exclusivo da **Comissão de Aeroportos da Região Amazônica (COMARA)** — Comando da Aeronáutica — Ministério da Defesa do Brasil.

## Manutenção da documentação

Toda alteração de comportamento, regra, configuração ou instalação deve incluir a revisão de todos os arquivos README e Markdown do repositório, inclusive todos os documentos em `docs/`. Atualize os documentos afetados na mesma entrega do código, mantendo coerência entre visão geral, manual operacional e guias de implantação. Arquivos sem impacto devem ser revisados, sem mudanças artificiais.

Documentos de referência:

- [Guia de implantação](DEPLOY_TUTORIAL.md)
- [Instalação no aaPanel](scripts/deploy_aapanel.md)
- [Manual operacional / NPA](docs/NPA_ELEICAO_PADROES_COMARA.md)
- [Regras, migração e testes da Fase 2](docs/ATUALIZACAO_FASE2.md)

## Fases 3 e 4 — ordenação do Estado-Maior

A urna recebe somente os representantes escolhidos na Fase 2, até seis por categoria, em ordem alfabética do nome de guerra, sem distinção de caixa e acentos. Os cartões exibem somente foto, posto/graduação (CIVIL SPPF/SPTF), nome de guerra e setor. O voto pode ser revisto antes da confirmação; depois da gravação, é definitivo. Categoria sem representantes não recebe cédula: sua escolha excepcional fica para a Presidência na Fase 5.

Ao encerrar a Fase 3, o sistema fixa votos e dados de classificação. Avançam até três por categoria, aplicando os quatro critérios de desempate da Fase 2. Se houver empate persistente no corte, a urna é encerrada e a Fase 4 fica bloqueada: a Presidência decide, com justificativa, somente entre os empatados pelas vagas restantes. A Administração avança novamente depois dessas decisões.

Na Fase 4, os Coronéis, Tenentes-Coronéis e Majores com efetivo e conta ativos selecionados pelo ADMIN na composição do pleito ordenam individualmente os finalistas e justificam cada posição em até 150 caracteres. Pesos: primeiro 5, segundo 3, terceiro 1. Com dois candidatos usam-se 5 e 3; com um, 5, sempre mediante confirmação expressa. Nenhuma avaliação confirmada pode ser editada. Somente depois das confirmações de todos os membros marcados a Administração pode avançar à Fase 5.

Referência da Fase 4: soma dos pontos → quatro critérios existentes → votos da Fase 3. Na Fase 5 a Presidência define expressamente a classificação em cada grupo, pode excluir finalistas e incluir qualquer integrante ativo da mesma categoria, mesmo sem participação anterior. A homologação exige quatro vencedores.

A migração de produção da Fase 2 continua sem confirmação. Após backup e apenas quando a implantação for autorizada, executar nesta ordem:

```sh
php database/migrate_fase2.php
php database/migrate_fases3_4.php
php database/migrate_fase5.php
```

As migrações são idempotentes e não convertem resultados antigos. Não reinstale nem execute carga inicial em banco em uso. Consulte [regras, transições, testes e implantação](docs/ATUALIZACAO_FASES3_4.md).

As issues do fork estão desabilitadas (GitHub HTTP 410). Os requisitos e critérios de aceite estão registrados no guia e no PR #2; não se declara que issues tenham sido abertas. O estado histórico dessa entrega está no PR #2; a revisão pós-deploy está no guia abaixo.

## Fase 5 — decisões e homologação

A Presidência define 1º, 2º e 3º lugares sem posições iniciais predefinidas. Pode ordenar, incluir e excluir pessoas do grupo até a homologação, com justificativa e auditoria de cada alteração. A homologação exige confirmação expressa, ata e quatro vencedores; grava resultado e auditoria em uma única transação. Formulários desatualizados, duplicidade e alterações posteriores são bloqueados. O botão Reiniciar não apaga eleição homologada.

Presidente e ADMIN consultam ordenações e justificativas individuais identificadas da Fase 4. DIRECAO_SUPERIOR mantém consulta da apuração consolidada, sem poderes presidenciais. O efetivo acessa vencedores apenas após homologação. Consulta histórica usa a ata original, sem reinterpretar resultados antigos.

Consulte [o guia da Fase 5](docs/ATUALIZACAO_FASE5.md), inclusive limitações de registros antigos e implantação. As issues continuam desabilitadas (HTTP 410 confirmado em 02/11/2026); requisitos registrados no guia e no PR da Fase 5.

## Correções pós-deploy — Fases 3 e 4

O cabeçalho preserva os diagnósticos das páginas. Sem ciclo preparado, a Fase 4 orienta aguardar o encerramento da urna; apenas cortes realmente pendentes exigem decisão presidencial. Na Fase 3, falhas de estrutura e seleções inconsistentes têm orientações distintas. Falhas SQL tratadas pelos formulários não são exibidas ao usuário; a STI consulta o log interno.

Publicar arquivos não executa migrações. Siga o [procedimento pós-deploy](docs/CORRECAO_DEPLOY_FASES3_4.md) após backup, incluindo a sequência Fase 2 → Fases 3–4 → Fase 5. Não reinstale o banco para corrigir a urna.

A suíte `tests/teste_deploy_fases3_4.php` inclui as verificações acumuladas das Fases 3–5 e as regressões de deploy; exige banco vazio, descartável e terminado em `_test`. Issues continuam desabilitadas no fork; achados e aceite são registrados no PR e nos guias.


Na Fase 5, cada finalista apresenta a **classificação popular da Fase 3 (posição e votos)** e a **ordenação final da Fase 4 (posição e pontos)** em colunas separadas como referência para a decisão presidencial. A posição popular considera apenas os votos de todos os candidatos da categoria, inclusive os que não chegaram à Fase 4; empates compartilham posição. A posição da Fase 4 aplica pontos 5–3–1, quatro critérios e votos anteriores. A ordenação presidencial não reescreve essas posições. Incluídos sem participação anterior não recebem classificação fictícia; homologações anteriores sem posições arquivadas indicam dado não registrado.

## Participantes da Fase 4

A seleção em **Atribuição de Chefias e Perfis > Membros da Direção Superior** determina quem ordena e quem é exigido no avanço. É independente dos perfis de chefe/ADMIN/PRESIDENTE, ajustável até a Fase 4 e bloqueada a partir da Fase 5. Desmarcados deixam de somar pontos e de bloquear o avanço; avaliações são preservadas. Após atualizar, reaplique `migrate_fases3_4.php` e salve a composição desejada. Consulte [procedimento e testes](docs/COMPOSICAO_FASE4.md).

### Migração da ordenação presidencial

Após backup e deploy, reaplique `php database/migrate_fase5.php` para criar `fase5_decisoes`. Em pleitos não homologados, salve expressamente as quatro categorias; decisões antigas de minerva não predefinem posições. As avaliações individuais da Fase 4 ficam antes da ata. Homologações anteriores permanecem imutáveis. Consulte o [guia atualizado](docs/ATUALIZACAO_FASE5.md).

## Cadastro: RH e matrícula/SARAM

Para bancos legados que recusam RH e para impedir novas matrículas duplicadas, siga o [procedimento de diagnóstico e atualização](docs/CORRECAO_CADASTRO_RH_SARAM.md). Após backup e deploy, execute `php database/migrate_perfil_rh.php --check` e `php database/migrate_saram_unico.php --check`. Aplique cada ajuste com `--apply` quando indicado. Duplicados existentes são reportados para conferência, sem exclusão automática de dados eleitorais.

## Atualização de login e primeiro acesso (sem migração)

A matrícula civil já é cadastrada em `efetivo.saram`: a autenticação apenas resolve matrícula ou CPF para a **mesma conta existente** (`usuarios.efetivo_id`). Não crie contas por CPF e não retire os zeros à esquerda da matrícula. Cadastros com CPF/matrícula que correspondam a mais de uma conta ativa exigem saneamento pelo RH/STI; o sistema recusa o login ambíguo sem escolher um registro arbitrário. Acesso corporativo LDAP continua disponível.

A exigência de troca usa o hash BCrypt da senha inicial; não acrescenta coluna, não redefine senhas anteriores e não altera votos ou históricos. Após backup e publicação, teste ADMIN, militar SARAM, civis por matrícula/CPF, mudança de senha, reset e conta LDAP antes de abrir a votação. O novo fluxo de autenticação **não exige migração SQL**. A rotina de testes adicional usa exclusivamente banco descartável `_test` e `AUTH_TEST_DB=1`.
