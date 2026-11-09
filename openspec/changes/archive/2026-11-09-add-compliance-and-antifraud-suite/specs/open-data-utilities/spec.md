# open-data-utilities Delta Specification

## ADDED Requirements

### Requirement: CGU Sanctions Check API (CEIS and CNEP)
O sistema SHALL disponibilizar consulta unificada de sanções administrativas no Cadastro de Empresas Inidôneas e Suspensas (CEIS) e Cadastro Nacional de Empresas Punidas (CNEP) via endpoint `/api/v1/sanctions`.

#### Scenario: Clean corporate entity without sanctions
- **GIVEN** um CNPJ válido sem registros no CEIS e CNEP
- **WHEN** uma requisição é enviada para `/api/v1/sanctions?cnpj=<cnpj>`
- **THEN** o sistema retorna HTTP 200 contendo `has_sanctions: false`, lista vazia de sanções e objeto `_oracle_proof` assinado em secp256k1

#### Scenario: Sanctioned corporate entity with active penalties
- **GIVEN** um CNPJ com penalidade ativa cadastrada pela CGU
- **WHEN** uma requisição é enviada para `/api/v1/sanctions?cnpj=<cnpj>`
- **THEN** o sistema retorna HTTP 200 contendo `has_sanctions: true`, órgão sancionador, motivo legal da penalidade e período de vigência

#### Scenario: Missing or malformed CNPJ for sanctions
- **GIVEN** um CNPJ ausente ou com quantidade incorreta de dígitos
- **WHEN** uma requisição é enviada para `/api/v1/sanctions`
- **THEN** o sistema retorna HTTP 400 Bad Request com descrição de erro

---

### Requirement: Simples Nacional and MEI Verification API
O sistema SHALL disponibilizar verificação oficial de opção pelo regime tributário do Simples Nacional e enquadramento como Microempreendedor Individual (MEI) via endpoint `/api/v1/simples`.

#### Scenario: Query Simples Nacional and MEI status
- **GIVEN** um CNPJ válido informado no parâmetro `cnpj`
- **WHEN** uma requisição é enviada para `/api/v1/simples?cnpj=<cnpj>`
- **THEN** o sistema retorna HTTP 200 informando booleanos de opção pelo Simples e SIMEI, situação cadastral e assinatura `_oracle_proof`

#### Scenario: Unsettled x402 payment for Simples query
- **GIVEN** uma requisição sem comprovante de pagamento ou cabeçalho sandbox
- **WHEN** uma requisição é enviada para `/api/v1/simples?cnpj=<cnpj>`
- **THEN** o sistema retorna HTTP 402 com o desafio de pagamento de 0.001 USDC na rede Base

---

### Requirement: Consolidated Compliance and Risk Score 360 API
O sistema SHALL calcular um score consolidado de conformidade e integridade empresarial (0 a 100) via endpoint `/api/v1/compliance`, combinando dados cadastrais da Receita Federal, sanções administrativas da CGU e histórico societário.

#### Scenario: High integrity approved company
- **GIVEN** um CNPJ ativo, sem sanções e com mais de 2 anos de atividade regular
- **WHEN** uma requisição é enviada para `/api/v1/compliance?cnpj=<cnpj>` com autorização válida
- **THEN** o sistema retorna HTTP 200 com `score` entre 85 e 100, `status: "APROVADO"` e array de flags vazio

#### Scenario: High risk rejected company due to active sanctions
- **GIVEN** um CNPJ com sanção punitiva ou situação inativa
- **WHEN** uma requisição é enviada para `/api/v1/compliance?cnpj=<cnpj>` com autorização válida
- **THEN** o sistema retorna HTTP 200 com `score` inferior a 50, `status: "REJEITADO"` e apontamentos específicos no array de flags

