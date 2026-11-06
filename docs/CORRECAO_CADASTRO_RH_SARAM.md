# Cadastro: perfil RH e matrícula/SARAM único

## Erro ao conceder RH

A mensagem genérica de falha de banco na rota `admin/salvar_efetivo` não identifica a causa SQL. Em bases antigas, `usuarios.perfil` pode ser um ENUM sem `RH`. As migrações eleitorais das Fases 2–5 não atualizam esse ENUM. O reconciliador `migrar_esquema.php` passa a verificar essa compatibilidade.

Após backup e deploy, no diretório do projeto e usando a mesma configuração do banco da aplicação:

```bash
php database/migrate_perfil_rh.php --check
php database/migrate_perfil_rh.php --apply
php database/migrate_perfil_rh.php --check
```

A migração acrescenta RH sem remover ou reordenar os perfis existentes, nem alterar contas, vínculos ou dados eleitorais. O diagnóstico retorna 2 quando há ajuste pendente, 0 quando compatível e 1 em falha. Se já estiver compatível e o erro continuar, conferir o log PHP com o prefixo `SALVAR_EFETIVO`; não repetir migrações eleitorais como solução genérica.

O cadastro, a conta vinculada, a concessão do perfil e sua auditoria são gravados em transação. Uma falha reverte essas alterações. Apenas ADMIN atribui/revoga ADMIN e RH; a precedência de ADMIN também vale ao promover alguém que era RH.

## Matrícula/SARAM duplicado

O cadastro manual rejeita matrícula/SARAM de outra pessoa, inclusive inativa, tanto ao criar quanto ao editar. Espaços externos são ignorados; zeros iniciais são preservados. Campo vazio continua permitido. A conta existente nunca é transferida automaticamente para o cadastro novo quando há conflito de login.

O mesmo bloqueio atende a importação CSV. Quando uma matrícula/CPF encontra mais de um registro legado, a linha é recusada e informada; a importação não escolhe arbitrariamente um deles. Linhas válidas podem ser processadas e os totais refletem apenas gravações concluídas. O cadastro existente identificado sem ambiguidade continua sendo atualizado, conforme a finalidade da importação.

As gravações pela aplicação são serializadas no banco para impedir duplicações entre dois operadores simultâneos, inclusive durante a correção de registros legados. Um índice único adicional protege também gravações diretas no banco:

```bash
php database/migrate_saram_unico.php --check
php database/migrate_saram_unico.php --apply
```

Se houver duplicados, ambos os comandos reportam matrícula e IDs do efetivo/usuário e retornam 2 **sem excluir, fundir, inativar ou modificar registros**. A Administração deve conferir qual identificador pertence a cada pessoa e os vínculos de fichas, chefias, votos e avaliações. Inativar não libera a matrícula: ela continua reservada. Não apagar nem renumerar pessoas apenas para contornar o erro durante um pleito.

Depois da correção administrativa dos identificadores, repetir `--apply` e `--check`. O índice usa `NULLIF(TRIM(saram),'')`, permitindo múltiplos campos vazios. Novas instalações já incluem essa proteção no `schema.sql`. Não executar instalação completa ou recarga do schema sobre produção.

## Verificação automatizada

`tests/teste_cadastro_integracao.php` usa exclusivamente banco vazio descartável `_test` com `CADASTRO_TEST_DB=1`. Cobre ENUM legado, preservação de atributos/perfis, rotas de concessão e revogação, promoção RH→ADMIN, CSRF, rollback de auditoria, duplicação em criação/edição, login ocupado, inativos e concorrência de duas conexões sem índice. O workflow mantém também as suítes eleitorais existentes.
