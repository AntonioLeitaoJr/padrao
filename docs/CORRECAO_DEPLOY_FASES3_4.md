# Correção pós-deploy — Fases 3 e 4

O cabeçalho compartilhado sobrescrevia `$erro` ao consultar mensagens flash. Isso ocultava o diagnóstico da Fase 4 e provocava acesso a um ciclo nulo. O cabeçalho agora usa variáveis próprias; a página distingue ciclo inexistente de corte pendente.

A Fase 3 preserva o diagnóstico de migração ou seleção inconsistente, sem expor exceções SQL. Fora da fase de votação, não consulta os candidatos nem o ciclo da próxima etapa. Falhas mantêm a votação bloqueada.

## Atualização no aaPanel

1. Faça backup do banco de produção e confirme que pode restaurá-lo.
2. Execute Deploy latest da main.
3. No terminal, entre no diretório do site e execute as migrações em ordem, interrompendo se qualquer comando falhar:

```bash
cd /www/wwwroot/padrao.comara.intraer
php database/migrate_fase2.php && php database/migrate_fases3_4.php && php database/migrate_fase5.php
```

Use o executável PHP 8 do site se o comando `php` do terminal apontar para outra versão. As migrações são idempotentes e não importam dados iniciais nem reiniciam o pleito. Não use `instalar.php` ou `schema.sql` para atualizar produção.

4. Reabra as Fases 3 e 4. Se aparecer “Seleções inconsistentes”, confira representantes e consolidação da Fase 2: migração não corrige decisões eleitorais. Não apague seleções ou votos para liberar a tela.
5. Sem ciclo preparado, a Fase 4 orienta aguardar o encerramento da Fase 3 pela Administração. Somente empates reais no corte devem encaminhar decisões à Presidência.

Publicar código no GitHub não executa migrações no servidor. Sem acesso ao banco e ao log de produção, a captura de tela não permite confirmar qual estrutura ou seleção estava pendente.

## Revisão de bugs e documentação — 04/11/2026

A correção inicial do cabeçalho e do ciclo ausente já integra a `main` no commit `2f654b1`. O PR complementar trata os formulários: falhas SQL no registro de voto ou nas operações da Fase 4 não devem revelar detalhes técnicos nem anunciar sucesso. Mensagens funcionais (CPF inválido, confirmação obrigatória, fase ou seleção incompatível) continuam específicas. CPF ou token CSRF enviados como lista são rejeitados sem erro fatal na Fase 3.

Issues estão desabilitadas no fork (`has_issues=false`, verificado em 04/11/2026). Este registro e o PR documentam os bugs, sem presumir issue criada ou habilitar configurações do repositório.

Critérios de aceite adicionais:
- POST com falha real de banco exibe orientação à STI, sem SQLSTATE ou sucesso indevido.
- POST inválido não altera votos, confirmações ou resultados.
- Mensagens de validação e proteções de fase, perfil e CSRF permanecem.
- Teste acumulado: `FASE34_TEST_DB=1 DB_NAME=comara_fase34_test php tests/teste_deploy_fases3_4.php`, somente em banco vazio e descartável. Inclui a suíte Fases 3–5; não executar ambas no mesmo banco já preenchido.

Todos os nove Markdown existentes foram revisados: README, DEPLOY_TUTORIAL, guia aaPanel e os seis documentos em `docs/`. Documentos afetados são atualizados neste PR; regras eleitorais permanecem iguais. Não houve acesso ao servidor de produção nem validação visual interativa.
