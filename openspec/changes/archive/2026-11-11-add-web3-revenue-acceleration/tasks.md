## 1. Backend e Validação de Passes On-Chain
- [x] 1.1 Atualizar `public_html/services/pass.php` para calibrar o cálculo de créditos para os tiers de 2 USDC (4.000 reqs), 5 USDC (10.000 reqs) e 20 USDC (50.000 reqs).
- [x] 1.2 Garantir suporte à consulta e emissão na rota `/api/v1/pass?tx=0x...` em `public_html/index.php`.
- [x] 1.3 Testar emissão de passes com script automatizado cobrindo transação simulada e persistência atômica.

## 2. Interface Frontend: Web3 One-Click Top-Up
- [x] 2.1 Adicionar seção visual destacada de "Abastecer Saldo On-Chain (Base)" em `public_html/index.php` com os 3 cards de planos (Starter 2 USDC, Pro 5 USDC, Fleet 20 USDC).
- [x] 2.2 Implementar script frontend para detecção de carteira, validação/troca de rede para Base (8453) e envio da transação ERC-20 `transfer` do USDC.
- [x] 2.3 Conectar a confirmação da transação com o endpoint `/api/v1/pass`, exibindo o card verde de sucesso com token `pass_g402_...` e botão de copiar.

## 3. Plugin ElizaOS (`integrations/elizaos/`)
- [x] 3.1 Inicializar a estrutura do pacote TypeScript em `integrations/elizaos/` com `package.json`, `tsconfig.json` e dependências `@elizaos/core`.
- [x] 3.2 Implementar as ações de Mercados (`forex`, `crypto`, `globalMarkets`).
- [x] 3.3 Implementar as ações de Compliance e Risco (`sanctions`, `lei`, `cnpj`).
- [x] 3.4 Implementar o provider de status e injeção do cabeçalho `X-Payment`.
- [x] 3.5 Criar documentação técnica com exemplos de uso e compilar o build TypeScript (`dist/`).

## 4. Dossiê Base Grants & Coinbase CDP
- [x] 4.1 Elaborar `docs/BASE_GRANTS_AND_CDP_DOSSIER.md` com pitch em inglês, métricas de rede, arquitetura do x402 e respostas prontas para o formulário oficial.
- [x] 4.2 Adicionar links e referências nas páginas de documentação e `README.md`.

## 5. Testes e Verificação
- [x] 5.1 Executar suíte de testes automatizados PHP verificando a emissão e consumo de passes de sessão.
- [x] 5.2 Validar build e sintaxe dos pacotes TypeScript do ElizaOS.
- [x] 5.3 Validar a renderização e interatividade da interface web.

