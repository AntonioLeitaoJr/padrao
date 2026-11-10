# Importação integral da lista do efetivo — 10/11/2026

## Diagnóstico
O CSV original fornecido pelo administrador, exportado em 06/11/2026, contém **484 pessoas**, das quais **473 ativas** e **11 inativas**. Não há duplicação exata de SARAM/matrícula nem de CPF no arquivo. O modelo legado ignorava a coluna ATIVO (ativava todos) e processava uma linha por vez, pulando registros ou mantendo importações parciais.

**Confidencialidade:** o arquivo original contém identificadores pessoais e não foi anexado ao GitHub. O PR contém apenas código, documentação e testes com registros sintéticos.

## Comportamento implementado
- Compatível com CSV delimitado por vírgula ou ponto e vírgula, com aspas e BOM UTF-8. ATIVO é opcional; quando presente, 0 inativa e 1 ativa.
- Falha imediatamente ao detectar cabeçalho incompleto, categoria ou vínculo inválidos, CPF ou SARAM repetidos, matrícula em conflito com CPF de outro registro, coluna faltante ou quantidade divergente. Nunca altera um registro pertencente a matrícula diferente só por CPF coincidente.
- Na base, insere pessoas novas e atualiza as já existentes, sem duplicar contas, redefinir senhas ou perder perfis. Preserva chefias, fotos e datas de registro nos cadastros já existentes. Não remove pessoas que não constem no arquivo.
- Faz backup pré-importação em backups/daily/ e exige confirmação de que o arquivo foi gravado. Em seguida, executa todos os registros numa transação com auditoria obrigatória e rollback em caso de qualquer erro.
- Informa total importado, inseridos, atualizados, ativos e inativos. O painel retorna com filtro "Todos" para que os 11 inativos sejam visíveis.
- Bloqueia importações quando houver pleito homologado ou votação em andamento (fase >= 3).
- NÃO recupera dados eleitorais ausentes (votos, fichas, notas TACF), nem substitui uma restauração completa de backup histórico.

## Procedimento no servidor (aaPanel / STI)
1. Faça **backup completo externo independente** do banco atual. Suspenda acessos concorrentes e mantenha a base no estágio de planejamento/Fases 1–2.
2. Confira que a versão deste PR está publicada. Coloque o CSV recebido de 06/11/2026 numa pasta PRIVADA acessível pelo PHP/CLI, **fora do diretório public**. Não o adicione ao GitHub.
3. Pelo painel web, entrar com ADMIN/RH: Gestão do Pleito → Gestão do Efetivo & Candidatos → Importar Lista Completa (CSV). Selecionar o arquivo original e executar. Ao terminar, verificar se o aviso indica **484 processados, 473 ativos e 11 inativos** (os valores de novos/atualizados dependem da base).
4. Alternativamente, via Terminal do aaPanel: rodar a prévia com o comando abaixo. Substitua o caminho privado pelo caminho real de upload:
   `php database/importar_efetivo_privado.php /CAMINHO-PRIVADO/efetivo_20261106.csv`
5. Após conferir a contagem e o SHA-256 apresentados na prévia, executar:
   `php database/importar_efetivo_privado.php /CAMINHO-PRIVADO/efetivo_20261106.csv --aplicar --esperado=484 --sha256=HASH_DA_PREVIA`
6. Confirmar no próprio banco ou exportando pelo painel, com filtro **Todos**: 484 pessoas importadas a partir do arquivo, sendo 473 ativas e 11 inativas. Se a base já tinha pessoas fora do CSV, a contagem global poderá ser superior; não apague registros sem reconciliação.

## Falhas
A importação cancela o lote por inteiro e informa a linha com problema. Em caso de erro de esquema, falta de contas, log obrigatório ou backup, não tente importar parcialmente; registre o erro na STI. Em recuperação de desastre, trabalhar sempre primeiro com ambiente isolado e com cópia do banco.
