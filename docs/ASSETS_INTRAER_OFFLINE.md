# Auditoria de CSS local — INTRAER (11/11/2026)

O projeto tinha duas folhas de estilo carregadas por CDN no cabeçalho: Bootstrap 5.3.3 e Bootstrap Icons 1.11.3. Além disso, o rodapé carregava o Bootstrap JavaScript externo. Todos foram transferidos para arquivos distribuídos pelo próprio projeto, preservando as versões.

## Inventário
- `public/assets/vendor/bootstrap/5.3.3/css/bootstrap.min.css` — Bootstrap 5.3.3
- `public/assets/vendor/bootstrap-icons/1.11.3/bootstrap-icons.min.css` — Bootstrap Icons 1.11.3
- `public/assets/vendor/bootstrap-icons/1.11.3/fonts/bootstrap-icons.woff2` — fonte preferencial
- `public/assets/vendor/bootstrap-icons/1.11.3/fonts/bootstrap-icons.woff` — fonte alternativa
- `public/assets/css/style.css` — estilos institucionais COMARA
- `public/assets/vendor/bootstrap/5.3.3/js/bootstrap.bundle.min.js` — JavaScript para modais, menus e popovers
- Licenças de redistribuição nos diretórios locais de cada fornecedor.

O arquivo `views/layouts/header.php` referencia os três CSS locais na ordem Bootstrap, ícones e estilos COMARA. O arquivo `views/layouts/footer.php` referencia o JavaScript local. As fontes são relativas ao CSS de ícones. Não há solicitação de fontes externas ou CSS por CDN.

## Auditoria individual das views

As páginas abaixo não incluem CSS remoto próprio e compartilham o cabeçalho da aplicação:

- Administração: `views/admin/backups.php`, `chefias.php`, `efetivo.php`, `fotos.php`, `logs.php`, `pleito.php` e `vencedores.php`
- Autenticação: `views/auth/login.php` e `primeiro_acesso.php`
- Painel: `views/dashboard/index.php`
- Fase 1: `views/fase1/form_ficha.php` e `index.php`
- Fase 2: `views/fase2/index.php`
- Fase 3: `views/fase3/comprovante.php` e `urna.php`
- Fase 4: `views/fase4/index.php`
- Fase 5: `views/fase5/index.php`
- Relatórios: `views/relatorios/aguardando_homologacao.php`, `estatisticas.php`, `placa_hall.php` e `site_oficial.php`

Layouts: `views/layouts/header.php` tinha os dois CSS remotos (substituídos); `views/layouts/footer.php` tinha Bootstrap JavaScript remoto (substituído). Estilos inline presentes em algumas páginas não requerem Internet e foram mantidos.

## Configuração do servidor

As URLs começam com `/assets/` a partir da raiz do domínio para funcionar em todas as rotas. A distribuição física está sob `public/assets/`.

Preferência: configure o DocumentRoot do domínio para a pasta `public`. Para Apache/LiteSpeed hospedado na raiz do repositório, o `.htaccess` da raiz recebeu o encaminhamento de `/assets/` para `public/assets/`. O Nginx não lê `.htaccess`; se o DocumentRoot do Nginx continuar na raiz do repositório, a STI deve configurar a entrega de `/assets/` a partir de `public/assets/`.

## Checklist após deploy

1. Confirme HTTP 200 para CSS Bootstrap, CSS de ícones, CSS COMARA, fontes WOFF/WOFF2 e JavaScript Bootstrap.
2. Abra login, painel, Fases 1–5 e relatórios e confira que os arquivos são atendidos pelo hostname interno, não por CDN.
3. No navegador, inspecione a aba Rede e atualize sem cache.
4. Execute `php tests/teste_assets_intranet.php` para auditoria estática e teste os caminhos reais do Nginx/Apache.

**Limite:** a auditoria é de código-fonte e o CI testa um servidor HTTP local. Ela não substitui a conferência no servidor INTRAER, cuja configuração Nginx e deploy não foram alterados por este PR.
