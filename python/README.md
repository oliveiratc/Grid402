# Grid402 - SDK Python Oficial

Biblioteca Python oficial para consumo dos servicos do ecossistema **Grid402** e liquidacao autonoma de micropagamentos sob o protocolo HTTP 402 na rede Base.

Projetada especificamente para agentes autonomos de inteligencia artificial, frameworks como Coinbase AgentKit, LangChain, CrewAI e desenvolvedores que buscam APIs abertas sem contas, cadastros ou cartoes de credito.

---

## Recursos Principais

- **11 Ferramentas Integradas:** Indicadores macroeconomicos (Selic, CDI, IPCA), calculadora de dias uteis financeiros ANBIMA, feriados nacionais e bancarios ANBIMA, CNPJ da Receita Federal, CEP postal e IBGE, cotacoes Forex e Cripto spot, resolucao DNS, auditoria TLS e SSL, analise ASN e BGP e status do ecossistema.
- **Zero Cadastro e Zero Friccao:** Sem necessidade de chaves de API estaticas ou contratos mensais.
- **Modo Sandbox Gratuito:** Teste e homologue localmente sem custos informando `sandbox=True`.
- **Integracao Nativa com IA:** Compativel com Coinbase Developer Platform (CDP) AgentKit, LangChain e CrewAI.
- **Validacao Estrita Pydantic:** Schemas tipados e compativeis com PEP 561 (`py.typed`).
- **Arquitetura Resiliente:** Construido com a biblioteca padrao do Python para maxima leveza e portabilidade.

---

## Instalacao

Instale o pacote diretamente do PyPI:

```bash
pip install grid402
```

---

## Inicio Rapido

### 1. Consultas em Modo Sandbox (Sem Custo)

Ideal para testes locais, ambientes de integracao continua e desenvolvimento:

```python
from grid402 import Grid402Client

# Inicializa o cliente apontando para o ambiente de testes gratuito
client = Grid402Client(sandbox=True)

# Consulta cadastral de Pessoa Juridica na Receita Federal
cnpj_data = client.get_cnpj("00.000.000/0001-91")
print("Razao Social:", cnpj_data.get("razao_social"))

# Resolucao de endereco postal e codigo IBGE
cep_data = client.get_cep("01310-100")
print("Logradouro:", cep_data.get("logradouro"), "-", cep_data.get("localidade"))

# Cotacoes fiduciarias oficiais
forex_data = client.get_forex()
print("USD para BRL:", forex_data.get("USD", {}).get("bid"))
```

---

### 2. Tratamento Estruturado de HTTP 402

Quando chamado sem comprovante de pagamento, o Grid402 retorna uma resposta estruturada contendo o desafio com pre-visualizacao parcial dos dados (teaser data):

```python
from grid402 import Grid402Client, Grid402PaymentRequiredError

# Cliente com raise_for_payment ativado
client = Grid402Client(raise_for_payment=True)

try:
    dados = client.get_cnpj("00000000000191")
except Grid402PaymentRequiredError as erro:
    print("Desafio recebido:")
    print("Valor exigido:", erro.price_usdc, erro.token)
    print("Carteira recebedora:", erro.recipient)
    print("Rede:", erro.network)
    print("Dados parciais (amostra gratuita):", erro.preview_data)
```

---

### 3. Utilizacao com Coinbase AgentKit e Auto-Liquidacao

Agentes autonomos equipados com carteiras CDP na rede Base podem liquidar chamadas M2M (Machine-to-Machine) de forma transparente:

```python
from grid402 import Grid402ActionProvider

# Provedor compativel com Coinbase AgentKit
provider = Grid402ActionProvider()

# Ao fornecer a instancia de carteira CDP do agente (wallet_provider),
# o provedor detecta o 402, transfere o USDC e repassa os dados completos
resultado = provider.query_cnpj("00000000000191", wallet_provider=meu_wallet_provider)
print("Dados completos:", resultado)
print("Comprovante on-chain:", resultado.get("_x402_settlement"))
```

---

### 4. Schemas Tipados Pydantic

Para fluxos de trabalho tipados e validacao antecipada:

```python
from grid402 import CnpjInput, CepInput, DnsInput, SslInput, AsnInput, HolidaysInput, WorkdaysInput

# Validacao de entrada antes do envio
payload_cnpj = CnpjInput(cnpj="00.000.000/0001-91")
payload_cep = CepInput(code="01310100")
payload_dns = DnsInput(domain="base.org")
payload_holidays = HolidaysInput(year=2026)
payload_workdays = WorkdaysInput(start_date="2026-11-06", days=5)
```

---

### 5. Verificacao Criptografica de Prova de Autenticidade (Proof of Data)

Todas as respostas de dados pagas e em sandbox trazem uma prova matematica de oraculo (`_oracle_proof`) assinada via ECDSA secp256k1 pela carteira oficial do Grid402 (`0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90`).

O SDK permite verificar matematicamente a integridade dos dados e a autenticidade do emissor em menos de 5 milissegundos sem chamadas de rede adicionais:

```python
from grid402 import Grid402Client

client = Grid402Client(sandbox=True)
dados = client.get_workdays(start_date="2026-11-06", days=5)

# Validacao matematica local e instantanea
verificacao = client.verify_proof(dados)

if verificacao.valid:
    print("Dados autenticos assinados pelo oraculo Grid402!")
    print("Endereco do oraculo:", verificacao.oracle_address)
    print("Carimbo temporal:", verificacao.timestamp)
else:
    print("Falha na autenticidade dos dados:", verificacao.error)
```

---

## Catalogo de Ferramentas

| Metodo | Descricao | Preco Base (USDC) |
|---|---|---|
| `get_summary()` | Resumo operacional consolidado de saude | Gratuito |
| `get_forex()` | Cotacoes cambiais oficiais de USD, EUR e GBP para BRL | 0.0050 |
| `get_crypto(symbol)` | Precos spot de BTC, ETH, SOL e USDC | 0.0050 |
| `get_dns(domain)` | Resolucao DNS com registros A, AAAA, MX, TXT, NS e CNAME | 0.0050 |
| `get_ssl(host)` | Inspecao e auditoria de certificado TLS e SSL na porta 443 | 0.0050 |
| `get_asn(ip)` | Inteligencia de roteamento BGP, bloco alocado e ASN | 0.0050 |
| `get_cep(code)` | Consulta de logradouro postal brasileiro e codigo IBGE | 0.0050 |
| `get_cnpj(cnpj)` | Dados cadastrais completos de PJ na Receita Federal | 0.0200 |
| `get_indicators()` | Indicadores macroeconomicos oficiais (Selic meta e diaria, CDI anual e IPCA) | 0.0050 |
| `get_holidays(year)` | Calendario oficial de feriados nacionais e bancarios ANBIMA | 0.0050 |
| `get_workdays(start_date?, end_date?, days?, date?)` | Calculadora de dias uteis financeiros e projecao de vencimentos ANBIMA | 0.0050 |

---

## Licenca

Distribuido sob os termos da licenca MIT. Consulte o arquivo `LICENSE` para detalhes.

