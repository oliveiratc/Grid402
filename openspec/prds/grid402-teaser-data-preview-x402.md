# PRD: Amostra Parcial de Dados (Teaser Data) no Desafio HTTP 402

**Status:** Em revisão
**Data:** 2026-11-08
**Autor:** Thiago Campos de Oliveira + Antigravity

---

## 1. Contexto e Problema
Atualmente, quando um desenvolvedor de agentes de IA ou um usuário humano consulta um micro-serviço monetizado do Grid402 (como `/api/v1/cnpj` ou `/api/v1/cep`) sem o cabeçalho `X-Payment`, a API retorna um código HTTP 402 com metadados puramente técnicos de liquidação (`wallet`, `token`, `price_usdc`).

Esse comportamento gera fricção e desconfiança na conversão:
1. O desenvolvedor não consegue comprovar programaticamente se o dado existe na base e se está atualizado antes de transferir USDC na Base.
2. No console web, o usuário vê apenas um bloco de erro JSON genérico, reduzindo o incentivo visual para clicar no botão Web3 ou escanear o QR Code.

A solução consiste em fornecer uma **Amostra Parcial (Teaser Data)** dentro do próprio corpo da resposta 402 para os serviços cadastrais (CNPJ e CEP), comprovando a existência do registro com dados básicos abertos e listando explicitamente os campos enriquecidos que serão liberados mediante pagamento.

---

## 2. Objetivos
1. Aumentar a taxa de conversão de requisições 402 em consultas pagas (HTTP 200).
2. Fornecer prova imediata de integridade cadastral sem comprometer o valor monetizado dos dados profundos (Sócios/QSA, Capital Social, Logradouro completo).
3. Entregar uma experiência visual intuitiva no console interativo da web, transformando o erro 402 em um convite de desbloqueio com dados reais.

---

## 3. Não Objetivos (Fora do Escopo)
- Não aplicar teaser data em micro-serviços puramente técnicos (DNS, SSL, ASN, Câmbio e Cripto), que mantêm o desafio 402 padrão.
- Não abrir campos de alto valor agregado (QSA/Sócios, Capital Social, Telefones, E-mails e CNAEs) na prévia gratuita.
- Não alterar a mecânica central do protocolo x402 (validação de hash on-chain, proteção anti-replay e escrita atômica).

---

## 4. Público-alvo e Personas
1. **Desenvolvedores de Agentes Autônomos (M2M):** Robôs de enriquecimento de leads e auditoria que recebem o 402, validam a chave `preview` para confirmar que a entidade existe e autorizam a liquidação imediata da tarifa.
2. **Desenvolvedores de Software e Usuários Web:** Humanos testando a API no console do Grid402 que visualizam os dados da empresa/CEP e decidem desbloquear os dados completos via carteira Web3 ou QR Code.

---

## 5. Histórias de Usuário
- **Como desenvolvedor de agente autônomo**, quero receber o nome da empresa e a situação cadastral no erro 402 para que meu agente decida se vale a pena gastar 0.0020 USDC na consulta completa.
- **Como usuário no console interativo**, quero ver os dados da empresa ou cidade pré-carregados visualmente para ter certeza de que a consulta trará os dados que procuro antes de pagar.

---

## 6. Requisitos Funcionais

| ID | Requisito | Prioridade |
|---|---|---|
| **RF01** | A API de CNPJ (`/api/v1/cnpj`) deve incluir no corpo do HTTP 402 a chave `preview` contendo `cnpj`, `razao_social`, `nome_fantasia`, `uf` e `situacao_cadastral`. | Must |
| **RF02** | A API de CNPJ deve listar no HTTP 402 a chave `locked_fields` com os campos protegidos: `qsa`, `capital_social`, `cnae_principal`, `cnaes_secundarios`, `telefones`, `email` e `logradouro_completo`. | Must |
| **RF03** | A API de CEP (`/api/v1/cep`) deve incluir no corpo do HTTP 402 a chave `preview` contendo `cep`, `cidade` e `uf`. | Must |
| **RF04** | A API de CEP deve listar no HTTP 402 a chave `locked_fields` com os campos protegidos: `bairro`, `logradouro`, `complemento`, `ddd` e `ibge`. | Must |
| **RF05** | Caso o CNPJ ou CEP informado na requisição seja inexistente ou inválido, a API deve retornar o erro cadastral apropriado (HTTP 400 ou 404) antes de emitir o 402, evitando que o usuário pague por registros inexistentes. | Must |
| **RF06** | O console interativo web (`#tab-console`) deve detectar a presença da chave `preview` na resposta 402 e renderizar um componente de destaque visual com os dados abertos e um botão de ação imediata para desbloqueio. | Must |
| **RF07** | O MCP Server (`grid402-mcp`) deve repassar a prévia informativa ao modelo de IA quando o desafio 402 for retornado sem pagamento configurado. | Should |

---

## 7. Requisitos Não Funcionais
1. **Performance:** A geração do preview no 402 não deve ultrapassar 150ms, utilizando a infraestrutura de dados abertos e cache local já existente.
2. **Segurança e Privacidade:** Respeito absoluto à LGPD. Nenhum dado de pessoa natural identificável é retornado na prévia.
3. **Compatibilidade com Protocolo x402:** Os cabeçalhos `X-Authenticate: x402` e `X-Accept-Payment` devem ser estritamente preservados no HTTP 402.
4. **Zero-DB:** Todos os metadados e caches continuam persistidos em arquivos JSON atômicos.

---

## 8. Estrutura do JSON do Desafio HTTP 402 com Teaser Data

### Exemplo: CNPJ (`/api/v1/cnpj?cnpj=00000000000191`)
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
  "payment_requirement": {
    "chain": "base",
    "chain_id": 8453,
    "token": "USDC",
    "token_contract": "0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913",
    "amount_raw": "2000",
    "amount_display": "0.0020",
    "recipient_wallet": "0xa31c31069c43ac1c0075a93c325b0dcd7744356c",
    "instruction": "Efetue a transferencia ERC-20 na Base Mainnet e reenvie com cabecalho X-Payment: <txHash>"
  }
}
```

---

## 9. Métricas de Sucesso
- **Taxa de Conversão:** Crescimento percentual de transações pagas a partir de requisições que receberam 402.
- **Redução de Pagamentos Desperdiçados:** Zero incidentes de usuários pagando por CNPJ ou CEP que não existem no banco de dados.
- **Tempo Médio até Liquidação:** Redução no tempo entre a emissão do 402 e o envio do `X-Payment` pelos clientes integrados.

---

## 10. Riscos, Dependências e Premissas
- **Risco:** Scraping indevido das prévias sem conversão em compras.  
  *Mitigação:* Os dados de preview (Razão Social e UF) são de domínio público universal e têm valor comercial limitado isoladamente. O verdadeiro valor procurado pelos clientes (QSA, Capital, CNAEs e Contatos) permanece 100% blindado atrás do paywall.
- **Dependência:** As fontes públicas abertas e o cache local dos serviços devem estar saudáveis para emitir o preview no 402.

