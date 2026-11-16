# Design: Internacionalização do Grid402 (i18n)

## 1. Abordagem de Arquitetura
A arquitetura adota um sistema de internacionalização client-side leve e reativo sem dependência externa:

```
[ Usuário clica no Seletor ] ---> [ setLanguage('en' | 'pt') ]
                                       |
                   +-------------------+-------------------+
                   |                                       |
          [ Salva localStorage ]                 [ Atualiza DOM ]
         (key: 'grid402_lang')                             |
                                             +-------------+-------------+
                                             |                           |
                                      [ data-i18n ]            [ data-i18n-placeholder ]
                                  (elementos de texto)          (inputs e textareas)
```

## 2. Dicionário de Traduções (`I18N_DICTIONARY`)
Estrutura de objeto JS contendo namespaces ou chaves planas:
- `header.*`: navegação, status, botões do cabeçalho.
- `hero.*`: título principal, estatísticas do mural, chamada para ação.
- `grid.*`: controles do canvas, zoom, coordenadas, dicas de clique.
- `modal.*`: formulário de compra de pixel, upload de imagem, título, URL, preview, botão de pagamento.
- `oracles.*`: cards de microserviços e demonstrações de endpoints x402.
- `footer.*`: links, termos, créditos.
- `messages.*`: toasts, erros web3, confirmações.

## 3. Função de Tradução Global `t(key, fallback)`
Permite traduzir strings geradas dinamicamente no JavaScript:
```javascript
function t(key, fallback = '') {
    const lang = currentLang;
    return (translations[lang] && translations[lang][key]) || (translations['pt'] && translations['pt'][key]) || fallback || key;
}
```

## 4. Estilo do Seletor
O botão alternador será inserido no cabeçalho ao lado do botão de conectar carteira ou links de navegação:
- Botão bipartido no estilo cyber:
  `[ PT | EN ]` com destaque com neon cyan (`#00f3ff`) ou amarelo cyber (`#ffd700`) na opção ativa.
- Totalmente responsivo para mobile e desktop.

