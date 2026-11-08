# PRD: Assinatura Criptografica de Oraculo e Prova de Autenticidade (Proof of Data)

**Status:** Em revisao  
**Data:** 2026-11-08  
**Autor:** Usuario + Antigravity (Google DeepMind)  

---

## 1. Contexto e problema

Atualmente, o **Grid402** entrega dados publicos oficiais e utilitarios de rede sob demanda via micropagamentos HTTP 402 na rede Base. Contudo, as respostas tecnicas retornadas sao objetos JSON comuns sem garantia matematica de nao-repudio e autenticidade de origem.

Para agentes autonomos de IA (Coinbase AgentKit, ElizaOS, CrewAI) e desenvolvedores Web3 que tomam decisoes financeiras on-chain baseadas nesses dados, a ausencia de uma assinatura digital gera dois obstaculos criticos:
1. **Desconfianca de Procedencia:** O consumidor nao consegue auditar se a resposta veio de fato do oraculo oficial do Grid402 ou se foi forjada, interceptada ou adulterada em transito.
2. **Barreira de Conversao Paga:** Especialmente operando sob o subdominio atual da hospedagem, a presenca de uma prova criptografica auditavel emitida pela carteira oficial do oraculo posiciona o Grid402 como uma infraestrutura Web3 de alta reputacao, justificando o pagamento de micropagamentos em USDC.

---

## 2. Objetivos

- **Autenticidade Criptografica (Proof of Data):** Assinar digitalmente cada resposta de micro-servico liberada com comprovante de pagamento ou em modo Sandbox, garantindo que o payload foi chancelado pela carteira oficial do oraculo.
- **Auditoria sem Latencia de Rede:** Permitir que agentes e desenvolvedores verifiquem a integridade da resposta localmente via SDKs oficiais em menos de 5 ms, sem necessidade de requisicoes HTTP adicionais.
- **Padronizacao Web3 na Base:** Alinhar o modelo de dados ao ecossistema Ethereum e Base atraves de assinaturas ECDSA na curva `secp256k1` e hashes criptograficos.
- **Elevar a Credibilidade e Conversao:** Diferenciar o Grid402 de meros scrapers ou proxies nao autenticados, transformando-o em um Oraculo Publico Verificavel.

---

## 3. Nao objetivos (fora do escopo)

- Nao sera criado smart contract relayer on-chain para liquidar chamadas em bloco de transacao EVM por query (a arquitetura continua stateless, off-chain de alta performance com liquidacao x402).
- Nao serao alterados precos, rotas de servico existentes nem a carteira oficial recebedora de USDC (`0xa31c31069c43ac1c0075a93c325b0dcd7744356c`).
- Nao havera banco de dados relacional (Zero-DB preservado).

---

## 4. Publico-alvo e personas

- **Agentes de IA Autonomos:** Robos que consom cotacoes cambiais, indicadores economicos ou dados cadastrais e precisam comprovar a idoneidade da informacao antes de assinar transacoes financeiras.
- **Desenvolvedores Web3 e DeFi:** Construtores de aplicacoes na rede Base que exigem atestacao confiavel de dados sem pagar custos exorbitantes de oraculos corporativos legados.
- **Engenheiros de Integracao e Dados:** Profissionais que integram ferramentas corporativas e necessitam de trilha de auditoria e carimbo temporal confiavel.

---

## 5. Historias de usuario

- **Como** agente autonomo de IA, **quero** receber um bloco de prova criptografica no JSON da API **para** auditar que o dado nao foi alterado antes de executar acoes financeiras.
- **Como** desenvolvedor Node.js ou TypeScript, **quero** executar `client.verifyProof(resultado)` **para** validar a assinatura da resposta com uma unica chamada de funcao.
- **Como** desenvolvedor Python, **quero** chamar `client.verify_proof(resultado)` **para** integrar a validacao de dados ao pipeline do meu agente LangChain ou CrewAI.
- **Como** integrador em fase de testes, **quero** receber a prova criptografica tambem no modo Sandbox `X-Payment: demo` **para** homologar meu fluxo de auditoria em CI sem consumir USDC real.

---

## 6. Requisitos funcionais

| ID | Requisito | Prioridade |
|---|---|---|
| **RF01** | Desenvolver modulo PHP (`services/oracle_signer.php`) capaz de assinar payloads JSON com ECDSA secp256k1 e hash criptografico. | Must |
| **RF02** | Anexar o bloco estruturado `_oracle_proof` ao final de todas as respostas pagas (HTTP 200) de todos os 11 micro-servicos do hub. | Must |
| **RF03** | Incluir a prova de oraculo no modo Sandbox (`X-Payment: demo`), marcando a propriedade `sandbox: true` no bloco de prova. | Must |
| **RF04** | Suportar a chave privada do oraculo via variavel de ambiente `GRID402_ORACLE_PRIVATE_KEY` no arquivo `.env` fora da raiz publica, mantendo fallback de teste para desenvolvimento local. | Must |
| **RF05** | Implementar metodo de verificacao de prova `verifyProof(payload, expectedOracleAddress?)` no SDK Node.js e TypeScript (`grid402`). | Must |
| **RF06** | Implementar metodo de verificacao de prova `verify_proof(payload, expected_oracle_address=None)` no SDK Python (`grid402`). | Must |
| **RF07** | Garantir que o Servidor MCP (`grid402-mcp`) repasse transparentemente o bloco `_oracle_proof` nas respostas das ferramentas. | Must |
| **RF08** | Atualizar schemas no `openapi.json`, `llms.txt`, documentacao dos SDKs e console web com a especificacao do Oraculo Verificavel. | Must |

---

## 7. Requisitos nao funcionais

- **Desempenho no Servidor:** O calculo de hash e a geracao da assinatura ECDSA no PHP devem adicionar menos de 5 milissegundos a latencia total da requisicao.
- **Desempenho nos Clientes:** A verificacao nos SDKs Node.js e Python deve ser executada localmente em menos de 5 milissegundos sem I/O de rede.
- **Seguranca da Chave:** A chave privada do oraculo nunca deve ser exposta no codigo fonte, em logs, no console web ou em commits do Git.
- **Zero Dependencias Pesadas no Backend:** Utilizar recursos nativos do PHP 8 (OpenSSL e Hash) sem necessidade de extensoes externas nao padronizadas.
- **Conformidade de Linguagem:** Zero ocorrencias do caractere e comercial ampersand em documentacoes, schemas, comentarios e respostas.

---

## 8. Fluxos e UX

### 8.1. Fluxo Principal: Chamada Paga com Atestacao de Oraculo
1. O cliente envia uma requisicao HTTP com cabecalho `X-Payment: <tx_hash>` para qualquer servico (ex: `/api/v1/workdays`).
2. O porteiro do Grid402 valida a transacao on-chain na Base e processa o resultado do servico.
3. Antes do envio, o modulo `oracle_signer` extrai o payload canonico dos dados, calcula o hash e assina com a chave privada do oraculo.
4. O servidor responde HTTP 200 contendo os dados e o objeto:
   ```json
   "_oracle_proof": {
     "oracle_address": "0xa31c31069c43ac1c0075a93c325b0dcd7744356c",
     "timestamp": 1794140000,
     "data_hash": "0x...",
     "signature": "0x...",
     "algorithm": "secp256k1",
     "sandbox": false
   }
   ```
5. O cliente no SDK executa `client.verifyProof(resultado)` e recebe `{ valid: true, oracle: "0xa31c3106..." }`.

### 8.2. Fluxo Alternativo: Chamada em Modo Sandbox
1. O cliente envia `X-Payment: demo`.
2. O sistema gera a resposta do servico e anexa o bloco `_oracle_proof` com `sandbox: true`.
3. O cliente no SDK consegue auditar a assinatura normalmente, comprovando a integridade dos dados simulados.

### 8.3. Fluxo de Inspecao de Fraude (Tampering)
1. Se um agente ou middleware intermediario alterar qualquer dado do payload (ex: modificar cotacao cambial ou CNPJ), o calculo de `data_hash` resultante difere da assinatura.
2. A chamada `verifyProof(payload)` retorna `{ valid: false, error: "DataHashMismatch" }`, permitindo que o agente aborte a operacao defensivamente.

---

## 9. Metricas de sucesso

- **100% de Cobertura de Testes:** Testes unitarios em PHP, Node.js e Python validando geracao de assinatura, verificacao positiva e rejeicao de dados adulterados.
- **Latencia Incremental:** Sobrecarga de assinatura inferior a 5 ms no backend.
- **Integracao nos SDKs:** Disponibilizacao imediata dos metodos de verificacao nas versoes atualizadas de `grid402` (npm e PyPI).

---

## 10. Riscos, dependencias e premissas

- **Premissa:** O PHP 8.3 da Hostinger dispoe da extensao OpenSSL compilada com suporte a curva `secp256k1` (ja validada com sucesso em ambiente de execucao).
- **Risco de Chave Nao Configurada:** Caso a variavel `GRID402_ORACLE_PRIVATE_KEY` nao seja informada no ambiente, o sistema utilizara um par de chaves deterministico documentado para modo de desenvolvimento, impedindo falhas em ambientes locais.
- **Dependencia nos SDKs:** O SDK Node.js utilizara modulos criptograficos nativos ou `ethers` (ja presente no projeto), e o Python utilizara bibliotecas leves compativeis.

---

## 11. Questoes em aberto

- Nenhuma questao em aberto; decisoes alinhadas e confirmadas na etapa de Grill-me.

