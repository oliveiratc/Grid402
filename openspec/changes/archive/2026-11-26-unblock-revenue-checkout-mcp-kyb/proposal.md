# Proposal

## Why

A auditoria de faturamento e audiência de 26/11/2026 (`openspec/prds/revenue-unblock-and-real-telemetry.md`) demonstrou que o Grid402 acumula apenas US$ 4,4005 em receita porque os poucos usuários humanos e agentes reais que alcançam a plataforma enfrentam travas técnicas e de conversão evitáveis: Solana anunciada como ativa apesar de estar sem ATA SPL USDC e com RPC retornando HTTP 403, bugs no Top-Up Web3 da Home com risco de perda de `txHash`, vazamentos residuais de conformidade x402 v1/v2 e CORS, checkout Pix disparando em 250ms antes da leitura e usando e-mail do próprio domínio do vendedor, ausência de opção fiat em USD no `/pass`, persistência de Session Pass quebrada em três chaves incompatíveis, erros HTTP 429 sem oferta do cupom `MCPTRY50` em inglês e `/dossie-kyb` fora da navbar com prévia placeholder. Resolver esse pacote de travas primeiro (Change 1 de 2) garante que o funil de receita em `public_html/` converta de forma confiável antes de escalar a distribuição externa.

## What Changes

- **Ocultação consistente de Solana até ativação futura (RF01):** omitir Solana da oferta padrão x402 (`accepts[]`), dos seletores de UI (`/pass`, Home Top-Up, `/catalog`) e dos metadados/manifestos de descoberta em `public_html/` enquanto `GRID402_SOLANA_X402_ENABLED` for `false`, preservando o suporte interno atrás da flag para reativação futura.
- **Confiabilidade do Top-Up Web3 na Home (RF02):** remover a duplicação de `copyOracleAddress()` e do ID `btn-copy-oracle` em `public_html/index.php` e adicionar retry com backoff (mínimo 5 tentativas) em `startWeb3Topup()`, mantendo o link do `txHash` visível e preenchido no campo de validação manual em caso de falha ou lentidão de RPC.
- **Conformidade estrita x402 v1/v2 e CORS completo (RF03, RF04):** emitir sempre URL absoluta em `resource` no x402 v1, normalizar `paymentPayload.accepted` no x402 v2 (`amount` string, `network` CAIP-2 canônico e espelhamento de chaves válidas em `extra`) antes de acionar PayAI, ampliar `Access-Control-Allow-Headers` em `/api/v1/*` e `/mcp`, e suportar `X-Payment-Signature` e `Payment` na leitura de credenciais de `public_html/mcp.php`.
- **Correção do checkout Mercado Pago / Pix e saída Cartão USD via RapidAPI (RF05, RF06):** remover o disparo automático de `startMpPix()` em 250ms ao abrir `/pass?method=pix`, validar disponibilidade do token Mercado Pago na UI de `/pass` e `/dossie-kyb`, eliminar o fallback `pagador@grid402.online` em `public_html/services/mercadopago.php`, usar `data.qr_mime` dinamicamente em `dossie_kyb.php` e exibir CTA claro de Cartão USD via RapidAPI no `/pass` e na Home.
- **Persistência unificada e uso imediato do Session Pass (RF07):** criar helper/chave unificada (localStorage + cookie `SameSite=Lax`, com leitura retrocompatível das chaves antigas) compartilhada por Home, `/pass`, `/dossie-kyb` e `/payment-dashboard`, e habilitar execução direta com Session Pass no Playground da Home.
- **Conversão de `429`/`402` com `MCPTRY50` em inglês e melhorias no Playground Web3 (RF08, RF11):** enriquecer as respostas HTTP `429` e orientações `402` com o cupom `MCPTRY50` em inglês + link `/pass` + RapidAPI USD, tratar `429` no Playground com card dedicado (sem loop de `402`) e diferenciar cancelamento EIP-1193 (`4001`) de falhas técnicas reais em `payPlaygroundWithWeb3()`.
- **Evolução do `/mcp` hospedado (RF09):** negociar dinamicamente `protocolVersion` no handshake `initialize`, oferecer 1 chamada gratuita de auto-trial diário por IP (`mcp_sandbox`) nas ferramentas pagas e retornar orientações de pagamento em inglês destacando `MCPTRY50` e `/pass`.
- **Destravamento comercial do `/dossie-kyb` (RF10):** incluir link `/dossie-kyb` na navbar pública global (`public_html/includes/nav.php`), substituir a prévia placeholder por uma amostra real completa (CNPJ público exemplo com todos os blocos e visualização/impressão PDF) e criar o novo tier Pix BRL **`micro` (R$ 4,90 -> 50 créditos = 5 Dossiês KYB, validade 90 dias)** ancorando o Starter R$ 12 (400 créditos = 40 dossiês).

## Capabilities

### New Capabilities
<!-- Nenhuma capability totalmente nova; todas as evoluções refinam capabilities existentes no repositório. -->

### Modified Capabilities
- `x402-exact-settlement`: ocultar Solana de `accepts[]` quando `GRID402_SOLANA_X402_ENABLED=false`, garantir `resource` absoluto no x402 v1, normalizar `paymentPayload.accepted` no x402 v2 para `deepEqual` estrito do PayAI e ampliar CORS em `/api/v1/*`.
- `mercadopago-checkout`: adicionar o pacote Pix BRL `micro` (R$ 4,90 / 50 créditos / 90 dias) alinhado à escala `v2_5000`, remover auto-disparo Pix em 250ms, validar configuração do gateway na UI, eliminar o fallback `pagador@grid402.online` e adicionar CTA de Cartão USD via RapidAPI.
- `human-revenue-funnel`: atualizar `/dossie-kyb` com amostra real completa (dados e PDF) e ancoragem Micro R$ 4,90 vs Starter R$ 12, corrigir Top-Up Web3 da Home (`copyOracleAddress` + retry/preservação de `txHash`), unificar persistência de Session Pass entre páginas e enriquecer respostas `429`/`402` com `MCPTRY50` em inglês.
- `web-playground-verifier`: adicionar modo de execução via Session Pass no Playground, exibir tratamento dedicado para HTTP `429` com oferta `MCPTRY50` (sem loop de `402`) e distinguir cancelamento de carteira (`4001`) de erros reais no pagamento Web3.
- `mcp-server`: adicionar negociação dinâmica de `protocolVersion`, CORS completo + aliases de header (`X-Payment-Signature`, `Payment`), auto-trial diário por IP (`mcp_sandbox`) e mensagens de pagamento/cota em inglês promovendo `MCPTRY50` no servidor hospedado `public_html/mcp.php`.
- `responsive-navigation`: incluir o link `/dossie-kyb` (`Dossiê KYB`) na navegação pública compartilhada (`public_html/includes/nav.php`) em desktop e mobile.

## Impact

- **PRD de origem:** `openspec/prds/revenue-unblock-and-real-telemetry.md` (RF01 a RF11; Change 1 de 2).
- **Escopo de código:** exclusivamente `public_html/` (`index.php`, `x402.php`, `facilitator.php`, `pass.php`, `dossie_kyb.php`, `payment_dashboard.php`, `catalog.php`, `mcp.php`, `includes/nav.php`, `services/mercadopago.php`, `services/multichain.php`, `assets/js/i18n.js` e manifestos estáticos em `public_html/` que citam Solana) + novos testes automatizados em `tests/`.
- **Fora desta change:** edição/publicação dos pacotes npm/PyPI (`mcp/`, `sdk/`, `python/`) e evolução da telemetria/cockpit `/admin` + RapidAPI cap + tradução `llms.txt` (cobertos pela Change 2: `real-telemetry-admin-and-discovery-hardening`).
- **Invariantes preservadas:** Zero-DB com `flock(LOCK_EX)`, carteira canônica `0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90`, paridade das 27 ferramentas MCP (`v2.0.0`), escala `v2_5000` e proibição do caractere `&` nos 4 arquivos de documentação validados por `tests/test_agentkit_integration.php`.
