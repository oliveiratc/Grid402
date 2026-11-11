# PRD: Infraestrutura Global M2M e Passes de Sessão x402

**Status:** Em revisão
**Data:** 2026-11-10
**Autor:** Thiago + Antigravity

## 1. Contexto e problema
O Grid402 consolidou uma infraestrutura robusta de oráculos M2M liquidados via protocolo HTTP 402 na Base Mainnet com assinatura criptográfica ECDSA (`secp256k1`). No entanto, parte relevante dos endpoints atuais de alto valor (como CNPJ, CEP, Simples Nacional e Indicadores BCB) é focada no mercado brasileiro. Para que agentes autônomos do mundo inteiro (EUA, Europa, Ásia e ecossistema global Web3/Coinbase AgentKit) adotem o Grid402 como infraestrutura básica obrigatória no dia a dia, dois gargalos precisam ser resolvidos:
1. **Escopo Geográfico:** Agentes internacionais precisam de primitivas universais de identidade corporativa mundial (LEI/ISO 17442), compliance/AML internacional (OFAC/ONU + inspeção de carteiras EVM), notarização criptográfica de decisões M2M e calendário financeiro global (NYSE/Fed, BCE/TARGET2, LSE, B3) com câmbio G10/mundial, sempre baseados em fontes 100% de domínio público e licença aberta (CC0), sem dados pessoais (Zero-PII) e sem riscos jurídicos.
2. **Latência em Alta Frequência:** Pagar uma transação on-chain na Base a cada requisição individual funciona bem para consultas pontuais, mas adiciona 2 a 3 segundos de espera pelo bloco quando um agente precisa executar 50 ou 100 consultas em sequência. Agentes de alta frequência precisam de um **Passe de Sessão Pré-pago (`x402 Session Pass`)**: uma única transação on-chain emite um token assinado pelo Oráculo que desbloqueia dezenas ou centenas de chamadas subsequentes em menos de 15ms.

## 2. Objetivos
- Expandir o catálogo do Grid402 de 14 para **19 micro-serviços operacionais**, adicionando 5 super-endpoints globais baseados exclusivamente em dados abertos/CC0 e protocolos públicos.
- Reduzir a latência de chamadas pagas recorrentes de ~2.500ms (tempo de bloco L2) para **< 50ms** por meio do Passe de Sessão Pré-pago (`/api/v1/pass` e cabeçalho `X-Payment: pass_<token>`).
- Garantir **100% de conformidade jurídica** (Creative Commons Zero - CC0, US Government Public Domain, European Central Bank Open Data e RPC público EVM), sem armazenar ou processar PII (dados pessoais identificáveis).
- Atualizar a interface bilíngue (`PT | EN`), Playground Interativo, Console e manifestos de descoberta (`llms.txt`, `openapi.json`, `x402.json`) com os 19 micro-serviços.

## 3. Não objetivos (fora do escopo)
- Scraping de sites privados, redes sociais ou plataformas protegidas por Termos de Uso restritivos.
- Processamento ou armazenamento de dados pessoais de pessoas físicas (CPF, nomes de cidadãos comuns, e-mails pessoais).
- Uso de bancos de dados relacionais pesados (MySQL/PostgreSQL) — o sistema permanece fiel à arquitetura Zero-DB / JSON atômico com lock exclusivo (`flock`).

## 4. Público-alvo e personas
- **Agentes Autônomos Globais (LangChain, CrewAI, Coinbase AgentKit, Claude MCP, Cursor):** Bots que realizam compras (procurement), auditoria de contratos inteligentes, arbitragem cambial, verificação KYB de fornecedores internacionais e carimbo de tempo de logs.
- **Desenvolvedores Web3 e FinTechs Globais:** Engenheiros que buscam uma API sem cadastro (Zero-Signup) para validar entidades LEI, checar carteiras contra listas de sanções OFAC, calcular liquidação D+N em feriados dos EUA/Europa e notarizar hashes com assinatura ECDSA.

## 5. Histórias de usuário
- Como um **Agente de Procurement Global**, quero consultar o registro mundial **GLEIF LEI** (`/api/v1/lei`) de um fornecedor internacional para verificar se a empresa está ativa, qual sua jurisdição legal e quem é sua controladora antes de fechar um contrato.
- Como um **Agente DeFi / Web3**, quero inspecionar um endereço EVM ou nome de entidade em `/api/v1/ofac` para saber em tempo real se consta na lista pública de sanções internacionais (OFAC SDN) e verificar os metadados on-chain da carteira na Base Mainnet.
- Como um **Agente Autônomo de Auditoria**, quero enviar um hash SHA-256 ou texto para `/api/v1/notarize` e receber um recibo assinado matematicamente (`secp256k1`) pelo Oráculo Grid402 comprovando a existência daquele estado em um instante UTC exato.
- Como um **Agente Financeiro Internacional**, quero consultar `/api/v1/global-markets` para obter cotações oficiais de mais de 30 moedas do Banco Central Europeu (ECB) e verificar se hoje ou uma data futura é dia útil bancário nos EUA (NYSE/Fed), Zona do Euro (TARGET2), Reino Unido (LSE) ou Brasil (B3).
- Como um **Agente de Alta Frequência**, quero enviar `0.05 USDC` uma única vez para `/api/v1/pass` e receber um token `pass_...` com 100 créditos para consumir qualquer micro-serviço do Grid402 instantaneamente sem aguardar novos blocos.

## 6. Requisitos funcionais
| ID | Requisito | Prioridade |
|----|-----------|------------|
| RF01 | **Endpoint `/api/v1/lei` (Global Corporate Identity - GLEIF LEI):** Consultar a API pública CC0 da Global Legal Entity Identifier Foundation (`api.gleif.org/api/v1/lei-records`) por código LEI de 20 caracteres ou busca textual por nome corporativo (`q`), retornando razão social, jurisdição ISO, status do registro, endereço legal e autoridade registradora. Tarifa: `0.0020 USDC` (4 créditos de passe). | Must |
| RF02 | **Endpoint `/api/v1/ofac` (Global Sanctions & Web3 AML Inspector):** Verificar endereço EVM (`0x...`) e/ou nome de entidade contra lista consolidada pública de endereços e entidades sancionadas pelo OFAC SDN (Domínio Público do Tesouro dos EUA) + telemetria on-chain em tempo real na Base Mainnet (saldo ETH/USDC, contagem de transações `nonce` e detecção se é Smart Contract via `eth_getCode`). Tarifa: `0.0020 USDC` (4 créditos de passe). | Must |
| RF03 | **Endpoint `/api/v1/notarize` (Cryptographic Timestamp & Attestation):** Receber via GET ou POST um `hash` (SHA-256) ou `data` (string/JSON), gerar um certificado de carimbo de tempo UTC com `certificate_id` determinístico, `sha256_digest`, `notarized_at_utc`, `epoch_seconds` e bloco `_oracle_proof` assinado via ECDSA `secp256k1`. Tarifa: `0.0005 USDC` (1 crédito de passe). | Must |
| RF04 | **Endpoint `/api/v1/global-markets` (World Financial Calendar & G10/ECB FX):** Retornar taxas de referência cambial globais (USD, EUR, GBP, JPY, CHF, CAD, AUD, CNY, MXN, BRL etc. via dados abertos do BCE/Frankfurter) e calendário determinístico de feriados e dias úteis de mercados globais (`market=US|EU|UK|BR`, `date=YYYY-MM-DD`). Tarifa: `0.0005 USDC` (1 crédito de passe). | Must |
| RF05 | **Endpoint `/api/v1/pass` e Autenticação `X-Payment: pass_<token>` (Prepaid Session Pass):** Permitir emissão de passe pré-pago mediante transação on-chain na Base (`>= 0.0050 USDC`, onde cada `0.0005 USDC` equivale a 1 crédito universal, ex: `0.05 USDC` = 100 créditos, `0.50 USDC` = 1.000 créditos) com validade de 30 dias. Permitir consulta gratuita do saldo do passe via `GET /api/v1/pass?token=pass_...` e débito atômico em qualquer endpoint pago quando enviado no header `X-Payment: pass_...`. | Must |
| RF06 | **Prévia Teaser (HTTP 402) nos Novos Endpoints:** Quando chamados sem pagamento, `/api/v1/lei`, `/api/v1/ofac`, `/api/v1/notarize`, `/api/v1/global-markets` e `/api/v1/pass` devem retornar HTTP 402 com bloco `preview` real e `locked_fields` para estimular a conversão imediata. | Must |
| RF07 | **Integração Completa no Hub, Playground e i18n (`PT | EN`):** Adicionar os 5 novos serviços nos cards da página inicial, nos presets do Playground Interativo, nos botões do Console, nos dicionários `i18n.js` (Português e Inglês) e nos manifestos (`llms.txt`, `openapi.json`, `x402.json`). | Must |

## 7. Requisitos não funcionais
- **Conformidade Legal e Licenciamento:** Apenas fontes com licença pública explícita (GLEIF CC0 1.0 Universal, US Treasury Public Domain, ECB Reference Rates, algoritmos matemáticos determinísticos de calendário e RPC público Base).
- **Performance:** Validação e débito de um `pass_<token>` deve ocorrer em menos de 15ms usando arquivo JSON atômico com `flock(LOCK_EX)`.
- **Segurança:** O token do passe deve conter um ID aleatório criptograficamente seguro (`random_bytes`) e assinatura HMAC-SHA256 derivada do segredo do Oráculo para impedir falsificação de tokens.

## 8. Fluxos e UX
1. **Fluxo de Compra e Uso do Session Pass:**
   - O agente (ou usuário no Playground/Console) chama `GET /api/v1/pass` sem header -> recebe `HTTP 402` explicando os pacotes (`0.05 USDC` = 100 créditos, `0.50 USDC` = 1.000 créditos).
   - Envia `X-Payment: 0x<tx_hash>` (ou testa com `X-Payment: demo` no Sandbox, que emite um passe de demonstração com 3 créditos).
   - Recebe `HTTP 200` com `pass_token: "pass_g402_..."`, `credits_total`, `credits_remaining`, `expires_at`.
   - Nas próximas chamadas a qualquer endpoint (ex: `/api/v1/lei`, `/api/v1/forex`, `/api/v1/notarize`), envia `X-Payment: pass_g402_...` e recebe resposta `HTTP 200` imediata com cabeçalho `X-Pass-Credits-Remaining`.

## 9. Métricas de sucesso
- Todos os 19 micro-serviços operacionais (`19/19 Services Operational`) no `/status` e na interface principal.
- 100% dos testes unitários e de integração passando para os 5 novos endpoints e para o ciclo completo de emissão, débito e expiração do Session Pass.

## 10. Riscos, dependências e premissas
- **Oscilação de APIs externas (GLEIF / BCE):** Mitigado com cache local em disco (`private_data/`) e fallback determinístico resiliente para garantir disponibilidade de 99.9%.

## 11. Questões em aberto
- Nenhuma. Todas as decisões foram alinhadas e confirmadas na etapa de Grill-me.

