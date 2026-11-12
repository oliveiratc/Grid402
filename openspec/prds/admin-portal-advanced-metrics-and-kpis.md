# PRD: Painel Administrativo de Alta Fidelidade - Indicadores Avançados e KPIs de Faturamento Grid402

**Status:** Aprovado  
**Data:** 2026-11-12  
**Autor:** thiago + Antigravity  

---

## 1. Contexto e problema
A Grid402 agora conta com liquidação nativa de micro-pagamentos x402 v2 (EIP-3009), Passes de Sessão Web3 (2, 5 e 20 USDC) e um oráculo criptográfico ativo na Base Mainnet.
No entanto, o painel administrativo (`/admin`) exibe apenas 5 indicadores consolidados de topo e 4 barras de contagem básicas. Faltam indicadores vitais de negócio e telemetria preditiva:
- Não há visibilidade do **Ticket Médio (ARPU)** por consulta paga, dificultando avaliar se a receita decorre de micropagamentos pontuais ($0.0005 - $0.0050) ou da compra de Passes de Sessão ($2, $5, $20).
- Não há métrica evidente de **SLA / Taxa de Sucesso Técnico (HTTP 200 %)**, obscurecendo eventuais degradações de infraestrutura.
- Não há monitoramento da proporção de **Sandbox vs Produção**, que é o principal termômetro de conversão de novos desenvolvedores e agentes autônomos.
- Não há acompanhamento da evolução temporal diária em **gráfico visual nativo** (últimos 14 dias), exigindo leitura manual de tabelas.
- Não há identificação dos **Top Agentes/IPs Consumidores** (respeitando a LGPD), impedindo o reconhecimento de clientes em escala ou frotas de agentes.
- Não há matriz de **Rentabilidade por Micro-Serviço** (quais dos 19 nós geram mais valor em USDC).

---

## 2. Objetivos
1. Expandir os KPIs executivos de topo de 5 para 8 indicadores de alta fidelidade:
   - Faturamento Total (USDC / R$)
   - Faturamento Hoje (USDC / R$)
   - Ticket Médio por Consulta Paga (ARPU em USDC)
   - Consultas Totais (com detalhamento Pagas x Sandbox)
   - Taxa de Conversão Comercial (% Pagos / Desafios 402)
   - Taxa de Sucesso Técnico (SLA HTTP 200 %)
   - Taxa de Adoção Sandbox (% Sandbox / Total)
   - Saldo On-Chain e Monitor de Passes Web3 Ativos
2. Implementar um **Gráfico Temporal SVG Nativo Interativo** (Zero-dependency) cobrindo os últimos 14 dias móveis com rotação automática em `metrics.json`.
3. Criar o painel **Top Agentes/IPs Consumidores** (Top 5 a 10 IPs mascarados mais frequentes no buffer circular de eventos recentes, com volumetria, endpoints preferidos e taxa de conversão).
4. Criar o painel **Rentabilidade por Micro-Serviço** cruzando volumetria com a tabela de preços oficial em USDC.
5. Manter todos os novos indicadores sincronizados em tempo real via JSON polling `/admin?api=stats` a cada 5 segundos.

---

## 3. Não objetivos (fora do escopo)
- Não adicionar banco de dados relacional externo (MySQL/PostgreSQL) — manter estritamente a arquitetura leve Zero-DB com travas atômicas `flock`.
- Não importar bibliotecas pesadas de terceiros (como Chart.js, Recharts ou D3 via CDN) — o gráfico deve ser desenhado em SVG puro e responsivo.
- Não violar privacidade ou LGPD — nenhum IP será armazenado ou renderizado desmascarado.
- Não alterar a lógica de liquidação x402 ou contratos on-chain existentes.

---

## 4. Público-alvo e personas
- **Operador/Fundador da Grid402:** precisa analisar diariamente a conversão de novos agentes, faturamento acumulado e identificar clientes de alto consumo para oferecer planos corporativos/passes de frota.
- **Auditor Técnico:** precisa verificar o SLA de entrega dos 19 micro-serviços, taxas de erro e status da reserva on-chain na Base Mainnet.

---

## 5. Histórias de usuário
- **Como operador**, quero ver o Ticket Médio (ARPU) para entender se os agentes estão comprando passes de sessão ou pagando por query individual.
- **Como operador**, quero ver o gráfico de evolução dos últimos 14 dias para identificar tendências de crescimento e dias de maior tráfego.
- **Como operador**, quero identificar os IPs mascarados mais ativos para saber se um mesmo agente corporativo está consumindo a API repetidamente.
- **Como operador**, quero ver a receita estimada gerada por cada um dos 19 micro-serviços para direcionar novos investimentos e parcerias nos nós mais rentáveis.

---

## 6. Requisitos funcionais

| ID | Requisito | Prioridade |
|----|-----------|------------|
| **RF01** | Novos cards de KPIs executivos no topo: Ticket Médio (ARPU), Taxa de Sucesso Técnico (SLA 200 %), Adoção Sandbox (%) e Passes Web3. | **Must** |
| **RF02** | Gráfico temporal SVG nativo interativo (sem bibliotecas externas) exibindo os últimos 14 dias de volume de consultas e receita USDC. | **Must** |
| **RF03** | Painel "Top Agentes/IPs Consumidores" agregando os IPs mascarados mais ativos no buffer circular com total de chamadas e status predominante. | **Must** |
| **RF04** | Painel "Rentabilidade Estimada por Micro-Serviço" calculando o valor em USDC gerado por cada um dos 19 nós segundo a tabela de preços oficial. | **Must** |
| **RF05** | Atualização do endpoint `/admin?api=stats` para retornar os novos KPIs calculados e dados agregados em tempo real. | **Must** |
| **RF06** | Manutenção da janela móvel de 14 dias no `metrics.json` com limpeza automática de datas anteriores. | **Must** |
| **RF07** | Inclusão dos novos indicadores no relatório exportável `/admin?api=export_csv`. | **Should** |

---

## 7. Requisitos não funcionais
- **Performance:** Renderização inicial em menos de 100ms no LiteSpeed/PHP 8.x; resposta do endpoint `/admin?api=stats` em menos de 40ms.
- **Concorrência:** Toda gravação em `metrics.json` e caches permanece protegida por `flock(LOCK_EX)`.
- **Zero-Dependency:** Nenhum script externo de CDN; todo SVG, CSS e JavaScript Vanilla embutido no arquivo.
- **Responsividade:** Layout adaptável de 1 a 4 colunas em telas de 360px a 1920px (Desktop, Tablet e Mobile).
- **Segurança:** Autenticação de sessão estrita com Bcrypt, proteção anti-CSRF, limitação anti-força-bruta e anonimização de IP conforme LGPD.

---

## 8. Fluxos e UX
1. **Visualização Inicial do Dashboard:**
   - Ao carregar `/admin`, o operador visualiza a barra de status com timer de 5s, 8 cards de KPIs executivos com gradientes modernos, o novo gráfico SVG de 14 dias, os painéis analíticos (Serviços, Status HTTP, Origem, Falhas, Top Agentes e Rentabilidade) e o Feed de Eventos.
2. **Interação com o Gráfico Diário:**
   - Ao passar o cursor ou tocar em uma barra do gráfico SVG, um tooltip visual dinâmico exibe a data, volume de requisições, consultas pagas e faturamento em USDC daquele dia.
3. **Atualização Dinâmica (Polling 5s):**
   - A cada 5 segundos, a função `fetchLiveStats()` atualiza os cards, o gráfico e os rankings em segundo plano sem piscar ou recarregar a tela.

---

## 9. Métricas de sucesso
- **100% de precisão** no cálculo dos novos KPIs (ARPU, SLA técnico e percentual sandbox).
- **Zero quebras visuais** ou lentidão em conexões móveis.
- **Tempo de carregamento < 150ms** verificado em produção no Hostinger.
- **100% de aprovação** na suíte de testes automatizados PHP.

---

## 10. Riscos, dependências e premissas
- **Risco:** Crescimento do arquivo `metrics.json` ao acumular datas antigas.  
  *Mitigação:* Rotação automática retendo apenas os últimos 14 dias na chave `daily`.
- **Premissa:** As consultas pagas e sandbox continuam sendo registradas corretamente no buffer de eventos e nas métricas de telemetria.

---

## 11. Questões em aberto
- Nenhuma questão pendente. Todas as decisões técnicas e de design foram alinhadas na entrevista de Grill-me.
