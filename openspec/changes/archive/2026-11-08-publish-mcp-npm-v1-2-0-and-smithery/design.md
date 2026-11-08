# Design: Publicação do Pacote npm v1.2.0 e Distribuição Smithery

## Contexto e Arquitetura

O pacote `grid402-mcp` foi concebido para execução direta via `npx` ou inclusão em configurações de clientes MCP como Claude Desktop, Cursor e Smithery.

### Metadados e Controle de Versão
A versão `1.2.0` consolida as 8 ferramentas operantes:
1. `grid402_summary` (Saúde geral dos serviços e latências)
2. `grid402_forex` (Cotações USD, EUR, GBP para BRL)
3. `grid402_crypto` (Preços spot BTC, ETH, SOL, USDC, BASE)
4. `grid402_dns` (Resolução de registros DNS)
5. `grid402_ssl` (Inspeção de certificados TLS/SSL)
6. `grid402_asn` (BGP e inteligência de ASN)
7. `grid402_cep` (Endereçamento postal e código IBGE)
8. `grid402_cnpj` (Dados cadastrais de Pessoa Jurídica da Receita Federal)

### Otimização do Tarball
Para garantir inicialização instantânea e menor latência de download pelo `npx`:
- Definir no `mcp/package.json` o campo:
  ```json
  "files": [
    "index.mjs",
    "tools.mjs",
    "package.json",
    "README.md"
  ]
  ```
- Excluir scripts de teste e documentações auxiliares do tarball publicado, mantendo o tamanho comprimido abaixo de 30 KB.

### Segurança e Autenticação
- Publicação executada com a conta autenticada `oliveiratco1` via `npm publish --access public`.
- Nenhuma chave privada ou segredo é empacotado.

