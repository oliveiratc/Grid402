## ADDED Requirements

### Requirement: Seletor de Idioma no Cabeçalho (REQ-I18N-001)
O cabeçalho da interface pública SHALL conter um seletor acessível permitindo alternar entre Português (`PT`) e Inglês (`EN`), indicando visualmente o idioma ativo e disparando a tradução imediata sem recarregar a página.

#### Scenario: Alternância de idioma via seletor
- **GIVEN** que o usuário está visualizando a interface pública
- **WHEN** clicar em "EN" ou "PT"
- **THEN** o texto de todos os elementos com chave de tradução muda imediatamente e o botão selecionado permanece destacado

### Requirement: Persistência de Preferência (REQ-I18N-002)
A preferência de idioma do usuário SHALL ser armazenada em `localStorage` sob a chave `grid402_lang` e restaurada automaticamente em novos acessos.

#### Scenario: Restauração automática após recarregamento
- **GIVEN** que o usuário alterou o idioma ativo
- **WHEN** recarregar a página ou abrir nova aba
- **THEN** o idioma previamente selecionado é restaurado automaticamente

### Requirement: Cobertura Completa de Conteúdo e Modais (REQ-I18N-003)
Todos os textos estáticos, placeholders, títulos e mensagens de script da interface pública SHALL possuir traduções equivalentes em português e inglês.

#### Scenario: Exibição de modais no idioma selecionado
- **GIVEN** que o usuário alternou para o idioma desejado
- **WHEN** abre qualquer modal ou console interativo
- **THEN** títulos, rótulos e botões são exibidos no idioma ativo
