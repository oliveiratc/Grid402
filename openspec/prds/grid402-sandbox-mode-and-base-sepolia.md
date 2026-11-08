# PRD: Modo Sandbox e Suporte a Base Sepolia no Protocolo x402

**Status:** Em revisão
**Data:** 2026-11-08
**Autor:** Thiago Campos de Oliveira + Antigravity

---

## 1. Contexto e Problema
Desenvolvedores de agentes de inteligência artificial, engenheiros de dados e integradores Web3 precisam rodar baterias de testes automatizados (testes unitários, pipelines de CI/CD e simulações em ambiente de homologação) antes de colocarem robôs em produção com dinheiro real na Base Mainnet.

Atualmente, todos os endpoints úteis do Grid402 exigem transações com USDC real na Base Mainnet (Chain ID 8453). Essa exigência impõe uma barreira de entrada desnecessária na fase de testes:
1. Desenvolvedores hesitam em integrar o Grid402 se precisarem gastar fundos reais durante o desenvolvimento do código do agente.
2. Não há forma de simular o ciclo de liberação do protocolo x402 sem custo em esteiras de integração contínua (GitHub Actions, Docker, testes locais).

A solução consiste em implementar o **Modo Sandbox do Grid402**, que oferece duas vias de homologação gratuita:
- Via Rápida: Envio do cabeçalho simplificado `X-Payment: demo` ou `X-Payment: sandbox`.
- Via On-Chain: Validação de transações de micropagamento na testnet **Base Sepolia** (Chain ID 84532).

---

## 2. Objetivos
1. Reduzir a zero a barreira de custo para desenvolvedores testarem e validarem agentes autônomos integrados ao Grid402.
2. Entregar respostas com dados reais enriquecidos acompanhadas da flag `"sandbox": true`.
3. Proteger a infraestrutura contra abusos e scraping gratuito em produção através de uma cota defensiva de 20 requisições por hora por endereço IP.
4. Fornecer no console web do portal um botão de 1 clique para simular o pagamento imediatamente.

---

## 3. Não Objetivos (Fora do Escopo)
- Não permitir volume ilimitado gratuito no modo sandbox (o limite de 20 req/hora por IP é estrito).
- Não aceitar tokens não autorizados na testnet (apenas USDC da Base Sepolia ou cabeçalho explícito de simulação).
- Não alterar as regras de segurança e cobrança da Base Mainnet (Chain ID 8453).

---

## 4. Público-alvo e Personas
1. **Desenvolvedores de Agentes Autônomos (M2M):** Desenvolvedores criando agentes em CrewAI, LangChain, AutoGen ou ElizaOS que precisam rodar scripts de teste sem gastar criptoativo real.
2. **Engenheiros de QA e DevOps:** Equipes que configuram pipelines de CI/CD para validar que suas ferramentas de consulta cadastral e de rede estão integradas corretamente.
3. **Visitantes e Testadores no Console Web:** Humanos que desejam ver os dados completos desbloqueados em tempo real na interface antes de realizarem uma transação Web3 com fundos da carteira.

---

## 5. Histórias de Usuário
- **Como desenvolvedor de IA**, quero enviar `X-Payment: demo` na requisição do meu agente para testar o parse do JSON de retorno sem precisar transferir USDC na Mainnet.
- **Como desenvolvedor Web3**, quero enviar uma transação de testnet na Base Sepolia para comprovar que a lógica de assinatura de carteira do meu agente funciona perfeitamente de ponta a ponta.
- **Como visitante no portal web**, quero clicar em "Simular Pagamento (Sandbox)" para ver imediatamente o desbloqueio dos dados na tela sem conectar carteira.

---

## 6. Requisitos Funcionais

| ID | Requisito | Prioridade |
|---|---|---|
| **RF01** | O gateway deve reconhecer o cabeçalho `X-Payment: demo` ou `X-Payment: sandbox` e autenticar a requisição no Modo Sandbox sem acionar o RPC da Mainnet. | Must |
| **RF02** | O gateway deve aceitar transações on-chain confirmadas da testnet **Base Sepolia** (Chain ID 84532) enviadas no cabeçalho `X-Payment`, validando contra o RPC público da Sepolia. | Must |
| **RF03** | As respostas entregues em Modo Sandbox devem incluir a chave `"sandbox": true` e o cabeçalho HTTP `X-Payment-Mode: sandbox`. | Must |
| **RF04** | O sistema deve aplicar um limite defensivo de taxa (Rate Limit) de 20 requisições de sandbox por hora por endereço IP. | Must |
| **RF05** | Caso o IP atinja o limite de 20 requisições de sandbox por hora, o servidor deve retornar HTTP 429 Too Many Requests com mensagem orientando a migração para a Base Mainnet. | Must |
| **RF06** | O console interativo web deve incluir o botão `🧪 Simular Pagamento (Sandbox)` que preenche o cabeçalho de teste e executa o desbloqueio dos dados com 1 clique. | Must |
| **RF07** | A documentação técnica nos snippets do portal (Python, Node.js, cURL) e no `llms.txt` deve orientar o uso do cabeçalho de teste para desenvolvedores de agentes. | Should |

---

## 7. Requisitos Não Funcionais
1. **Performance:** O processamento de requisições com `X-Payment: demo` deve ter latência inferior a 30ms (sem chamadas on-chain adicionais).
2. **Armazenamento Zero-DB:** O controle do rate limit de sandbox por IP deve ser persistido em arquivo JSON atômico com expiração automática por janela de tempo de 3600 segundos.
3. **Compatibilidade:** O modo sandbox deve funcionar em todos os micro-serviços do Grid402 (CNPJ, CEP, DNS, SSL, ASN, Forex e Cripto).

---

## 8. Exemplo de Resposta no Modo Sandbox

### Consulta de CNPJ com `X-Payment: demo`
```json
{
  "success": true,
  "sandbox": true,
  "service": "cnpj",
  "cnpj": "00.000.000/0001-91",
  "razao_social": "BANCO DO BRASIL SA",
  "nome_fantasia": "DIRECAO GERAL",
  "situacao_cadastral": "ATIVA",
  "capital_social": "12000000000.00",
  "cnae_fiscal": 6422100,
  "cnae_fiscal_descricao": "Bancos múltiplos, com carteira comercial",
  "endereco": {
    "logradouro": "QUADRA SAUN QUADRA 5 LOTE B TORRES I, II E III",
    "bairro": "ASA NORTE",
    "municipio": "BRASILIA",
    "uf": "DF",
    "cep": "70040-912"
  },
  "sandbox_info": {
    "remaining_quota_this_hour": 19,
    "production_ready": "Para chamadas em producao sem limite de taxa, envie transacoes de 0.0020 USDC na Base Mainnet."
  }
}
```

---

## 9. Métricas de Sucesso
- Aumento de 50% no número de desenvolvedores que concluem uma chamada com sucesso no console web.
- Crescimento na adoção do pacote `grid402-mcp` e SDKs por robôs rodando em esteiras de teste.
- Zero incidentes de sobrecarga por abuso de IP no sandbox graças à trava de 20 req/hora.

---

## 10. Riscos, Dependências e Premissas
- **Risco:** Usuários tentarem usar o modo sandbox para operações comerciais em produção.  
  *Mitigação:* A cota estrita de 20 consultas por hora por IP impede qualquer viabilidade de scraping ou produção comercial no modo gratuito, direcionando os usuários para a liquidação via x402.
- **Dependência:** RPC público da Base Sepolia (`https://sepolia.base.org`) acessível para validação de testes on-chain.

