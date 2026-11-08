# Proposta: Modernizacao e Monitoramento Avancado do Portal Administrativo

## Por que

Com as recentes expansoes do Grid402 (novos servicos financeiros `workdays`, `indicators`, `holidays`, oraculo criptografico ECDSA secp256k1 e simulador de Sandbox no Playground), o portal administrativo atual (`public_html/admin.php`) ficou desatualizado em relacao a capacidade e ao modelo operacional do hub. O painel monitora apenas 8 servicos antigos, nao distingue consultas em Sandbox de pagamentos reais, nao exibe saldos on-chain da carteira oficial na Base e carece de filtros, exportacao de relatorios e alteracao segura de senha. Veja `prds/grid402-admin-portal-upgrade.md` para a especificacao completa de produto.

## O que muda

- **Cobertura Integral dos 11 Micro-Servicos**: Inclusao de `workdays`, `indicators` e `holidays` nos graficos e tabelas de volume.
- **Auditoria On-Chain na Base Mainnet**: Integracao via RPC para consulta em cache (60s) dos saldos em USDC e ETH da carteira oficial do oraculo (`0xa31c31069c43ac1c0075a93c325b0dcd7744356c`).
- **Classificacao de Trafego e Buffer de 100 Eventos**: Registro do atributo `type` (`paid`, `sandbox`, `challenge_402`, `blocked_403`, `free`) em `metrics.php` com expansao do buffer de telemetria para 100 eventos e badges visuais dedicados.
- **Filtros Reativos e Busca no Feed**: Filtragem instantanea no navegador por tipo e termo de busca (IP ou hash) sem recarregar a pagina.
- **Exportacao de Relatorio CSV**: Endpoint `/admin?api=export_csv` e botao no dashboard para download de planilha formatada em UTF-8 com BOM.
- **Alteracao Segura de Senha do Operador**: Modal autenticado no cabecalho para troca de senha com validacao da credencial atual e novo hash Bcrypt (custo 11).
- **Roteamento Resiliente**: Fallback no `public_html/index.php` direcionando requisicoes para `/admin` e `/dashboard` diretamente ao `admin.php`.

## Impacto

- **Specs afetadas**: `admin-dashboard`
- **Codigo afetado**:
  - `public_html/admin.php`: Implementacao do novo layout, modal de senha, exportacao CSV, cards on-chain e filtros reativos.
  - `public_html/metrics.php`: Registro de `type` e expansao do limite de eventos recentes para 100.
  - `public_html/index.php`: Fallback para as rotas `/admin` e `/dashboard`.
  - `tests/test_admin_dashboard.php`: Novos testes cobrindo exportacao CSV, troca de senha, classificacao de tipos e saldo on-chain.

