# Tasks: Modernizacao e Monitoramento Avancado do Portal Administrativo

## 1. Backend e Telemetria

- [x] 1.1 Atualizar `public_html/metrics.php` para classificar o atributo `type` (`paid`, `sandbox`, `challenge_402`, `blocked_403`, `free`) e expandir o buffer circular de consultas recentes para 100 eventos
- [x] 1.2 Implementar funcao de leitura de saldos on-chain (USDC ERC-20 e ETH) via RPC da Base com cache de 60 segundos em `public_html/admin.php`
- [x] 1.3 Adicionar endpoint `/admin?api=export_csv` para geracao e download de relatorio financeiro consolidado em formato CSV com BOM UTF-8
- [x] 1.4 Adicionar acao `/admin?action=change_password` para alteracao segura de senha com validacao da credencial atual e hash Bcrypt
- [x] 1.5 Implementar fallback de roteamento direto para `/admin` e `/dashboard` no gateway `public_html/index.php`

## 2. Interface Web do Dashboard

- [x] 2.1 Atualizar o painel de volume por micro-servico em `public_html/admin.php` para abranger os 11 servicos operantes (`workdays`, `indicators`, `holidays`, `forex`, `crypto`, `dns`, `ssl`, `asn`, `cep`, `cnpj`, `summary`)
- [x] 2.2 Criar card de KPI dedicado para Saldo On-Chain da Carteira do Oraculo exibindo saldos em USDC e ETH e indicativo de saude
- [x] 2.3 Implementar badges visuais coloridos no feed para cada tipo de trafego (Pago x402, Sandbox demo, Desafio 402, Bloqueado 403 e Gratuito)
- [x] 2.4 Implementar barra de filtros reativos em Vanilla JS e campo de busca instantanea por IP ou hash no feed recente
- [x] 2.5 Criar modal interativo de alteracao de senha no cabecalho do dashboard com tratamento amigavel de erros e sucesso
- [x] 2.6 Adicionar botao de exportacao "Exportar Relatorio CSV" na interface com acionamento em 1 clique

## 3. Testes Automatizados e Homologacao

- [x] 3.1 Atualizar suite de testes automatizados `tests/test_admin_dashboard.php` cobrindo classificacao de 100 eventos, saldos on-chain, exportacao CSV e troca de senha
- [x] 3.2 Executar a suite completa E2E `tests/test_e2e_full.php` garantindo integridade de todas as rotas
- [x] 3.3 Validar a especificacao da change em modo estrito via `openspec validate upgrade-admin-portal --strict`

