# Proposta: Amostra Parcial de Dados (Teaser Data) no Desafio HTTP 402

## Por quê
Conforme documentado no PRD [`openspec/prds/grid402-teaser-data-preview-x402.md`](file:///c:/Antigravity/Projetos/Grid402/openspec/prds/grid402-teaser-data-preview-x402.md), a conversão de requisições gratuitas em pagas sofre com o atrito da "compra no escuro". Clientes (agentes autônomos e humanos no console web) que recebem HTTP 402 Payment Required precisam de prova concreta da existência e qualidade dos dados cadastrais antes de liquidar micro-transações em USDC na rede Base.

## O que muda
- O gerador de respostas HTTP 402 (`grid402_respond_402`) passa a aceitar dados opcionais de amostra (`preview`) e lista de campos bloqueados (`locked_fields`).
- A API de CNPJ (`/api/v1/cnpj`) gera uma prévia aberta com `cnpj`, `razao_social`, `nome_fantasia`, `uf` e `situacao_cadastral`, listando os campos enriquecidos como bloqueados.
- A API de CEP (`/api/v1/cep`) gera uma prévia aberta com `cep`, `cidade` e `uf`, listando bairro, logradouro e complemento como bloqueados.
- Erros de validação (formato incorreto ou registro inexistente) continuam sendo retornados com HTTP 400 ou 404 antes de qualquer emissão de 402, impedindo cobranças indevidas.
- O console interativo do portal web renderiza um card visual de destaque para a prévia com botão direto para desbloqueio completo.

## Impacto
- **Specs afetadas:** `x402-payment-oracle` e `open-data-utilities`
- **Código afetado:**
  - `public_html/x402.php`
  - `public_html/index.php`
  - `public_html/services/cnpj.php`
  - `public_html/services/cep.php`

