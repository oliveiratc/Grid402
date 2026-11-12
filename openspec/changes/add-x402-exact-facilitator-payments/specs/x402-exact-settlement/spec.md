## Purpose
Permitir que clientes x402 padrão paguem pelos micro-serviços do Grid402 com autorização assinada (EIP-3009, esquema `exact`), com verificação e liquidação delegadas a um facilitador externo, sem exigir ETH de gás na carteira do oráculo.

## ADDED Requirements

### Requirement: Extração do payload x402 padrão
O sistema MUST aceitar o payload de pagamento x402 padrão enviado em base64 nos cabeçalhos `PAYMENT-SIGNATURE` (v2) ou `X-PAYMENT` (v1) e decodificá-lo antes da validação.

#### Scenario: Payload v2 válido no cabeçalho PAYMENT-SIGNATURE
- **WHEN** um cliente envia `PAYMENT-SIGNATURE` com JSON base64 contendo `x402Version`, `accepted` e `payload` de autorização
- **THEN** o sistema decodifica o payload e inicia a verificação contra os requisitos de pagamento do serviço solicitado

#### Scenario: Payload malformado
- **WHEN** o cabeçalho contém base64 inválido ou JSON sem os campos obrigatórios
- **THEN** o sistema responde HTTP 400 com mensagem clara indicando o campo ausente, sem acionar o facilitador e sem registrar faturamento

### Requirement: Validação local dos requisitos de pagamento
O sistema MUST validar localmente, antes de acionar o facilitador, que a autorização corresponde ao serviço: rede Base (`eip155:8453`), ativo USDC, destinatário igual à carteira do oráculo, valor maior ou igual ao preço do serviço e janela de validade vigente.

#### Scenario: Valor menor que o preço do serviço
- **WHEN** a autorização assinada informa valor inferior ao preço do serviço
- **THEN** o sistema responde HTTP 402 com o desafio do serviço, sem acionar o facilitador

#### Scenario: Destinatário ou rede incorretos
- **WHEN** a autorização aponta para outra carteira, outro ativo ou outra rede
- **THEN** o sistema responde HTTP 402 indicando o requisito não atendido, sem acionar o facilitador

#### Scenario: Autorização expirada
- **WHEN** o momento atual está fora do intervalo `validAfter` e `validBefore` da autorização
- **THEN** o sistema responde HTTP 402 informando que a autorização expirou

### Requirement: Verificação e liquidação por facilitador externo
O sistema MUST verificar e liquidar a autorização por meio do facilitador configurado, e só liberar o dado do serviço após a liquidação confirmada.

#### Scenario: Pagamento liquidado com sucesso
- **WHEN** o facilitador confirma a verificação e a liquidação da autorização
- **THEN** o sistema entrega o payload do serviço com HTTP 200 e inclui o cabeçalho `PAYMENT-RESPONSE` com o recibo de liquidação, incluindo o hash da transação on-chain

#### Scenario: Facilitador rejeita a autorização
- **WHEN** o facilitador responde que a assinatura é inválida ou a liquidação falhou
- **THEN** o sistema responde HTTP 402 com o motivo informado, não entrega o dado e não registra faturamento

#### Scenario: Facilitador indisponível
- **WHEN** o facilitador não responde dentro do tempo limite ou retorna erro de servidor
- **THEN** o sistema responde HTTP 503 com `Retry-After`, não entrega o dado, não registra faturamento e mantém a autorização reutilizável

### Requirement: Anti-replay por nonce de autorização
O sistema MUST impedir a reutilização de uma mesma autorização, identificada por pagador e `nonce`, de forma atômica sob concorrência.

#### Scenario: Reuso do mesmo nonce
- **WHEN** uma autorização com pagador e `nonce` já liquidados é enviada novamente
- **THEN** o sistema responde HTTP 403 indicando replay e não aciona o facilitador

#### Scenario: Requisições simultâneas com o mesmo nonce
- **WHEN** várias requisições paralelas enviam a mesma autorização
- **THEN** exatamente uma é processada e as demais recebem HTTP 403

### Requirement: Configuração e degradação segura
O sistema MUST permitir configurar o facilitador por variáveis de ambiente (URL, credenciais e modo `disabled`, `verify_only` ou `settle`), mantendo os segredos fora do código e dos logs.

#### Scenario: Facilitador desabilitado
- **WHEN** o modo é `disabled` e um cliente envia payload x402 padrão
- **THEN** o sistema responde HTTP 402 com instruções do fluxo por `tx_hash`, sem erro interno

#### Scenario: Segredos nunca expostos
- **WHEN** ocorre qualquer erro na comunicação com o facilitador
- **THEN** nenhuma credencial aparece na resposta HTTP, nos logs nem na telemetria

