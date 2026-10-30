# 📖 Guia Definitivo de Deploy — Sistema de Eleição COMARA

Este guia detalha o passo a passo completo para realizar o deploy do **Sistema de Eleição dos Padrões da COMARA** tanto no painel **aaPanel (Debian 12/13)** quanto em qualquer servidor Linux convencional ou ambiente **Docker**.

## Atualização das Fases 2, 3 e 4 em banco existente

Antes de liberar esta versão, faça backup do banco e dos arquivos, publique a versão que contém os ajustes e execute na raiz do projeto, usando as credenciais do banco configuradas:

```bash
php database/migrate_fase2.php
php database/migrate_fases3_4.php
```

Em Docker, o comando equivalente é:

```bash
docker exec -it votacao_comara_app php /var/www/html/database/migrate_fase2.php
docker exec -it votacao_comara_app php /var/www/html/database/migrate_fases3_4.php
```

As duas migrações podem ser repetidas. A primeira cria `historico_vencedores` e adiciona `efetivo.data_inicio_servico` quando necessário; a segunda cria as estruturas de corte, avaliadores, confirmações e ordenações das Fases 3–4, preservando os registros existentes. Não reexecute instaladores ou cargas iniciais em banco em uso. Para instalações novas, execute a migração após a carga inicial, pois o instalador pode usar um dump anterior a esta alteração.

Antes de abrir a seleção, Administrador/RH deve cadastrar os vencedores dos dois anos anteriores ao pleito (2024 e 2025 em 2026), conferir as datas de apresentação/admissão e início do serviço e manter as chefias das divisões e das sete assessorias/seções. Não converta antigas indicações em vitórias nem tempo na OM em tempo total de serviço.

Confira na Fase 2 o corte de 20%, o mínimo de dois quando disponíveis, os empates e a escolha de um por grupo. A Fase 5 mantém o voto de minerva. Procedimentos completos: [Atualização da Fase 2](docs/ATUALIZACAO_FASE2.md). Os testes de integração usam exclusivamente banco vazio e descartável terminado em `_test`.

---

## 📋 Sumário
1. [Opção A: Deploy no aaPanel (Debian 12 / 13)](#-opcao-a-deploy-no-aapanel-debian-12--13)
   - [Passo 1: Instalação dos Pacotes no aaPanel](#passo-1-instalacao-dos-pacotes-no-aapanel)
   - [Passo 2: Criação do Banco de Dados](#passo-2-criacao-do-banco-de-dados)
   - [Passo 3: Criação do Site](#passo-3-criacao-do-site)
   - [Passo 4: Clonar o Código do GitHub](#passo-4-clonar-o-codigo-do-github)
   - [Passo 5: Configurar o Arquivo .env](#passo-5-configurar-o-arquivo-env)
   - [Passo 6: Instalação Automática do Banco](#passo-6-instalacao-automatica-do-banco)
   - [Passo 7: Ajustar Permissões de Pastas](#passo-7-ajustar-permissoes-de-pastas)
   - [Passo 8: Configurar o Web Server (Nginx ou Apache)](#passo-8-configurar-o-web-server-nginx-ou-apache)
   - [Passo 9: Agendamento dos Backups Automáticos (Cron)](#passo-9-agendamento-dos-backups-automaticos-cron)
2. [Opção B: Deploy com Docker Compose (Qualquer Linux / VPS)](#-opcao-b-deploy-com-docker-compose-qualquer-linux--vps)
3. [Perfis e Credenciais Iniciais de Acesso](#-perfis-e-credenciais-iniciais-de-acesso)
4. [Resolução de Problemas Comuns (Troubleshooting)](#-resolucao-de-problemas-comuns-troubleshooting)

---

## 🐧 Opção A: Deploy no aaPanel (Debian 12 / 13)

### Passo 1: Instalação dos Pacotes no aaPanel
No painel do aaPanel (menu **App Store**):
1. **Servidor Web**: Instale **Nginx 1.24+** ou **Apache 2.4+**.
2. **Banco de Dados**: Instale **MySQL 8.0** (ou MariaDB 10.11+).
3. **PHP**: Instale **PHP 8.3** (ou PHP 8.2).
4. No PHP 8.3, clique em **Settings > Extensions** e certifique-se de que as seguintes extensões estão ativas:
   - `pdo_mysql`
   - `curl`
   - `ldap` *(necessário para integração com o Active Directory da INTRAER)*
   - `fileinfo`
   - `gd`
   - `mbstring`
   - `zip`

---

### Passo 2: Criação do Banco de Dados
1. No menu lateral do aaPanel, acesse **Databases > Add Database**.
2. Preencha:
   - **DBName**: `comara_votacao`
   - **DBUser**: `comara_user` (ou de sua preferência)
   - **Password**: Gere uma senha forte (anote-a)
   - **Character Set**: `utf8mb4`
3. Clique em **Submit**.

---

### Passo 3: Criação do Site
1. No menu lateral, acesse **Website > Add site**.
2. Preencha:
   - **Domain**: Informe o domínio da INTRAER (ex.: `votacao.comara.intraer`) ou o IP do servidor.
   - **Root Directory**: Deixe `/www/wwwroot/padrao`.
   - **Database**: Pode selecionar o banco criado ou deixar para conectar via `.env`.
   - **PHP Version**: Selecione `PHP-83`.
3. Clique em **Submit**.

---

### Passo 4: Clonar o Código do GitHub
Abra o **Terminal** do aaPanel ou conecte-se via SSH:

```bash
# Entre no diretório do site
cd /www/wwwroot/padrao

# Se o diretório já tiver arquivos padrão criados pelo aaPanel (index.html, 404.html), limpe-os:
rm -rf * .htaccess

# Clone o repositório oficial
git clone https://github.com/fortunatolf-tech/padrao.git .
```

---

### Passo 5: Configurar o Arquivo .env
Copie o modelo de ambiente e edite com os dados do seu MySQL:

```bash
cp .env.example .env
nano .env
```

Ajuste as linhas do banco de dados com a senha que você gerou no **Passo 2**:
```env
APP_ENV=production
APP_KEY=gere_uma_chave_aleatoria_com_32_caracteres_ou_mais

DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=comara_votacao
DB_USER=comara_user
DB_PASS=sua_senha_do_banco_aqui

# Integrações INTRAER (opcional, pode manter como está):
SIGPES_API_BASE=http://api.servicos.ccarj.intraer/sigpesApi
LDAP_HOST=ldap.comara.intraer
LDAP_LOCAL_FALLBACK=true
```
*(Pressione `Ctrl + O` e depois `Enter` para salvar; `Ctrl + X` para sair)*

---

### Passo 6: Instalação Automática do Banco
Execute o instalador oficial automatizado em linha de comando:

```bash
php database/instalar.php
php database/migrate_fase2.php
php database/migrate_fases3_4.php
```

O instalador criará todas as tabelas, importará os **443 militares e civis**, criará as contas de acesso e configurará o administrador geral `admin` com a senha padrão `padrao@2026`.

Você verá a saída:
```text
[OK] Banco de dados 'comara_votacao' validado/criado com sucesso.
[OK] Carga inicial executada com sucesso.
[OK] Conta mestre 'admin' atualizada com a senha padrao@2026.
[INFO] Total de registros no Efetivo: 443
[OK] INSTALAÇÃO FINALIZADA COM SUCESSO!
```

---

### Passo 7: Ajustar Permissões de Pastas
Para que o PHP consiga salvar fotografias enviadas e gerar logs e backups:

```bash
chown -R www:www /www/wwwroot/padrao
find /www/wwwroot/padrao -type d -exec chmod 755 {} \;
find /www/wwwroot/padrao -type f -exec chmod 644 {} \;

# Pastas que precisam de escrita pelo PHP:
chmod -R 775 /www/wwwroot/padrao/cache
chmod -R 775 /www/wwwroot/padrao/backups
chmod -R 775 /www/wwwroot/padrao/public/uploads/fotos
```

---

### Passo 8: Configurar o Web Server (Nginx ou Apache)

#### 1. Configurar o Diretório de Execução (Recomendado):
Na aba **Site dir** do aaPanel:
- **Site directory**: `/www/wwwroot/padrao`
- **Running directory**: Selecione `/public` e clique em **Save**.
*(Isso garante que apenas os arquivos públicos fiquem expostos na web, mantendo o código fonte protegido).*

#### 2. Se estiver usando NGINX no aaPanel:
No aaPanel, clique no seu site e vá em **URL Rewrite**. Adicione as seguintes regras:
```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ /\.(env|git) {
    deny all;
}
```

#### 3. Se estiver usando APACHE no aaPanel:
O arquivo `.htaccess` dentro de `public/` (e na raiz) já gerencia o roteamento e a proteção de arquivos confidenciais automaticamente. Certifique-se apenas de que o módulo `rewrite` está ativado no Apache.

---

### Passo 9: Agendamento dos Backups Automáticos (Cron)
No aaPanel, vá em **Cron**:

1. **Backup Diário (23h)**:
   - **Type**: Shell Script
   - **Name**: Backup Diario COMARA
   - **Period**: Every day às `23:00`
   - **Script Content**:
     ```bash
     bash /www/wwwroot/padrao/scripts/backup_diario.sh
     ```

2. **Backup Semanal Completo (Domingo às 02h)**:
   - **Type**: Shell Script
   - **Name**: Backup Semanal COMARA (5 Anos)
   - **Period**: Weekly no Domingo às `02:00`
   - **Script Content**:
     ```bash
     bash /www/wwwroot/padrao/scripts/backup_semanal.sh
     ```

---

## 🐳 Opção B: Deploy com Docker Compose (Qualquer Linux / VPS)

Se preferir rodar em contêineres Docker isolados:

1. Clone o repositório:
   ```bash
   git clone https://github.com/fortunatolf-tech/padrao.git /opt/padrao
   cd /opt/padrao
   ```

2. Crie o arquivo `.env`:
   ```bash
   cp .env.example .env
   ```
   No `.env`, altere o host do banco para o nome do serviço Docker:
   ```env
   DB_HOST=db
   DB_USER=comara_user
   DB_PASS=comara_secret_2026
   ```

3. Suba os serviços:
   ```bash
   docker compose up -d
   ```

4. Execute o instalador do banco no contêiner:
   ```bash
   docker exec -it votacao_comara_app php /var/www/html/database/instalar.php
   docker exec -it votacao_comara_app php /var/www/html/database/migrate_fase2.php
   docker exec -it votacao_comara_app php /var/www/html/database/migrate_fases3_4.php
   ```

5. O sistema estará acessível em: `http://SEU_IP:8080`.

---

## 🔑 Perfis e Credenciais Iniciais de Acesso

- **URL de Acesso**: `http://seu-dominio-ou-ip/`
- **Administrador Master**:
  - **Login**: `admin`
  - **Senha inicial**: `padrao@2026`
- **Militares e Servidores Cadastrados**:
  - **Login**: **SARAM** ou **CPF** (com ou sem pontuação)
  - **Senha padrão**: `padrao@2026` (ou senha corporativa caso o Active Directory esteja conectado)

---

## 🛡️ Atribuição de Administradores Adicionais
O administrador master pode delegar permissões de administrador para outros membros:
1. Acesse o menu **Chefias** (`/index.php?r=admin/chefias`).
2. Acesse a aba **2. Administradores do Sistema**.
3. Selecione qualquer militar/civil na lista e clique em **Atribuir Privilégio de Administrador**.
4. Ou acesse **Efetivo**, clique no botão de editar qualquer membro e marque a opção **Perfil de Administrador do Sistema**.

---

## 🩺 Resolução de Problemas Comuns (Troubleshooting)

### 1. Erro "Access denied for user 'comara_user'@'localhost'"
- Verifique se as credenciais cadastradas no arquivo `.env` coincidem exatamente com o banco de dados criado no aaPanel.
- Teste a conexão via terminal com: `mysql -u comara_user -p comara_votacao`.

### 2. Extensão LDAP não encontrada no PHP
- No Debian/Ubuntu via terminal:
  ```bash
  apt-get update && apt-get install -y php8.3-ldap
  systemctl restart php-fpm-83 # ou restart php8.3-fpm
  ```
- O sistema possui **fallback automático**: caso o LDAP esteja inacessível ou desligado, a autenticação local continua funcionando normalmente.

### 3. Fotos não carregam ou erro de upload
- Certifique-se de que a pasta `public/uploads/fotos` tem permissão de escrita para o usuário `www`:
  ```bash
  chown -R www:www /www/wwwroot/padrao/public/uploads
  chmod -R 775 /www/wwwroot/padrao/public/uploads
  ```

## Atualização das Fases 3 e 4

A entrega de código não autoriza merge ou deploy. Quando a implantação for autorizada:

1. Faça backup do banco, código, `.env` e fotos; suspenda o acesso durante a atualização.
2. Publique a versão aprovada sem substituir configurações ou dados locais.
3. Na raiz do projeto execute, nesta ordem:

```sh
php database/migrate_fase2.php
php database/migrate_fases3_4.php
```

4. Verifique o sucesso dos dois comandos, as cinco tabelas `fase4_*`, o histórico de vencedores, as datas de serviço, as chefias e as contas dos Oficiais Superiores. Não se presume que a migração anterior tenha sido executada.
5. Confira urna, corte, mensagem de bloqueio, decisão presidencial, ordem alfabética, confirmação imutável e restrição da exceção por categoria. Libere o acesso somente após as conferências.

Não execute instaladores, carga inicial ou testes de escrita em produção. Para instalação nova, o schema já contém as tabelas; como o instalador pode usar dump antigo, execute também as duas migrações após a instalação. As migrações são idempotentes, preservam dados e não fabricam avaliações para pleitos já em andamento.

Pleito antigo em Fase 4 ou 5, ainda não homologado e sem `fase4_ciclos`, não pode ser automaticamente apurado pela regra nova: antes de liberar a versão, retorne administrativamente até a Fase 3 e encerre a urna pelo novo fluxo. Reveja eventuais exclusões legadas. Homologações antigas permanecem consultáveis. Depois de registrado o encerramento no fluxo novo, retorno comum fica bloqueado; somente o procedimento existente de anulação/reinicialização com limpeza pode iniciar outro ciclo, mediante autorização institucional e backup.

Detalhes, limitações e testes: [guia das Fases 3 e 4](docs/ATUALIZACAO_FASES3_4.md).
