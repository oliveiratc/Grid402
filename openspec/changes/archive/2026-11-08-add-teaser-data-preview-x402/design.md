# Design Técnico: Amostra Parcial de Dados (Teaser Data) no Desafio HTTP 402

## 1. Arquitetura da Solução

O fluxo de processamento de um micro-serviço cadastral (CNPJ ou CEP) segue as seguintes etapas:

```
[Requisição HTTP sem X-Payment]
       │
       ▼
[Validação Sintática / Matemática] ──(Inválido)──> HTTP 400 Bad Request
       │
     (Válido)
       ▼
[Consulta de Existência no Cache ou Upstream] ──(Não Encontrado)──> HTTP 404 Not Found
       │
   (Encontrado)
       ▼
[Montagem do Payload de Amostra (Preview)]
       │
       ▼
[Emissão de HTTP 402 com Chaves 'preview' e 'locked_fields']
```

## 2. Contratos de API (JSON HTTP 402)

### 2.1. Desafio de CNPJ
```json
{
  "error": "PaymentRequired",
  "message": "Acesso a dados cadastrais completos da empresa requer micropagamento x402 de 0.0020 USDC na Base.",
  "service": "cnpj",
  "preview": {
    "cnpj": "00.000.000/0001-91",
    "razao_social": "BANCO DO BRASIL SA",
    "nome_fantasia": "DIRECAO GERAL",
    "uf": "DF",
    "situacao_cadastral": "ATIVA"
  },
  "locked_fields": [
    "qsa_socios_administradores",
    "capital_social",
    "cnae_fiscal_principal",
    "cnaes_secundarios",
    "telefones",
    "email",
    "endereco_completo"
  ],
  "payment_requirement": { ... }
}
```

### 2.2. Desafio de CEP
```json
{
  "error": "PaymentRequired",
  "message": "Acesso a dados completos de logradouro postal requer micropagamento x402 de 0.0005 USDC na Base.",
  "service": "cep",
  "preview": {
    "cep": "01310-100",
    "cidade": "São Paulo",
    "uf": "SP"
  },
  "locked_fields": [
    "bairro",
    "logradouro",
    "complemento",
    "ddd",
    "ibge"
  ],
  "payment_requirement": { ... }
}
```

## 3. Alterações nos Módulos Backend

1. **`public_html/x402.php`:**
   Assinatura atualizada:
   `function grid402_respond_402(string $service, ?string $detail = null, ?string $priceDisplay = null, ?int $priceRaw = null, ?array $preview = null, ?array $lockedFields = null): void`
   Se `$preview` for informado, inclui `preview` e `locked_fields` no JSON.

2. **`public_html/services/cnpj.php`:**
   Adicionar função `grid402_get_cnpj_preview(string $cnpj): ?array` que extrai apenas os dados públicos básicos para alimentar o desafio 402.

3. **`public_html/services/cep.php`:**
   Adicionar função `grid402_get_cep_preview(string $code): ?array` que extrai cidade e UF para o desafio 402.

4. **`public_html/index.php`:**
   - Na checagem inicial de `$paymentProof === ''`:
     - Se `$apiParam === 'cnpj'`, valida o formato e gera o preview antes de chamar `grid402_respond_402`.
     - Se `$apiParam === 'cep'`, valida o formato e gera o preview antes de chamar `grid402_respond_402`.
     - Para os demais serviços, emite o 402 padrão.
   - No frontend JavaScript do console (`runConsole`), quando receber `res.status === 402` e `json.preview`:
     - Renderiza o card visual interativo destacando a amostra e oferecendo o botão de desbloqueio.

