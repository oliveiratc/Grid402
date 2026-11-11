# Proposta: Aceleração de Faturamento Web3 (Passes On-Chain, Plugin ElizaOS e Base Grants)

## Por quê
O Grid402 possui 19 micro-serviços operando sob o protocolo HTTP x402 na Base Mainnet, com 184 consultas e mais de 100 desafios HTTP 402 emitidos, porém o faturamento acumulado atual é de **US$ 0.0000**.
Conforme detalhado no PRD [`openspec/prds/web3-revenue-acceleration-passes-elizaos-grants.md`](file:///c:/Antigravity/Projetos/Grid402/openspec/prds/web3-revenue-acceleration-passes-elizaos-grants.md), a ausência de um fluxo de compra rápida em lote de saldo Web3, a falta de distribuição para agentes no framework dominante (**ElizaOS**) e a falta de submissão formal a grants e incentivos da Base Foundation e Coinbase CDP limitam a conversão e receita do projeto.

## O que muda
1. **Portal Web (`public_html/index.php`):**
   - Inclusão do componente "Web3 One-Click Top-Up" no portal permitindo a compra direta de passes de crédito (Starter: 2 USDC / 4.000 reqs, Pro: 5 USDC / 10.000 reqs, Fleet: 20 USDC / 50.000 reqs).
   - Integração com provedores EIP-1193 e Coinbase Smart Wallet na rede Base (Chain ID 8453), efetuando transferência direta de USDC para a carteira do oráculo.
   - Apresentação do token gerado `pass_g402_...` com 1 clique de cópia e instruções visuais.
2. **Backend de Emissão de Passes (`public_html/services/pass.php` & `public_html/index.php`):**
   - Garantia de suporte à emissão on-demand de passes via rota `/api/v1/pass` mediante verificação do recibo da transação on-chain na Base.
   - Cálculo automático de créditos conforme o valor transferido (2 USDC = 4.000 reqs, 5 USDC = 10.000 reqs, 20 USDC = 50.000 reqs).
3. **Integração ElizaOS (`integrations/elizaos/`):**
   - Criação do pacote oficial em TypeScript compatível com o ecossistema ElizaOS (`@elizaos/core`).
   - Implementação de ações para consulta de Câmbio (Forex), Cripto, Mercados Globais, Sanções OFAC, LEI Global e CNPJ da Receita Federal.
   - Gerenciamento automático de cabeçalho `X-Payment` suportando Session Pass e transações on-chain.
   - Documentação de uso e instalação no `README.md`.
4. **Dossiê Base Grants & Coinbase CDP (`docs/BASE_GRANTS_AND_CDP_DOSSIER.md`):**
   - Produção do dossiê completo para submissão de grants de ecossistema na Base e programa Agentic Commerce da Coinbase.

## Impacto
- **Specs afetadas:** `x402-payment-oracle`, `agent-discovery`.
- **Código afetado:**
  - `public_html/index.php` (frontend top-up widget e rota `/api/v1/pass`)
  - `public_html/services/pass.php` (cálculo e emissão de pacotes)
  - `integrations/elizaos/*` (novo pacote e plugin ElizaOS)
  - `docs/BASE_GRANTS_AND_CDP_DOSSIER.md` (novo dossiê técnico e pitch)

