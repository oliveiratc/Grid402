# Design: Aceleração de Faturamento

## Arquitetura das Mudanças

### 1. Resgate HTTP 402
No handler que emite `HTTP 402 Payment Required` em `public_html/x402.php`, injetar no body JSON:
* `quick_pix_pass_url`: URL com parâmetros pré-selecionados para checkout imediato.
* `pix_price_brl`: 4.90
* `message`: Instrução bilíngue/concisa para agentes de IA e humanos.

### 2. Otimização do Fluxo Pix
* **Clipboard API:** Adicionar fallback `document.execCommand('copy')` caso a Clipboard API falhe em contextos restritos.
* **UX Tátil:** Mudar visual do botão para verde com texto "✓ Código Pix Copiado!" por 3 segundos.
* **Auto-Polling:** Polling a cada 2000ms. Ao receber `status: approved`, toca alerta sonoro discreto e transiciona o container com fade-in para a visualização do token/dossiê sem recarregar a página.

### 3. Tier Finance Pack
* Identificador: `finance_pack`
* Preço: R$ 29,90 (BRL) ou 5 USDC (Web3)
* Créditos: 10.000 chamadas
* Serviços Permitidos: `['forex', 'workdays', 'holidays', 'indicators']`
* Validade: 365 dias
