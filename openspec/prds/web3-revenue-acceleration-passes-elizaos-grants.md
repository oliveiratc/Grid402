# PRD: Aceleração de Faturamento Web3 (Passes On-Chain, Plugin ElizaOS e Base Grants)

**Status:** Em revisão  
**Data:** 2026-11-11  
**Autor:** Thiago + Antigravity  

---

## 1. Contexto e problema
O **Grid402** é um hub operacional de dados públicos e utilitários de rede para agentes autônomos de IA e clientes M2M, operando sob o protocolo HTTP x402 na rede Base Mainnet.

Apesar da infraestrutura robusta (19 micro-serviços, 184 consultas processadas, 100 desafios HTTP 402 emitidos), o **faturamento total registrado é de US$ 0.0000**. O modelo atual apresenta três gargalos críticos:
1. **Ticket unitário irrisório:** Cobrar $0.0005 por chamada requer 2.000 consultas para faturar US$ 1,00.
2. **Atrito de transação individual:** Cada consulta exige uma assinatura e transação on-chain separada ou cai no modo sandbox gratuito (`X-Payment: demo`).
3. **Distribuição ausente nos frameworks dominantes de agentes:** O principal framework de agentes cripto autônomos com carteiras on-chain ativas na Base é o **ElizaOS** (ai16z), onde o Grid402 ainda não possui um plugin oficial publicado.
4. **Captação de ecossistema inexplorada:** A Base Foundation e o programa Coinbase Developer Platform (CDP) financiam projetos pioneiros de comércio agêntico (*Agentic Commerce*) na Base, mas o Grid402 ainda não submeteu seu dossiê formal de candidatura a grants.

---

## 2. Objetivos
- **O1:** Habilitar a compra de passes de saldo em lote diretamente na interface web via Web3 (MetaMask / Coinbase Smart Wallet / Rainbow na rede Base), elevando o ticket médio de $0.0005 para **US$ 2.00, US$ 5.00 e US$ 20.00** por transação.
- **O2:** Disponibilizar o pacote oficial `@grid402/plugin-elizaos` (ou `plugin-grid402`) para que agentes ElizaOS possam consultar mercados, compliance e utilitários liquidando pagamentos com carteira própria.
- **O3:** Estruturar e produzir o dossiê executivo completo para submissão imediata ao **Base Ecosystem Grants** e **Coinbase Developer Platform (CDP)**, visando captação de recursos e destaque oficial no ecossistema Base.

---

## 3. Não objetivos (fora do escopo)
- Não implementaremos gateways de pagamento fiduciário tradicional (Pix, Stripe, cartão de crédito), mantendo a arquitetura 100% Web3 on-chain.
- Não criaremos novos micro-serviços do zero nesta entrega; utilizaremos o catálogo existente de 19 endpoints.
- Não alteraremos o algoritmo de hashing HMAC do Session Pass já validado em `services/pass.php`.

---

## 4. Público-alvo e personas
1. **Operadores de Agentes de IA:** Desenvolvedores e criadores de agentes (CrewAI, LangChain, ElizaOS) que desejam alimentar seus bots com dados de câmbio, cripto e compliance sem limites de taxa e sem burocracia de cadastros.
2. **Desenvolvedores Web3 & B2B:** Devs que precisam de APIs rápidas para validação cadastral (CNPJ/LEI/OFAC) e preferem pagar 5 ou 20 USDC de uma vez via carteira on-chain.
3. **Avaliadores de Grants da Base/Coinbase:** Membros da equipe de ecossistema da Base e CDP que buscam casos reais de utilitários M2M funcionando com micropagamentos x402.

---

## 5. Histórias de usuário
- **HU01:** Como operador de agente, quero conectar minha carteira na página inicial do Grid402 e comprar um pacote de 5 USDC de créditos, para obter um token de passe imediato sem assinar uma transação para cada requisição.
- **HU02:** Como desenvolvedor de agentes ElizaOS, quero instalar o plugin do Grid402 via npm no meu agente, para que ele consulte câmbio e antecedentes de compliance usando a carteira CDP/EVM do agente.
- **HU03:** Como gestor do projeto Grid402, quero dispor de um dossiê técnico e pitch prontos para submeter nos formulários do Base Grants e Coinbase CDP, para atrair aporte e visibilidade institucional.

---

## 6. Requisitos funcionais

| ID | Requisito | Prioridade |
|---|---|---|
| **RF01** | Criar componente visual "Web3 One-Click Top-Up" no portal (`index.php`), permitindo seleção entre 3 tiers: Starter (2 USDC / 4.000 reqs), Pro (5 USDC / 10.000 reqs) e Fleet (20 USDC / 50.000 reqs). | Must |
| **RF02** | Integrar fluxo EIP-1193 / Coinbase Smart Wallet no frontend para aprovar e transferir o valor em USDC na Base (Chain ID 8453) para a carteira oráculo do Grid402 (`0xa31c31069c43ac1c0075a93c325b0dcd7744356c`). | Must |
| **RF03** | Criar endpoint na API (`/api/v1/pass/checkout` ou chamada a `/api/v1/pass?tx=0x...`) que recebe o hash confirmado, verifica on-chain via RPC Base, registra o passe em `private_data/session_passes.json` e retorna o token `pass_g402_...`. | Must |
| **RF04** | Exibir o token gerado em modal ou card de sucesso com botão de cópia rápida em 1 clique e instrução de uso no cabeçalho `X-Payment`. | Must |
| **RF05** | Desenvolver o pacote TypeScript `integrations/elizaos/` com ações para: `GET_FOREX_RATE`, `GET_CRYPTO_PRICE`, `GET_GLOBAL_MARKETS`, `CHECK_SANCTIONS_OFAC`, `LOOKUP_LEI` e `LOOKUP_CNPJ`. | Must |
| **RF06** | Adicionar suporte no plugin ElizaOS para auto-resolução do cabeçalho `X-Payment` (consumindo Session Pass existente ou injetando txHash da carteira do agente). | Must |
| **RF07** | Produzir a documentação do plugin com instruções de instalação, configuração de variáveis e exemplos de runtime no ElizaOS. | Must |
| **RF08** | Elaborar o documento de candidatura executiva `docs/BASE_GRANTS_AND_CDP_DOSSIER.md` contendo formulários preenchidos, métricas reais, arquitetura x402, diferenciais e links de verificação. | Must |

---

## 7. Requisitos não funcionais
- **Segurança:** As transações USDC devem ser verificadas on-chain contra a Base Mainnet (contrato oficial USDC `0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913`) e carteira oráculo do Grid402 antes de creditar qualquer saldo. Anti-replay garantido por travamento de disco `flock`.
- **Performance:** A emissão do passe após a confirmação on-chain deve responder em menos de 800ms.
- **Compatibilidade:** O widget frontend deve funcionar perfeitamente em navegadores desktop e mobile (Coinbase Wallet App, MetaMask Mobile, Phantom, Rainbow).
- **Zero-DB:** Manter a persistência 100% baseada no arquivo `session_passes.json` atômico sem dependência de banco de dados SQL.

---

## 8. Fluxos e UX

### 8.1. Fluxo de Compra Web3 Top-Up
1. O usuário acessa `grid402.com.br` e visualiza o card "Abastecer Saldo On-Chain (Base)".
2. Clica em "Conectar Carteira". O sistema detecta o provider EIP-1193 ou abre o modal da Coinbase Smart Wallet.
3. Se a rede não for Base Mainnet (8453), solicita a troca de rede automaticamente (`wallet_switchEthereumChain`).
4. O usuário escolhe o plano (2 USDC, 5 USDC ou 20 USDC) e clica em "Comprar Créditos".
5. A carteira solicita a assinatura da transferência ERC-20 de USDC.
6. Uma vez transmitida e confirmada na rede, o frontend envia o `txHash` para `/api/v1/pass?tx={txHash}`.
7. O backend valida a transação no RPC da Base e emite o token `pass_g402_{id}_{hmac}`.
8. O frontend apresenta o card verde com o token gerado, validade e botão "Copiar Token".

### 8.2. Estados de Erro
- Usuário rejeita a transação na carteira: mensagem amigável sem quebrar o fluxo.
- Saldo insuficiente de USDC ou ETH (gas): alerta orientando a abastecer USDC na Base.
- RPC temporariamente indisponível: mecanismo de retry seguro.

---

## 9. Métricas de sucesso
- **Conversão de Faturamento:** Sair de US$ 0.0000 para as primeiras transações pagas de $2, $5 e $20 USDC liquidadas on-chain.
- **Adoção do ElizaOS:** Pacote `integrations/elizaos` compilado, testado e publicado, pronto para adoção por agentes da comunidade.
- **Submissão de Grants:** Dossiê submetido no Base Ecosystem Grants e no portal CDP da Coinbase.

---

## 10. Riscos, dependências e premissas
- **Dependência do RPC Base:** O backend depende de um RPC público/privado da Base para checar o recibo da transação. Já há fallbacks configurados em `config.php`.
- **Aprovação do Token USDC:** A transferência direta via `transfer(to, amount)` de USDC simplifica o fluxo e evita a necessidade de transação dupla (`approve` + `transferFrom`), economizando gas do usuário.
- **Premissa de Liquidez:** O usuário possui USDC na rede Base para adquirir os pacotes.

---

## 11. Questões em aberto
- [x] Faixas de valores dos passes: 2 USDC (4.000 reqs), 5 USDC (10.000 reqs), 20 USDC (50.000 reqs).
- [x] Endpoints prioritários do plugin ElizaOS: Mercados (Forex, Cripto, Global) + Compliance (OFAC, LEI, CNPJ).
- [x] Foco dos Grants: Base Ecosystem Grants + Coinbase CDP.

