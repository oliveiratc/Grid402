# Grid402 - Oráculo M2M de Telemetria e Status de Serviços Críticos (Protocolo x402)

O PRD oficial e detalhado deste projeto foi gerado seguindo o padrão OpenSpec e está localizado em:
👉 [openspec/prds/grid402-oraculo-telemetria.md](file:///c:/Antigravity/Projetos/Grid402/openspec/prds/grid402-oraculo-telemetria.md)

### Resumo Executivo:
* **Domínio:** https://www.grid402.com.br
* **Arquitetura:** PHP 8.1+ puro sobre LiteSpeed (Hospedagem Compartilhada Hostinger), Zero Banco de Dados (Flat-file JSON com proteção de acesso).
* **Protocolo de Pagamento:** HTTP x402 (`402 Payment Required`), liquidado em USDC na rede Base (US$ 0.0005/req).
* **Motor de Coleta:** `collector.php` paralelo via `curl_multi_init` (< 4 segundos de execução total para 15 a 20 alvos).
* **Descoberta:** Manifesto `llms.txt` na raiz para crawlers e agentes de IA.
