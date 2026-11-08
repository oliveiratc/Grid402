# Design Tecnico: Assinatura Criptografica de Oraculo (Proof of Data)

## 1. Visao Geral da Arquitetura

O modulo `services/oracle_signer.php` atua como o carimbador criptografico final do Grid402. Quando o despachante central (`index.php`) conclui com sucesso o processamento de um micro-servico (HTTP 200 via pagamento on-chain ou modo sandbox), ele submete o array de dados ao assinador antes de codificar a resposta HTTP final.

```
+-------------------------------------------------------------------+
|                       Requisicao de Entrada                       |
|          (X-Payment: 0x... ou X-Payment: demo no Gateway)         |
+---------------------------------+---------------------------------+
                                  |
                                  v
+---------------------------------+---------------------------------+
|               Execucao do Servico de Dados / Rede                 |
|       (CNPJ, CEP, Forex, Crypto, DNS, SSL, ASN, Workdays...)      |
+---------------------------------+---------------------------------+
                                  |
                                  v
+---------------------------------+---------------------------------+
|                  services/oracle_signer.php                       |
|  1. Remove metadados transientes (_oracle_proof, sandbox_info)    |
|  2. Serializa JSON canonico (chaves ordenadas, sem escape UTF-8)  |
|  3. Computa data_hash = 0x + hash(sha256, json)                  |
|  4. Assina hash + timestamp com secp256k1 (GRID402_ORACLE_KEY)    |
|  5. Injeta bloco _oracle_proof no payload de resposta             |
+---------------------------------+---------------------------------+
                                  |
                                  v
+---------------------------------+---------------------------------+
|                    Resposta HTTP 200 com JSON                     |
|            (Verificavel localmente pelos SDKs em < 5ms)           |
+-------------------------------------------------------------------+
```

---

## 2. Especificacao do Bloco `_oracle_proof`

```json
{
  "_oracle_proof": {
    "oracle_address": "0xa31c31069c43ac1c0075a93c325b0dcd7744356c",
    "timestamp": 1794140000,
    "data_hash": "0x4f8a12...",
    "signature": "0x3045022100...",
    "algorithm": "secp256k1-sha256",
    "sandbox": false
  }
}
```

- **oracle_address:** Endereco publico da carteira oraculo oficial na rede Base.
- **timestamp:** Carimbo de data e hora Unix UTC da geracao da assinatura.
- **data_hash:** Hash criptografico SHA-256 do payload canonico dos dados.
- **signature:** Assinatura digital ECDSA na curva `secp256k1` codificada em hexadecimal.
- **algorithm:** Identificador do algoritmo utilizado (`secp256k1-sha256`).
- **sandbox:** Booleano indicando se a resposta foi originada no ambiente de simulacao ou na Mainnet.

---

## 3. Normalizacao e Canonizacao dos Dados

Para garantir que a verificacao do hash seja 100% deterministica entre diferentes plataformas (PHP, Node.js e Python):
1. Excluem-se as chaves de metadados transientes: `_oracle_proof`, `sandbox_info` e `_x402_settlement`.
2. Ordenam-se recursivamente todas as chaves do dicionario em ordem alfabetica crescente.
3. Serializa-se para string UTF-8 sem espacos desnecessarios entre delimitadores.

---

## 4. Gestao de Chaves Criptograficas

- **Producao:** A chave privada e lida exclusivamente a partir do arquivo `.env` fora da raiz publica (`GRID402_ORACLE_PRIVATE_KEY`).
- **Ambiente de Teste Local:** Caso a variavel de ambiente nao esteja definida no servidor, o sistema recorre a um par de chaves EC secp256k1 de teste deterministico gerado em memoria, garantindo que testes unitarios e CI rodem sem necessidade de segredos reais.

---

## 5. Algoritmo de Verificacao nos SDKs

### No SDK Node.js e TypeScript (`grid402`):
```typescript
const result = await client.getWorkdays({ startDate: '2026-12-24', endDate: '2026-12-28' });
const audit = client.verifyProof(result);
if (audit.valid) {
  console.log('Dados autenticados pelo oraculo oficial:', audit.oracleAddress);
}
```

### No SDK Python (`grid402`):
```python
result = client.get_workdays(start_date='2026-12-24', end_date='2026-12-28')
audit = client.verify_proof(result)
if audit['valid']:
    print('Dados autenticados pelo oraculo oficial:', audit['oracle_address'])
```

Ambos os SDKs reproduzem o algoritmo canonico de hash e validam a assinatura ECDSA contra a chave publica do oraculo em memoria, com tempo medio de execucao inferior a 3 milissegundos.

