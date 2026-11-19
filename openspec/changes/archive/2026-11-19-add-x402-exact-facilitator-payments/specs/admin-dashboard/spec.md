## ADDED Requirements

### Requirement: Origem do pagamento no faturamento e na telemetria
O sistema MUST registrar a origem de cada pagamento (`tx_hash`, `facilitator`, `session_pass` ou `sandbox`) nos eventos de telemetria e exibi-la no painel, somando ao faturamento apenas pagamentos liquidados.

#### Scenario: Pagamento facilitado contabilizado
- **WHEN** um pagamento é liquidado via facilitador
- **THEN** o evento é gravado com origem `facilitator`, valor real e hash de liquidação, e o faturamento total e do dia aumentam pelo valor pago

#### Scenario: Falha de liquidação não gera faturamento
- **WHEN** o facilitador rejeita a autorização ou fica indisponível
- **THEN** o evento é gravado como falha com o motivo, sem alterar o faturamento

### Requirement: Visibilidade de falhas de pagamento
O sistema MUST exibir no painel a contagem de falhas de pagamento por categoria (prova inválida, autorização expirada, replay, facilitador indisponível), para que nenhum evento fique fora da distribuição de status.

#### Scenario: Categorias de falha visíveis
- **WHEN** o administrador abre o painel após falhas de pagamento
- **THEN** cada categoria de falha aparece com sua contagem, e a soma das barras de status corresponde ao total de consultas

