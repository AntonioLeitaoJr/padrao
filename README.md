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
- **Fase 4 — Apreciação pela Direção Superior.**
- **Fase 5 — Homologação e voto de minerva do Presidente**, quando necessário.

### Regras da Fase 2

O corte de 20% é arredondado para cima e inclui pelo menos dois candidatos quando disponíveis. Havendo apenas um, ele fica disponível; sem candidatos, o grupo fica sem representante. Empates no corte são resolvidos, nesta ordem, pela preferência a quem não venceu nos dois anos anteriores ao pleito, maior antiguidade na COMARA, maior tempo de serviço militar ou civil e maior nota do TACF. Persistindo o empate após os quatro critérios, todos os empatados na última vaga entram.

O representante das sete assessorias e seções é o chefe militar mais antigo entre elas que não acumule chefia de divisão. Se necessário, a busca continua pelos seguintes na antiguidade. A escolha depende do cadastro de chefias, não apenas da lotação.

O clique no nome exibe foto, nome de guerra, setor e posto/graduação; para civis, **CIVIL SPPF** ou **CIVIL SPTF**. A seleção permanece nos controles existentes. O painel geral apresenta quatro critérios; a funcionalidade de minerva da Fase 5 permanece.

Administrador e RH registram em **Vencedores anteriores** um vencedor por categoria e ano dos dois anos anteriores ao pleito ativo (2024 e 2025 para 2026). São vencedores finais, não todos os indicados. Consulte as regras completas, os dados necessários e a migração em [Atualização da Fase 2](docs/ATUALIZACAO_FASE2.md).

---

## 🚀 Funcionalidades Principais

- **Autenticação Flexível e Segura**:
  - Login transparente com **SARAM** ou **CPF** (com ou sem pontuação).
  - Autenticação corporativa integrada ao **Active Directory / LDAP da INTRAER** com fallback local seguro para contingência.
  - Troca de senha pelo próprio usuário autenticado (recomendada obrigatoriamente no primeiro acesso); redefinição e reset de senhas restritos com exclusividade ao **Administrador / Setor de TI (STI)** para garantia de segurança e inviolabilidade.
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
   ```

4. Acesse no navegador:
   - URL: `http://localhost:8080`
   - Login inicial: `admin` ou seu SARAM/CPF
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
```

Em uma instalação existente, faça backup e execute somente a migração da Fase 2 após publicar a versão. Não reexecute a carga inicial para atualizar um banco em uso. Antes de liberar a seleção, confira os vencedores anteriores, as datas de apresentação/admissão e início de serviço e o cadastro de chefias.

---

## 🔐 Credenciais Padrão Iniciais

| Usuário / Login | Senha Inicial | Perfil | Descrição |
| :--- | :--- | :--- | :--- |
| `admin` | `padrao@2026` | `ADMIN` | Administrador Geral do Sistema |
| SARAM ou CPF do militar | `padrao@2026` | `ELEITOR` / `CHEFE` | Usuários do efetivo cadastrado |

> **IMPORTANTE**: Após o primeiro acesso com o usuário `admin`, altere a senha na barra superior clicando no seu nome de usuário.

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

## Revisão das Fases 3 e 4 — entrega parcial

A urna valida os representantes selecionados da Fase 2, apresenta somente os quatro dados solicitados e ordena por nome de guerra com tratamento de caixa e acentos. Civis aparecem como CIVIL SPPF/SPTF. Candidatos não selecionados, categoria incorreta e votos fora da fase são rejeitados no servidor.

A Fase 4 atual permanece de validação/exclusão, agora em ordem alfabética. O validador de ordenação e justificativas foi preparado, mas ainda não está conectado a uma tela ou gravação. Corte dos três finalistas, ausências, consolidação e edição dependem de decisão expressa. A Fase 5 permanece sem alteração. Consulte [escopo, testes e pendências](docs/ATUALIZACAO_FASES3_4.md).

Não há nova migração nesta entrega parcial. A execução de `php database/migrate_fase2.php` no servidor continua sem confirmação. As issues do fork estão desabilitadas (GitHub HTTP 410); os requisitos estão registrados no documento acima e no PR, sem alegação de abertura de issues.
