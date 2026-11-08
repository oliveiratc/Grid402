# Design: Playground Interativo e Verificador Visual de Oraculo

## Context

O Grid402 opera como um hub de micro-servicos de dados abertos sob o protocolo HTTP 402 com emissor criptografico ECDSA secp256k1 implementado em `public_html/services/oracle_signer.php`. A landing page (`public_html/index.php`) e construida em PHP puro gerando HTML responsivo com estilos CSS embutidos em tema dark-mode. Veja `proposal.md` para a motivacao do negocio.

## Goals / Non-Goals

**Goals:**
- Implementar uma interface interativa de Playground e Verificador sem adicionar dependencias pesadas ou frameworks JavaScript (Vanilla JS puro).
- Disponibilizar presets funcionais para os 11 micro-servicos existentes no ecossistema com parametros realistas.
- Oferecer o micro-endpoint `/api/v1/verify` (POST e GET) para validar provas de dados sem estado no servidor em menos de 10 ms.
- Apresentar um Card de Auditoria Tecnica em 3 Etapas com feedback visual instantaneo e medicao de latencia.
- Manter o tempo de carregamento da landing page em menos de 50 ms e preservar 100% da arquitetura Zero-DB.

**Non-Goals:**
- Nao incluir bibliotecas pesadas de Web3 ou gerenciadores de estado no front-end.
- Nao criar conexao via extensao de carteira Web3 nesta entrega.
- Nao persistir logs ou consultas de usuarios em banco de dados ou disco.

## Decisions

### Decisao 1: Interface em Vanilla JS e CSS Nativo dentro do index.php
- **Escolha:** Componentes de interface construidos diretamente com HTML5 semantico, CSS moderno (CSS Grid e Flexbox) e Vanilla JS nativo embutido no `index.php`.
- **Racional:** Preserva a inicializacao ultrarrapida da pagina (< 50 ms), elimina custos de requisicoes externas de bundles (CDN) e garante integracao perfeita com o visual dark-mode existente.
- **Alternativas consideradas:**
  - Carregar React ou Vue via CDN: descartado por inflar o payload da pagina em mais de 150 KB e aumentar o tempo de renderizacao inicial.
  - Pagina separada `/playground`: descartado para manter a navegacao continua e fluida em pagina unica (Single Page).

### Decisao 2: Arquitetura de Verificacao Hibrida com Endpoint Backend `/api/v1/verify`
- **Escolha:** O frontend tenta primeiro uma checagem local de hash canônico e aciona o endpoint leve `/api/v1/verify` no backend.
- **Racional:** O backend ja dispoe do modulo `public_html/services/oracle_signer.php` com o metodo `grid402_verify_oracle_proof()`, que valida a assinatura ECDSA secp256k1 via OpenSSL compilado em C em menos de 1 ms. Isso garante compatibilidade com 100% dos navegadores (inclusive navegadores sem suporte a curvas secp256k1 na Web Crypto API) sem necessidade de bibliotecas JS volumosas.
- **Alternativas consideradas:**
  - Injetar biblioteca JavaScript pesada de curvas elipticas (ex: elliptic ou noble-secp256k1 via CDN): descartado para evitar dependencias externas e atrasos de download.

### Decisao 3: Estrutura do Endpoint `/api/v1/verify`
- **Contrato:**
  - **Metodo:** `POST` com body JSON (ou `GET` com payload codificado na query string para conveniencia).
  - **Limite:** Body restrito defensivamente a 64 KB.
  - **Resposta de Sucesso:**
    ```json
    {
      "valid": true,
      "oracle_address": "0xa31c31069c43ac1c0075a93c325b0dcd7744356c",
      "timestamp": 1794156928,
      "sandbox": true,
      "verification_time_ms": 0.42
    }
    ```
  - **Resposta de Divergencia:**
    ```json
    {
      "valid": false,
      "error": "DataHashMismatch",
      "expected_hash": "0x...",
      "received_hash": "0x...",
      "verification_time_ms": 0.35
    }
    ```

## Risks / Trade-offs

- **[Risco: Abuso de consultas ao vivo pelo Playground]** → O modo sandbox do Grid402 ja impoe limite de taxa de 20 consultas por hora por endereco IP, prevenindo saturacao de infraestrutura.
- **[Risco: JSON malformado colado pelo usuario na area manual]** → Tratamento defensivo no JavaScript com `try/catch` exibindo mensagem amigavel antes do envio ao backend.
- **[Risco: Payload excessivamente grande no endpoint de verificacao]** → Rejeicao com HTTP 413 caso o corpo exceda 64 KB.

## Migration Plan

1. Implementar o despachante da rota `/api/v1/verify` em `public_html/index.php`.
2. Adicionar o HTML, CSS e JavaScript da secao de Playground e Verificador em `public_html/index.php`.
3. Atualizar `public_html/openapi.json` e `public_html/llms.txt`.
4. Criar teste unitario automatizado `tests/test_verify_endpoint.php`.
5. Atualizar a suite E2E `tests/test_e2e_full.php` para cobrir o endpoint de verificacao e simular a interacao do playground.
6. Reempacotar e publicar na Hostinger via MCP `execute`.

