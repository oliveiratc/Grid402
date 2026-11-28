# Spec Delta

## ADDED Requirements

### Requirement: Idempotent session pass issuance shall not leak token to third parties
O endpoint `/api/v1/pass-emitir` SHALL entregar o `pass_token` apenas na primeira emissão bem-sucedida. Em chamadas subsequentes com o mesmo `txHash`, o sistema SHALL retornar status `already_issued` sem expor o `pass_token` em texto claro, a menos que uma prova de posse da carteira pagadora seja fornecida.

#### Scenario: Repeticao de requisicao com txHash ja utilizado
- **WHEN** um cliente envia uma requisição para `/api/v1/pass-emitir` com um `txHash` que já emitiu um Session Pass ativo
- **THEN** o sistema responde com HTTP 200 contendo `{"ok": false, "status": "already_issued"}` e não inclui o campo `pass_token`

#### Scenario: Primeira emissao com txHash valido
- **WHEN** um cliente envia um `txHash` on-chain válido ainda não utilizado
- **THEN** o sistema emite o Session Pass e retorna `{"ok": true, "pass_token": "pass_g402_..."}`

### Requirement: Telemetry recording shall redact session pass bearer tokens
O sistema de telemetria e métricas SHALL sanitizar e redigir qualquer token portador `pass_g402_...` antes de gravá-lo no campo `tx_hash` de eventos recentes.

#### Scenario: Requisicao autenticada via Session Pass registrada na telemetria
- **WHEN** uma requisição é consumida utilizando um token `pass_g402_...`
- **THEN** o arquivo `metrics.json` e as respostas da API admin registram o evento com identificador anonimizado ou redigido, sem conter o segredo em claro

### Requirement: Oracle and session pass secrets shall fail closed without fallbacks
O assinador do oráculo e o cálculo de HMAC de passes SHALL operar exclusivamente com chaves ativas do ambiente, rejeitando chaves padrão públicas fora do modo de teste.

#### Scenario: Inicializacao em producao sem chave do oraculo
- **WHEN** o sistema executa fora do ambiente de teste e nenhuma chave privada de oráculo está configurada
- **THEN** a emissão de atestações do oráculo falha com HTTP 503 e não recorre à chave pública padrão do repositório
