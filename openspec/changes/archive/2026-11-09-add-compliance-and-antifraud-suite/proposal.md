# Adicionar Suite de Compliance e Antifraude (Sanções CEIS/CNEP, Simples Nacional e Score 360)

## Por quê
Conforme especificado no PRD (`openspec/prds/grid402-compliance-antifraud-suite.md`), agentes autônomos de inteligência artificial e contratos inteligentes operando no Brasil enfrentam alto risco de responsabilidade civil e fraudes ao liquidar transações financeiras para fornecedores sem validação prévia de idoneidade jurídica e enquadramento tributário.
Bureaus tradicionais cobram preços proibitivos (R$ 2,00 a R$ 15,00 por consulta) com burocracia contratual, inviabilizando micropagamentos de agentes. O Grid402 adiciona três novos serviços oficiais com base em dados públicos abertos da CGU e Receita Federal, com carimbo criptográfico secp256k1 e liquidação via x402 na rede Base.

## O que muda
- Implementação do endpoint `/api/v1/sanctions` para consulta unificada de penalidades ativas no CEIS e CNEP (CGU).
- Implementação do endpoint `/api/v1/simples` para verificação de opção pelo Simples Nacional e enquadramento MEI.
- Implementação do endpoint `/api/v1/compliance` (Compliance Score 360) para análise consolidada de risco com pontuação de 0 a 100 e veredito direto (`APROVADO`, `ALERTA` ou `REJEITADO`).
- Mecanismo de persistência e cache indexado (24h para sanções e 7 dias para Simples Nacional) garantindo latência abaixo de 80ms.
- Atualização das rotas de liquidação x402 no oráculo com precificação escalonada (0.001 USDC para consultas pontuais e 0.003 USDC para o score consolidado).
- Exposição das novas ferramentas no portal público (`index.php`), painel administrativo (`admin.php`), adaptadores do Coinbase AgentKit e SDK Node.js.

## Impacto
- Specs afetadas: `open-data-utilities`
- Código afetado:
  - `src/Grid402/Services/ComplianceService.php` (novo serviço)
  - `public_html/index.php` (rotas de API e catálogo visual)
  - `public_html/admin.php` (telemetria de compliance)
  - `integrations/agentkit/grid402_action_provider.ts` e `.py`
  - `mcp/index.mjs`

