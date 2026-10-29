# Atualização da Fase 2

Este guia complementa o [README](../README.md), o [manual operacional](NPA_ELEICAO_PADROES_COMARA.md) e os guias de implantação [geral](../DEPLOY_TUTORIAL.md) e [aaPanel](../scripts/deploy_aapanel.md).

## Instalação em ambiente existente

Faça backup do banco e publique os arquivos deste commit. Antes de disponibilizar o sistema, execute na raiz do projeto, com as credenciais do banco configuradas:

```sh
php database/migrate_fase2.php
```

Em uma instalação nova, execute também essa migração após a carga inicial: o instalador pode importar um dump antigo. Em banco existente, não reexecute a carga inicial.

A migração é idempotente: cria o histórico por ano/grupo e adiciona `data_inicio_servico`. Não transforma os antigos contadores de indicações em vitórias, nem copia tempo de OM para tempo de serviço. Nenhum vencedor é presumido.

No menu Gestão do Efetivo ou na Fase 2 (administrador), abra **Vencedores anteriores**. Registre um vencedor por grupo para cada um dos dois anos anteriores ao pleito ativo. Em 2026, os anos são 2024 e 2025. Administrador e RH podem manter esse cadastro antes da Fase 3. Homologações anteriores existentes são consultadas automaticamente; o cadastro manual prevalece em caso de correção.

Em Gestão do Efetivo, preencha apresentação/admissão na COMARA e início do serviço militar ou civil. Datas desconhecidas permanecem vazias; não são inventadas. Para a representação unificada, mantenha as chefias de ACI, AGOV, AINT, AJUR, APOG, SCS e SIJ no cadastro de chefias; lotação do militar não substitui a designação de chefe. O chefe de qualquer divisão é excluído da disputa pela representação. Preserva-se a ordenação de antiguidade do cadastro (posto, apresentação na COMARA e identificador em igualdade cadastral).

## Regras

- Por órgão e grupo: 20% dos candidatos com ficha concluída e observada, arredondados para cima, mínimo de dois ou o único disponível. Nota zero válida conta; ausência de avaliação e ficha NOB não geram classificação. Uma ficha NOB não invalida outras avaliações observadas do mesmo candidato.
- Nota média primeiro; depois: não vencedor nos dois anos anteriores, antiguidade na COMARA, tempo de serviço e TACF. Todos os empatados no corte após os quatro critérios entram. A lista é exibida por nome de guerra.
- As cinco divisões (DPC, DA, DL, DE e DS) e a representação conjunta das sete assessorias/seções formam seis unidades de seleção. Um selecionado por grupo disponível em cada unidade; grupos vazios não têm representante. Servidor valida órgão, grupo, classificação e chefia. O avanço para a Fase 3 exige seleções válidas.
- Clique no nome abre foto, nome de guerra, posto/graduação (CIVIL SPPF/SPTF) e setor. A escolha continua pelo botão de opção e pela confirmação já existente.
- O painel mostra quatro critérios. A Fase 5 e o voto de minerva permanecem; a apuração usa os mesmos quatro critérios atualizados.

## Verificação

```sh
php tests/teste_fase2_regras_unitarias.php
# Somente em banco vazio e descartável com nome terminado em _test:
FASE2_TEST_DB=1 DB_NAME=comara_fase2_test php tests/teste_fase2_integracao.php
```

Os testes antigos que escrevem em pleitos ativos não devem ser executados em produção.

A rotina automatizada em `.github/workflows/fase2.yml` executa validação de sintaxe PHP/JavaScript, 16 testes unitários e 19 verificações de integração com MySQL 8.0, incluindo migração idempotente, histórico, representação das chefias e passagem à urna.
