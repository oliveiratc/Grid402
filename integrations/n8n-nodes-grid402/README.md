# @oliveiratco1/n8n-nodes-grid402 ⚡

Conector oficial de **Community Node para n8n** do **Grid402** (`https://grid402.online`) — Hub de 25 Micro-Serviços de Dados Públicos Abertos, KYB Brasil (Receita Federal CNPJ, Simples/MEI, Sanções CGU CEIS/CNEP, Dossiê 360º com Veredito IA), Dias Úteis Bancários ANBIMA, Extração Web-to-Markdown e Verificação Zero-PII de E-mails Corporativos.

100% em conformidade com a **LGPD (Art. 1º)**, **GDPR (Recital 14)** e **Lei de Acesso à Informação (Lei nº 12.527/2011 - LAI)**: processa exclusivamente registros públicos de Pessoas Jurídicas e fatos técnicos de rede, com descarte estrito de dados pessoais (Zero-PII e Zero-Retention) e assinatura criptográfica de autenticidade (`_oracle_proof` ECDSA `secp256k1`).

---

## 🚀 Como Instalar no seu n8n

No painel do seu n8n:
1. Vá em **Settings** → **Community Nodes** → **Install**.
2. Digite **`@oliveiratco1/n8n-nodes-grid402`** e clique em **Install**.

---

## 🔑 Como Autenticar (Teste Grátis ou Pix Instantâneo em 10s)

1. **Modo Sandbox Gratuito (Zero Configuração):**
   - Funciona imediatamente após instalar com **3 consultas grátis por dia** (`X-Payment: demo`).
2. **Session Pass Pré-Pago (`<15ms`, Pix, Cartão ou Multi-Chain USDC):**
   - Adquira um passe instantâneo a partir de **R$ 12,00 (400 créditos)** em:
     👉 `https://grid402.online/pass?tier=starter&autopix=1`
   - Cole o token `pass_g402_...` gerado na credencial **Grid402 API** dentro do n8n.

---

## 🛠️ Operações Incluídas (25 Micro-Serviços + Gestão de Saldo)

- **KYB Brasil e Compliance Corporativo (Zero-PII):**
  - `Dossiê 360º KYB Brasil + Veredito IA` (`APPROVED`, `FLAGGED`, `REJECTED` + Score 0-100 + Receita Federal + Simples/MEI + CGU CEIS/CNEP)
  - `Consultar CNPJ Receita Federal`
  - `Simples Nacional e MEI por CNPJ`
  - `Sanções CGU CEIS e CNEP por CNPJ`
  - `Compliance Score 360 por CNPJ`
  - `Auditoria KYB Global (CNPJ + OFAC + GLEIF LEI)`
- **Automação B2B, IA e RAG:**
  - `Verificar E-mail Corporativo B2B Zero-PII` (Sintaxe RFC, MX, SPF, DMARC, disposable e score 0-100)
  - `Extrair Site para Markdown Limpo / RAG` (Conversão anti-SSRF de páginas web para Markdown)
- **Financeiro, Calendário Bancário ANBIMA e Endereçamento:**
  - `Dias Úteis Bancários ANBIMA (D+N)`
  - `Feriados Nacionais e Bancários ANBIMA`
  - `Indicadores BCB (Selic, CDI e IPCA)`
  - `Câmbio PTAX BCB (USD, EUR, GBP para BRL)`
  - `Preços Cripto Spot (BTC, ETH, SOL, USDC)`
  - `Panorama Financeiro Consolidado (Market Snapshot)`
  - `Mercados Globais e 30+ Moedas BCE`
  - `Consultar CEP Brasileiro + Código IBGE`
- **Rede, Segurança e Identidade Global:**
  - `Resolução DNS Autoritativa`, `Inspeção TLS/SSL`, `Inteligência BGP/ASN`, `Auditoria Completa de Domínio`, `GLEIF LEI`, `OFAC SDN / ONU` e `Notarização Criptográfica SHA-256 + ECDSA`.

Licença MIT • Grid402 Network Intelligence (`https://grid402.online`)
