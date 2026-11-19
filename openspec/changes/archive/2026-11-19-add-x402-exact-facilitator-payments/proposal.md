# Proposal

## Why

O Grid402 anuncia no desafio HTTP 402 o esquema padrão do x402 v2 (`scheme: exact`, `x402Version: 2`), mas só aceita um `tx_hash` já liquidado no cabeçalho `X-Payment`. Clientes x402 padrão (Coinbase AgentKit, SDKs oficiais do x402) respondem ao desafio com uma assinatura EIP-3009 em base64 (`PAYMENT-SIGNATURE` ou `X-PAYMENT`), o que o servidor rejeita como `tx_hash` inválido (HTTP 400). Resultado observado: centenas de consultas e desafios 402 emitidos, nenhum faturamento real. Como a carteira do oráculo não tem ETH de gás, a liquidação não pode ser feita pelo próprio servidor; é preciso delegá-la a um facilitador.

## What Changes

- O servidor passa a aceitar o pagamento x402 padrão: ler `PAYMENT-SIGNATURE` (v2) e `X-PAYMENT` em base64 (v1), decodificar o payload e validá-lo contra os `paymentRequirements` do serviço.
- Verificação e liquidação do pagamento por um **facilitador externo configurável** (padrão: Coinbase CDP), via `/verify` e `/settle`, sem exigir ETH de gás na carteira do oráculo.
- Após liquidação com sucesso, o serviço responde HTTP 200 e inclui o cabeçalho `PAYMENT-RESPONSE` (e `X-PAYMENT-RESPONSE`) com o recibo de liquidação (hash da transação on-chain).
- O faturamento e a telemetria registram o pagamento facilitado com o valor real e o `tx_hash` de liquidação; o anti-replay também cobre o `nonce` da autorização EIP-3009.
- O fluxo atual (`X-Payment: 0x<tx_hash>`, `pass_g402_...` e `demo`) **continua funcionando**; o servidor distingue o formato da prova recebida.
- O painel administrativo passa a diferenciar a origem do pagamento (`tx_hash` direto ou facilitador).
- Falhas do facilitador (indisponível, assinatura inválida, valor insuficiente) retornam erro claro e nunca liberam o dado nem registram faturamento.
- Configuração por variáveis de ambiente: URL do facilitador, credenciais e modo de operação (`disabled`, `verify_only` ou `settle`).

Fora de escopo: liquidação pelo próprio servidor (relayer com chave e ETH), liquidação em lote, outras redes além da Base e outros ativos além do USDC.

## Capabilities

### New Capabilities
- `x402-exact-settlement`: aceitação de pagamentos x402 `exact` (EIP-3009) com verificação e liquidação por facilitador externo, anti-replay por `nonce`, recibo de liquidação e degradação segura quando o facilitador falha.

### Modified Capabilities
- `x402-payment-oracle`: o servidor deixa de aceitar apenas `tx_hash` em `X-Payment`; passa a aceitar também o payload x402 padrão, mantendo os fluxos existentes.
- `admin-dashboard`: a telemetria e o faturamento passam a identificar a origem do pagamento (`tx_hash` ou facilitador) e exibir falhas de liquidação.

## Impact

- Código: `public_html/index.php` (extração da prova de pagamento), `public_html/x402.php` (validação, desafio 402 e novo cliente do facilitador), `public_html/metrics.php`, `public_html/admin.php`, `public_html/config.php` e `.env.example`.
- Documentação: `llms.txt`, `llms-full.txt`, `openapi.json`, `.well-known/mcp.json` e SDKs (`sdk/node`, `sdk/python`, `mcp/x402_payer.mjs`) para refletir o novo fluxo.
- Dependências: nenhuma nova no PHP (chamadas HTTP com cURL já utilizado). Serviço externo novo: o facilitador (Coinbase CDP exige API key; verificar limites e custos antes de contratar).
- Segurança: segredos do facilitador apenas em `.env`, nunca em log; resposta ao cliente nunca expõe credenciais.
- Operação: exige deploy em produção e configuração das credenciais do facilitador; a carteira recebedora continua a mesma.

