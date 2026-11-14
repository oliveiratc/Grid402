# Grid402 // Exemplos Práticos de Agentes Autônomos (M2M x402)

Esta pasta contém exemplos prontos para demonstração e integração do **Grid402** em ecossistemas de agentes de inteligência artificial autônomos na rede **Base Mainnet**.

---

## 📁 Arquivos

- **`autonomous_agent.mjs`**: Exemplo completo em **Node.js (ESM)** utilizando `ethers.js` para auto-liquidação on-chain.
- **`autonomous_agent.py`**: Exemplo em **Python** para integração em frameworks como **CrewAI**, **LangChain** e **ElizaOS**.
- **`sepolia_x402_buyer.mjs`**: Buyer de **homologação em Base Sepolia** — assina EIP-3009 contra o USDC de teste e troca pelo recurso via facilitador público x402.org. Chave só via env (`GRID402_TEST_PRIVATE_KEY`). Ver `docs/FACILITATOR_SETUP.md`.

---

## ⚡ Como Rodar o Exemplo Node.js

```bash
# 1. Instale a biblioteca ethers (se ainda não tiver)
npm install ethers

# 2. Executar em modo demonstração (exibe o desafio x402)
node examples/autonomous_agent.mjs

# 3. Executar com auto-pagamento real na Base (envia 0.0020 USDC e desbloqueia os dados)
export AGENT_PRIVATE_KEY="0x_sua_chave_privada_base"
node examples/autonomous_agent.mjs
```

---

## 🐍 Como Rodar o Exemplo Python

```bash
python examples/autonomous_agent.py
```

---

## 💡 Como Funciona o Ciclo M2M x402

1. O agente faz a chamada HTTP padrão: `GET /api/v1/cnpj?cnpj=00000000000191`.
2. O servidor valida a existência e responde com `HTTP 402 Payment Required`, informando:
   - Carteira de destino do oráculo: `0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90`
   - Preço exato em USDC: `0.0020` (2000 unidades brutas)
   - Contrato do USDC na Base: `0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913`
3. O agente assina e transmite a transferência ERC-20 na Base Mainnet.
4. O agente reenvia a requisição adicionando o cabeçalho:
   `X-Payment: <hash_da_transacao>`
5. O Grid402 audita a transação on-chain em milissegundos via RPC, valida que não houve gasto duplo (anti-replay com lock atômico) e libera a ficha cadastral completa com `HTTP 200 OK`.

