# Grid402 - SDK Node.js e TypeScript Oficial

Biblioteca cliente oficial para integracao com o ecossistema **Grid402** e consumo de dados abertos e utilitarios de rede sob o protocolo HTTP 402 na rede Base.

Projetada especificamente para agentes autonomos de IA, frameworks como Coinbase AgentKit, LangChain.js, ElizaOS, desenvolvedores Node.js, Next.js e TypeScript.

---

## Recursos Principais

- **11 Micro-Servicos Integrados:** Indicadores Macroeconomicos (Selic, CDI, IPCA), Calculadora de Dias Uteis Financeiros ANBIMA, Feriados Nacionais e Bancarios ANBIMA, CNPJ da Receita Federal, CEP postal e IBGE, cotacoes Forex e Cripto spot, resolucao DNS, auditoria TLS e SSL, analise ASN e BGP e status do ecossistema.
- **Suporte Hibrido ESM e CJS:** Compativel nativamente com `import` (ES Modules) e `require` (CommonJS).
- **Modo Sandbox Gratuito:** Teste e homologue localmente sem custos informando `sandbox: true`.
- **Integracao Nativa com IA:** Inclui `Grid402ActionProvider` com auto-liquidacao na rede Base para agentes autônomos.
- **Tipagem TypeScript Estrita:** Definicoes `.d.ts` completas para IDEs modernas.
- **Zero Dependencias Externas:** Construido inteiramente sobre a API padrao `fetch` do Node.js 18+.

---

## Instalacao

Instale o pacote diretamente do registro npm:

```bash
npm install grid402
```

---

## Inicio Rapido

### 1. Consultas em Modo Sandbox (Sem Custo)

Ideal para testes locais, ambientes de integracao continua e desenvolvimento:

```javascript
import { Grid402Client } from 'grid402';

// Inicializa o cliente em modo Sandbox gratuito
const client = new Grid402Client({ sandbox: true });

// Consulta cadastral de Pessoa Juridica na Receita Federal
const cnpjData = await client.getCnpj('00000000000191');
console.log('Razao Social:', cnpjData.razao_social);

// Resolucao de endereco postal e codigo IBGE por CEP
const cepData = await client.getCep('01310100');
console.log('Logradouro:', cepData.logradouro, '-', cepData.uf);

// Cotacoes de moedas fiduciarias
const forexData = await client.getForex();
console.log('USD para BRL:', forexData.rates?.USD?.bid);
```

---

### 2. Uso com CommonJS (require)

Em projetos legados ou scripts Node.js tradicionais:

```javascript
const { Grid402Client } = require('grid402');

const client = new Grid402Client({ sandbox: true });
client.getSummary().then(summary => {
  console.log('Status do Grid402:', summary.status);
});
```

---

### 3. Tratamento Estruturado de HTTP 402 e Teaser Data

Quando uma consulta requer micropagamento em producao, o Grid402 lanca `PaymentRequiredError` contendo amostra parcial gratuita dos dados:

```javascript
import { Grid402Client, PaymentRequiredError } from 'grid402';

const client = new Grid402Client({ sandbox: false });

try {
  const dados = await client.getCnpj('00000000000191');
} catch (err) {
  if (err instanceof PaymentRequiredError) {
    console.log('Preco exigido:', err.priceUsdc, err.token);
    console.log('Carteira recebedora:', err.recipientWallet);
    console.log('Pre-visualizacao parcial (amostra gratuita):', err.previewData);
  }
}
```

---

### 4. Utilizacao com Coinbase AgentKit e Auto-Liquidacao

Agentes autonomos equipados com carteiras CDP na rede Base liquidam chamadas M2M de forma transparente:

```javascript
import { Grid402ActionProvider } from 'grid402';

const provider = new Grid402ActionProvider();

// Ao fornecer o walletProvider do agente CDP, o provedor
// detecta o 402, transfere o USDC e retorna os dados completos
const resultado = await provider.queryCnpj('00000000000191', meuWalletProvider);
console.log('Razao Social:', resultado.razao_social);
console.log('Comprovante on-chain:', resultado._x402_settlement);
```

---

### 5. Verificacao Criptografica de Prova de Autenticidade (Proof of Data)

Todas as respostas de dados pagas e em sandbox trazem uma prova matematica de oraculo (`_oracle_proof`) assinada via ECDSA secp256k1 pela carteira oficial do Grid402 (`0xa31c31069c43ac1c0075a93c325b0dcd7744356c`).

O SDK permite verificar matematicamente a integridade dos dados e a autenticidade do emissor em menos de 5 milissegundos sem chamadas de rede adicionais:

```javascript
import { Grid402Client } from 'grid402';

const client = new Grid402Client({ sandbox: true });
const dados = await client.getWorkdays({ startDate: '2026-11-06', endDate: '2026-11-13' });

// Validacao matematica local e instantanea
const verificacao = client.verifyProof(dados);

if (verificacao.valid) {
  console.log('Dados autenticos assinados pelo oraculo Grid402!');
  console.log('Endereco do oraculo:', verificacao.oracleAddress);
  console.log('Carimbo temporal:', verificacao.timestamp);
} else {
  console.error('Falha na autenticidade dos dados:', verificacao.error);
}
```

---

## Catalogo de Ferramentas

| Metodo | Descricao | Preco Base (USDC) |
|---|---|---|
| `getSummary()` | Resumo operacional consolidado e saude do hub | Gratuito |
| `getForex(txHash?)` | Cotacoes cambiais de USD, EUR e GBP para BRL | 0.0005 |
| `getCrypto(txHash?, symbol?)` | Precos spot de BTC, ETH, SOL e USDC | 0.0005 |
| `getDns(domain, txHash?)` | Resolucao DNS com registros A, AAAA, MX, TXT, NS e CNAME | 0.0005 |
| `getSsl(host, txHash?)` | Inspecao e auditoria de certificado TLS e SSL na porta 443 | 0.0005 |
| `getAsn(ip?, txHash?)` | Inteligencia de roteamento BGP, bloco alocado e ASN | 0.0005 |
| `getCep(code, txHash?)` | Consulta de logradouro postal brasileiro e codigo IBGE | 0.0005 |
| `getCnpj(cnpj, txHash?)` | Dados cadastrais completos de PJ na Receita Federal | 0.0020 |
| `getIndicators(txHash?)` | Indicadores macroeconomicos oficiais (Selic meta e diaria, CDI anual e IPCA) | 0.0005 |
| `getHolidays(year?, txHash?)` | Calendario oficial de feriados nacionais e bancarios ANBIMA | 0.0005 |
| `getWorkdays(params?, txHash?)` | Calculadora de dias uteis financeiros e projecao de vencimentos ANBIMA | 0.0005 |

---

## Licenca

Distribuido sob os termos da licenca MIT. Consulte o arquivo `LICENSE` para detalhes.
