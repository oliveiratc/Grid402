# open-data-utilities Delta Specification

## ADDED Requirements

### Requirement: Consulta Cadastral de CNPJ da Pessoa Jurídica
O sistema SHALL disponibilizar a consulta de situação cadastral e atributos públicos da Pessoa Jurídica a partir de um número de CNPJ de 14 dígitos.

#### Scenario: Validação matemática prévia rejeita CNPJ inválido sem emitir cobrança
- **GIVEN** uma requisição enviada para `/api/v1/cnpj?cnpj=11111111111111` ou CNPJ com dígitos verificadores matematicamente incorretos
- **WHEN** o servidor processa a validação antes da cobrança
- **THEN** o sistema SHALL retornar HTTP 400 Bad Request imediatamente sem emitir desafio HTTP 402 ou debitar o pagador.

#### Scenario: Consulta sem pagamento retorna desafio x402 de 0.0020 USDC
- **GIVEN** um número de CNPJ matematicamente válido informado sem o cabeçalho `X-Payment`
- **WHEN** a requisição é enviada para `/api/v1/cnpj?cnpj=00000000000191`
- **THEN** o sistema SHALL retornar status HTTP 402 Payment Required com `price_usdc` fixado em 0.0020 e cabeçalho `X-Accept-Payment` com a tarifa correspondente.

#### Scenario: Consulta com pagamento x402 válido retorna dados normalizados de PJ
- **GIVEN** uma transação válida de 0.0020 USDC ou mais liquidada na Base para a carteira do oráculo informada no cabeçalho `X-Payment`
- **WHEN** a requisição é processada com sucesso
- **THEN** o sistema SHALL retornar HTTP 200 com os dados cadastrais da PJ (Razão Social, Situação, CNAE, Endereço e Capital Social) descartando intencionalmente quaisquer dados de pessoas físicas (QSA/Sócios).

#### Scenario: Resposta rápida a partir do cache local atômico
- **GIVEN** um CNPJ que já foi consultado e armazenado há menos de 7 dias
- **WHEN** uma nova consulta paga é realizada
- **THEN** o sistema SHALL responder em menos de 100ms a partir do arquivo JSON persistente local sem acionar o upstream externo.

#### Scenario: CNPJ não localizado na base pública da Receita
- **GIVEN** um CNPJ matematicamente válido que não existe no cadastro nacional
- **WHEN** o upstream público confirma a inexistência da inscrição
- **THEN** o sistema SHALL retornar HTTP 404 Not Found com mensagem estruturada.

