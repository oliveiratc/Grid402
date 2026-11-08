# PRD: Playground Interativo e Verificador Visual de Oraculo

**Status:** Em revisao
**Data:** 2026-11-08
**Autor:** Usuario + Agente

---

## 1. Contexto e problema

O ecossistema Grid402 conta com 11 micro-servicos de utilitarios de rede e dados publicos operando sob o protocolo HTTP 402 na rede Base, agora com emissao de prova matematica criptografica de oraculo (`_oracle_proof` ECDSA secp256k1).

Contudo, para um desenvolvedor ou operador de agente de IA que visita a pagina principal do Grid402 pela primeira vez, a experiencia ainda e predominantemente descritiva. Nao ha uma forma imediata de:
1. Experimentar as consultas em tempo real diretamente pelo navegador sem escrever codigo ou configurar terminal;
2. Visualizar como funciona o desafio HTTP 402 e o teaser data de forma didatica;
3. Auditar a autenticidade da assinatura de oraculo colando um JSON ou executando uma chamada de teste com feedback visual claro.

Essa barreira cognitiva reduz a credibilidade percebida e a taxa de conversao de novos desenvolvedores dispostos a integrar suas carteiras e pagar por consultas na Base Mainnet.

---

## 2. Objetivos

- Fornecer uma secao interativa de **Playground e Verificador de Oraculo** diretamente na landing page (`index.php`), permitindo testar qualquer servico em menos de 2 cliques.
- Oferecer presets rapidos de 1 clique para os 11 micro-servicos em modo Sandbox gratuito com inspecao visual da resposta JSON formatada.
- Disponibilizar um simulador do desafio HTTP 402 demonstrando a estrutura do teaser data, preco em USDC e carteira recebedora oficial.
- Exibir um **Card de Auditoria Tecnica em 3 Etapas** (Hash dos Dados, Carteira Oficial BaseScan e Assinatura Criptografica secp256k1) com medicao de tempo de verificacao em milissegundos.
- Criar o micro-endpoint backend `/api/v1/verify` para validar provas de oraculo via requisicao HTTP leve, compativel com qualquer cliente web.
- Preservar 100% da arquitetura Zero-DB, sem cookies, sem banco de dados e com tempo de carregamento inicial da pagina inferior a 50 milissegundos.

---

## 3. Nao objetivos (fora do escopo)

- Nao sera criada conexao Web3 com carteiras de extensao de navegador (como MetaMask ou Coinbase Wallet) para liquidacao direta no front-end nesta entrega (a liquidacao on-chain permanece via SDKs, agentes CDP e servidor MCP).
- Nao serao armazenados historicos de consultas do usuario no navegador ou no servidor (principio Zero-DB e privacidade integral).
- Nao serao adicionados frameworks pesados de frontend (como React, Vue ou bibliotecas pesadas de terceiros via CDN).

---

## 4. Publico-alvo e personas

- **Desenvolvedor de Agentes Autonomos:** Precisa inspecionar o formato do payload e a integridade da assinatura antes de adicionar o SDK ou servidor MCP ao seu pipeline.
- **Engenheiro Web3 e DeFi:** Deseja auditar a carteira do oraculo e comprovar que os dados financeiros (como Selic, CDI, PTAX e Feriados ANBIMA) sao matematicamente autenticos.
- **Desenvolvedor Backend:** Busca entender a dinamica do protocolo HTTP 402 com amostra gratuita antes de integrar chamadas de dados em suas aplicacoes.

---

## 5. Historias de usuario

- Como desenvolvedor, quero clicar em um botao de preset no Playground para testar o servico de dias uteis ou indicadores e ver a resposta JSON completa com a prova de oraculo imediatamente.
- Como desenvolvedor, quero alternar para o modo "Desafio x402" para visualizar como a API responde com codigo 402 e amostra parcial quando nenhuma taxa e enviada.
- Como auditor, quero colar qualquer JSON retornado pelo Grid402 no campo de verificacao e conferir em tempo real se os dados sao autenticos e se a carteira emissora e a oficial.
- Como usuario, quero ver o link direto da carteira do oraculo no BaseScan e o tempo de checagem da assinatura para validar a confiabilidade do hub.

---

## 6. Requisitos funcionais

| ID | Requisito | Prioridade |
|---|---|---|
| RF01 | Secao de Playground e Verificador integrada visualmente na Landing Page (`index.php`) no tema dark-mode existente. | Must |
| RF02 | Botao de navegacao rapida "Playground" no cabecalho superior da pagina, direcionando suavemente para a secao correspondente. | Must |
| RF03 | Barra de selecao de micro-servicos com 11 presets de teste: Workdays, Indicadores, Feriados, CNPJ, CEP, Forex, Crypto, DNS, SSL, ASN e Status. | Must |
| RF04 | Alternador visual de modo de execucao: "Modo Sandbox (Gratuito)" e "Simulador Desafio x402 (HTTP 402)". | Must |
| RF05 | Painel de visualizacao de codigo JSON com formatacao colorida, syntax highlighting nativo em CSS e botao de copiar para a area de transferencia. | Must |
| RF06 | Painel de Verificacao Manual: campo de texto textarea para o usuario colar qualquer resposta JSON externa do Grid402. | Must |
| RF07 | Card de Auditoria Tecnica apresentando o status da verificacao em 3 etapas claras: 1. Integridade do Hash de Dados; 2. Carteira Oficial BaseScan; 3. Validade da Assinatura ECDSA. | Must |
| RF08 | Indicador de status visual: badge verde ("Oraculo Autentico Grid402") em caso de sucesso, ou badge vermelho ("Adulteracao Detectada" / "Assinatura Invalida") em caso de divergencia. | Must |
| RF09 | Micro-endpoint backend `/api/v1/verify` (POST e GET) que aceita o payload JSON e retorna o resultado da verificacao estruturado utilizando `oracle_signer.php`. | Must |
| RF10 | Medicao e exibicao do tempo de latencia da verificacao em milissegundos. | Should |

---

## 7. Requisitos nao funcionais

- **Desempenho:** Carregamento do HTML e CSS da landing page em menos de 50 ms. Execucao de consultas sandbox do playground em menos de 400 ms.
- **Arquitetura Zero-DB:** Nenhuma sessao, cookie, armazenamento em disco ou gravacao em banco de dados. Processamento totalmente em memoria e stateless.
- **Compatibilidade e Responsividade:** Layout fluido adaptavel para desktop, tablets e celulares, mantendo o padrao dark-mode de alta densidade visual (Plus Jakarta Sans e JetBrains Mono).
- **Segurança:** O endpoint de verificacao deve conter validacao defensiva contra entradas malformadas ou JSONs gigantescos (limite maximo de 64 KB no body).
- **Sem Ampersands:** Conformidade estrita com a regra de zero ocorrencias do caractere e-comercial em toda a documentacao, codigo, schemas e textos da interface.

---

## 8. Fluxos e UX

### Fluxo Principal 1: Exploracao com Presets de 1 Clique
1. O usuario clica em "Playground" no menu superior.
2. A tela rola suavemente ate a secao interativa.
3. O usuario clica no preset de um micro-servico (ex: "Dias Uteis ANBIMA" ou "Indicadores Macro").
4. O frontend dispara requisicao assincrona via `fetch` para o endpoint com cabecalho `X-Payment: demo`.
5. O JSON recebido e exibido no painel de saida formatado com JetBrains Mono.
6. O Card de Auditoria Tecnica analisa o `_oracle_proof` automaticamente e exibe os 3 passos verdes com o selo de oraculo verificado.

### Fluxo Principal 2: Simulacao de Desafio HTTP 402
1. O usuario seleciona o modo "Simulador Desafio x402".
2. O usuario clica em "Executar Consulta".
3. A requisicao e enviada sem cabecalho `X-Payment`.
4. O servidor retorna HTTP 402 com as instrucoes de pagamento e teaser data.
5. A interface exibe a resposta destacando a carteira recebedora oficial, o valor em USDC e os dados da amostra gratuita.

### Fluxo Alternativo: Verificacao de JSON Externo Colado
1. O usuario cola um JSON retornado anteriormente por um script ou aplicacao.
2. O usuario clica em "Verificar Autenticidade".
3. O verificador calcula o hash canônico, checa a carteira oficial e valida a assinatura criptografica.
4. Se o JSON foi adulterado (ex: valor alterado), a interface destaca em vermelho o erro `DataHashMismatch`.

---

## 9. Métricas de sucesso

- 100% dos 11 presets de teste executaveis com retorno de dados validos.
- Taxa de acerto de 100% no verificador para payloads legitimos e deteccao imediata de payloads alterados.
- Zero impacto no tempo de carregamento inicial da landing page (< 50ms).
- Aprovacao em 100% dos testes unitarios e suites de homologacao E2E.

---

## 10. Riscos, dependencias e premissas

- **Premissa:** O motor de assinatura `public_html/services/oracle_signer.php` ja implementa a logica matematica necessaria (`grid402_verify_oracle_proof`).
- **Risco:** Requisicoes de navegadores antigos sem suporte a metodos modernos de Web Crypto.
  - **Mitigacao:** Arquitetura hibrida com fallback imediato para o endpoint leve `/api/v1/verify` do backend PHP.
- **Risco:** Abuso de consultas via playground gerando sobrecarga.
  - **Mitigacao:** O modo sandbox do Grid402 ja aplica limitacao transparente de cota por IP (20 consultas por hora).

---

## 11. Questões em aberto

- Nenhuma questao em aberto. Todas as premissas tecnicas e de design foram alinhadas na etapa de Grill-me.
