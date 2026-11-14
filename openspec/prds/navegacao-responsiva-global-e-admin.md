# PRD: Navegação Responsiva Unificada (Site Público + Portal Admin)

**Status:** Em revisão
**Data:** 2026-11-14
**Autor:** Thiago Campos de Oliveira + Agente

## 1. Contexto e problema
Hoje, cada página do Grid402 (`index.php`, `pass.php`, `catalog.php`, `agents.php`, `trust.php`, `growth_program.php`, `builder_reputation.php`, `agenthub.php`, `payment_dashboard.php`, `admin.php` e `growth_admin.php`) possui um cabeçalho diferente — algumas têm apenas links soltos, outras não têm menu mobile e sofrem com quebra de linha ou overflow horizontal em telas pequenas (`<= 768px`). Isso prejudica a descoberta do `/pass`, `/catalog` e `/agents` no celular e dificulta o uso do painel `/admin` em dispositivos móveis.

## 2. Objetivos
- Unificar a navegação pública em um componente reutilizável (`public_html/includes/nav.php`) com menu hambúrguer acessível no mobile (`<= 768px`).
- Implantar navegação responsiva dedicada no portal administrativo (`admin.php` e `growth_admin.php`) com menu hambúrguer/painel retrátil no mobile.
- Eliminar overflow horizontal (`scrollWidth > innerWidth`) nos headers de todas as páginas em viewports de `360px` a `1440px`.

## 3. Não objetivos (fora do escopo)
- Reescrever o layout completo do corpo das páginas internas além do necessário para acomodar o header responsivo.
- Alterar rotas de API ou regras de negócio de pagamento/telemetria.
- Adicionar frameworks CSS/JS externos (tudo em HTML, CSS e JS puro).

## 4. Público-alvo e personas
- **Visitante / Desenvolvedor no celular:** precisa navegar entre Home, Catálogo, Session Pass, Agentes e Trust sem quebrar o layout.
- **Operador / Administrador:** acessa `/admin` e `/growth_admin` pelo celular para acompanhar métricas, passes, faturamento e aprovações.

## 5. Histórias de usuário
- Como visitante no mobile (`<= 768px`), quero abrir um menu hambúrguer claro e acessível para ir direto para `/pass`, `/catalog`, `/agents` ou `/trust`.
- Como administrador no celular, quero alternar facilmente entre as seções do `/admin`, `/growth_admin`, voltar ao site público ou encerrar sessão sem botões cortados na tela.

## 6. Requisitos funcionais
| ID | Requisito | Prioridade (Must/Should/Could) |
|----|-----------|--------------------------------|
| RF01 | Criar partial compartilhado `public_html/includes/nav.php` com HTML semântico (`<header>`, `<nav>`), CSS responsivo e JS leve para menu hambúrguer (`<= 768px`). | Must |
| RF02 | O menu público deve exibir: logo/link **Grid402** (`/`), **Home** (`/`), **Catálogo** (`/catalog`), **Session Pass** (`/pass`), **Agentes** (`/agents`), **Trust** (`/trust`), destaque do link ativo (`aria-current="page"`) e seletor opcional de idioma `PT \| EN` quando habilitado na página. | Must |
| RF03 | Integrar o partial compartilhado em todas as páginas públicas: `index.php` (Home + rotas institucionais `/about`, `/contact`, `/terms`, `/privacy`), `pass.php`, `catalog.php`, `agents.php`, `trust.php`, `growth_program.php`, `builder_reputation.php`, `agenthub.php` e `payment_dashboard.php`. | Must |
| RF04 | Implantar navegação responsiva própria no portal administrativo (`admin.php` e `growth_admin.php`) com links para **Visão Geral** (`/admin#overview`), **Telemetria** (`/admin#telemetry`), **Passes / MP** (`/admin#passes`), **Growth** (`/growth_admin`), **Voltar ao Site** (`/`) e **Sair** (logout), com menu hambúrguer funcional em `<= 860px`. | Must |
| RF05 | Garantir acessibilidade no menu mobile: botão `<button>` com `aria-expanded`, `aria-controls`, `aria-label`, fechamento com tecla `Escape`, fechamento ao clicar fora ou ao selecionar um link âncora, e área de toque mínima de `44x44px`. | Must |
| RF06 | Garantir que `scripts/package_deploy.ps1` inclua `public_html/includes/nav.php` automaticamente no pacote de deploy. | Must |

## 7. Requisitos não funcionais
- **Performance:** zero bibliotecas externas; CSS e JS enxutos inline/scoped no partial sem requisições extras bloqueantes.
- **Compatibilidade / CSP:** compatível com o `Content-Security-Policy` atual em `.htaccess` (`script-src 'self' 'unsafe-inline'`, `style-src 'self' 'unsafe-inline'`).
- **Acessibilidade:** contraste WCAG AA, foco visível por teclado e `aria-current="page"`.

## 8. Fluxos e UX
- **Desktop (`> 768px` público / `> 860px` admin):** barra horizontal fixa/limpa no topo com marca à esquerda e links alinhados à direita.
- **Mobile (`<= 768px` público / `<= 860px` admin):** marca à esquerda + botão hambúrguer (`44x44px`) à direita; ao clicar, abre painel retrátil vertical com todos os links empilhados e fechamento por botão, clique fora, clique em link ou tecla `ESC`.

## 9. Métricas de sucesso
- 100% das páginas públicas e administrativas renderizam sem erros PHP e incluem o header responsivo.
- 100% dos cenários da spec cobertos por testes automatizados PHP verificando marcação semântica, atributos ARIA, links obrigatórios e ausência de erros de sintaxe.

## 10. Riscos, dependências e premissas
- **Risco:** conflito de estilos CSS entre o partial `nav.php` e CSS legado de alguma página interna (ex.: `.nav-links`, `.btn`). Mitigação: usar classes prefixadas `.g402-nav-*` no partial compartilhado.

## 11. Questões em aberto
- Nenhuma pendência; aguardando aprovação do PRD para gerar a change OpenSpec.
