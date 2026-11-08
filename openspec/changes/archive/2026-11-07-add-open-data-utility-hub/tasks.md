# Tarefas de Implementação: Grid402 // Hub de Utilitários de Rede e Dados Públicos

## 1. Configuração e Utilitários Core
- [x] 1.1 Atualizar `public_html/config.php` com constantes das novas APIs, limites de cache e fontes públicas abertas
- [x] 1.2 Implementar módulo de Câmbio (`forex.php`) com conversão em tempo real de USD, EUR, GBP para BRL
- [x] 1.3 Implementar módulo de Criptoativos (`crypto.php`) com cotações de BTC, ETH, SOL, USDC e BASE
- [x] 1.4 Implementar módulo de DNS Lookup (`dns.php`) para resolução de registros A, AAAA, MX, TXT, NS, CNAME
- [x] 1.5 Implementar módulo Inspetor SSL (`ssl.php`) com conexão TLS na porta 443 e extração de CN, emissor, SANs e dias até expirar
- [x] 1.6 Implementar módulo ASN e IP Info (`asn.php`) com resolução BGP e organização de bloco
- [x] 1.7 Implementar módulo CEP e IBGE (`cep.php`) com normalização e código IBGE

## 2. Trava Atômica Anti-Replay e Gateway x402
- [x] 2.1 Refatorar `public_html/x402.php` com trava de arquivo exclusiva (`flock(LOCK_EX)`) eliminando a condição de corrida (*TOCTOU*)
- [x] 2.2 Integrar despacho de rotas `/api/v1/*` no fluxo de validação do pagamento x402

## 3. Coletor de Background e Cache Atômico
- [x] 3.1 Atualizar `public_html/collector.php` para sincronizar e persistir periodicamente os caches de Câmbio e Cripto
- [x] 3.2 Gerar atomicamente `summary_free.json` com o status consolidado dos 6 utilitários

## 4. Roteamento e Regras de Borda (.htaccess e index.php)
- [x] 4.1 Configurar regras de rewrite no `public_html/.htaccess` para rotas limpas `/api/v1/forex`, `/api/v1/crypto`, `/api/v1/dns`, `/api/v1/ssl`, `/api/v1/asn`, `/api/v1/cep`, `/api/v1/summary`
- [x] 4.2 Reativar o servidor saindo do modo manutenção para a nova infraestrutura

## 5. Interface, Console e Badges Dinâmicos
- [x] 5.1 Atualizar a Landing Page dark-mode (`index.php`) com o novo propósito e catálogo de 6 micro-serviços
- [x] 5.2 Implementar no console interativo abas de teste ao vivo para cada um dos utilitários
- [x] 5.3 Atualizar `public_html/badge.php` para gerar badges SVG em tempo real dos novos utilitários

## 6. Documentação e SDKs
- [x] 6.1 Atualizar `public_html/llms.txt` com a especificação das 6 APIs para crawlers e agentes de IA
- [x] 6.2 Atualizar o SDK Python (`sdk/python/grid402.py`) e conector de ferramentas (`grid402_tool.py`)
- [x] 6.3 Atualizar o SDK Node.js (`sdk/node/grid402.mjs` e `grid402.d.ts`)

## 7. Testes e Homologação
- [x] 7.1 Criar testes unitários para os 6 utilitários em `tests/test_utilities.php`
- [x] 7.2 Atualizar teste de concorrência anti-replay em `tests/test_x402_gateway.php`
- [x] 7.3 Atualizar bateria mestre de integração em `tests/test_e2e_full.php`
- [x] 7.4 Executar suíte completa localmente e homologar aprovação de 100% dos testes
