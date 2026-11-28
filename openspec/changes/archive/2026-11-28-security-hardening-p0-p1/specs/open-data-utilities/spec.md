# Spec Delta

## ADDED Requirements

### Requirement: PDF generation shall operate in linear time and protect sample mode
O serviço de geração de relatórios em PDF SHALL processar quebras de linha em complexidade linear e SHALL utilizar fixture estática pré-definida no servidor para o modo amostra.

#### Scenario: Requisicao em modo amostra ilustrativa
- **WHEN** uma requisição POST é enviada para `/api/v1/dossier/pdf` com a flag de amostra ativa
- **THEN** o sistema gera o PDF utilizando exclusivamente a fixture estática do servidor, ignorando campos de texto enviados pelo cliente

#### Scenario: Payload extenso submetido para conversao em PDF
- **WHEN** um documento com múltiplos parágrafos é enviado para renderização
- **THEN** o cálculo de quebra de linhas processa em tempo linear proporcional ao tamanho do texto sem degradação excessiva de CPU

### Requirement: Workdays projection shall guard against infinite loops
O calculador de dias úteis SHALL validar os limites suportados de calendário antes e durante o laço de cálculo, limitando o número total de iterações.

#### Scenario: Projecao de data fora do intervalo suportado
- **WHEN** uma consulta de dias úteis solicita projeção além do ano 2100 ou antes de 1900
- **THEN** o sistema responde com HTTP 400 informando que a data está fora do intervalo suportado e encerra o processamento
