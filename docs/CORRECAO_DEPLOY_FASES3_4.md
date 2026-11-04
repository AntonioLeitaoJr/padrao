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
