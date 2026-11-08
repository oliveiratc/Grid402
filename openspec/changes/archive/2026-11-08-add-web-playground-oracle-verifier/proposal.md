# Proposal: Playground Interativo e Verificador Visual de Oraculo

## Why

Embora o Grid402 conte com 11 micro-servicos de utilitarios de rede e dados publicos sob o protocolo HTTP 402 com atestacao criptografica de oraculo (`_oracle_proof` secp256k1), a experiencia para novos desenvolvedores e operadores de agentes de IA na landing page ainda e predominantemente passiva e textual. Nao ha uma forma imediata e sem friccao de experimentar chamadas reais, visualizar a mecanica do desafio HTTP 402 com amostra gratuita (teaser data) e auditar a prova matematica do oraculo diretamente pelo navegador sem configurar ambientes de desenvolvimento ou carteiras locais.

Implementar uma secao interativa de Playground e Verificador de Oraculo na landing page eleva a confianca tecnica, demonstra a transparencia das respostas assinadas e acelera a conversao de desenvolvedores para consultas pagas na Base Mainnet.

## What Changes

- **Nova Secao de Playground e Verificador:** Interface interativa responsiva em dark-mode integrada diretamente na pagina principal (`public_html/index.php`), com link de acesso rapido no cabecalho ("Playground").
- **Barra de Presets de 1 Clique:** Seletores para testar instantaneamente qualquer um dos 11 servicos (Workdays, Indicadores, Feriados, CNPJ, CEP, Forex, Crypto, DNS, SSL, ASN e Status).
- **Alternador de Modos:** Permite alternar entre "Modo Sandbox Gratuito" (executa a chamada real de teste com retorno da assinatura) e "Simulador de Desafio x402" (demonstra a resposta HTTP 402 com teaser data e dados para liquidacao).
- **Card de Auditoria Tecnica em 3 Etapas:** Painel visual com status em tempo real avaliando: 1. Integridade do hash SHA-256 do payload; 2. Carteira oficial com link direto no BaseScan; 3. Validade da assinatura digital ECDSA secp256k1 e tempo de verificacao em milissegundos.
- **Area de Verificacao Manual (JSON Arbitrario):** Campo de texto onde qualquer resposta JSON externa do Grid402 pode ser colada e auditada, detectando alteracoes indevidas (`DataHashMismatch`).
- **Novo Micro-Endpoint Backend `/api/v1/verify`:** Rota leve em PHP que recebe um JSON contendo `_oracle_proof` e processa a validacao matematica no servidor utilizando `oracle_signer.php`, garantindo compatibilidade universal em qualquer navegador.

## Capabilities

### New Capabilities
- `web-playground-verifier`: Interface interativa e componentes de visualizacao no navegador para experimentacao de consultas HTTP 402, simulador de desafios e auditoria tecnica de provas de oraculo em 3 etapas visuais.

### Modified Capabilities
- `x402-payment-oracle`: Expansao do servico de oraculo para disponibilizar o endpoint publico `/api/v1/verify`, permitindo que clientes e ferramentas de auditoria validem provas criptograficas diretamente via requisicao HTTP sem necessidade de implementar a logica criptografica secp256k1 localmente.

## Impact

- `public_html/index.php`: Adicao da secao HTML do Playground e Verificador, estilizacao CSS integrada ao tema dark-mode e scripts em Vanilla JS para consumo assincrono e renderizacao do card de auditoria. Adicao da rota `/api/v1/verify` no despachante principal.
- `public_html/openapi.json`: Documentacao formal da rota `/api/v1/verify` com schemas de entrada e saida tipados.
- `public_html/llms.txt`: Atualizacao com a documentacao do endpoint de verificacao para consumo por agentes autonomos.
- `tests/`: Adicao de testes automatizados para o endpoint `/api/v1/verify` e integracao na suite E2E.
- Zero impacto negativo em desempenho: codigo 100% Zero-DB em Vanilla JS puro, sem bibliotecas externas pesadas e com carregamento inicial da pagina preservado em menos de 50 ms.

