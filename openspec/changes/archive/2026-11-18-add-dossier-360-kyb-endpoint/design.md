# Design Técnico: Dossiê 360º KYB (`/api/v1/dossier`)

## 1. Arquitetura Geral
O serviço `public_html/services/dossier.php` atua como um agregador determinístico de alto valor sobre os oráculos já existentes no Grid402 (`cnpj.php` e `compliance.php`), seguindo 100% a arquitetura Zero-DB:

```text
GET /api/v1/dossier?cnpj=...
        │
        ▼
  [index.php / x402 Gateway]
  ├── Sem pagamento ──► HTTP 402 (0.0500 USDC / 50000 raw) + Teaser Seguro
  └── Pago (USDC / 10 créditos Pass / Sandbox)
        │
        ▼
  [grid402_service_dossier($cnpj)]
  ├── Validação matemática dos dígitos do CNPJ (HTTP 400 se inválido)
  ├── Cache Zero-DB atômico (dossier_<cnpj>.json, TTL 24h)
  ├── Agregação interna:
  │     ├── grid402_service_cnpj($cnpj)       → Receita Federal (Zero PII)
  │     ├── grid402_service_simples($cnpj)    → Simples Nacional / MEI
  │     ├── grid402_service_sanctions($cnpj)  → CGU CEIS / CNEP
  │     └── grid402_service_compliance($cnpj) → Score 360
  ├── Motor de Veredito para IA (grid402_dossier_compute_verdict)
  │     ├── REJECTED: situação != ATIVA ou possui sanção ativa no CEIS/CNEP
  │     ├── FLAGGED:  situação == ATIVA, sem sanções, mas score < 70 ou alerta de atenção
  │     └── APPROVED: situação == ATIVA, sem sanções e score >= 70
  └── Assinatura ECDSA secp256k1 (_oracle_proof) + _meta + _billing
```

## 2. Contrato da API (`GET /api/v1/dossier?cnpj=00000000000191`)

### Resposta HTTP 200 (Sucesso)
```json
{
  "success": true,
  "service": "dossier",
  "cnpj": "00.000.000/0001-91",
  "cnpj_raw": "00000000000191",
  "verdict": {
    "status": "APPROVED",
    "score": 95,
    "risk_level": "LOW",
    "alerts": [],
    "summary": "Empresa ATIVA na Receita Federal, sem sanções registradas no CEIS/CNEP da CGU e com alta integridade cadastral (score 95/100)."
  },
  "company": {
    "razao_social": "BANCO DO BRASIL SA",
    "nome_fantasia": "DIRECAO GERAL",
    "situacao_cadastral": "ATIVA",
    "data_abertura": "1966-08-01",
    "natureza_juridica": "Sociedade de Economia Mista",
    "cnae_fiscal": 6422100,
    "cnae_fiscal_descricao": "Bancos múltiplos, com carteira comercial",
    "capital_social": 120000000000,
    "municipio": "BRASILIA",
    "uf": "DF"
  },
  "tax_regime": {
    "opcao_pelo_simples": false,
    "opcao_pelo_mei": false,
    "regime_resumido": "Lucro Real / Presumido (Não optante pelo Simples)"
  },
  "sanctions": {
    "has_sanctions": false,
    "total_sanctions": 0,
    "records": []
  },
  "compliance_score": {
    "score": 95,
    "factors": []
  },
  "data_protection_compliance": "LGPD Art. 1º e GDPR Recital 14 (Exclusivo para Pessoa Jurídica — Zero PII / Sem QSA ou CPF)",
  "cached": false,
  "checked_at": "2026-11-18T23:30:00Z"
}
```
*(Os blocos `_billing`, `_meta` e `_oracle_proof` são anexados automaticamente pelo gateway em `index.php`.)*

### Resposta HTTP 402 (Desafio com Teaser)
- `price_usdc`: `"0.0500"`
- `amount_raw`: `50000` (USDC com 6 decimais)
- `credits_cost`: `10` (1 crédito = 0.0050 USDC)
- `teaser_data`: contém apenas `cnpj`, `razao_social`, `municipio`, `uf`, `situacao_cadastral` e `has_sanctions` (sem o dossiê detalhado, alertas ou score completo).

## 3. Regras de Decisão do Veredito (`grid402_dossier_compute_verdict`)
1. **`REJECTED` (Risco Crítico):**
   - `situacao_cadastral` diferente de `ATIVA` (ex: `BAIXADA`, `INAPTA`, `SUSPENSA`, `NULA`), **OU**
   - `has_sanctions === true` (consta registro no CEIS ou CNEP da CGU).
2. **`FLAGGED` (Atenção / Revisão Manual):**
   - Empresa `ATIVA` e sem sanções no CEIS/CNEP, mas `score < 70` **OU** possui alertas de atenção (ex: empresa recém-aberta com menos de 180 dias, capital social zerado/não informado, ou divergência cadastral).
3. **`APPROVED` (Aprovada):**
   - Empresa `ATIVA`, sem sanções no CEIS/CNEP e `score >= 70`.

## 4. Integração MCP (`grid402-mcp`)
- Nome da ferramenta: `grid402_get_dossier_360`
- Parâmetro obrigatório: `cnpj` (string, 14 dígitos, com ou sem formatação)
- Custo declarado na descrição da tool: `0.0500 USDC (10 créditos)`
- Comportamento: chama `GET /api/v1/dossier?cnpj={cnpj}` reaproveitando o cliente x402 / Session Pass / Sandbox já existente em `mcp/tools.mjs`.

## 5. Trade-offs e Decisões
- **Reuso dos oráculos internos vs chamadas HTTP externas redundantes:** O `dossier.php` chama diretamente as funções PHP internas (`grid402_service_cnpj`, `grid402_service_simples`, `grid402_service_sanctions`, `grid402_service_compliance`), eliminando latência de loopback e aproveitando o cache individual de cada base.
- **Zero PII estrito:** A camada de montagem do campo `company` faz projeção explícita (allowlist de campos PJ), garantindo que nenhum campo de pessoa física ou QSA jamais vaze mesmo se o upstream mudar o payload.
