# Correcao de deploy: esquema SQL da votacao COMARA (novembro/2026)

## Causa confirmada no repositorio
O instalador usa a carga inicial **database/carga_inicial_443.sql** no lugar do schema atualizado.
O dump e anterior ao codigo PHP e nao contem:
- Colunas fichas_indicacao.is_nob e fichas_indicacao.status;
- Coluna efetivo.data_inicio_servico;
- Tabela historico_vencedores;
- Capacidade de aceitar NULL nos sete criterios da ficha e em justificativa.

O dump possui instrucoes DROP TABLE. **Nunca execute novamente database/instalar.php em um banco ja instalado.**

## Procedimento seguro em servidor ja instalado (aaPanel / Debian)

### Passo 1 — Confirmar a pasta e a versao do codigo
No terminal do servidor:

    cd /www/wwwroot/padrao.comara.intraer
    pwd
    git status --short
    git remote -v

Se nao houver diretorio .git, a instalacao foi feita por copia/upload; a migracao PHP devera ser adicionada manualmente em database/ pelo responsavel pelo deploy. Nao use git reset --hard.

### Passo 2 — Criar backup do banco ANTES de qualquer alteracao
Use o recurso de backup do aaPanel (Banco de Dados > Backup) ou no terminal:

    umask 077
    mkdir -p /root/comara-backups
    chmod 700 /root/comara-backups
    DUMP_BIN=$(command -v mysqldump || true)
    [ -n "$DUMP_BIN" ] || DUMP_BIN=/www/server/mysql/bin/mysqldump
    test -x "$DUMP_BIN" || { echo "mysqldump nao encontrado: use o backup do aaPanel"; exit 1; }
    BACKUP="/root/comara-backups/comara_votacao_$(date +%Y%m%d_%H%M%S).sql"
    "$DUMP_BIN" --host=127.0.0.1 --port=3306 --user=comara_user -p --single-transaction --routines --triggers comara_votacao > "$BACKUP"
    test -s "$BACKUP" && ls -lh "$BACKUP"

O terminal pedira a senha do banco: nao a cole em chats nem em comandos. Confirme que o backup tem tamanho maior que zero e mantenha-o fora da pasta do site.

### Passo 3 — Disponibilizar o codigo da correcao
Apos revisao do PR e integracao autorizada, atualize a instalacao conforme a rotina de deploy da equipe.
Confirme que existe o arquivo database/migrar_esquema.php no servidor.

### Passo 4 — Diagnosticar sem alterar dados

    php -l database/migrar_esquema.php
    php database/migrar_esquema.php --check

Resultado esperado no estado anterior: ajustes pendentes listados sem alterar o banco. O comando retorna codigo de saida 2 quando detecta incompatibilidades.

### Passo 5 — Aplicar somente apos backup confirmado

    php database/migrar_esquema.php --apply
    php database/migrar_esquema.php --check

Resultado esperado no segundo comando: [OK] Estrutura de banco compativel.

A migracao **nao** usa DROP, DELETE, TRUNCATE nem importa dados. Ela adiciona 3 colunas, flexibiliza ate 8 campos e cria 1 tabela, conforme necessidade. Nao altera as notas e nao preenche datas de servico desconhecidas. ALTER TABLE realiza commits implicitos: use sempre backup previo.

### Passo 6 — Testar os modulos
Acesse com conta autorizada, por ordem:
1. Administracao do pleito: /index.php?r=admin/pleito
2. Fase 1: /index.php?r=fase1
3. Fase 2: /index.php?r=fase2
4. Fase 5: /index.php?r=fase5
5. Relatorio: /index.php?r=relatorios/placa

Verifique consultas, acesso e salvamento com dados de teste, sem homologar ou avancar um pleito real durante a validacao.

## Pendencias adicionais de seguranca
1. Proteger ou desativar o instalador web; nao deixá-lo acessivel via HTTP.
2. Substituir a senha inicial de administrador e verificar os acessos existentes.
3. Restringir acesso aos arquivos .env, backups e SQL no servidor.
4. Examinar a exposicao de dados pessoais no dump do repositorio publico e definir tratamento adequado com a equipe responsavel.
5. Desativar display_errors para requisicoes web em producao, mantendo logs internos.
6. Configurar HTTPS/TLS na intranet.

## Dados funcionais
A coluna efetivo.data_inicio_servico e criada com NULL. O RH devera preencher datas validadas para o desempate; **nao** copiar automaticamente data_admissao_comara ou data_vinculo_om, pois significam fatos diferentes.

## Limites
Este ajuste corrige a estrutura prevista no schema e o erro encontrado nas telas. Nao valida o conteudo real do banco ate a execucao de --check no servidor, nem substitui testes funcionais completos das Fases 3, 4 e 5.

## Complemento das Fases 3, 4 e 5

`migrar_esquema.php --check` verifica a compatibilidade descrita neste documento, mas não substitui as migrações eleitorais. Depois de resolver essas pendências e confirmar o backup, execute em ordem `migrate_fase2.php`, `migrate_fases3_4.php` e `migrate_fase5.php`, interrompendo diante de falha. Consulte [a correção pós-deploy das Fases 3 e 4](CORRECAO_DEPLOY_FASES3_4.md). Não execute os testes de integração no banco de produção.

## Cadastro: RH e matrícula/SARAM

Para bancos legados que recusam RH e para impedir novas matrículas duplicadas, siga o [procedimento de diagnóstico e atualização](CORRECAO_CADASTRO_RH_SARAM.md). Após backup e deploy, execute `php database/migrate_perfil_rh.php --check` e `php database/migrate_saram_unico.php --check`. Aplique cada ajuste com `--apply` quando indicado. Duplicados existentes são reportados para conferência, sem exclusão automática de dados eleitorais.
