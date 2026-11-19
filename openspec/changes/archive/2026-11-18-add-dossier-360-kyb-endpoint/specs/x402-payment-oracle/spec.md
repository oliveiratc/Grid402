## ADDED Requirements

### Requirement: Rota Canônica de Dossiê 360º KYB (`/api/v1/dossier`)
O sistema MUST disponibilizar a rota `GET /api/v1/dossier?cnpj={cnpj}` aceitando CNPJ com ou sem pontuação e retornando HTTP 400 (`InvalidCNPJ`) quando o CNPJ for matematicamente inválido.

#### Scenario: Consulta de CNPJ inválido retorna HTTP 400
- GIVEN um cliente autenticado em Modo Sandbox (`X-Payment: demo`)
- WHEN envia `GET /api/v1/dossier?cnpj=12345678000100`
- THEN o sistema responde HTTP 400 com `error: "InvalidCNPJ"` sem debitar créditos.

#### Scenario: Consulta de CNPJ formatado ou sem máscara é aceita
- GIVEN um CNPJ válido (`00.000.000/0001-91` ou `00000000000191`)
- WHEN o cliente envia `GET /api/v1/dossier?cnpj=00.000.000/0001-91` com pagamento válido ou sandbox
- THEN o sistema normaliza o CNPJ para 14 dígitos e retorna HTTP 200 com `service: "dossier"`.

### Requirement: Precificação Premium x402 de 0.0500 USDC e Teaser Gratuito no 402
O sistema MUST exigir pagamento de `0.0500` USDC (50000 unidades raw na Base Mainnet ou 10 créditos de Session Pass) para o endpoint `/api/v1/dossier`, e devolver uma prévia segura (Teaser) no desafio HTTP 402.

#### Scenario: Chamada sem pagamento retorna HTTP 402 com preço 0.0500 USDC e Teaser
- GIVEN uma requisição `GET /api/v1/dossier?cnpj=00000000000191` sem cabeçalho de pagamento
- WHEN o gateway x402 processa a chamada
- THEN retorna HTTP 402 com `price_usdc: "0.0500"`, `credits_cost: 10`, carteira oficial do oráculo e bloco `teaser_data` contendo razão social, UF e indicação de existência de sanções.

#### Scenario: Liquidação via Session Pass debita exatamente 10 créditos
- GIVEN um Session Pass válido com saldo de 20 créditos
- WHEN o agente envia `GET /api/v1/dossier?cnpj=00000000000191` com cabeçalho `X-Session-Pass`
- THEN o sistema retorna HTTP 200, debita exatamente 10 créditos (`_billing.credits_debited: 10`) e reporta saldo restante de 10 créditos.

### Requirement: Consolidação de Fontes Oficiais e Motor de Veredito para IA (`verdict`)
O sistema MUST consolidar dados da Receita Federal (CNPJ), Simples Nacional/MEI, Sanções CGU (CEIS/CNEP) e Compliance Score 360, produzindo o bloco `verdict` com `status` (`APPROVED`, `FLAGGED`, `REJECTED`), `score` (0 a 100), `alerts` e `summary` executivo.

#### Scenario: Empresa ativa, sem sanções e score >= 70 recebe veredito APPROVED
- GIVEN uma empresa com situação cadastral `ATIVA`, sem registros no CEIS/CNEP e score >= 70
- WHEN o serviço `grid402_service_dossier` consolida o dossiê
- THEN `verdict.status` é `"APPROVED"`, `verdict.risk_level` é `"LOW"` e `verdict.summary` confirma a aptidão cadastral.

#### Scenario: Empresa com sanção ativa no CEIS/CNEP ou situação inapta recebe veredito REJECTED
- GIVEN um CNPJ sancionado no CEIS/CNEP (ex: `11111111000191`) ou com situação diferente de `ATIVA`
- WHEN o serviço `grid402_service_dossier` consolida o dossiê
- THEN `verdict.status` é `"REJECTED"`, `verdict.risk_level` é `"HIGH"` e `verdict.alerts` lista explicitamente a restrição encontrada.

#### Scenario: Empresa ativa sem sanções mas com score < 70 ou alerta de atenção recebe veredito FLAGGED
- GIVEN uma empresa `ATIVA` sem sanções na CGU, porém com score inferior a 70 ou alerta cadastral de atenção
- WHEN o serviço `grid402_service_dossier` consolida o dossiê
- THEN `verdict.status` é `"FLAGGED"`, `verdict.risk_level` é `"MEDIUM"` e `verdict.alerts` detalha os pontos para revisão.

### Requirement: Garantia de Zero PII e Assinatura Criptográfica ECDSA (`_oracle_proof`)
O sistema MUST excluir qualquer dado pessoal de sócios (Zero PII / sem QSA ou CPF) e assinar toda resposta HTTP 200 do Dossiê 360º com `_oracle_proof` (ECDSA secp256k1) e `_meta`.

#### Scenario: Resposta do Dossiê 360º não contém PII e inclui prova criptográfica
- GIVEN uma chamada bem-sucedida a `GET /api/v1/dossier?cnpj=00000000000191`
- WHEN a resposta JSON é inspecionada
- THEN não existem chaves `qsa`, `socios` ou `cpf` na resposta, e os blocos `_oracle_proof` (com `signature`, `data_hash` e `algorithm`) e `_meta` estão presentes.
