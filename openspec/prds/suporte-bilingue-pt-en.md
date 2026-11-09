# PRD — Suporte Bilíngue (PT-BR / EN) com Seletor de Idioma

## 1. Visão Geral e Contexto
O Grid402 é uma plataforma de grade de pixels descentralizada e microserviços HTTP 402 baseada em Base / USDC. Com a listagem recente em marketplaces globais e acesso internacional via x402 e web3, a interface pública principal (`index.php`) necessita de internacionalização completa para atender tanto o público brasileiro quanto a comunidade global de desenvolvedores e entusiastas web3.

Este projeto adiciona suporte nativo a dois idiomas (**Português do Brasil - PT-BR** e **Inglês - EN**) na interface pública (`index.php`), com comutador visual de idioma no cabeçalho e persistência de preferência via LocalStorage.

---

## 2. Objetivos e Critérios de Sucesso
- **Bilinguismo Integral no index.php**: Todas as seções (hero, grid de pixels, modal de compra e inspeção, showcase de oráculos, FAQs, rodapé e toasts de notificação) traduzidas com alta fidelidade terminológica (web3, cypherpunk, micropagamentos).
- **Zero Recarregamento de Página**: A alternância entre PT-BR e EN ocorre instantaneamente no cliente (client-side) via dicionário de tradução leve.
- **Persistência de Preferência**: O idioma selecionado é gravado em `localStorage` (chave `grid402_lang`). Em acessos futuros, a escolha é restaurada automaticamente.
- **Idioma Padrão**: PT-BR é o idioma inicial na ausência de preferência salva.
- **Design Consistente**: O seletor de idioma adota o tema visual retro-futurista/cyber do Grid402 (`🌐 EN | PT`) com estado ativo destacado.

---

## 3. Escopo

### O que está no escopo (In-Scope)
1. **Interface Principal (`public_html/index.php`)**:
   - Cabeçalho (Header) com botão alternador (`🌐 EN | PT`).
   - Textos estáticos (títulos, subtítulos, botões, descrições, labels, dicas, rodapé).
   - Textos dinâmicos gerados via JavaScript (modais de compra de pixel, cálculo de preço USDC, mensagens de validação, toasts de sucesso/erro, status de rede).
   - Atributos dinâmicos (`placeholder`, `title`, `aria-label`).
2. **Módulo de Internacionalização Client-Side (`assets/js/i18n.js` ou embutido de forma otimizada)**:
   - Dicionário com chaves estruturadas para `pt` e `en`.
   - Função `setLanguage(lang)` que varre elementos com atributo `data-i18n` e `data-i18n-attr`.
   - Persistência e leitura do `localStorage`.
3. **Estilização CSS**:
   - Botão alternador de idioma estilizado com o padrão de botões e bordas néon/cyber do Grid402.

### O que está fora do escopo (Out-of-Scope)
- Painel administrativo (`admin.php`) e diretório de agentes (`agents.php`) — mantidos como estão.
- Respostas de APIs JSON internas e endpoints de microserviços (que já seguem payload padronizado em inglês).

---

## 4. Requisitos Funcionais

1. **Seletor de Idioma**:
   - Deve ser posicionado no topo da página (header/navbar), facilmente visível tanto no desktop quanto no mobile.
   - Deve indicar visualmente qual idioma está ativo (ex.: `PT` destacado em neon cyan/magenta quando ativo).
   - Ao clicar na opção inativa, atualiza o idioma imediatamente e salva no `localStorage`.

2. **Dicionário de Termos**:
   - Traduções naturais e técnicas corretas:
     - PT: "Comprar Pixels", "Conectar Carteira", "Microserviços 402", "Taxa por Pixel", etc.
     - EN: "Buy Pixels", "Connect Wallet", "402 Microservices", "Pixel Rate", etc.

3. **Modais e Interações JS**:
   - O modal de compra (seleção de coordenadas X, Y, upload de imagem, link de redirecionamento, valor em USDC) deve ser totalmente traduzido de acordo com o idioma ativo no momento da abertura ou alternância.
   - Mensagens de feedback (toasts como "Carteira conectada com sucesso", "Transação enviada", "Coordenadas inválidas") devem emitir texto no idioma selecionado.

---

## 5. Requisitos Não Funcionais
- **Performance**: Dicionário leve (< 25 KB sem comprimir), sem dependências pesadas externas (sem bibliotecas de terceiros como i18next).
- **Acessibilidade**: Atributo `lang` no elemento `<html>` atualizado dinamicamente (`pt-BR` ou `en`) para leitores de tela e SEO.
- **Robustez**: Se faltar alguma chave em inglês, fallback transparente para português sem quebrar a UI.

---

## 6. Riscos e Mitigações
- **Risco**: Textos inseridos dinamicamente via JS (ex: status de transação web3) ficarem fixos em português.
  - *Mitigação*: Criar uma função auxiliar global `t(key, params)` acessível em todo o script frontend.
- **Risco**: Quebra de layout por variação no comprimento de palavras em inglês vs português.
  - *Mitigação*: Testar responsividade dos botões e cabeçalho em telas menores (< 480px).

