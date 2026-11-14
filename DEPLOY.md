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
GRID402_WALLET_ADDRESS=0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90
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

