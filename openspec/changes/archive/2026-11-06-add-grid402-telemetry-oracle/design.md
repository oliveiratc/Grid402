# Design: Grid402 Telemetry Oracle

## Context
O sistema opera em ambiente de Hospedagem Compartilhada da Hostinger com LiteSpeed Web Server e PHP 8.1+. O ambiente impõe restrições rígidas:
- Limite de tempo de CPU e conexões de rede simultâneas.
- Inviabilidade de bancos relacionais para concorrência de alta frequência.
- Necessidade de entrega de payloads sob demanda em menos de 50ms para agentes e clientes M2M.

Para a motivação e requisitos comportamentais, consulte [proposal.md](proposal.md) e as especificações em `specs/`.

## Goals / Non-Goals

**Goals:**
- Arquitetura 100% baseada em arquivos planos (flat-file JSON) com escritas atômicas seguras via `tempnam()` e `rename()`.
- Coleta assíncrona paralela de 15 a 20 alvos em menos de 4 segundos via `curl_multi_init`.
- Validação on-chain direta contra a Base Mainnet (`https://mainnet.base.org`) sem intermediários pagos.
- Detecção de cabeçalho `Accept` e entrega adaptativa: HTML moderno e dark-mode para navegadores, JSON/402 para bots.
- Isolamento de segurança estrito via `.htaccess` e segredos em `.env`.

**Non-Goals:**
- Indexação histórica com busca em banco de dados relacional.
- Suporte a múltiplas redes blockchain na v1 (foco exclusivo em Base/USDC).
- Painéis complexos de administração com autenticação por sessão/cookie.

## Decisions

### 1. Persistência em Flat-File com Escrita Atômica
* **Decisão:** Armazenar `summary_free.json`, `telemetry_paid.json` e `used_txs.json` em `private_data/`. Para evitar condições de corrida (race conditions) e leituras parciais durante a escrita do coletor, toda persistência grava primeiro em arquivo temporário (`tempnam()`) e em seguida executa um `rename()` atômico.
* **Alternativas consideradas:**
  * *SQLite:* Descartado devido a bloqueios de tabela (`database is locked`) sob alta concorrência de requisições de agentes.
  * *MySQL:* Descartado pelo pool de conexões reduzido da hospedagem compartilhada da Hostinger.

### 2. Coletor Paralelo via `curl_multi_init`
* **Decisão:** Disparar todos os 15 a 20 probes HTTP concorrentemente com `CURLOPT_TIMEOUT_MS = 2800` e `CURLOPT_CONNECTTIMEOUT_MS = 1500`. Um laço assíncrono com `curl_multi_select()` monitora o término das respostas, finalizando todo o processo em ~3 segundos.
* **Alternativas consideradas:**
  * *cURL Sequencial:* Demoraria de 15 a 30 segundos, estourando limites de timeout da Hostinger e tornando a atualização impraticável.

### 3. Validação On-Chain x402 Direta via JSON-RPC
* **Decisão:** O servidor PHP efetua uma chamada POST cURL para o endpoint RPC da Base (`eth_getTransactionReceipt`) com payload JSON-RPC. A verificação checa:
  1. `status == "0x1"` (transação minerada com sucesso).
  2. Endereço do contrato do log coincide com o USDC oficial na Base (`0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913`).
  3. Tópico `0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef` (Transfer).
  4. Endereço recebedor no segundo tópico indexado confere com a carteira configurada no oráculo.
  5. Valor no campo `data` (em hexadecimal) >= `0x1f4` (500 unidades atômicas = 0.0005 USDC com 6 decimais).
* **Alternativas consideradas:**
  * *Uso de API externa / Gateway x402 de terceiros:* Descartado para eliminar taxas adicionais, latência e pontos únicos de falha de provedores terceirizados.

### 4. Prevenção a Replay Attacks via Log com Rotação (TTL)
* **Decisão:** O arquivo `private_data/used_txs.json` armazena um mapa chave-valor `{ "0x<hash>": <timestamp_unix> }`. Se o hash já estiver presente, a requisição é rejeitada com HTTP 403. Durante cada validação ou rodada do coletor, entradas com mais de 7 dias são purgadas automaticamente para manter o arquivo leve (< 100 KB).
* **Alternativas consideradas:**
  * *Validação stateless:* Impossível sem banco para x402 baseado em `tx_hash` on-chain, pois permitiria reusar o mesmo comprovante eternamente.

### 5. Resposta Adaptativa e Entrega de Alta Velocidade no `index.php`
* **Decisão:**
  * Se o cabeçalho `Accept` incluir `text/html` e não for um bot JSON explícito, o PHP renderiza inline uma Landing Page dark-mode responsiva com CSS embutido e visual moderno (estilo Tailwind/Vercel), sem requisições a bibliotecas externas pesadas.
  * Para chamadas de API, a entrega dos arquivos JSON é feita diretamente via cabeçalhos `Content-Type: application/json` e `readfile()`, minimizando o consumo de memória RAM do processo PHP.

### 6. Configuração Segura via `.env`
* **Decisão:** O `config.php` busca variáveis em arquivo `.env` localizado na raiz do projeto (fora do `public_html` em produção, ou em `private_data/.env` se na raiz da hospedagem), com valores de fallback seguros e documentados em `.env.example`.

## Risks / Trade-offs

- **[Latência ou Indisponibilidade do RPC Público da Base]**  
  *Mitigação:* Timeout estrito de 1.5s na chamada cURL para o RPC. Caso ocorra timeout, o oráculo retorna HTTP 503 com instrução clara para tentar novamente, sem debitar falsamente a transação. Possibilidade de configurar RPCs alternativos no `.env`.
- **[Concorrência de Leitura e Escrita em Arquivos]**  
  *Mitigação:* O uso de escrita em arquivo temporário seguido de `rename()` garante atomicidade no sistema de arquivos do Linux.
- **[Crescimento do Arquivo `used_txs.json`]**  
  *Mitigação:* Rotina de auto-limpeza em memória removendo transações registradas há mais de 7 dias antes de persistir o arquivo.

## Migration / Deployment Plan
1. Upload dos arquivos para o diretório `public_html` da conta Hostinger.
2. Criação do arquivo `.env` fora do `public_html` (ou em `private_data/.env`) preenchendo `WALLET_ADDRESS` e `CRON_SECRET`.
3. Configuração do Cron Job nativo no hPanel para rodar `php /home/usuario/public_html/collector.php` a cada 2 ou 5 minutos.
4. Teste de ponta a ponta acessando `https://www.grid402.com.br` no navegador e via cURL.

