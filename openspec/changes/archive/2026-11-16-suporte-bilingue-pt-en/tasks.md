# Tasks: Implementação de Suporte Bilíngue (PT/EN)

- [x] 1. Mapeamento e Criação do Dicionário i18n
  - [x] 1.1 Extrair textos estáticos e dinâmicos de `public_html/index.php`
  - [x] 1.2 Estruturar dicionário de tradução com equivalências completas PT-BR <-> EN
  - [x] 1.3 Criar helper de tradução `t(key, fallback)` e função de atualização do DOM `setLanguage(lang)`

- [x] 2. Componente de UI e Estilização do Seletor
  - [x] 2.1 Adicionar botão comutador `PT | EN` no cabeçalho do `public_html/index.php`
  - [x] 2.2 Estilizar o seletor mantendo a estética retro-cyberpunk do Grid402
  - [x] 2.3 Conectar eventos de clique para alternância instantânea e persistência em `localStorage`

- [x] 3. Anotação dos Elementos HTML e Scripts Dinâmicos
  - [x] 3.1 Adicionar atributos `data-i18n`, `data-i18n-html` e `data-i18n-placeholder` nas tags HTML do `index.php`
  - [x] 3.2 Adaptar modais (legal, pagamento Web3) para responder à troca de idioma
  - [x] 3.3 Atualizar calculadora de ROI / economia para adaptar moedas e textos (R$ vs $)

- [x] 4. Testes e Validação
  - [x] 4.1 Validar integridade da alternância entre PT e EN sem quebras de layout
  - [x] 4.2 Testar persistência em recarregamento de página
  - [x] 4.3 Garantir que nenhuma funcionalidade do hub ou endpoints seja impactada
