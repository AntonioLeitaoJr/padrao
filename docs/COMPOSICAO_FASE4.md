# Composição dos avaliadores do Estado-Maior

## Composição da Fase 4 por pleito — 05/11/2026

Em **Gestão do Pleito > Atribuição de Chefias e Perfis > Membros da Direção Superior (Fase 4: Ordenação e Pareceres)**, o ADMIN marca quem participará da Fase 4. Somente Coronéis, Tenentes-Coronéis e Majores com conta e efetivo ativos podem ser selecionados. A composição é independente do perfil de acesso: um CHEFE_DIV_ASSESS, ADMIN ou PRESIDENTE que seja Oficial Superior pode participar sem perder suas atribuições.

A lista pode ser alterada nas Fases 1–4. Na Fase 4, novos marcados entram imediatamente como pendentes. Desmarcados não podem confirmar e deixam de contar tanto no avanço à Fase 5 quanto na soma de pontos. Posições, justificativas e confirmações já gravadas são preservadas; ao remarcar alguém, sua confirmação anterior continua definitiva. Toda alteração registra a composição anterior e a nova na auditoria, na mesma transação.

É obrigatório haver ao menos um membro selecionado e todos os selecionados devem confirmar. A mensagem de bloqueio identifica os nomes pendentes. Conta inativada não deve ser usada como substituto da seleção: desmarque o membro nesta tela. A partir da Fase 5, a composição fica bloqueada, preservando apuração, decisões e homologações.

### Instalação existente e correção do bloqueio

Após backup e publicação da versão aprovada, execute novamente:

```sh
php database/migrate_fase2.php && php database/migrate_fases3_4.php && php database/migrate_fase5.php
```

A migração das Fases 3–4 cria `fase4_composicao` e preserva inicialmente os avaliadores já registrados. Não tenta deduzir a intenção dos checkboxes antigos, que dependiam de perfil e ignoravam chefes de divisão. Reexecutá-la não reativa membros desmarcados.

Abra a composição do pleito, deixe marcados apenas os participantes desejados e salve. Para o caso relatado, marque somente Cel LEITAO, confira que sua avaliação aparece confirmada e avance novamente à Fase 5. Não reinicie a eleição, não apague avaliações e não é necessário desativar contas dos demais oficiais.

### Testes

`FASE34_TEST_DB=1 DB_NAME=comara_fase34_test php tests/teste_composicao_fase4.php` executa a suíte acumulada e os cenários de composição; use somente banco vazio e descartável. Cobertura inclui chefia preservada, inclusão durante a Fase 4, exclusão de pendente, preservação de confirmação, exclusão de pontos, reativação, lista vazia, permissões, formulário obsoleto, rollback da auditoria, migração repetida e bloqueio a partir da Fase 5. Nenhuma migração foi executada no servidor de produção nesta entrega.
