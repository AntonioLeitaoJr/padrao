# Atualização da Fase 5

## Ordenação presidencial

A Fase 5 passa a exigir decisão expressa em **todas as categorias**, substituindo a regra anterior que limitava a intervenção aos empates finais e categorias sem finalistas. A Presidência pode definir 1º, 2º e 3º lugares independentemente das classificações anteriores, excluir finalistas nesta fase e incluir qualquer integrante **ativo da mesma categoria**, mesmo que não tenha participado da Fase 1. Não é necessário reiniciar o pleito em andamento.

Os poderes já existentes de ADMIN são preservados. DIRECAO_SUPERIOR pode consultar a consolidação, mas não ordenar, incluir, excluir ou homologar. O efetivo consulta os vencedores somente após a homologação.

## Operação

1. Acesse a Fase 5. Em cada grupo, a coluna **Resultado da Fase 5** começa sem posição selecionada. A ordem visual das linhas não representa classificação presidencial.
2. Para cada finalista, selecione 1º, 2º, 3º ou **Excluir nesta fase**. Exclusão afeta apenas o resultado da Fase 5; não apaga cadastro, votos ou avaliação anterior.
3. Se desejar, use **Incluir integrante deste grupo**. A lista contém integrantes ativos do grupo, inclusive novos cadastros, sem exigir passagem pelas fases anteriores. Escolha a posição da inclusão.
4. Registre a fundamentação da ordenação, inclusões e exclusões, confirme e salve a categoria. São permitidos um, dois ou três classificados, com posições contínuas a partir do 1º e sem repetição. O 1º lugar é obrigatório. Se não existir integrante ativo para uma categoria, não se inventa um vencedor e a homologação permanece bloqueada.
5. Repita nas quatro categorias. Decisões podem ser revistas até a homologação; dados anteriores, autor e fundamentação ficam na auditoria.
6. Consulte **Avaliações individuais da Fase 4**, agora antes do **Ato de Homologação Oficial do Pleito**, com acesso restrito à Presidência e ADMIN.
7. Confira o resultado salvo, registre a ata e homologue como anteriormente. Alterações locais ainda não salvas bloqueiam o botão de homologação no navegador. O servidor valida a versão salva que foi conferida, o cadastro ativo e a categoria dos escolhidos.

A classificação popular da Fase 3 usa exclusivamente os votos de todos os candidatos da categoria. A referência da Fase 4 conserva pontos 5–3–1, quatro critérios e votos anteriores; empates compartilham posição. A ordenação presidencial não reescreve essas referências. Quem não participou de determinada fase aparece como **Não participou**, sem posição ou pontuação fictícia. Quem participou da Fase 3 e foi incluído depois do corte mantém a posição popular correspondente.

## Persistência e proteção

`fase5_decisoes` armazena a decisão revisável de cada grupo (ranking, exclusões, fundamentação, autor e data). Alteração e auditoria são atômicas. A linha do pleito é bloqueada para serializar decisões e homologação. Formulário obsoleto exige recarga. A rota antiga de minerva não grava resultados.

A homologação continua exigindo quatro vencedores, confirmação expressa e ata de pelo menos 20 caracteres. Grava IDs oficiais, ranking e exclusões em `fase5_resultados`, ata, status e auditoria em uma única transação. Depois dela não há edição, retorno de fase ou reinicialização pelas rotas comuns. As consultas históricas preservam os snapshots/atas anteriores, inclusive homologações da regra antiga; nenhuma migração os recalcula.

Decisões antigas de minerva em pleitos ainda não homologados permanecem armazenadas para histórico, mas não substituem a nova definição expressa de todas as posições. A Presidência deve salvar novamente as quatro categorias antes de homologar. Reinicialização formal de um pleito não homologado limpa também suas decisões da Fase 5.

## Implantação

Após backup e deploy da versão aprovada, na raiz do projeto:

```bash
php database/migrate_fase5.php
```

As migrações das Fases 2 e 3–4 devem estar instaladas. Esta migração pode ser repetida: cria a nova tabela sem converter, apagar ou modificar decisões/homologações anteriores. Não execute carga inicial ou reinstalação sobre o banco em uso. Publicar arquivos não executa migrações.

## Verificação

O workflow executa as suítes das Fases 2–5 e composição, lint PHP e JavaScript. A integração usa bancos vazios, descartáveis `_test`, com `FASE34_TEST_DB=1`. Abrange alteração das três posições, exclusão, inclusão sem participação anterior, categoria e estado ativo, permissões, CSRF malformado, decisões obsoletas, auditoria atômica, falha na homologação, duas conexões concorrentes, histórico preservado e renderização real PHP das telas.

A aprovação do CI não equivale à implantação nem à conferência visual no navegador de produção. As issues do fork estavam desabilitadas nas entregas anteriores; requisitos e evidências estão documentados no PR.
