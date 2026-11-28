# Especificação: Resgate 402, Checkout Pix e Passe Financeiro

## Cenários de Teste

### Cenário 1: Resgate no HTTP 402
* **Dado** que uma requisição sem token atinge `/forex` ou `/dossier`
* **Quando** o porteiro x402 retornar HTTP 402
* **Então** os cabeçalhos x402 originais devem permanecer intactos
* **E** o corpo da resposta deve conter o JSON com a chave `quick_pix_pass_url` válida

### Cenário 2: Pacote Finance Pack no Config e Pricing
* **Dado** que o catálogo de passes é carregado em `config.php`
* **Quando** a chave `finance_pack` for consultada
* **Então** deve retornar `amount_brl` igual a `29.90` e `credits` igual a `10000`
* **E** `pricing.json` deve listar o tier com os limites de endpoints

### Cenário 3: Cópia e Auto-Desbloqueio do Pix
* **Dado** que um pedido Pix foi gerado em `/pass` ou `/dossie-kyb`
* **Quando** o botão "Copiar Pix" for clicado
* **Então** o código Pix deve estar na área de transferência e o botão indicar "Copiado!"
* **E** quando o status da ordem mudar para `approved`, o painel deve transicionar automaticamente para o token gerado
