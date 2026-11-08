# PRD: Modernizacao e Lancamento do SDK Node.js e TypeScript Grid402 v2.2.0

**Status:** Em revisao  
**Data:** 2026-11-08  
**Autor:** oliveiratc + Antigravity  

---

## 1. Contexto e Problema

O pacote `grid402` no npm foi registrado e publicado anteriormente na versao `2.1.0`. Contudo, desde entao, o ecossistema Grid402 evoluiu com frentes de alto impacto:
1. Inclusao do servico de CNPJ da Receita Federal com pre-visualizacao cadastral;
2. Suporte ao Modo Sandbox gratuito (`sandbox=true` com cabecalho `X-Payment: demo`) para testes locais sem consumo de saldo;
3. Resposta de desafio HTTP 402 enriquecida com `preview_data` (amostra parcial / teaser data);
4. Criacao do `Grid402ActionProvider` compativel com Coinbase Developer Platform (CDP) AgentKit para auto-liquidacao on-chain na rede Base;
5. Necessidade de suporte hibrido ESM e CommonJS (CJS) com tipagens TypeScript (`.d.ts`) completas para que desenvolvedores de Next.js, Node.js e ferramentas CLI possam utilizar a biblioteca sem erros de modulo.

O objetivo deste projeto e atualizar a base do SDK TypeScript e JavaScript em `sdk/node/`, modernizar seus modulos para a versao `2.2.0`, validar 100% dos testes e preparar a publicacao no npm.

---

## 2. Objetivos

- **Paridade Funcional Total:** Oferecer os 8 servicos publicos do Grid402 (`getSummary`, `getForex`, `getCrypto`, `getDns`, `getSsl`, `getAsn`, `getCep`, `getCnpj`).
- **Modo Sandbox Integrado:** Permitir que desenvolvedores utilizem `new Grid402Client({ sandbox: true })` para chamadas de teste sem custo.
- **Auto-Liquidacao AgentKit:** Incluir `Grid402ActionProvider` com deteccao automatica de HTTP 402 e transferencia de USDC on-chain via carteiras CDP do Coinbase AgentKit.
- **Distribuicao Hibrida ESM e CJS:** Fornecer suporte nativo tanto para `import` (ESM) quanto `require` (CommonJS), alem das definicoes de tipo TypeScript (`grid402.d.ts`).
- **Zero Ocorrencias de Caractere Comercial:** Garantir ausencia absoluta do caractere de 'e comercial' em todo o codigo, testes, documentacao e schemas.

---

## 3. Nao Objetivos (Fora do Escopo)

- Nao criar banco de dados ou servicos de sessao com estado (o cliente permanece estritamente stateless e Zero-DB).
- Nao gerenciar chaves privadas diretamente no core do cliente (a assinatura de transacoes e delegada ao `wallet_provider` do AgentKit ou fornecida pelo usuario).
- Nao alterar contratos inteligentes na blockchain Base.

---

## 4. Publico-Alvo e Personas

- **Engenheiros de IA e Agentes Autonomos:** Criadores de bots que rodam sobre Coinbase AgentKit, LangChain.js, ElizaOS e CrewAI precisando de dados confiaveis e liquidacao machine-to-machine.
- **Desenvolvedores Full Stack (Node.js e Next.js):** Programadores que buscam consultar dados publicos brasileiros (CNPJ, CEP) ou infraestrutura de rede (DNS, SSL, ASN) sem cartao de credito ou chaves estaticas.
- **Desenvolvedores Web3:** Construtores no ecossistema Base e Ethereum que integram o protocolo x402.

---

## 5. Historias de Usuario

- **Como agente de IA**, quero detectar quando um endpoint exige pagamento x402 e auto-liquidar o valor em USDC na Base para que a tarefa continue sem intervencao humana.
- **Como desenvolvedor**, quero instanciar `Grid402Client({ sandbox: true })` para validar fluxos de teste em CI e CD gratuitamente.
- **Como desenvolvedor TypeScript**, quero autocompletar e tipagem estrita para todas as entradas e saidas do Grid402 via definicoes `.d.ts`.

---

## 6. Requisitos Funcionais

| ID | Requisito | Prioridade |
|---|---|---|
| RF01 | Implementar suporte a `sandbox: true` no `Grid402Client`, injetando `X-Payment: demo` automaticamente | Must |
| RF02 | Suportar as 8 ferramentas: `getSummary`, `getForex`, `getCrypto`, `getDns`, `getSsl`, `getAsn`, `getCep` e `getCnpj` | Must |
| RF03 | Integrar `Grid402ActionProvider` no pacote principal com suporte a `wallet_provider.transfer(...)` | Must |
| RF04 | Atualizar a classe de erro `PaymentRequiredError` para expor `preview_data` e metadados detalhados de liquidacao | Must |
| RF05 | Fornecer tipagem TypeScript completa (`grid402.d.ts`) cobrindo todas as classes, parametros e formatos de retorno | Must |
| RF06 | Configurar exportacoes duais ESM (`grid402.mjs`) e CommonJS (`grid402.cjs`) no `package.json` | Must |
| RF07 | Criar suite de testes automatizados (`test_sdk.mjs` e testes de integracao ao vivo) cobrindo modo Sandbox e AgentKit | Must |

---

## 7. Requisitos Nao Funcionais

- **Compatibilidade:** Node.js >= 18.0.0 (suporte nativo a `fetch`).
- **Dependencias:** Zero dependencias externas obrigatorias no core do SDK.
- **Segurança:** Ausencia de gravacao de dados sensiveis ou chaves privadas em disco.
- **Qualidade de Codigo:** Zero ocorrencias do caractere 'e comercial' (`&`) em qualquer arquivo.
- **Tamanho do Pacote:** Pacote leve, descompactado em menos de 50 KB.

---

## 8. Fluxos e UX

1. **Instalacao Simples:** `npm install grid402`
2. **Modo Sandbox:**
   ```javascript
   import { Grid402Client } from 'grid402';
   const client = new Grid402Client({ sandbox: true });
   const cnpj = await client.getCnpj('00000000000191');
   ```
3. **Modo AgentKit com Auto-Liquidacao:**
   ```javascript
   import { Grid402ActionProvider } from 'grid402';
   const provider = new Grid402ActionProvider();
   const data = await provider.queryCnpj('00000000000191', walletProvider);
   ```

---

## 9. Metricas de Sucesso

- 100% de testes automatizados passando localmente em Node.js (unitarios e integracao live sandbox).
- Pacote `grid402@2.2.0` pronto para publicacao no npm.
- Compatibilidade comprovada com projetos ESM (`import`) e CommonJS (`require`).

---

## 10. Riscos, Dependencias e Premissas

- **Risco:** Erro de resolucao em projetos CommonJS se a exportacao CJS for omitida.  
  *Mitigacao:* Gerar `grid402.cjs` e mapear no `exports` do `package.json`.
- **Risco:** Falha de rede durante testes live.  
  *Mitigacao:* Mocks isolados para testes unitarios e suite live com timeout resiliente.
- **Premissa:** O usuario possui credenciais ativas de publicador no npm para o pacote `grid402`.

---

## 11. Questoes em Aberto

- Nenhuma questao em aberto; decisoes alinhadas durante a etapa de Grill-me.

