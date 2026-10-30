# Revisão das Fases 3 e 4 — entrega parcial para revisão

## Base e rastreabilidade

Base: `main` do fork AntonioLeitaoJr/padrao, commit `c4528ffa1e003b88554ee1b200037a9043d600da`, contendo o PR #1 da Fase 2. Foram consultadas as branches, issues e PRs dos dois repositórios. As atualizações posteriores dos cinco documentos Markdown do repositório original foram incorporadas, preservando os ajustes da Fase 2.

As tentativas de criar uma issue para cada fase foram recusadas pelo GitHub com HTTP 410: issues desabilitadas no fork. Não há issues publicadas por esta entrega. Este documento registra os requisitos para futura abertura, e o PR deve referenciá-lo. Sem merge ou deploy.

## Fase 3 — requisitos e critérios de aceite

- [x] Até seis representantes por categoria: seleção explícita das cinco divisões e das assessorias/seções unificadas; grupos sem representante não geram candidato.
- [x] Servidor rejeita candidato não selecionado, categoria incorreta, categoria adicional e seleção inconsistente ou duplicada por unidade.
- [x] Ordem alfabética por nome de guerra com `utf8mb4_unicode_ci`, incluindo caixa e acentos.
- [x] Cartão somente com foto, posto/graduação, nome de guerra e setor; civis identificados como CIVIL SPPF/SPTF.
- [x] Consulta da urna não carrega pontuação, classificação, nome completo ou histórico.
- [x] Preservados autenticação, CSRF, confirmação, comprovante e separação entre cédulas e controle de participação.
- [x] Validação da fase/status no serviço de gravação, sob transação; voto duplicado bloqueado.
- [x] Correção da ordem de atualização SQL para eleitor pré-cadastrado com `votou=0`.
- [ ] Categoria totalmente vazia: definição eleitoral pendente. O comportamento atual exige quatro votos e continua bloqueado, agora com orientação antes do envio. Não foram criadas regras de branco/nulo.

O comprovante atual usa código aleatório; não foi encontrado selo HMAC nesta base nem nas alterações comparadas do original. Não se declara assinatura cega criptográfica ou sigilo absoluto com base apenas na separação de tabelas.

## Fase 4 — requisitos e critérios de aceite

- [x] Apresentação atual em ordem alfabética pelo nome de guerra.
- [x] Validador independente de ordenação completa, candidatos/posições sem repetição e justificativa obrigatória de até 150 caracteres.
- [x] Contagem por grafemas Unicode no validador: letras com acentos decompostos também contam como um caractere.
- [ ] Corte dos três finalistas por categoria após a votação do efetivo.
- [ ] Tela de ordenação individual e contador de caracteres correspondente ao servidor.
- [ ] Persistência transacional, recuperação, exibição e confirmação após gravação.
- [ ] Migração idempotente e schema para as novas avaliações.
- [ ] Integração com a Fase 5 e testes do fluxo completo.

O validador não está conectado às rotas e não define quantos candidatos classificam. A tela atual continua permitindo validação/exclusão/reabilitação. Não existe hoje uma gravação de ordenações individuais cuja correção possa ser simplesmente aplicada. `AuthManager::isEstadoMaior` autoriza ADMIN, PRESIDENTE e DIRECAO_SUPERIOR ou os postos constantes de POSTOS_ESTADO_MAIOR; não foi alterado nesta entrega.

### Decisões necessárias antes de ativar a nova Fase 4

A. No empate pela terceira vaga, aplicam-se os quatro critérios? Persistindo, qual autoridade/procedimento decide a vaga? Não incluir automaticamente todos os empatados.

B. Com um ou dois candidatos, ordenar apenas os disponíveis? Categoria vazia deve seguir sem representante e sem vencedor na homologação?

C. Qual método consolida as ordenações individuais, e quais desempates, para produzir o resultado destinado à Presidência? O código atual apenas conta votos da urna; não contém pesos para as ordenações.

D. A avaliação confirmada poderá ser editada? Até quando? Não há regra atual para essas avaliações.

As perguntas foram apresentadas, sem resposta recebida. Não foram presumidas decisões. A apuração e a homologação da Fase 5 permanecem inalteradas.

## Banco e implantação

Não há alteração de estrutura nesta entrega parcial, portanto não há nova migração nem mudança do schema. A migração de produção da Fase 2 continua sem confirmação.

Quando a implantação for autorizada, realizar backup, manter o sistema indisponível durante a atualização e executar na raiz da versão aprovada:

```sh
php database/migrate_fase2.php
```

Verificar `historico_vencedores` e `efetivo.data_inicio_servico` e os dados de vencedores, datas e chefias antes de liberar o pleito. Nunca executar reinstalação, carga inicial ou testes de escrita para atualizar produção. Falhas ao carregar representantes bloqueiam a urna com orientação; não são substituídas silenciosamente por candidatos ou pontuações fictícias. Esta entrega não corrige globalmente todas as telas antigas em caso de migração ausente.

## Testes

```sh
php tests/teste_fase2_regras_unitarias.php
php tests/teste_ordenacao_estado_maior.php
# Cada suíte de integração exige seu próprio banco vazio e descartável:
FASE2_TEST_DB=1 DB_NAME=comara_fase2_test php tests/teste_fase2_integracao.php
FASE34_TEST_DB=1 DB_NAME=comara_fase34_test php tests/teste_fase3_integracao.php
node --check public/assets/js/main.js
```

O workflow `.github/workflows/fase2.yml` executa sintaxe PHP/JavaScript, regressão da Fase 2, validador da futura ordenação e integração da urna com MySQL 8.0 separado. As migrações da Fase 2 são repetidas em banco descartável. O ambiente local não disponibilizou PHP/MySQL; a execução PHP/banco é feita pelo GitHub Actions e o resultado deve ser informado no PR, sem presumir aprovação antes do término.

Não há testes concluídos de persistência de ordenações, corte, consolidação ou novas migrações da Fase 4, pois essas funcionalidades continuam pendentes. Não foi feita verificação visual em navegador nem teste de concorrência em múltiplas conexões.

## Revisão documental

Todos os Markdown existentes foram revisados e atualizados: `README.md`, `docs/NPA_ELEICAO_PADROES_COMARA.md`, `docs/ATUALIZACAO_FASE2.md`, `DEPLOY_TUTORIAL.md` e `scripts/deploy_aapanel.md`. Este documento complementa a entrega. A NPA mantém títulos, numeração, listas e tabela de cronograma, identifica o que está pendente e não presume aprovação ou publicação em Boletim.
