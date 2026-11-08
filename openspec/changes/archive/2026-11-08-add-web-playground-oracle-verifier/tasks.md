# Tasks: Playground Interativo e Verificador Visual de Oraculo

## 1. Backend: Micro-Endpoint de Verificacao

- [x] 1.1 Implementar rota `/api/v1/verify` (POST e GET) em `public_html/index.php` integrando `grid402_verify_oracle_proof`
- [x] 1.2 Adicionar validacao defensiva de tamanho maximo de payload (64 KB) e tratamento de erros de JSON malformado
- [x] 1.3 Criar teste automatizado unitario em PHP `tests/test_verify_endpoint.php` cobrindo payload valido, payload adulterado e assinatura forjada

## 2. Interface Web: Estrutura HTML e Estilos CSS

- [x] 2.1 Adicionar botao de navegacao rapida "Playground" no cabecalho de `public_html/index.php` com rolagem suave
- [x] 2.2 Criar markup HTML da secao de Playground com seletores dos 11 presets, alternador de modo (Sandbox vs Desafio x402) e area de verificacao manual
- [x] 2.3 Criar estilos CSS responsivos no tema dark-mode para os botoes de presets, visualizador de JSON com syntax highlighting nativo e Card de Auditoria

## 3. Logica do Cliente: JavaScript Interativo

- [x] 3.1 Implementar logica assincrona em Vanilla JS para execucao de presets em Sandbox gratuito (`X-Payment: demo`) e simulador de HTTP 402
- [x] 3.2 Implementar motor de verificacao com chamada assincrona para `/api/v1/verify` e renderizacao em tempo real do Card de Auditoria em 3 etapas
- [x] 3.3 Adicionar funcionalidade de copia rapida do JSON retornado e medicao da latencia de verificacao em milissegundos

## 4. Documentacao de Maquina

- [x] 4.1 Atualizar `public_html/openapi.json` documentando o endpoint `/api/v1/verify` com schemas de entrada e saida
- [x] 4.2 Atualizar `public_html/llms.txt` documentando o endpoint de verificacao para consumo por agentes de IA
- [x] 4.3 Validar ausencia absoluta do caractere e-comercial em todos os arquivos de documentacao e interface

## 5. Homologacao E2E e Validacao OpenSpec

- [x] 5.1 Atualizar e executar a suite de testes E2E `tests/test_e2e_full.php` cobrindo o endpoint `/api/v1/verify`
- [x] 5.2 Validar todas as especificacoes com `openspec validate add-web-playground-oracle-verifier --strict`

