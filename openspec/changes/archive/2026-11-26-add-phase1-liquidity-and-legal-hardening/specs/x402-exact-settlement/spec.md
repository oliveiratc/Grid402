# x402-exact-settlement Delta Specification

## MODIFIED Requirements

### Requirement: Verificação e liquidação por facilitador externo
O sistema MUST verificar e liquidar a autorização via roteamento Dual-Facilitator (`Coinbase CDP` e `PayAI`) e só liberar o dado após liquidação confirmada. Na Base (`eip155:8453`) em modo `auto`, SHALL priorizar o Coinbase CDP quando configurado, com fallback para PayAI apenas na etapa pré-`/settle` (`/verify`) em falha de infraestrutura/quota; Polygon, Arbitrum e Solana SHALL rotear diretamente via PayAI.

#### Scenario: Pagamento liquidado com sucesso
- **WHEN** o facilitador confirma a verificação e a liquidação da autorização
- **THEN** o sistema entrega o payload do serviço com HTTP 200 e inclui o cabeçalho `PAYMENT-RESPONSE` com o recibo de liquidação, incluindo o hash da transação on-chain

#### Scenario: Facilitador rejeita a autorização
- **WHEN** o facilitador responde que a assinatura é inválida ou a liquidação falhou
- **THEN** o sistema responde HTTP 402 com o motivo informado, não entrega o dado e não registra faturamento

#### Scenario: Facilitador indisponível
- **WHEN** o facilitador não responde dentro do tempo limite ou retorna erro de servidor
- **THEN** o sistema responde HTTP 503 com `Retry-After`, não entrega o dado, não registra faturamento e mantém a autorização reutilizável

#### Scenario: Pagamento na Base liquidado via Coinbase CDP primário
- **GIVEN** o gateway em produção com `GRID402_CDP_API_KEY_ID` e `GRID402_CDP_API_KEY_SECRET` configurados e `GRID402_FACILITATOR_ROUTING=auto`
- **WHEN** um cliente envia payload x402 v2 válido na rede `eip155:8453` e o CDP confirma `/verify` e `/settle`
- **THEN** o sistema SHALL verificar e liquidar no Coinbase CDP Facilitator, entregar HTTP 200 com `PAYMENT-RESPONSE` e registrar `cdp` como facilitador utilizado

#### Scenario: Fallback automático na Base para PayAI quando CDP falha no /verify
- **GIVEN** o gateway na Base (`eip155:8453`) com credenciais CDP configuradas e o endpoint `/v2/x402/verify` do CDP retornando indisponibilidade (`503`, `429` ou timeout)
- **WHEN** um cliente envia payload x402 v2 válido para a Base
- **THEN** o sistema SHALL executar fallback automático para o facilitador PayAI na etapa `/verify`, concluir `/settle` via PayAI e entregar HTTP 200 sem perda da requisição

#### Scenario: Rejeição por mérito no /verify não aciona fallback
- **GIVEN** o facilitador primário respondendo HTTP 200 com `isValid: false` (assinatura ou autorização inválida do cliente)
- **WHEN** o gateway processa a verificação
- **THEN** o sistema SHALL liberar o nonce, responder HTTP 402 com o motivo da rejeição e SHALL NOT tentar o facilitador de fallback

#### Scenario: Roteamento de Polygon, Arbitrum e Solana diretamente para PayAI
- **GIVEN** credenciais CDP configuradas e `GRID402_SOLANA_X402_ENABLED=true`
- **WHEN** um cliente envia payload x402 v2 válido em `eip155:137`, `eip155:42161` ou `solana:5eykt4UsFv8P8NJdTREpY1vzqKqZKvdp`
- **THEN** o sistema SHALL rotear `/verify` e `/settle` diretamente para o facilitador PayAI sem acionar o CDP

### Requirement: Configuração e degradação segura
O sistema MUST permitir configurar os facilitadores por variáveis de ambiente (`GRID402_FACILITATOR_ROUTING`, URLs/paths de CDP e PayAI, credenciais CDP e modo `disabled`, `verify_only` ou `settle`), mantendo segredos fora de respostas e logs. Em erro ambíguo ou timeout durante `/settle`, SHALL manter o nonce em estado `pending`, retornar HTTP 503 com `Retry-After` e **MUST NOT** reenviar ao facilitador secundário.

#### Scenario: Facilitador desabilitado
- **WHEN** o modo é `disabled` e um cliente envia payload x402 padrão
- **THEN** o sistema responde HTTP 402 com instruções do fluxo por `tx_hash`, sem erro interno

#### Scenario: Segredos nunca expostos
- **WHEN** ocorre qualquer erro na comunicação com o facilitador
- **THEN** nenhuma credencial aparece na resposta HTTP, nos logs nem na telemetria

#### Scenario: Erro ambíguo ou timeout durante /settle bloqueia fallback para evitar dupla cobrança
- **GIVEN** uma autorização na Base aprovada no `/verify` pelo facilitador primário (`cdp`) cujo passo `/settle` sofre timeout ou retorna HTTP 503 sem confirmação
- **WHEN** o gateway trata a resposta do `/settle`
- **THEN** o sistema SHALL manter o nonce marcado como `pending` em `used_nonces.json`, responder HTTP 503 com `Retry-After` e SHALL NOT acionar o facilitador fallback (`payai`) no `/settle`

## ADDED Requirements

### Requirement: Conformidade Estrita da Extensão extensions.bazaar v2 para Coinbase CDP Bazaar
O sistema MUST emitir em todos os desafios HTTP 402 (`PAYMENT-REQUIRED`) metadados `resource` e `extensions.bazaar` compatíveis com o Coinbase CDP Bazaar: `resource.url` em HTTPS absoluto canônico, `description` técnica com no máximo `500` caracteres sem padrões de prompt-injection, e `extensions.bazaar.schema` totalmente *inlined* sem `$ref` ou `$id` externos.

#### Scenario: Desafio 402 atende todos os critérios estruturais do CDP Bazaar
- **GIVEN** qualquer dos 24 endpoints pagos do catálogo do Grid402
- **WHEN** um cliente solicita o endpoint sem cabeçalho de pagamento e recebe HTTP 402 com cabeçalho `PAYMENT-REQUIRED`
- **THEN** o payload decodificado SHALL conter `resource.url` começando com `https://grid402.online/`, `resource.description` com até 500 caracteres sem texto de prompt-injection, e `extensions.bazaar.schema` sem chaves `$ref` ou `$id` externas

### Requirement: Validação Automatizada CLI de Conformidade CDP Bazaar
O repositório MUST prover o script CLI `scripts/validate_cdp_bazaar.php` para auditar offline todos os endpoints pagos contra as restrições estruturais do CDP Bazaar v2 e, com `--live`, validar cada recurso contra `https://api.cdp.coinbase.com/platform/v2/x402/validate`.

#### Scenario: Validação offline CLI aprova todos os endpoints pagos do catálogo
- **GIVEN** o catálogo oficial em `public_html/config.php` e os geradores em `public_html/x402.php`
- **WHEN** um operador executa `php scripts/validate_cdp_bazaar.php --json`
- **THEN** o script SHALL sair com código `0` reportando aprovação estrutural de todos os endpoints pagos
