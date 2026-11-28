# Spec Delta

## ADDED Requirements

### Requirement: Session pass credit minting shall match exact settled value
O facilitador e o gateway de pagamento SHALL creditar saldo de Session Pass estritamente proporcional ao montante financeiro liquidado na transação, rejeitando discrepâncias entre autorização e liquidação.

#### Scenario: Pagamento Permit2 com valor autorizado superior ao liquidado
- **WHEN** um cliente envia uma autorização Permit2 com montante máximo autorizado superior aos 2 USDC exigidos para o tier Starter
- **THEN** o sistema liquida apenas os 2 USDC no contrato e credita exatamente 400 créditos no Session Pass

#### Scenario: Transacao Solana com campos JSON divergentes
- **WHEN** um payload x402 para Solana contém campos JSON de valor divergentes da instrução SPL verificada
- **THEN** o sistema calcula os créditos com base exclusiva na instrução on-chain autenticada contra a conta ATA e mint oficiais

### Requirement: Automated payers shall enforce transaction caps and canonical recipients
Clientes e auto-payers automatizados SHALL validar o destinatário contra allowlist canônica por rede e aplicar tetos rígidos de segurança antes de assinar pagamentos.

#### Scenario: Resposta HTTP 402 apontando para carteira desconhecida
- **WHEN** uma resposta HTTP 402 recebida pelo auto-payer contém endereço de `payTo` fora da lista oficial da rede
- **THEN** o auto-payer aborta a transação e retorna erro de segurança sem assinar a transferência

#### Scenario: Resposta HTTP 402 com valor acima do teto maximo configurado
- **WHEN** uma cobrança x402 solicita montante superior ao limite configurado por transação
- **THEN** o auto-payer rejeita a liquidação automática informando limite excedido
