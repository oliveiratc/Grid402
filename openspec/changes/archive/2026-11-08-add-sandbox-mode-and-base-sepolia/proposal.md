# Proposta: Modo Sandbox e Suporte a Base Sepolia no Protocolo x402

## Por quê
Conforme detalhado no PRD [`openspec/prds/grid402-sandbox-mode-and-base-sepolia.md`](file:///c:/Antigravity/Projetos/Grid402/openspec/prds/grid402-sandbox-mode-and-base-sepolia.md), desenvolvedores de agentes de IA e engenheiros de automação precisam rodar testes unitários, pipelines de CI/CD e simulações locais sem custo antes de utilizarem fundos reais na Base Mainnet. O Modo Sandbox elimina a barreira financeira de desenvolvimento enquanto protege a infraestrutura com rate limit defensivo.

## O que muda
- O gateway x402 passa a reconhecer o cabeçalho `X-Payment: demo` e `X-Payment: sandbox`.
- O módulo de validação blockchain passa a suportar consultas de recibo contra o nó RPC público da testnet **Base Sepolia** (Chain ID 84532) quando um hash de 64 caracteres não for localizado na Mainnet ou quando explicitamente parametrizado.
- É implementado um controle atômico de taxa (Rate Limit) de 20 requisições por hora por endereço IP para chamadas em modo sandbox, persistido em `sandbox_rates.json`.
- Respostas de sandbox recebem a flag `"sandbox": true` e o cabeçalho HTTP `X-Payment-Mode: sandbox`.
- O console interativo do portal web ganha o botão `🧪 Simular Pagamento (Sandbox)` para desbloqueio imediato em 1 clique.

## Impacto
- **Specs afetadas:** `x402-payment-oracle`
- **Código afetado:**
  - `public_html/config.php`
  - `public_html/x402.php`
  - `public_html/index.php`

