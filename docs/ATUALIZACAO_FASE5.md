# Atualização da Fase 5

## Base histórica e rastreabilidade

Branch `ajustes-fase5`, derivada do commit `384bc268a44e78619b0a4a266e515feaef51b7da` do PR #2 (`ajustes-fases3-4`), ainda aberto. PR da Fase 5 direcionado a essa branch para revisão apenas do incremento; após integrar o PR #2, reavaliar a base antes da integração. Sem merge ou deploy nesta entrega.

Fork e original conferidos antes da edição. Original `main` em `66a436752eec1b953d28fa9a66478478b31a92cd`: suas duas alterações após a base comum são documentais, contempladas na base do PR #2. Não foi localizado trabalho de HMAC em outra branch/PR. Issues do fork seguem desabilitadas: tentativa em 02/11/2026 retornou HTTP 410. Requisitos e critérios estão neste guia e no PR, sem declaração de issue publicada.

## Regras confirmadas e preservadas

- Resultado: soma 5–3–1, quatro critérios (não vencedor nos dois anos anteriores, antiguidade COMARA, serviço, TACF), votos da Fase 3 e minerva somente entre empatados finais. Sem novo critério.
- Vencedor regular não admite escolha livre. Minerva e exceção têm identificação distinta, fundamento obrigatório e validação no servidor.
- Lista excepcional permanece a registrada ao encerrar a Fase 3, somente da categoria sem finalistas. Não se atualiza com novos cadastros nem se amplia outra categoria.
- Todos os avaliadores registrados da Fase 4 devem confirmar. Registros incompletos ou posições incompatíveis bloqueiam apuração, sem converter ausências em zero.
- Decisões presidenciais revisáveis até homologar, conforme resposta do criador neste chat. Revisão exige confirmação e justificativa; auditam-se valor anterior, novo, autor e data/hora. Reenvio idêntico é idempotente e versão desatualizada é rejeitada.
- Presidente e ADMIN consultam as avaliações identificadas da Fase 4; demais avaliadores mantêm consulta própria. DIRECAO_SUPERIOR vê somente a consolidação na Fase 5 e não ganha poderes presidenciais.
- Divulgação de vencedores ao efetivo somente após homologação. Placa permanece restrita ao ADMIN.
- ADMIN mantém poderes anteriores, exceto a reinicialização de eleição homologada, cujo bloqueio foi expressamente escolhido pelo criador. Nenhum pleito homologado é apagado pelo botão Reiniciar.

Essas definições de desenvolvimento não constituem aprovação institucional ou publicação da NPA.

## Homologação, persistência e histórico

Exige quatro vencedores, ata com pelo menos 20 caracteres e confirmação expressa. O servidor bloqueia a linha do pleito: decisões, homologação e reinicialização são serializadas. A versão da apuração conferida pelo Presidente é validada antes de homologar. Ata, IDs dos vencedores, retrato em `fase5_resultados`, status, contador legado e auditoria obrigatória são gravados numa transação. Falha em qualquer etapa reverte tudo. Chave única por pleito e lock impedem dupla homologação.

Decisões, retorno, nova homologação e reinicialização ficam bloqueados depois do ato. Gravações legadas de ficha, TACF e delegações também passam pelo mesmo lock e bloqueio de homologação. Cadastros administrativos continuam disponíveis sem reinterpretar o resultado arquivado.

Consulta nova recupera o retrato oficial; não recalcula os vencedores usando dados atuais. Consultas históricas selecionam explicitamente `pleito_id` homologado. Registros anteriores à migração usam os IDs e a ata originais; pontuações e critérios não reconstruídos são indicados como desconhecidos. Sem retrato anterior, nomes e demais identificações vêm do cadastro atual, e fotos continuam vinculadas ao serviço existente: não se declara arquivamento histórico das imagens.

O histórico dos próximos pleitos já consulta `homologacao_pleito` diretamente. Não duplicar registros em `historico_vencedores`. Precedência das correções manuais existentes permanece; a correção não altera a ata original nem o retrato homologado.

## Exceções institucionais

Categoria sem qualquer integrante no universo congelado mantém homologação bloqueada. Não há criação de vencedor, inclusão posterior automática ou dispensa de categoria. O procedimento institucional para solucionar esse caso permanece por definir; encaminhar à Presidência, preservando os dados.

Status divergente de ata, vencedor inexistente ou retrato incompatível bloqueiam a consulta/operação com orientação de conferência. Não corrigir por recálculo nem por reinstalação. A preparação de pleitos legados ainda em curso segue o guia das Fases 3–4; qualquer caso incompatível depende de conferência específica, não de migração automática. Não foi instituído fluxo de anulação de homologações.

## Banco e implantação

Somente quando a implantação for autorizada, após backup e em manutenção, publicar a versão aprovada e executar:

```sh
php database/migrate_fase2.php
php database/migrate_fases3_4.php
php database/migrate_fase5.php
```

A última migração é CLI, idempotente e cria `fase5_resultados`, sem apagar, preencher ou converter registros antigos. O schema inclui a estrutura para instalações novas. Execute as três também após instalador que use dump antigo. Não usar reinstalação/carga inicial em banco em uso. Ausência da estrutura bloqueia novas decisões e homologações, com orientação ao STI. Não houve execução em produção; execução anterior da Fase 2 continua sem confirmação.

Não retornar código anterior ao serviço depois de utilizar as proteções novas sem procedimento específico de compatibilidade e preservação de dados.

## Critérios de aceite e testes

O workflow executa sintaxe PHP/JavaScript, classificação da Fase 2, integração da Fase 2 e a suíte acumulada Fases 3–5:

```sh
php tests/teste_fase2_regras_unitarias.php
php tests/teste_ordenacao_estado_maior.php
FASE2_TEST_DB=1 DB_NAME=comara_fase2_test php tests/teste_fase2_integracao.php
FASE34_TEST_DB=1 DB_NAME=comara_fase34_test php tests/teste_deploy_fases3_4.php
```

Bancos distintos, inicialmente vazios, descartáveis e terminados em `_test`. A suíte da Fase 5 inclui as fixtures das Fases 3–4; não executá-las separadamente no mesmo banco. Testes de falha real usam triggers apenas no banco descartável. Concorrência utiliza dois processos PHP/conexões disputando o mesmo pleito.

Cobertura: pesos e critérios existentes; minerva restrita; exceção restrita e vazia; autorização PRESIDENTE/ADMIN/DIRECAO_SUPERIOR; fase/status; fundamento e ata obrigatórios; revisão, reenvio e formulário obsoleto; rollback por falha no retrato e auditoria; duplicidade/concorrência; proteção posterior; consulta legada; histórico dos próximos anos; migração repetida; regressões 2–4. Resultado confirmado e link do CI constarão no PR. Renderização de HTML por PHP é diferente de validação visual interativa em navegador.

## Documentos e limitações

Revisados todos os Markdown da base: README, NPA, guias Fases 2 e 3–4, DEPLOY_TUTORIAL e scripts/deploy_aapanel; atualizados junto deste guia. A NPA mantém estrutura institucional e condição de minuta.

Não há HMAC do comprovante demonstrado nesta base, e esta entrega não o declara implementado. Autenticação, CSRF e separação das cédulas da Fase 3 são preservados. A consulta identificada autorizada refere-se exclusivamente às avaliações da Fase 4. Não houve acesso à produção ou teste visual interativo no navegador nesta entrega.

## Revisão pós-deploy de 04/11/2026

As referências de branches/PRs no início descrevem a entrega original. A correção `2f654b1` já integra a `main`; o PR complementar revisa erros dos formulários e documentação. A suíte de deploy inclui a suíte da Fase 5 e as anteriores, com regressões de ciclo ausente, mensagens flash, migrações e falhas SQL. Consulte [diagnóstico, implantação e critérios de aceite](CORRECAO_DEPLOY_FASES3_4.md). As regras eleitorais deste guia permanecem.
