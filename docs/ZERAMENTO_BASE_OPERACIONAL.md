# Zeramento operacional da base — COMARA

## Finalidade
Funcionalidade excepcional do perfil ADMIN, habilitada temporariamente pela STI. Serve para reiniciar o cadastramento e as etapas de um pleito **não homologado**, sem reinstalar o sistema. É diferente de **Reiniciar Pleito**, que mantém o cadastro de efetivo.

## Alcance
- Apaga: efetivo e usuários não ADMIN; chefias vinculadas; fichas de avaliação; TACF; seleções; controles da urna; votos; ordenações; decisões ainda não homologadas; registros operacionais históricos e pleitos anteriores não homologados.
- Mantém: estrutura e índices do banco, divisões/assessorias, contas ADMIN com suas senhas, backups e **auditoria perene**.
- Deixa um pleito de referência em **Planejamento/Fase 1**, sem cronograma, pronto para novo cadastramento.
- Não remove arquivos externos (fotos e uploads): somente registros SQL.
- **Bloqueio absoluto na interface** quando existir qualquer pleito homologado ou registro de homologação oficial.

## Procedimento da STI
1. Programar janela de manutenção e bloquear os acessos de eleitores, administradores e integrações. Não executar com a votação em curso.
2. Gerar um backup externo, conferir arquivo e testar restauração em banco isolado. O sistema fará também outro backup obrigatório.
3. Conferir que existe pelo menos um ADMIN ativo com senha local e que não há eleição homologada.
4. No ambiente protegido do servidor, habilitar temporariamente ALLOW_DATABASE_RESET=true (padrão false).
5. Entrar como ADMIN e abrir Gestão do Pleito → Backups Incrementais → Administração avançada.
6. Selecionar **Preparar zeramento da base**. A prévia fica válida por cinco minutos.
7. Informar a senha pessoal atual do ADMIN, digitar exatamente **ZERAR BASE COMARA**, marcar ciência e confirmar.
8. A operação grava um backup SQL comprimido antes de começar, valida integridade dos dados, apaga os registros em transação com auditoria obrigatória e encerra a sessão.
9. Fazer novo login, conferir pleito em planejamento, cronograma vazio, efetivo zerado e contas ADMIN preservadas. Reconfigurar as fases.
10. Desabilitar imediatamente ALLOW_DATABASE_RESET (definir false/remover variável) e encerrar janela de manutenção.

## Proteções e recuperação
- A rotina bloqueia CSRF inválido, confirmação expirada, senha/fras​e incorretas, falta de habilitação, tabelas desconhecidas, resultados homologados, ausência de ADMIN ativo e alterações de contagem detectadas durante confirmação.
- Falhas antes da limpeza não apagam registros. Erros durante a transação acionam rollback; o backup produzido pode permanecer para contingência.
- O backup da operação fica fora da pasta pública, em backups/reset/, com permissão restrita e SHA-256 registrado na auditoria. **Contém dados pessoais e hashes de senha:** nunca compartilhar publicamente.
- O dump inclui DDL e dados, omitindo colunas geradas nas instruções INSERT; testes de restauração devem ser feitos em banco isolado e MySQL/MariaDB compatível.
- Não usar reinstalação nem comandos DELETE manuais em produção para contornar um bloqueio.
- O evento ZERAMENTO_BASE_OPERACIONAL fica no histórico de auditoria com autor, contagens, nome e hash do backup.

## Testes
A suíte de integração tests/teste_zeramento_integracao.php requer banco vazio, terminando em _test, e variável RESET_TEST_DB=1. O GitHub Actions executa esse teste independentemente dos bancos eleitorais; nenhuma rotina do teste deve atingir produção.

**A presença da função no código não constitui autorização institucional para zerar um pleito em andamento.**
