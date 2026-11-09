# Design Técnico: Compliance and Antifraud Suite

## Arquitetura do Serviço

A suite é centralizada na classe `Grid402\Services\ComplianceService`, que orquestra as consultas públicas, sanitização de identificadores fiscais e enriquecimento dos payloads com a assinatura secp256k1 do oráculo.

### 1. Modelo de Dados e Endpoints

1. **`/api/v1/sanctions?cnpj=<digits>`**
   - Parâmetro obrigatório: `cnpj` (14 dígitos sanitizados).
   - Fonte primária: Cadastro de Empresas Inidôneas e Suspensas (CEIS) e Cadastro Nacional de Empresas Punidas (CNEP) disponibilizados pela Controladoria-Geral da União (CGU).
   - Resposta:
     ```json
     {
       "cnpj": "00000000000191",
       "has_sanctions": false,
       "total_sanctions": 0,
       "sanctions": [],
       "checked_at": "2026-11-09T07:10:00Z",
       "_oracle_proof": { ... }
     }
     ```

2. **`/api/v1/simples?cnpj=<digits>`**
   - Parâmetro obrigatório: `cnpj` (14 dígitos).
   - Fonte primária: Base Pública da Receita Federal do Brasil (Dados Abertos do Simples Nacional).
   - Resposta:
     ```json
     {
       "cnpj": "00000000000191",
       "optante_simples": false,
       "data_opcao_simples": null,
       "optante_simei": false,
       "data_opcao_simei": null,
       "situacao_atual": "NAO_OPTANTE",
       "checked_at": "2026-11-09T07:10:00Z",
       "_oracle_proof": { ... }
     }
     ```

3. **`/api/v1/compliance?cnpj=<digits>`**
   - Cruza a consulta oficial de CNPJ (`CnpjService`), `Sanctions` e `Simples`.
   - Algoritmo de Pontuação de Risco (0 a 100):
     - Base inicial: 100 pontos.
     - Empresa com situação cadastral inativa, suspensa ou baixada na Receita Federal: subtrai 60 pontos (status `REJEITADO`).
     - Presença de sanção ativa no CEIS ou CNEP: subtrai 70 pontos (status `REJEITADO`).
     - Empresa com menos de 6 meses de abertura: subtrai 15 pontos (alerta de empresa recém-criada).
     - Ausência de apontamentos negativos: Score entre 85 e 100 (status `APROVADO`).
   - Resposta:
     ```json
     {
       "cnpj": "00000000000191",
       "razao_social": "BANCO DO BRASIL SA",
       "score": 100,
       "status": "APROVADO",
       "flags": [],
       "summary": {
         "situacao_cadastral": "ATIVA",
         "has_sanctions": false,
         "optante_simples": false,
         "optante_simei": false
       },
       "checked_at": "2026-11-09T07:10:00Z",
       "_oracle_proof": { ... }
     }
     ```

### 2. Estratégia de Cache e Armazenamento

- Utilização de cache local em SQLite (`data/compliance_cache.sqlite` ou tabela auxiliar em `data/db.sqlite`) com TTL:
  - Sanções: 86.400 segundos (24 horas).
  - Simples Nacional: 604.800 segundos (7 dias).
  - Compliance Score: derivado em tempo de execução a partir dos caches locais, garantindo latência abaixo de 50ms.

### 3. Matriz de Precificação x402 (Rede Base)

- `/api/v1/sanctions`: 0.001 USDC
- `/api/v1/simples`: 0.001 USDC
- `/api/v1/compliance`: 0.003 USDC
- Bypass de Sandbox: cabeçalho `X-Payment: demo` concede execução instantânea com payload completo.

