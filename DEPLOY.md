# Guia de Deploy na Hostinger // Grid402

Este documento detalha o passo a passo para colocar o **Grid402** em produção no LiteSpeed da Hostinger.

---

## 📦 1. Pacote de Produção Gerado
O pacote otimizado já foi gerado e se encontra em:
👉 `dist/grid402_hostinger_deploy.zip` (apenas 17 KB)

Ele contém estritamente os arquivos de produção necessários:
* `.htaccess` (roteamento, CORS e compressão)
* `index.php` (porteiro x402 e landing page)
* `collector.php` (motor paralelo de coleta cURL Multi)
* `config.php` (catálogo e constantes)
* `x402.php` (validação on-chain na Base)
* `llms.txt` (manifesto para IA)
* `private_data/.htaccess` (bloqueio total de acesso direto)

*(Se precisar recriar o zip a qualquer momento, execute `powershell -ExecutionPolicy Bypass -File scripts\package_deploy.ps1`)*.

---

## 🚀 2. Passo a Passo no hPanel da Hostinger

### Passo 2.1: Upload dos Arquivos
1. Acesse o **hPanel da Hostinger** (https://hpanel.hostinger.com).
2. Vá em **Sites ➔ Gerenciar ➔ Gerenciador de Arquivos** (ou acesse via FTP).
3. Abra a pasta `public_html/`.
4. Faça o upload do arquivo `grid402_hostinger_deploy.zip`.
5. Clique com o botão direito no arquivo zip e selecione **Extrair** (direto na pasta `public_html`).
6. Após extrair, você pode excluir o arquivo `.zip`.

### Passo 2.2: Criar o Arquivo `.env` Seguro
1. No Gerenciador de Arquivos, navegue até a pasta **um nível acima de `public_html/`** (a pasta raiz do seu usuário, ex.: `/home/u123456789/`).
2. Crie um novo arquivo com o nome exato `.env`.
3. Cole as seguintes configurações preenchendo sua carteira e chave:

```ini
GRID402_WALLET_ADDRESS=0xa31c31069c43ac1c0075a93c325b0dcd7744356c
GRID402_CRON_SECRET=crie_um_token_secreto_longo_aqui_123456
GRID402_BASE_RPC_URL=https://mainnet.base.org
GRID402_USDC_CONTRACT=0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913
GRID402_USDC_REQUIRED_RAW=500
GRID402_PRICE_DISPLAY=0.0005
GRID402_TIMEOUT_MS=2800
GRID402_CONNECT_TIMEOUT_MS=1500
```
*(Nota: O `config.php` detecta automaticamente este arquivo fora da raiz pública, impedindo que visitantes ou robôs tenham acesso aos seus segredos)*.

---

### Passo 2.3: Configurar a Tarefa Cron (Coleta Autônoma)
1. No hPanel, vá em **Avançado ➔ Tarefas Cron** (Cron Jobs).
2. Selecione o tipo de cron: **Comum (Custom)**.
3. Escolha o intervalo: **A cada 2 minutos** (`*/2 * * * *`) ou **A cada 5 minutos** (`*/5 * * * *`).
4. No campo **Comando a executar**, insira o caminho completo do PHP da Hostinger seguido do `collector.php`:
```bash
/usr/bin/php /home/u123456789/public_html/collector.php
```
*(Substitua `u123456789` pelo nome de usuário da sua conta Hostinger, visível no topo do Gerenciador de Arquivos)*.
5. Clique em **Salvar**.

---

## 5. Git Deploy nativo da Hostinger (branch `deploy`) - RECOMENDADO

O Git Deploy da Hostinger publica a **raiz do branch** no document root do site. Como
o repositorio tem `public_html/` como subpasta, publicar a branch `main` faria o app
cair em `/public_html/` e o docroot ficaria sem `index.php` -> **HTTP 403 Forbidden**.

Por isso existe a branch **`deploy`**: um branch orfao gerado automaticamente onde a
raiz e exatamente o conteudo de `public_html/`.

### 5.1 Configuracao no hPanel (uma vez)

Sites -> grid402.online -> Gerenciar -> Git:
- **Repositorio:** `oliveiratc/Grid402`
- **Branch:** `deploy`  <- importante (nao `main`)
- **Diretorio raiz:** `public_html` (pasta do servidor, ja correta)
- **Implantacao automatica:** ativada

Depois: **Reimplantar**.

### 5.2 Geracao automatica da branch `deploy`

- `.github/workflows/build-deploy-branch.yml` roda a cada push em `main`
- `scripts/build_deploy_branch.sh` extrai `public_html/` de `main`, remove segredos
  (`private_data/*.json`, `.env`) e publica a branch `deploy` (force-push)
- Local: `bash scripts/build_deploy_branch.sh deploy origin`

Fluxo: `push main` -> CI gera `deploy` -> Hostinger auto-redeploya `deploy` -> site atualizado.

### 5.3 Como saber que deu certo

```bash
curl -s -o /dev/null -w "%{http_code}" https://grid402.online/          # 200 (nao 403)
curl -s https://grid402.online/api/v1/forex | grep -q sandbox_test && echo "sandbox_test OK"
curl -s -o /dev/null -w "%{http_code}" https://grid402.online/pass     # 200
curl -s -o /dev/null -w "%{http_code}" https://grid402.online/plans    # 200
```

Se `/` continuar 403: a branch ainda esta `main`. Troque para `deploy` e reimplante.
Se `/` der 200 mas `/pass` der 404: a branch esta correta mas o deploy ainda nao rodou
(ou `public_html/` no servidor tem residuos) -> use **Reimplantar** novamente ou limpe
a pasta `public_html/` no Gerenciador de Arquivos antes de reimplantar.

---

## 4. Deploy Automatico (GitHub Actions -> Hostinger)

A partir do workflow `.github/workflows/deploy.yml`, todo push em `main` que passa nos testes dispara um deploy automatico via FTP para a Hostinger.

### 4.1 Configuracao unica (uma vez)

No GitHub do repositorio -> Settings -> Secrets and variables -> Actions -> New repository secret, crie:

| Secret | Valor | Onde obter |
|---|---|---|
| `FTP_SERVER` | host FTP (ex.: `ftp.grid402.online` ou o host fornecido pela Hostinger) | hPanel -> Sites -> Gerenciar -> FTP Accounts |
| `FTP_USERNAME` | usuario FTP | hPanel -> FTP Accounts |
| `FTP_PASSWORD` | senha FTP | hPanel -> FTP Accounts |

Use a conta FTP principal (a que tem acesso a raiz do site). O workflow envia `public_html/` -> raiz do site.

### 4.2 Como funciona

1. Push em `main` dispara `.github/workflows/ci.yml` (testes PHP + SDKs).
2. Ao concluir com sucesso, dispara `.github/workflows/deploy.yml` (via `workflow_run`).
3. O job de deploy:
   - Lint PHP
   - Smoke test (`test_config.php`)
   - Upload incremental via FTPS (porta 21) de `public_html/` -> raiz do site, **excluindo** `private_data/*.json`, `private_data/.env`, `.env`, logs, `node_modules`, `dist`
4. Disponivel tambem via Actions -> "Deploy - Grid402 (Hostinger)" -> Run workflow (manual).

### 4.3 O que o deploy automatico NAO toca (configuracao manual, uma vez)

- **`.env` na raiz do usuario** (um nivel acima de `public_html/`) - copie de `.env.example` e preencha (`GRID402_FACILITATOR_MODE=settle`, CDP creds, oracle, Telegram, sandbox rate limit 30). Sem isso, o facilitador fica `disabled` e nada fatura.
- **Tarefas Cron** no hPanel (Advanced -> Cron Jobs) apontando para `collector.php` - sem elas, forex/summary ficam congelados.
- **Webhook do Mercado Pago** -> `https://grid402.online/webhooks/mercadopago` (eventos `payment`).
- **Payment Links / Price IDs** no `.env` (MP_LINK_*, STRIPE_PRICE_*).

### 4.4 Quando o deploy automatico NAO e suficiente

- Primeira publicacao em um host vazio: prefira o pacote `dist/grid402_hostinger_deploy.zip` (upload manual) para garantir permissoes e estrutura.
- Mudancas de `.htaccess` com `mod_rewrite` desabilitado: o hPanel pode exigir ativacao manual.
- Alteracoes de `.env` (sempre manuais, por seguranca).
- Migracoes de schema (nao ha - zero-DB).

### 4.5 Desativar o deploy automatico

Em `.github/workflows/deploy.yml`, comente o bloco `on:` ou adicione `if: false` no job. Ou remova os secrets - sem `FTP_PASSWORD`, o job falha antes de enviar.

### 4.6 Rollback

- Reverta o commit no GitHub (`git revert <sha> && git push`) -> CI roda -> deploy automatico restaura a versao anterior.
- Ou restaure manualmente via hPanel (Gerenciador de Arquivos -> versoes anteriores, se disponivel).
- Dados em `private_data/*.json` (metricas, passes, vendas) **nao** sao sobrescritos pelo deploy (excluidos) -> seguros.

---

## 🔍 3. Verificação de Funcionamento

Após o deploy e a primeira execução do cron (em até 2 minutos):

### A. Teste de Navegador
Acesse no navegador:
👉 `https://grid402.online`
*Você deverá ver a Landing Page dark-mode com status ao vivo dos serviços e latências.*

### B. Teste do Manifesto de IA
Acesse:
👉 `https://grid402.online/llms.txt`

### C. Teste do Desafio x402 via cURL
No terminal da sua máquina:
```bash
curl -i -H "Accept: application/json" https://grid402.online/telemetry
```
*Deve retornar `HTTP/1.1 402 Payment Required` com o endereço da sua carteira e o valor de 0.0005 USDC.*

### D. Teste do Resumo Gratuito
```bash
curl -H "Accept: application/json" https://grid402.online/status?format=json
```
*Deve retornar HTTP 200 em menos de 30ms com as métricas consolidadas dos serviços brasileiros.*

