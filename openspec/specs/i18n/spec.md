# Spec: Internacionalização e Seletor de Idioma (i18n)

## Requirements

### Requirement: Seletor de Idioma no Cabeçalho (REQ-I18N-001)
O cabeçalho da interface pública DEVE conter um seletor acessível permitindo alternar entre Português (`PT`) e Inglês (`EN`), indicando visualmente o idioma ativo e disparando a tradução imediata no DOM sem recarregar a página.

#### Scenario: Alternância para Inglês
- GIVEN que o usuário está na interface pública do Grid402
- WHEN clicar na opção "EN"
- THEN o texto de todos os elementos com chave de tradução deve mudar para inglês e a opção EN deve ficar destacada

#### Scenario: Retorno para Português
- GIVEN que o usuário está visualizando a interface em inglês
- WHEN clicar na opção "PT"
- THEN todos os textos devem voltar para o português do Brasil

---

### Requirement: Persistência de Preferência (REQ-I18N-002)
A preferência de idioma do usuário DEVE ser armazenada em `localStorage` sob a chave `grid402_lang` e restaurada automaticamente em novos acessos, mantendo Português (`pt`) como idioma padrão inicial.

#### Scenario: Primeiro acesso sem preferência salva
- GIVEN que um usuário novo acessa o site pela primeira vez sem `grid402_lang` definido
- WHEN a página é carregada
- THEN o idioma exibido deve ser Português (`pt`)

#### Scenario: Persistência após recarregamento
- GIVEN que o usuário alterou o idioma para Inglês (`en`)
- WHEN recarregar a página ou abrir uma nova aba
- THEN o site deve ser renderizado diretamente em Inglês (`en`)

---

### Requirement: Cobertura Completa de Conteúdo, Modais e Card de Conversão x402 (REQ-I18N-003)
Todos os textos estáticos e dinâmicos da interface pública (Hero, 14 micro-serviços, Calculadora de ROI, Playground, Card de Desbloqueio Web3 x402, Console, Modais e Rodapé) DEVEM possuir versão em português e inglês, sem uso do caractere `&` literal em textos descritivos.

#### Scenario: Abertura de modais e desafio 402 em Inglês
- GIVEN que o idioma ativo é Inglês (`en`)
- WHEN o usuário recebe um desafio HTTP 402 no Playground ou abre o modal Web3
- THEN todos os títulos, botões de desbloqueio em 1 clique, QR Code e termos legais devem ser exibidos em inglês

