# Proposta: Aceleração de Faturamento e Otimização do Funil de Conversão

## Motivação
A análise dos dados reais do Grid402 em produção (`metrics.json`, `requests.jsonl` e `mp_orders.json`) revelou:
1. **118 requisições rejeitadas com HTTP 402**, indicando alta demanda reprimida de desenvolvedores e agentes sem saldo Web3 ativo no momento.
2. **6 pedidos Pix pendentes** no Mercado Pago por atrito ou abandono na visualização do código Pix.
3. **162 requisições gratuitas/sandbox** concentradas nos serviços de alta frequência financeira (`forex` e `workdays`).

Esta change visa destravar o faturamento através de 3 intervenções cirúrgicas:
1. Resgate direto do erro 402 via Pix com link amigável.
2. Otimização do checkout Pix inline com cópia em 1 clique e auto-desbloqueio.
3. Criação do "Passe Financeiro / Automação" por R$ 29,90 com 10.000 requisições.

## Impacto
* **Arquivos Afetados:**
  * `public_html/x402.php`: payload JSON do erro 402 enriquecido com link de compra Pix.
  * `public_html/config.php` e `public_html/pricing.json`: cadastro do tier `finance_pack`.
  * `public_html/pass.php`: inclusão do card do novo pacote e aprimoramento do fluxo de cópia e polling Pix.
  * `public_html/dossie_kyb.php`: feedback aprimorado ao copiar Pix e polling instantâneo.
* **Segurança e Compatibilidade:** Preserva 100% dos headers x402 e compatibilidade com EIP-3009 e MCP.
