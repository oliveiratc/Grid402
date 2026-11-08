# PRD: Modernizacao e Monitoramento Avancado do Portal Administrativo

**Status:** Aprovado  
**Data:** 2026-11-08  
**Autor:** Thiago + Agente Antigravity  

## 1. Contexto e problema

O Grid402 opera como um hub descentralizado de micro-servicos de rede e dados publicos sob o protocolo HTTP 402 com liquidacao em USDC na rede Base Mainnet. Com as recentes entregas de 3 novos micro-servicos (`workdays`, `indicators`, `holidays`), da infraestrutura de assinatura criptografica ECDSA secp256k1 (`_oracle_proof`), do endpoint `/api/v1/verify` e do Playground web com simulador de Sandbox, o portal administrativo atual (`public_html/admin.php`) ficou defasado em relacao a capacidade real da plataforma:

1. **Inconsistencia de Catalogo**: O dashboard monitora apenas 8 servicos antigos, omitindo os dados de volume dos servicos financeiros recem-implantados.
2. **Falta de Diferenciacao de Trafego**: As consultas em modo Sandbox (`X-Payment: demo`) sao agrupadas indistintamente com consultas gratuitas ou desafios 402, mascarando as taxas reais de conversao comercial.
3. **Invisibilidade Financeira On-Chain**: O operador precisa consultar exploradores externos (BaseScan) para auditar os saldos de USDC e de ETH para gas da carteira oficial do oraculo (`0xa31c31069c43ac1c0075a93c325b0dcd7744356c`).
4. **Ausencia de Ferramentas de Exportacao**: Nao existe exportacao de relatorios para conciliacao contabil, fiscal ou analise externa de faturamento em BRL e USDC.
5. **Dificuldade na Gestao de Credenciais**: A alteracao de senha depende de processos manuais ou rota secreta de reset via cron.

Modernizar o Portal Admin centralizara o controle operacional, fornecera visibilidade financeira instantanea em tempo real e garantira a auditoria completa do ecossistema.

## 2. Objetivos

- **Objetivo 1**: Cobrir 100% dos 11 micro-servicos operantes do Grid402 no monitoramento de volume e distribuicao de trafego.
- **Objetivo 2**: Apresentar no dashboard o saldo on-chain da carteira oficial em USDC e ETH atualizado via RPC da Base com cache de 60 segundos e latencia de resposta menor que 50 ms.
- **Objetivo 3**: Classificar com precisao os tipos de evento (Pago x402, Sandbox demo, Desafio 402, Bloqueado 403 e Gratuito) no feed recente com buffer expandido para 100 eventos e filtros instantaneos no cliente.
- **Objetivo 4**: Permitir o download de relatorio consolidado em formato CSV com um clique e a alteracao segura de senha de operador via modal autenticado com Bcrypt (cost 11).
- **Objetivo 5**: Garantir roteamento resiliente das rotas `/admin` e `/dashboard` mesmo em execucao direta pelo gateway `index.php`.

## 3. Nao objetivos (fora do escopo)

- Nao criar conexao de carteira Web3 para saque de fundos (operações de transferencias de fundos da carteira continuam manuais e frias por seguranca).
- Nao adicionar multiplos niveis de permissao ou perfis hierarquicos de usuarios (permanece modelo de operador unico autenticado com sessao segura).
- Nao migrar a persistencia para banco de dados relacional SQL, preservando estritamente a arquitetura Zero-DB com arquivos JSON atomicos em `private_data/`.

## 4. Publico-alvo e personas

- **Operador do Hub Grid402**: Responsavel pela gestao de faturamento, monitoramento de disponibilidade dos nos, conciliacao contabil e acompanhamento de incidentes de rede ou tentativas de fraude (replay attacks).

## 5. Historias de usuario

- **US01**: Como operador, quero visualizar o volume de chamadas dos 11 micro-servicos para entender a demanda especifica por dados financeiros e de rede.
- **US02**: Como operador, quero acompanhar o saldo em USDC e a reserva de gas em ETH da carteira do oraculo sem precisar abrir sites externos.
- **US03**: Como operador, quero distinguir facilmente no feed quais chamadas foram pagamentos reais, testes em sandbox ou desafios 402 para avaliar a taxa de conversao comercial.
- **US04**: Como operador, quero filtrar rapidamente o feed por tipo e pesquisar por IP ou hash para investigar chamadas suspeitas.
- **US05**: Como operador, quero exportar uma planilha CSV das transacoes para enviar a contabilidade e prestar contas fiscais.
- **US06**: Como operador, quero alterar minha senha diretamente pelo painel administrativo de forma simples e segura.

## 6. Requisitos funcionais

| ID | Requisito | Prioridade |
|---|---|---|
| RF01 | O painel admin SHALL monitorar e exibir o volume de requisicoes de todos os 11 micro-servicos (`workdays`, `indicators`, `holidays`, `forex`, `crypto`, `dns`, `ssl`, `asn`, `cep`, `cnpj`, `summary`). | Must |
| RF02 | O backend SHALL consultar o saldo em USDC (via `balanceOf` no contrato ERC-20 na Base) e o saldo em ETH (via `eth_getBalance`) da carteira oficial `0xa31c31069c43ac1c0075a93c325b0dcd7744356c` com cache em disco de 60 segundos. | Must |
| RF03 | O painel SHALL exibir um card dedicado de Saldo On-Chain apresentando valor em USDC, valor em ETH e status operacional do oraculo criptografico. | Must |
| RF04 | O modulo `metrics.php` SHALL registrar o atributo `type` (`paid`, `sandbox`, `challenge_402`, `blocked_403`, `free`) para cada consulta, mantendo retrocompatibilidade total com eventos legados. | Must |
| RF05 | O painel admin SHALL expandir o buffer em memoria de consultas recentes de 50 para 100 eventos e renderizar badges coloridos especificos para cada tipo de trafego. | Must |
| RF06 | O painel SHALL fornecer uma barra de filtros instantaneos em Vanilla JS (`Todos`, `Pagos`, `Sandbox`, `Desafios 402`, `Bloqueados 403`) e campo de texto de busca reativa. | Must |
| RF07 | O painel SHALL disponibilizar endpoint `/admin?api=export_csv` e botao de interface para download direto de relatorio CSV com delimitador compativel e encoding UTF-8 com BOM. | Must |
| RF08 | O painel SHALL fornecer modal interativo de alteracao de senha validando a senha atual do operador e exigindo nova senha com no minimo 8 caracteres criptografada via Bcrypt com custo 11. | Must |
| RF09 | O gateway `public_html/index.php` SHALL interceptar as rotas `/admin` e `/dashboard` redirecionando ou carregando diretamente o script `admin.php`. | Must |

## 7. Requisitos nao funcionais

- **Performance**: O endpoint de polling `/admin?api=stats` deve responder em menos de 50 ms utilizando dados locais e o cache do saldo on-chain.
- **Zero-DB**: Nenhuma operacao utilizara banco de dados externo ou relacional; todos os estados operacionais devem residir em arquivos JSON com travas atomicas (`flock`) em `public_html/private_data/`.
- **Seguranca**: Sessao administrativa com cookies protegidos (`HttpOnly`, `SameSite=Lax`, `Secure` em HTTPS). Delay defensivo contra ataques de forca bruta mantido em 350 ms.
- **Acessibilidade e UX**: Tema dark-mode consistente com a identidade visual do Grid402, responsivo para dispositivos moveis e desktops.
- **Integridade Textual**: Ausencia absoluta do caractere e-comercial em todas as mensagens, comentarios e documentacoes (usar estritamente "e" ou "and").

## 8. Fluxos e UX

### Fluxo 1: Visualizacao e Polling do Dashboard
1. O operador acessa `/admin`. Se nao autenticado, e direcionado a tela de login.
2. Apos login, visualiza 5 cards de KPI principais:
   - Faturamento Total (USDC e BRL)
   - Faturamento Hoje (USDC e BRL)
   - Consultas Totais e Liquidadas
   - Taxa de Conversao (Pagos vs Desafios 402)
   - Saldo On-Chain da Carteira do Oraculo (USDC e ETH)
3. A cada 5 segundos, o JavaScript consome `/admin?api=stats` atualizando os numeros e barras sem recarregar a tela.

### Fluxo 2: Filtragem e Auditoria de Feed
1. O operador clica no botao de filtro "Sandbox" ou "Bloqueados 403".
2. As linhas da tabela sao filtradas instantaneamente no cliente pelo atributo de dados.
3. Ao digitar um IP ou hash no campo de busca, o filtro refina dinamicamente as linhas visiveis.

### Fluxo 3: Exportacao de Relatorio CSV
1. O operador clica no botao "Exportar CSV".
2. O navegador faz o download de `grid402_telemetria_YYYY-MM-DD.csv`.
3. O arquivo pode ser aberto diretamente no Microsoft Excel ou Google Sheets sem problemas de acentuacao.

### Fluxo 4: Alteracao de Senha
1. O operador clica no botao "Alterar Senha" no cabecalho.
2. Um modal surge solicitando: Senha Atual, Nova Senha e Confirmacao da Nova Senha.
3. Se a senha atual conferir e a nova atender aos requisitos, o arquivo de credenciais e regravado com novo hash Bcrypt e uma notificacao de sucesso e apresentada.

## 9. Metricas de sucesso

- 100% dos 11 servicos refletidos corretamente na telemetria.
- Tempo de resposta do endpoint `/admin?api=stats` consistentemente inferior a 50 ms.
- 0 falhas na conciliacao de consultas pagas entre o dashboard e os recibos de transacao na Base Mainnet.
- 100% de testes unitarios e E2E aprovados para o modulo de administracao e exportacao.

## 10. Riscos, dependencias e premissas

- **Risco: Falha temporaria ou lentidao no RPC da Base**:
  - *Mitigacao*: O cache em disco de 60 segundos isola o painel admin de interrupcoes no RPC publico da Base, retornando o ultimo saldo conhecido caso a chamada falhe.
- **Risco: Arquivo de telemetria corrompido em concorrencia**:
  - *Mitigacao*: Todas as operacoes de leitura e escrita utilizam gravacao atomica com `flock` exclusiva.
- **Premissa**: O servidor dispoe de conexao HTTPS ativa em producao para cookies seguros de sessao.

## 11. Questoes em aberto

- Nenhuma questao em aberto. Todas as premissas e requisitos foram alinhados e validados no processo de Grill-me.

