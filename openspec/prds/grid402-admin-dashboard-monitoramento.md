# PRD: Painel de Monitoramento em Tempo Real e Faturamento das APIs (Admin Dashboard)

**Status:** Em revisão  
**Data:** 2026-11-07  
**Autor:** Thiago Campos + Antigravity  

---

## 1. Contexto e problema
O **Grid402** está ativo em produção, processando consultas públicas gratuitas e consultas técnicas pagas via protocolo **x402** com micropagamentos em USDC na rede **Base Mainnet**.
Atualmente, o operador do sistema não possui uma interface visual e centralizada para acompanhar em tempo real:
- O volume e a distribuição de chamadas recebidas por serviço (`forex`, `crypto`, `dns`, `ssl`, `asn`, `cep`, `summary`).
- O volume de desafios HTTP 402 emitidos vs transações pagas liquidadas.
- O faturamento total e diário acumulado em USDC e o equivalente em BRL.
- O histórico recente de transações para auditoria de pagamentos.

O administrador precisa de um painel web privado, seguro e com proteção de credenciais, mantendo a arquitetura leve **Zero-DB** (sem necessidade de banco de dados SQL) e em conformidade estrita com a **LGPD** (com anonimização de IPs).

---

## 2. Objetivos
- Disponibilizar um painel web administrativo responsivo (`/admin` ou `/dashboard`) acessível via navegador web.
- Proteger 100% dos dados analíticos e de faturamento através de autenticação com usuário e senha (criptografia via `password_hash` com Bcrypt/Argon2id).
- Implementar fluxo de primeiro acesso assistido para configuração segura das credenciais caso ainda não existam.
- Monitorar em tempo real (com atualização automática a cada 5 segundos via polling JSON leve):
  - Faturamento acumulado e do dia em USDC e conversão estimada em BRL.
  - Total de transações pagas confirmadas on-chain.
  - Contadores de requisições por API e taxas de status HTTP (200, 402, 403).
  - Feed das últimas 50 requisições/transações com hash on-chain e IP anonimizado (`189.40.xxx.xxx`).
- Manter o impacto de performance na hospedagem Hostinger inferior a 1% de CPU e sem degradação do tempo de resposta das APIs públicas.

---

## 3. Não objetivos (fora do escopo)
- Não haverá execução de ações destrutivas (ex: exclusão de histórico financeiro ou deleção de arquivos do servidor).
- Não haverá envio de e-mails ou relatórios externos automatizados (push notifications/webhooks).
- Não haverá múltiplos níveis de permissão ou RBAC (o painel é exclusivo para o operador individual/pessoa física).
- Não haverá integração com carteiras de custódia ou saques automáticos de USDC a partir do painel (a custódia do USDC permanece direta na carteira Web3 do operador).

---

## 4. Público-alvo e personas
- **Operador do Hub Grid402 (Pessoa Física):** Precisa acompanhar o uso das APIs, identificar picos de tráfego de robôs e agentes de IA, verificar o faturamento real em USDC e manter controle da operação sem depender de SSH ou inspeção manual de arquivos no servidor.

---

## 5. Histórias de usuário
1. **Como operador do Grid402**, quero acessar `/admin` e, caso seja meu primeiro acesso, definir com facilidade meu usuário e senha administrativa, para que o sistema fique imediatamente protegido contra acessos não autorizados.
2. **Como operador**, quero fazer login com usuário e senha para acessar um dashboard dark-mode limpo e direto, visualizando o faturamento em USDC e em Reais gerado pelo protocolo x402.
3. **Como operador**, quero visualizar gráficos/tabelas das APIs mais consultadas e das últimas transações on-chain com link direto para o BaseScan, para auditar a liquidação dos micropagamentos.
4. **Como operador**, quero que o painel atualize automaticamente a cada 5 segundos sem recarregar a página, para que eu possa acompanhar requisições de agentes de IA ao vivo.
5. **Como operador**, quero que os endereços IP dos agentes no histórico sejam anonimizados, para manter o compliance rigoroso com a LGPD.

---

## 6. Requisitos funcionais

| ID | Requisito | Prioridade |
|---|---|---|
| **RF01** | Rota web administrativa dedicada (`/admin`) protegida por autenticação de sessão PHP segura. | Must |
| **RF02** | Assistente de primeiro acesso: caso não exista arquivo `private_data/admin_auth.json`, exibir tela de setup inicial para cadastro do usuário e senha do administrador. | Must |
| **RF03** | Validação segura de login com proteção contra brute-force básica (delay e verificação `password_verify`). | Must |
| **RF04** | Mecanismo de gravação atômica de telemetria interna (`metrics.json`) disparado nas chamadas de API, computando acessos por serviço, status HTTP e buffer circular das últimas 50 consultas. | Must |
| **RF05** | Cálculo em tempo real do faturamento acumulado e do dia em USDC a partir da fonte da verdade (`used_txs.json` e `metrics.json`) e estimativa em BRL baseada na cotação USD/BRL atual. | Must |
| **RF06** | Feed tabular das últimas consultas com timestamp, endpoint, status HTTP, valor em USDC, tx_hash (com link para o BaseScan) e IP anonimizado (`189.40.xxx.xxx`). | Must |
| **RF07** | Endpoint interno JSON `/admin?api=stats` acessível apenas com sessão administrativa autenticada para abastecer o polling a cada 5 segundos. | Must |
| **RF08** | Botão de Logout seguro destruindo a sessão PHP e redirecionando para a tela de login. | Must |
| **RF09** | Botão de atualização manual com indicador de último timestamp atualizado. | Should |

---

## 7. Requisitos não funcionais
- **Segurança:** 
  - Cookies de sessão com `HttpOnly`, `SameSite=Lax` (ou `Strict`) e `Secure` (quando em HTTPS).
  - Senhas armazenadas exclusivamente em formato hash criptográfico (`PASSWORD_BCRYPT` com custo adequado).
  - O arquivo de credenciais e de métricas deve residir em `private_data/` bloqueado contra qualquer acesso HTTP externo via `.htaccess` (`Require all denied`).
- **Arquitetura Zero-DB:** Persistência flat-file em JSON com trava atômica de arquivo (`flock(LOCK_EX)`) para evitar corrupção em concorrência.
- **Desempenho e Sobrecarga:** O incremento de métricas nas requisições da API deve levar menos de 2 milissegundos para não afetar o tempo de resposta das consultas públicas.
- **Design e Responsividade:** Interface dark-mode consistente com o padrão visual do Grid402, adaptável para telas desktop e mobile.
- **Privacidade (LGPD):** Anonimização de endereço IP (máscara de sub-rede no último octeto de IPv4 ou blocos de IPv6).

---

## 8. Fluxos e UX

### 8.1. Fluxo de Primeiro Acesso (Setup de Credenciais)
1. Operador acessa `https://www.grid402.com.br/admin`.
2. O sistema detecta que `private_data/admin_auth.json` não existe.
3. Exibe a tela: *"Configuração Inicial de Acesso Administrativo"*.
4. Operador informa o Nome de Usuário desejado, Senha e Confirmação de Senha (mínimo 8 caracteres).
5. O sistema grava o hash em `private_data/admin_auth.json`, inicia a sessão autenticada e redireciona direto ao Dashboard com mensagem de boas-vindas.

### 8.2. Fluxo Normal de Login
1. Operador acessa `/admin`.
2. Se autenticado, exibe o Dashboard imediatamente.
3. Se não autenticado, exibe a tela de login dark-mode com campos de Usuário e Senha.
4. Após submeter credenciais válidas, regenera o ID da sessão (`session_regenerate_id(true)`) e carrega o painel.
5. Se credenciais inválidas, exibe mensagem amigável de erro com delay defensivo anti-bruteforce.

### 8.3. Visualização do Dashboard e Polling
1. Ao carregar o Dashboard, exibe 4 cards principais de destaque:
   - **Faturamento Total:** `US$ 0.0000 USDC` (`≈ R$ 0,00`)
   - **Faturamento Hoje:** `US$ 0.0000 USDC` (`N transações`)
   - **Total de Consultas:** `N requisições`
   - **Taxa de Conversão:** `% de pagamentos / desafios 402`
2. Abaixo, gráfico/barras de distribuição de tráfego por serviço (`forex`, `crypto`, `dns`, `ssl`, `asn`, `cep`, `summary`).
3. Tabela do Feed em Tempo Real com indicador pulsante "Ao Vivo" atualizando os dados a cada 5 segundos via fetch `/admin?api=stats`.

---

## 9. Métricas de sucesso
- 100% das páginas e dados administrativos protegidos por autenticação (zero vazamento não autenticado).
- Atualização em tempo real das métricas refletida no painel em até 5 segundos após a ocorrência de uma consulta na API.
- Zero impacto no tempo de resposta das consultas da API pública (acréscimo de latência < 2ms).
- Setup de primeiro acesso concluído pelo operador em menos de 1 minuto.

---

## 10. Riscos, dependências e premissas
- **Risco de Concorrência na Gravação de Métricas:** Se múltiplos robôs consultarem a API simultaneamente, a gravação de contadores em `metrics.json` poderia sofrer colisão.
  - *Mitigação:* Usar `flock(LOCK_EX)` atômico idêntico ao já homologado no `x402.php`.
- **Risco de Tamanho do Histórico de Consultas:** Se armazenarmos todas as consultas indefinidamente em JSON, o arquivo cresceria sem controle.
  - *Mitigação:* Buffer circular mantendo apenas as últimas 100 consultas detalhadas no arquivo, acumulando apenas os contadores numéricos consolidados.

---

## 11. Questões em aberto
- Nenhuma questão em aberto pendente de definição. Todas as decisões foram refinadas na etapa de Grill-me.

