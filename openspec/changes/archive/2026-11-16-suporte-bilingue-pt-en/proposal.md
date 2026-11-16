# Proposal: Suporte Bilíngue (PT-BR / EN) com Seletor de Idioma

## Motivação
O Grid402 é uma infraestrutura inovadora de micropagamentos web3 HTTP 402 e mural de pixels descentralizado na rede Base. Com a expansão do ecossistema e acessos internacionais, faz-se necessária uma interface pública acessível tanto para a comunidade brasileira quanto global.

## O que muda
- Implementação de um módulo de internacionalização (i18n) em JavaScript vanilla no frontend.
- Inclusão de um componente seletor de idioma elegante no cabeçalho do `index.php` (`🌐 EN | PT`).
- Mapeamento de todos os textos estáticos e dinâmicos (modais, alertas, botões, títulos) com atributos `data-i18n` e substituições JS.
- Suporte a persistência em `localStorage` com fallback padrão para `pt-BR`.
- Atualização em tempo real do atributo `<html lang="...">`.

## Impacto
- **Arquivos modificados**: `public_html/index.php`.
- **Arquivos novos/recursos**: Pode ser integrado diretamente ou em `public_html/assets/i18n.js` (ou bloco dedicado dentro do `index.php` para manter empacotamento ágil do projeto).
- **Compatibilidade**: Total retrocompatibilidade com as funcionalidades existentes (Web3, USDC payments, Grid interativo, API endpoints).

