# Design Técnico: API de Consulta Cadastral de CNPJ (Pessoa Jurídica)

## 1. Arquitetura e Decisões Técnicas

### 1.1. Validação Matemática Precoce (Módulo 11)
Para evitar que agentes autônomos ou desenvolvedores gastem USDC em consultas de CNPJs inválidos ou digitados incorretamente:
1. O endpoint higieniza o parâmetro `cnpj`, removendo quaisquer caracteres não numéricos (`preg_replace('/\D/', '', $raw)`).
2. Se o comprimento for diferente de 14 dígitos ou se consistir em dígitos repetidos (ex.: `00000000000000`, `11111111111111`), o sistema rejeita imediatamente com HTTP 400 Bad Request.
3. Os dois dígitos verificadores são validados rigorosamente pelo algoritmo padrão Módulo 11 da Receita Federal:
   - Primeiro dígito: pesos `[5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]`.
   - Segundo dígito: pesos `[6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]`.
4. Apenas se o CNPJ for matematicamente válido o sistema aciona o desafio de pagamento HTTP 402 ou valida a transação `X-Payment`.

### 1.2. Conformidade e Risco Zero sob LGPD
A extração e normalização descartam estritamente o array `qsa` (Quadro de Sócios e Administradores) retornado pelos upstreams, retendo apenas atributos públicos pertencentes à Pessoa Jurídica:
- `cnpj`: número formatado (ex: `00.000.000/0001-91`) e numérico.
- `razao_social`: Razão Social da empresa.
- `nome_fantasia`: Nome Fantasia cadastrado.
- `situacao_cadastral`: Situação ativa, inapta, baixada, suspensa ou nula.
- `data_situacao`: Data do evento da situação cadastral.
- `data_abertura`: Data de início de atividade.
- `cnae_fiscal`: Código do CNAE principal.
- `cnae_fiscal_descricao`: Descrição do CNAE principal.
- `cnaes_secundarios`: Coleção de CNAEs secundários (código e descrição).
- `natureza_juridica`: Descrição e código da natureza jurídica.
- `endereco`: Logradouro, número, complemento, bairro, município, UF e CEP.
- `capital_social`: Valor do capital social registrado.

### 1.3. Estratégia de Cache Zero-DB e Resiliência Upstream
1. As consultas são gravadas em `GRID402_STORAGE_DIR/cnpj_cache.json` utilizando leitura compartilhada (`LOCK_SH`) e escrita atômica (`grid402_atomic_write` com `LOCK_EX`).
2. Cada entrada possui um TTL de 7 dias (`604800` segundos), garantindo respostas sub-100ms para consultas subsequentes e protegendo upstreams contra sobrecarga.
3. A consulta externa utiliza **BrasilAPI** (`https://brasilapi.com.br/api/cnpj/v1/{cnpj}`) como upstream primário com timeout estrito de 4.0 segundos, e **MinhaReceita** (`https://minhareceita.org/{cnpj}`) como fallback automático caso o primário retorne timeout ou indisponibilidade.

### 1.4. Modelo de Preço Parametrizado no Protocolo x402
- O oráculo mantém suporte a preços diferenciados por serviço.
- `GRID402_PRICE_DEFAULT = '0.0005'` (utilitários de rede e câmbio).
- `GRID402_PRICE_CNPJ = '0.0020'` (consulta de CNPJ da Receita Federal).
- O cabeçalho `X-Accept-Payment` emitido para o serviço `cnpj` informa explicitamente:
  `base:usdc:0xa31c31069c43ac1c0075a93c325b0dcd7744356c:0.0020`
- A verificação de transação no `public_html/x402.php` valida se o valor transferido atende ao montante exigido de 0.0020 USDC.

---

## 2. Diagrama de Dados Normalizados (JSON)

```json
{
  "status": "success",
  "service": "cnpj",
  "cnpj": "00.000.000/0001-91",
  "cnpj_raw": "00000000000191",
  "razao_social": "BANCO DO BRASIL SA",
  "nome_fantasia": "DIRECAO GERAL",
  "situacao_cadastral": "ATIVA",
  "data_situacao": "2005-11-03",
  "data_abertura": "1966-08-01",
  "cnae_fiscal": 6422100,
  "cnae_fiscal_descricao": "Bancos múltiplos, com carteira comercial",
  "cnaes_secundarios": [],
  "natureza_juridica": "Sociedade Anônima Aberta",
  "endereco": {
    "logradouro": "SAUN QUADRA 5 LOTE B",
    "numero": "SN",
    "complementO": "TORRE I, II, III",
    "bairro": "ASA NORTE",
    "municipio": "BRASILIA",
    "uf": "DF",
    "cep": "70040-912"
  },
  "capital_social": 12000000000.0,
  "cached": true,
  "cache_age_seconds": 3600,
  "timestamp": "2026-11-07T23:20:00Z"
}
```

