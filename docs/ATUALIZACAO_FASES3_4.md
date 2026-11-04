# Atualização das Fases 3 e 4

## Base e decisões confirmadas

Branch `ajustes-fases3-4`, PR #2 no fork AntonioLeitaoJr/padrao. A base contém o PR #1 da Fase 2 e as atualizações posteriores dos documentos do repositório original. Sem merge ou deploy autorizado.

Em 30/10/2026 foram confirmadas as regras: todos os Oficiais Superiores habilitados ordenam individualmente; pesos 5–3–1 (5–3 com dois e 5 com um); confirmação definitiva; corte empatado resolvido pela Presidência com bloqueio da Fase 4; consolidação por pontos, quatro critérios, votos anteriores e minerva; escolha excepcional restrita à categoria sem finalistas. Essa confirmação de requisitos não constitui aprovação institucional ou publicação da NPA em Boletim.

As issues estão desabilitadas no fork; as duas tentativas retornaram HTTP 410. Nenhuma issue foi publicada. Os requisitos e critérios de aceite estão registrados neste documento e vinculados pelo PR.

## Urna e encerramento

Somente representantes explicitamente selecionados na Fase 2 entram na cédula, no máximo seis por categoria. Servidor confere unidade, categoria e consolidação; consolidados antigos sem seleção ficam fora. Apresentação por nome de guerra, ignorando caixa e acentos; cartões somente com foto, posto/graduação, nome de guerra e setor. Civis: CIVIL SPPF/SPTF.

O eleitor pode revisar antes de confirmar; depois da gravação não pode editar ou votar novamente. Categoria sem representantes não aceita voto e não impede a participação nas demais; não se cria voto branco ou nulo. O comprovante e a separação entre participação e cédulas permanecem. O código examinado não contém selo HMAC nem assinatura cega criptográfica; esta entrega não declara essas garantias.

Ao avançar a Fase 3, uma transação bloqueia o pleito, registra o retrato dos votos/dados usados no corte e encerra a urna. Até três classificados por categoria são definidos pela votação e pelos quatro critérios existentes. Se houver empate no corte, a fase permanece 3, mas a urna fica fechada e a Fase 4 bloqueada. A mensagem orienta a Presidência para `Fase 4 > decisão da terceira vaga`.

Se o empate abranger mais de uma vaga (por exemplo quatro candidatos empatados no primeiro lugar), a Presidência preenche exatamente as vagas restantes entre esses empatados, sem substituir candidatos já classificados acima do corte. Decisão com fundamentação, confirmação definitiva e auditoria. Depois, o Administrador avança novamente.

## Ordenação do Estado-Maior

- Abertura registra a lista dos avaliadores com conta e efetivo ativos, postos Cel, Ten Cel e Maj. Todos ordenam individualmente, independentemente da chefia da Fase 2.
- DIRECAO_SUPERIOR não dispensa posto de Oficial Superior. ADMIN/PRESIDENTE conservam gestão e consulta, mas não ganham cédula de ordenação sem esse requisito.
- Finalistas apresentados alfabeticamente, sem revelar a votação anterior como ordem inicial.
- Cada avaliador atribui posições únicas e justificativa individual obrigatória de até 150 grafemas Unicode, com contador e validação no cliente/servidor. Acentos decompostos contam como uma letra. Navegadores atuais usam Intl.Segmenter; o servidor é sempre a validação definitiva.
- Confirmação única abrange todas as categorias com finalistas. Com dois usa 5 e 3; com um usa 5, mediante escolha/justificativa/confirmacão expressas. Categoria vazia não recebe posição.
- Confirmação e itens são gravados na mesma transação. Restrição única e bloqueio do pleito impedem duplicidade e edição após confirmar. O usuário pode consultar suas próprias posições e justificativas.
- Todos os avaliadores registrados devem confirmar antes da Fase 5. A Administração deve conferir contas e postos antes da abertura. Não há dispensa automática por ausência ou troca de função.

## Consolidação e Presidência

Somam-se 5, 3 e 1 por posição. Empate: preferência a quem não venceu nos dois anos anteriores; antiguidade na COMARA; tempo de serviço; TACF; votos da Fase 3. Persistindo, minerva somente entre os empatados finais.

Categoria que chegou sem finalistas possui uma lista excepcional própria, registrada no encerramento da urna, contendo apenas seu efetivo ativo. Somente essa categoria pode ser decidida a partir dessa lista, com justificativa e auditoria específicas. Não há consulta genérica que amplie categorias com finalistas. A validação também ocorre no servidor, impedindo manipulação do formulário. Se nem o efetivo tiver integrante nessa categoria, a homologação fica bloqueada em vez de fabricar vencedor.

Homologação exige quatro vencedores e bloqueia decisões posteriores. Votos populares continuam disponíveis como informação de desempate, enquanto pontos da Fase 4 são o resultado principal.

## Banco e implantação

Com backup e manutenção programada, somente após autorização de implantação, publique o código aprovado e execute:

```sh
php database/migrate_fase2.php
php database/migrate_fases3_4.php
```

A primeira execução em produção continua sem confirmação. A segunda exige os objetos da primeira e cria, de forma idempotente, `fase4_ciclos`, `fase4_decisoes_corte`, `fase4_avaliadores`, `fase4_confirmacoes` e `fase4_ordenacoes`. As chaves únicas impedem duplicidade de confirmação, candidato ou posição. Não se apagam nem se convertem seleções, votos, avaliações anteriores ou homologações. `database/schema.sql` também inclui as tabelas para instalações novas.

Não use reinstalação/carga inicial em banco existente. Ausência das migrações bloqueia operações com orientação para o STI, sem presumir resultados. Dados cadastrais usados nos critérios mantêm o tratamento da Fase 2; conferir histórico de vencedores, datas e TACF antes de encerrar a urna. Campos desconhecidos não são inventados.

Pleitos antigos em Fases 4/5 sem o novo registro de encerramento não são convertidos automaticamente: antes de liberar a versão, retornar administrativamente à Fase 3 e executar o encerramento pelo novo fluxo, após revisar eventuais exclusões legadas. Homologações antigas permanecem consultáveis pela apuração legada. Após o encerramento novo, retorno comum fica bloqueado. A reinicialização com limpeza, já existente e reservada à anulação formal, limpa também os novos registros; não deve ser usada para permitir edição de uma avaliação individual.

Rollback de código após o novo encerramento exige planejamento: a versão antiga não reconhece os bloqueios nem as ordenações. Não recolocar a versão antiga em operação com o mesmo pleito aberto. Restaurar backup somente mediante procedimento autorizado, preservando previamente os registros posteriores para auditoria.

## Critérios de aceite e testes

- Representantes válidos, até seis, ausências, ordenação alfabética e quatro dados na cédula.
- Rejeição de candidato/categoria/unidade inválidos, duplicidade e gravações fora da fase.
- Corte de até três, decisão só entre empatados e urna encerrada enquanto aguarda.
- Oficiais Superiores habilitados, ordenação completa, posições únicas e justificativas 150/151 com acentos.
- Persistência, recuperação, confirmação imutável, ausência de gravação parcial e avanço só após todas as confirmações.
- Soma 5–3–1, quatro critérios antes dos votos da Fase 3 e minerva restrita.
- Categoria vazia excepcional sem ampliar demais listas; quatro vencedores para homologação.
- Migração repetida e preservação de dados em banco descartável; ausência de estrutura com orientação.

```sh
php tests/teste_fase2_regras_unitarias.php
php tests/teste_ordenacao_estado_maior.php
# Bancos separados, vazios, descartáveis e terminados em _test:
FASE2_TEST_DB=1 DB_NAME=comara_fase2_test php tests/teste_fase2_integracao.php
FASE34_TEST_DB=1 DB_NAME=comara_fase34_test php tests/teste_fase4_integracao.php
node --check public/assets/js/main.js
node --check public/assets/js/fase4.js
```

O teste da Fase 4 inclui as fixtures e verificações da Fase 3. Não o execute sobre um banco que já tenha sido preenchido pelo teste isolado da Fase 3. O workflow usa MySQL 8.0 e executa também lint PHP. O resultado efetivamente observado da execução consta no PR; não confundir aprovação dos commits parciais anteriores com a versão final.

## Documentação revisada

Todos os Markdown existentes: README, esta atualização, atualização da Fase 2, NPA, DEPLOY_TUTORIAL e scripts/deploy_aapanel. A NPA mantém títulos, numeração, listas e tabelas; atualiza atribuições, transições e cronograma, sem presumir aprovação institucional.
