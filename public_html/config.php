<?php
/**
 * Grid402 - Configuração Central e Catálogo de Alvos
 * 
 * Oráculo M2M de Telemetria e Status de Serviços Críticos (Protocolo x402)
 */

declare(strict_types=1);

// Evitar execução se chamado diretamente pelo navegador
if (!defined('GRID402_EXEC') && php_sapi_name() !== 'cli' && basename($_SERVER['PHP_SELF'] ?? '') === 'config.php') {
    http_response_code(403);
    exit('Acesso direto proíbido.');
}

// 1. Carregador de variáveis de ambiente (.env)
function grid402_load_env(): void {
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;

    $candidates = [
        dirname(__DIR__) . '/.env',                 // Raiz segura (acima de public_html)
        __DIR__ . '/private_data/.env',             // Dentro de private_data
        __DIR__ . '/.env',                          // Local de desenvolvimento
    ];

    foreach ($candidates as $filePath) {
        if (file_exists($filePath) && is_readable($filePath)) {
            $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines === false) {
                continue;
            }
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }
                if (str_contains($line, '=')) {
                    [$key, $value] = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value, " \t\n\r\0\x0B\"'");
                    if (!isset($_SERVER[$key]) && !isset($_ENV[$key])) {
                        putenv("{$key}={$value}");
                        $_ENV[$key] = $value;
                        $_SERVER[$key] = $value;
                    }
                }
            }
            break;
        }
    }
}

grid402_load_env();

// Função auxiliar para leitura com fallback
function grid402_env(string $key, mixed $default = null): mixed {
    $val = getenv($key);
    if ($val !== false && $val !== '') {
        return $val;
    }
    return $_ENV[$key] ?? $_SERVER[$key] ?? $default;
}

// 2. Parâmetros x402 e Blockchain (Base / USDC)
define('GRID402_WALLET_ADDRESS', strtolower((string) grid402_env('GRID402_WALLET_ADDRESS', '0xa31c31069c43ac1c0075a93c325b0dcd7744356c')));
define('GRID402_BASE_RPC_URL', (string) grid402_env('GRID402_BASE_RPC_URL', 'https://mainnet.base.org'));
define('GRID402_USDC_CONTRACT', strtolower((string) grid402_env('GRID402_USDC_CONTRACT', '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913')));
define('GRID402_CHAIN_ID', 8453); // Base Mainnet
define('GRID402_USDC_REQUIRED_RAW', (int) grid402_env('GRID402_USDC_REQUIRED_RAW', 500)); // 0.000500 USDC
define('GRID402_PRICE_DISPLAY', (string) grid402_env('GRID402_PRICE_DISPLAY', '0.0005'));
define('GRID402_CRON_SECRET', (string) grid402_env('GRID402_CRON_SECRET', 'grid402_secret_token_change_me'));

// 3. Parâmetros de Performance e Timeout
define('GRID402_TIMEOUT_MS', (int) grid402_env('GRID402_TIMEOUT_MS', 2800));
define('GRID402_CONNECT_TIMEOUT_MS', (int) grid402_env('GRID402_CONNECT_TIMEOUT_MS', 1500));
define('GRID402_USER_AGENT', (string) grid402_env('GRID402_PROBE_USER_AGENT', 'Grid402-Telemetry-Probe/1.0 (+https://www.grid402.com.br)'));
define('GRID402_SSL_VERIFY', (bool) grid402_env('GRID402_SSL_VERIFY', false)); // false por padrão para compatibilidade com cadeias ICP-Brasil SEFAZ

// 4. Caminhos de Arquivos e Armazenamento Privado
define('GRID402_PRIVATE_DIR', __DIR__ . '/private_data');
define('GRID402_FILE_FREE', GRID402_PRIVATE_DIR . '/summary_free.json');
define('GRID402_FILE_PAID', GRID402_PRIVATE_DIR . '/telemetry_paid.json');
define('GRID402_FILE_USED_TXS', GRID402_PRIVATE_DIR . '/used_txs.json');
define('GRID402_FILE_FOREX', GRID402_PRIVATE_DIR . '/forex_cache.json');
define('GRID402_FILE_CRYPTO', GRID402_PRIVATE_DIR . '/crypto_cache.json');

// 5. Catálogo Oficial dos 6 Micro-Serviços Core de Dados Públicos e Rede
function grid402_get_utility_services(): array {
    return [
        'forex' => [
            'id' => 'forex',
            'name' => 'Câmbio & Moedas',
            'description' => 'Cotações em tempo real de USD, EUR, GBP para BRL com variação diária',
            'endpoint' => '/api/v1/forex',
            'category' => 'Mercado',
            'type' => 'cached',
        ],
        'crypto' => [
            'id' => 'crypto',
            'name' => 'Cripto Tickers',
            'description' => 'Preços de mercado de BTC, ETH, SOL, USDC e BASE em USD e BRL',
            'endpoint' => '/api/v1/crypto',
            'category' => 'Mercado',
            'type' => 'cached',
        ],
        'dns' => [
            'id' => 'dns',
            'name' => 'DNS Records Lookup',
            'description' => 'Resolução autoritativa de registros A, AAAA, MX, TXT, NS e CNAME',
            'endpoint' => '/api/v1/dns',
            'category' => 'Rede',
            'type' => 'on_demand',
        ],
        'ssl' => [
            'id' => 'ssl',
            'name' => 'Inspetor TLS/SSL',
            'description' => 'Inspeção de cadeia X.509, emissor, SANs e dias até expiração na porta 443',
            'endpoint' => '/api/v1/ssl',
            'category' => 'Rede',
            'type' => 'on_demand',
        ],
        'asn' => [
            'id' => 'asn',
            'name' => 'ASN & IP Info',
            'description' => 'Metadados de roteamento BGP, número de ASN, bloco IP e país',
            'endpoint' => '/api/v1/asn',
            'category' => 'Rede',
            'type' => 'on_demand',
        ],
        'cep' => [
            'id' => 'cep',
            'name' => 'CEP & IBGE',
            'description' => 'Resolução de logradouro, bairro, município, UF e código IBGE oficial',
            'endpoint' => '/api/v1/cep',
            'category' => 'Dados Abertos',
            'type' => 'on_demand',
        ],
    ];
}

function grid402_get_services(): array {
    return grid402_get_utility_services();
}

// 6. Catálogo de Alvos Complementares / Monitoramento
function grid402_get_targets(): array {
    return [
        // --- SEFAZ (Autorizadores Fiscais) ---
        [
            'id' => 'sefaz_sp',
            'name' => 'SEFAZ São Paulo',
            'category' => 'SEFAZ',
            'url' => 'https://nfe.fazenda.sp.gov.br/ws/nfeautorizacao4.asmx',
            'method' => 'GET',
            'expected_codes' => [200, 400, 403, 405],
        ],
        [
            'id' => 'sefaz_rs',
            'name' => 'SEFAZ Rio Grande do Sul',
            'category' => 'SEFAZ',
            'url' => 'https://nfe.sefazrs.rs.gov.br/ws/NfeAutorizacao/NFeAutorizacao4.asmx',
            'method' => 'GET',
            'expected_codes' => [200, 400, 403, 405],
        ],
        [
            'id' => 'sefaz_mg',
            'name' => 'SEFAZ Minas Gerais',
            'category' => 'SEFAZ',
            'url' => 'https://nfe.fazenda.mg.gov.br/nfe2/services/NFeAutorizacao4',
            'method' => 'GET',
            'expected_codes' => [200, 400, 403, 405],
        ],
        [
            'id' => 'sefaz_svrs',
            'name' => 'SEFAZ Virtual RS (SVRS)',
            'category' => 'SEFAZ',
            'url' => 'https://nfe.svrs.rs.gov.br/ws/NfeAutorizacao/NFeAutorizacao4.asmx',
            'method' => 'GET',
            'expected_codes' => [200, 400, 403, 405],
        ],
        [
            'id' => 'sefaz_svan',
            'name' => 'SEFAZ Virtual Ambiente Nacional (SVAN)',
            'category' => 'SEFAZ',
            'url' => 'https://www.sefazvirtual.fazenda.gov.br/NFeAutorizacao4/NFeAutorizacao4.asmx',
            'method' => 'GET',
            'expected_codes' => [200, 400, 403, 405],
        ],

        // --- GATEWAYS DE PAGAMENTO ---
        [
            'id' => 'mercadopago',
            'name' => 'Mercado Pago Core API',
            'category' => 'Gateways',
            'url' => 'https://api.mercadopago.com/v1/payment_methods',
            'method' => 'GET',
            'expected_codes' => [200, 401, 405],
        ],
        [
            'id' => 'asaas',
            'name' => 'Asaas Gateway API',
            'category' => 'Gateways',
            'url' => 'https://api.asaas.com/v3/ping',
            'method' => 'GET',
            'expected_codes' => [200, 401],
        ],
        [
            'id' => 'stripe_br',
            'name' => 'Stripe Payments API',
            'category' => 'Gateways',
            'url' => 'https://api.stripe.com/healthcheck',
            'method' => 'GET',
            'expected_codes' => [200],
        ],
        [
            'id' => 'pagbank',
            'name' => 'PagBank API',
            'category' => 'Gateways',
            'url' => 'https://ws.pagseguro.uol.com.br/',
            'method' => 'GET',
            'expected_codes' => [200, 403, 404],
        ],
        [
            'id' => 'cora',
            'name' => 'Cora Bank API',
            'category' => 'Gateways',
            'url' => 'https://api.cora.com.br/',
            'method' => 'GET',
            'expected_codes' => [200, 404, 403],
        ],

        // --- BANCOS E PIX ---
        [
            'id' => 'bacen_dados',
            'name' => 'Banco Central do Brasil (BCB)',
            'category' => 'Bancos & PIX',
            'url' => 'https://olinda.bcb.gov.br/olinda/servico/PTAX/versao/v1/odata/Moedas?$top=1&$format=json',
            'method' => 'GET',
            'expected_codes' => [200, 403],
        ],
        [
            'id' => 'openfinance_dir',
            'name' => 'Diretório Open Finance Brasil',
            'category' => 'Bancos & PIX',
            'url' => 'https://data.directory.openbankingbrasil.org.br/participants',
            'method' => 'GET',
            'expected_codes' => [200],
        ],
        [
            'id' => 'nubank_status',
            'name' => 'Nubank Public Gateway',
            'category' => 'Bancos & PIX',
            'url' => 'https://prod-global-auth.nubank.com.br/api/discovery',
            'method' => 'GET',
            'expected_codes' => [200, 401, 404],
        ],
        [
            'id' => 'inter_api',
            'name' => 'Banco Inter Developers',
            'category' => 'Bancos & PIX',
            'url' => 'https://cdpj.partners.bancointer.com.br/',
            'method' => 'GET',
            'expected_codes' => [200, 403, 404],
        ],

        // --- TELECOM E CONECTIVIDADE BRASIL ---
        [
            'id' => 'cloudflare_sp',
            'name' => 'Cloudflare Edge SP (GRU)',
            'category' => 'Telecom & Infra',
            'url' => 'https://1.1.1.1/cdn-cgi/trace',
            'method' => 'GET',
            'expected_codes' => [200, 404],
        ],
        [
            'id' => 'aws_sa_east',
            'name' => 'AWS São Paulo (sa-east-1)',
            'category' => 'Telecom & Infra',
            'url' => 'https://dynamodb.sa-east-1.amazonaws.com',
            'method' => 'GET',
            'expected_codes' => [200, 400, 403, 404],
        ],
        [
            'id' => 'google_cloud_br',
            'name' => 'Google Cloud South America',
            'category' => 'Telecom & Infra',
            'url' => 'https://southamerica-east1-docker.pkg.dev/v2/',
            'method' => 'GET',
            'expected_codes' => [200, 401, 405],
        ],
        [
            'id' => 'rnp_ix_br',
            'name' => 'IX.br / NIC.br PTT Ponto de Troca',
            'category' => 'Telecom & Infra',
            'url' => 'https://ix.br/',
            'method' => 'GET',
            'expected_codes' => [200, 301, 302],
        ],
    ];
}
