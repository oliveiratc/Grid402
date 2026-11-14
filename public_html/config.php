<?php
/**
 * Grid402 - ConfiguraÃ§Ã£o Central e CatÃ¡logo de Alvos
 * 
 * OrÃ¡culo M2M de Telemetria e Status de ServiÃ§os CrÃ­ticos (Protocolo x402)
 */

declare(strict_types=1);

// Evitar execuÃ§Ã£o se chamado diretamente pelo navegador
if (!defined('GRID402_EXEC') && php_sapi_name() !== 'cli' && basename($_SERVER['PHP_SELF'] ?? '') === 'config.php') {
    http_response_code(403);
    exit('Acesso direto proÃ­bido.');
}

// 1. Carregador de variÃ¡veis de ambiente (.env)
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

// FunÃ§Ã£o auxiliar para leitura com fallback
function grid402_env(string $key, mixed $default = null): mixed {
    $val = getenv($key);
    if ($val !== false and $val !== '') {
        return $val;
    }
    return $_ENV[$key] ?? $_SERVER[$key] ?? $default;
}

/**
 * ValidaÃ§Ã£o defensiva do segredo de automaÃ§Ã£o e reset
 */
function grid402_is_default_secret(): bool {
    $secret = defined('GRID402_CRON_SECRET') ? GRID402_CRON_SECRET : (string) grid402_env('GRID402_CRON_SECRET', '');
    if ($secret === '' or $secret === 'grid402_secret_token_change_me' or strlen($secret) < 16) {
        return true;
    }
    return false;
}

/**
 * ObtenÃ§Ã£o confiÃ¡vel do IP do cliente protegida contra spoofing de headers
 */
function grid402_get_trusted_client_ip(): string {
    $remote = (string)($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');

    $isLocalOrPrivate = in_array($remote, ['127.0.0.1', '::1'], true)
        or str_starts_with($remote, '10.')
        or str_starts_with($remote, '172.16.')
        or str_starts_with($remote, '192.168.');

    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $cf = trim((string)$_SERVER['HTTP_CF_CONNECTING_IP']);
        if (filter_var($cf, FILTER_VALIDATE_IP)) {
            return $cf;
        }
    }

    if ($isLocalOrPrivate) {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $parts = explode(',', (string)$_SERVER['HTTP_X_FORWARDED_FOR']);
            $candidate = trim($parts[0]);
            if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                return $candidate;
            }
        }
    }

    return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '127.0.0.1';
}

/**
 * Escrita atÃ´mica em arquivo via arquivo temporÃ¡rio com fallback direto seguro
 */
if (!function_exists('grid402_atomic_write')) {
    function grid402_atomic_write(string $filePath, mixed $data): bool {
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
            @chmod($dir, 0775);
        }
        $content = is_array($data) ? (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : (string)$data;
        $tmp = $filePath . '.' . bin2hex(random_bytes(6)) . '.tmp';

        // 1. Tentar escrita atÃ´mica via arquivo temporÃ¡rio + rename
        $written = @file_put_contents($tmp, $content, LOCK_EX);
        if ($written !== false) {
            if (@rename($tmp, $filePath)) {
                @chmod($filePath, 0664);
                return true;
            }
            @unlink($tmp);
        }

        // 2. Fallback imediato: escrita direta com trava exclusiva caso rename seja restrito pelo SO/host
        $directWritten = @file_put_contents($filePath, $content, LOCK_EX);
        if ($directWritten !== false) {
            @chmod($filePath, 0664);
            return true;
        }

        return false;
    }
}

// 2. ParÃ¢metros x402 e Blockchain (Base / USDC)
define('GRID402_WALLET_ADDRESS', strtolower((string) grid402_env('GRID402_WALLET_ADDRESS', '0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90')));
define('GRID402_BASE_RPC_URL', (string) grid402_env('GRID402_BASE_RPC_URL', 'https://mainnet.base.org'));
define('GRID402_USDC_CONTRACT', strtolower((string) grid402_env('GRID402_USDC_CONTRACT', '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913')));
define('GRID402_CHAIN_ID', 8453); // Base Mainnet
define('GRID402_SEPOLIA_RPC_URL', (string) grid402_env('GRID402_SEPOLIA_RPC_URL', 'https://sepolia.base.org'));
define('GRID402_SEPOLIA_CHAIN_ID', 84532); // Base Sepolia Testnet

// Rede de aceite do fluxo x402 `exact`. 'base' (mainnet, padrao) ou 'base-sepolia' (testnet).
// Opt-in via GRID402_X402_NETWORK; qualquer valor invalido cai para 'base'.
$grid402X402Network = strtolower(trim((string) grid402_env('GRID402_X402_NETWORK', 'base')));
if (!in_array($grid402X402Network, ['base', 'base-sepolia'], true)) {
    $grid402X402Network = 'base';
}
define('GRID402_X402_NETWORK', $grid402X402Network);
if ($grid402X402Network === 'base-sepolia') {
    define('GRID402_X402_CHAIN_ID', 84532);
    define('GRID402_X402_NETWORK_V2', 'eip155:84532');
    define('GRID402_X402_NETWORK_V1', 'base-sepolia');
    define('GRID402_X402_USDC_CONTRACT', '0x036cbd53842c5426634e7929541ec2318f3dcf7e');
    define('GRID402_X402_TOKEN_NAME', 'USDC'); // Circle Sepolia USDC (EIP-712 domain name() = "USDC")
    define('GRID402_X402_TOKEN_VERSION', '2');
} else {
    define('GRID402_X402_CHAIN_ID', (int) GRID402_CHAIN_ID);
    define('GRID402_X402_NETWORK_V2', 'eip155:' . GRID402_CHAIN_ID);
    define('GRID402_X402_NETWORK_V1', 'base');
    define('GRID402_X402_USDC_CONTRACT', GRID402_USDC_CONTRACT);
    define('GRID402_X402_TOKEN_NAME', 'USD Coin'); // Circle Base Mainnet USDC (EIP-712 domain name() = "USD Coin")
    define('GRID402_X402_TOKEN_VERSION', '2');
}
unset($grid402X402Network);
define('GRID402_SEPOLIA_USDC_CONTRACT', strtolower((string) grid402_env('GRID402_SEPOLIA_USDC_CONTRACT', '0x036cbd53842c5426634e7929541ec2318f3dcf7e')));
define('GRID402_SANDBOX_RATE_LIMIT', (int) grid402_env('GRID402_SANDBOX_RATE_LIMIT', 30)); // 30 consultas gratuitas por hora por IP (converte para x402 pago)
define('GRID402_USDC_REQUIRED_RAW', (int) grid402_env('GRID402_USDC_REQUIRED_RAW', 5000)); // 0.005000 USDC
define('GRID402_PRICE_DISPLAY', (string) grid402_env('GRID402_PRICE_DISPLAY', '0.0050'));
define('GRID402_PRICE_CNPJ', (string) grid402_env('GRID402_PRICE_CNPJ', '0.0200'));
define('GRID402_USDC_CNPJ_RAW', (int) grid402_env('GRID402_USDC_CNPJ_RAW', 20000)); // 0.020000 USDC
define('GRID402_CRON_SECRET', (string) grid402_env('GRID402_CRON_SECRET', 'grid402_secret_token_change_me'));

// 2.2. Notificacoes de Pagamento em Tempo Real no Telegram (Opcional)
define('GRID402_TELEGRAM_BOT_TOKEN', (string) grid402_env('GRID402_TELEGRAM_BOT_TOKEN', ''));
define('GRID402_TELEGRAM_CHAT_ID', (string) grid402_env('GRID402_TELEGRAM_CHAT_ID', ''));

// 2.3. Dominio Canonico Oficial do Grid402
define('GRID402_CANONICAL_DOMAIN', (string) grid402_env('GRID402_CANONICAL_DOMAIN', 'https://grid402.online'));
define('GRID402_CANONICAL_HOST', (string) grid402_env('GRID402_CANONICAL_HOST', 'grid402.online'));

// 2.1. ParÃ¢metros de Assinatura CriptogrÃ¡fica do OrÃ¡culo (Proof of Data)
// 1.6. Chave publica/privada do oraculo (Proof of Data). Em producao, DEFINIR via env (rotacao).
// NUNCA usar a chave default em producao: ela esta publica no repo (testes apenas).
define('GRID402_ORACLE_WALLET', strtolower((string) grid402_env('GRID402_ORACLE_WALLET', GRID402_WALLET_ADDRESS)));
define('GRID402_ORACLE_PRIVATE_KEY', (string) grid402_env('GRID402_ORACLE_PRIVATE_KEY', ''));
define('GRID402_ORACLE_PUBLIC_KEY', (string) grid402_env('GRID402_ORACLE_PUBLIC_KEY', ''));
if (GRID402_ORACLE_PRIVATE_KEY !== '') {
    define('GRID402_ORACLE_KEY_CONFIGURED', true);
} else {
    define('GRID402_ORACLE_KEY_CONFIGURED', false);
}
define('GRID402_ORACLE_ALGORITHM', 'secp256k1-sha256');

// 3. ParÃ¢metros de Performance e Timeout
define('GRID402_TIMEOUT_MS', (int) grid402_env('GRID402_TIMEOUT_MS', 2800));
define('GRID402_CONNECT_TIMEOUT_MS', (int) grid402_env('GRID402_CONNECT_TIMEOUT_MS', 1500));
define('GRID402_USER_AGENT', (string) grid402_env('GRID402_PROBE_USER_AGENT', 'Grid402-Telemetry-Probe/1.0 (+https://grid402.online)'));
define('GRID402_SSL_VERIFY', (bool) grid402_env('GRID402_SSL_VERIFY', false)); // false por padrÃ£o para compatibilidade com cadeias ICP-Brasil SEFAZ

// 4. Caminhos de Arquivos e Armazenamento Privado (Persistente e Zero-DB)
function grid402_get_storage_dir(): string {
    static $storageDir = null;
    if ($storageDir !== null) {
        return $storageDir;
    }

    // 0. Sobrescrita via variÃ¡vel de ambiente se configurada
    $envStorage = (string) grid402_env('GRID402_STORAGE_DIR', '');
    if ($envStorage !== '' && is_dir($envStorage) && is_writable($envStorage)) {
        $storageDir = rtrim($envStorage, '/\\');
        return $storageDir;
    }

    // 1. Tentar diretÃ³rio persistente fora do DocumentRoot (imune a deploys na Hostinger)
    $parentDir = dirname(__DIR__);
    $isLocalDev = (DIRECTORY_SEPARATOR === '\\') || str_contains(__DIR__, 'Projetos');

    if (!$isLocalDev && $parentDir !== __DIR__ && is_dir($parentDir) && is_writable($parentDir)) {
        $externalDir = $parentDir . '/grid402_storage';
        if (!is_dir($externalDir)) {
            @mkdir($externalDir, 0775, true);
            @chmod($externalDir, 0775);
        }
        if (is_dir($externalDir) && is_writable($externalDir)) {
            $storageDir = $externalDir;
            return $storageDir;
        }
    }

    // 2. Fallback: private_data dentro de public_html
    $internalDir = __DIR__ . '/private_data';
    if (!is_dir($internalDir)) {
        @mkdir($internalDir, 0775, true);
        @chmod($internalDir, 0775);
    }
    $storageDir = $internalDir;
    return $storageDir;
}

define('GRID402_PRIVATE_DIR', grid402_get_storage_dir());
define('GRID402_FILE_FREE', GRID402_PRIVATE_DIR . '/summary_free.json');
define('GRID402_FILE_PAID', GRID402_PRIVATE_DIR . '/telemetry_paid.json');
define('GRID402_FILE_USED_TXS', GRID402_PRIVATE_DIR . '/used_txs.json');
define('GRID402_FILE_FOREX', GRID402_PRIVATE_DIR . '/forex_cache.json');
define('GRID402_FILE_CRYPTO', GRID402_PRIVATE_DIR . '/crypto_cache.json');
define('GRID402_FILE_METRICS', GRID402_PRIVATE_DIR . '/metrics.json');
define('GRID402_FILE_ADMIN_AUTH', GRID402_PRIVATE_DIR . '/admin_auth.json');
define('GRID402_FILE_CNPJ', GRID402_PRIVATE_DIR . '/cnpj_cache.json');
define('GRID402_FILE_SANDBOX_RATES', (string) grid402_env('GRID402_FILE_SANDBOX_RATES', GRID402_PRIVATE_DIR . '/sandbox_rates.json'));
define('GRID402_FILE_INDICATORS', GRID402_PRIVATE_DIR . '/indicators_cache.json');
define('GRID402_FILE_HOLIDAYS', GRID402_PRIVATE_DIR . '/holidays_cache.json');
define('GRID402_FILE_WORKDAYS', GRID402_PRIVATE_DIR . '/workdays_cache.json');
define('GRID402_FILE_LEI', GRID402_PRIVATE_DIR . '/lei_cache.json');
define('GRID402_FILE_OFAC', GRID402_PRIVATE_DIR . '/ofac_cache.json');
define('GRID402_FILE_GLOBAL_MARKETS', GRID402_PRIVATE_DIR . '/global_markets_cache.json');
define('GRID402_FILE_PASSES', GRID402_PRIVATE_DIR . '/session_passes.json');
define('GRID402_FILE_USED_NONCES', GRID402_PRIVATE_DIR . '/used_nonces.json');

// Facilitador x402 (pagamentos `exact` / EIP-3009). Modos: disabled (padrao), verify_only, settle
// Decisão do mantenedor (2026-11-13): produção usa PayAI como facilitador padrão (via créditos
// pré-pagos do merchant payTo; 1.000 créditos grátis para começar). CDP segue suportável via env.
$grid402FacilitatorMode = strtolower(trim((string) grid402_env('GRID402_FACILITATOR_MODE', 'settle')));
if (!in_array($grid402FacilitatorMode, ['disabled', 'verify_only', 'settle'], true)) {
    $grid402FacilitatorMode = 'settle';
}
define('GRID402_FACILITATOR_MODE', $grid402FacilitatorMode);
unset($grid402FacilitatorMode);
define('GRID402_FACILITATOR_URL', rtrim((string) grid402_env('GRID402_FACILITATOR_URL', 'https://facilitator.payai.network'), '/'));
define('GRID402_FACILITATOR_VERIFY_PATH', (string) grid402_env('GRID402_FACILITATOR_VERIFY_PATH', '/verify'));
define('GRID402_FACILITATOR_SETTLE_PATH', (string) grid402_env('GRID402_FACILITATOR_SETTLE_PATH', '/settle'));
define('GRID402_CDP_API_KEY_ID', (string) grid402_env('GRID402_CDP_API_KEY_ID', ''));
define('GRID402_CDP_API_KEY_SECRET', (string) grid402_env('GRID402_CDP_API_KEY_SECRET', ''));

// 5. CatÃ¡logo Oficial dos 6 Micro-ServiÃ§os Core de Dados PÃºblicos e Rede
function grid402_get_utility_services(): array {
    return [
        'forex' => [
            'id' => 'forex',
            'name' => 'CÃ¢mbio e Moedas',
            'description' => 'CotaÃ§Ãµes em tempo real de USD, EUR, GBP para BRL com variaÃ§Ã£o diÃ¡ria',
            'endpoint' => '/api/v1/forex',
            'category' => 'Mercado',
            'type' => 'cached',
        ],
        'crypto' => [
            'id' => 'crypto',
            'name' => 'Cripto Tickers',
            'description' => 'PreÃ§os de mercado de BTC, ETH, SOL, USDC e BASE em USD e BRL',
            'endpoint' => '/api/v1/crypto',
            'category' => 'Mercado',
            'type' => 'cached',
        ],
        'dns' => [
            'id' => 'dns',
            'name' => 'DNS Records Lookup',
            'description' => 'ResoluÃ§Ã£o autoritativa de registros A, AAAA, MX, TXT, NS e CNAME',
            'endpoint' => '/api/v1/dns',
            'category' => 'Rede',
            'type' => 'on_demand',
        ],
        'ssl' => [
            'id' => 'ssl',
            'name' => 'Inspetor TLS/SSL',
            'description' => 'InspeÃ§Ã£o de cadeia X.509, emissor, SANs e dias atÃ© expiraÃ§Ã£o na porta 443',
            'endpoint' => '/api/v1/ssl',
            'category' => 'Rede',
            'type' => 'on_demand',
        ],
        'asn' => [
            'id' => 'asn',
            'name' => 'ASN e IP Info',
            'description' => 'Metadados de roteamento BGP, nÃºmero de ASN, bloco IP e paÃ­s',
            'endpoint' => '/api/v1/asn',
            'category' => 'Rede',
            'type' => 'on_demand',
        ],
        'domain-audit' => [
            'id' => 'domain-audit',
            'name' => 'Domain Audit (DNS + SSL + ASN)',
            'description' => 'Auditoria completa de domÃ­nio em 1 chamada: registros DNS, certificado TLS/SSL e roteamento ASN/BGP',
            'endpoint' => '/api/v1/domain-audit',
            'category' => 'Rede',
            'type' => 'on_demand',
        ],
        'kyb-audit' => [
            'id' => 'kyb-audit',
            'name' => 'KYB & Compliance Audit (CNPJ + OFAC + LEI)',
            'description' => 'Auditoria completa de entidade em 1 chamada: dados cadastrais CNPJ, triagem OFAC/sanÃ§Ãµes e registro LEI',
            'endpoint' => '/api/v1/kyb-audit',
            'category' => 'Compliance',
            'type' => 'on_demand',
        ],
        'market-snapshot' => [
            'id' => 'market-snapshot',
            'name' => 'Market Snapshot (Forex + Crypto + Indicators)',
            'description' => 'Snapshot consolidado de mercado em 1 chamada: cÃ¢mbio Forex, criptoativos e indicadores econÃ´micos',
            'endpoint' => '/api/v1/market-snapshot',
            'category' => 'Financeiro',
            'type' => 'on_demand',
        ],
        'cep' => [
            'id' => 'cep',
            'name' => 'CEP e IBGE',
            'description' => 'ResoluÃ§Ã£o de logradouro, bairro, municÃ­pio, UF e cÃ³digo IBGE oficial',
            'endpoint' => '/api/v1/cep',
            'category' => 'Dados Abertos',
            'type' => 'on_demand',
        ],
        'cnpj' => [
            'id' => 'cnpj',
            'name' => 'Consulta Cadastral CNPJ',
            'description' => 'SituaÃ§Ã£o cadastral, razÃ£o social, CNAE, data de abertura e endereÃ§o da PJ',
            'endpoint' => '/api/v1/cnpj',
            'category' => 'Corporativo',
            'type' => 'on_demand',
            'price_usdc' => GRID402_PRICE_CNPJ,
        ],
        'indicators' => [
            'id' => 'indicators',
            'name' => 'Indicadores EconÃ´micos',
            'description' => 'Taxas oficiais Selic (meta e diÃ¡ria), CDI anual e diÃ¡rio, IPCA acumulado 12m e mensal (BCB e IBGE)',
            'endpoint' => '/api/v1/indicators',
            'category' => 'Mercado',
            'type' => 'cached',
        ],
        'holidays' => [
            'id' => 'holidays',
            'name' => 'Feriados e CalendÃ¡rio BancÃ¡rio',
            'description' => 'Feriados nacionais oficiais e dias sem expediente bancÃ¡rio (ANBIMA e Febraban) com cÃ¡lculo determinÃ­stico',
            'endpoint' => '/api/v1/holidays',
            'category' => 'Dados Abertos',
            'type' => 'on_demand',
        ],
        'workdays' => [
            'id' => 'workdays',
            'name' => 'Calculadora de Dias Ãšteis ANBIMA',
            'description' => 'CÃ¡lculo de dias Ãºteis bancÃ¡rios (contagem entre datas, projeÃ§Ã£o de prazos e checagem pontual) no padrÃ£o ANBIMA',
            'endpoint' => '/api/v1/workdays',
            'category' => 'Financeiro',
            'type' => 'on_demand',
        ],
        'sanctions' => [
            'id' => 'sanctions',
            'name' => 'SanÃ§Ãµes e Inidoneidade (CEIS/CNEP)',
            'description' => 'Consulta cadastros oficiais da CGU (CEIS e CNEP) sobre puniÃ§Ãµes e impedimentos de contratar',
            'endpoint' => '/api/v1/sanctions',
            'category' => 'Compliance',
            'type' => 'on_demand',
            'price_usdc' => '0.0100',
        ],
        'simples' => [
            'id' => 'simples',
            'name' => 'Simples Nacional e MEI',
            'description' => 'Consulta oficial de enquadramento tributÃ¡rio no Simples Nacional e SIMEI na Receita Federal',
            'endpoint' => '/api/v1/simples',
            'category' => 'Fiscal',
            'type' => 'on_demand',
            'price_usdc' => '0.0100',
        ],
        'compliance' => [
            'id' => 'compliance',
            'name' => 'Compliance Score 360',
            'description' => 'Score unificado de risco cadastral (0 a 100) cruzando Receita Federal, CGU e histÃ³rico societÃ¡rio',
            'endpoint' => '/api/v1/compliance',
            'category' => 'Compliance',
            'type' => 'on_demand',
            'price_usdc' => '0.0300',
        ],
        'lei' => [
            'id' => 'lei',
            'name' => 'Identidade Corporativa Mundial (GLEIF LEI)',
            'description' => 'Registro global ISO 17442 de empresas internacionais (jurisdiÃ§Ã£o, status ativo, autoridade legal e endereÃ§o) via base CC0 da GLEIF',
            'endpoint' => '/api/v1/lei',
            'category' => 'Global Corp',
            'type' => 'on_demand',
            'price_usdc' => '0.0200',
        ],
        'ofac' => [
            'id' => 'ofac',
            'name' => 'SanÃ§Ãµes Globais OFAC e AML Web3',
            'description' => 'VerificaÃ§Ã£o internacional contra listas de sanÃ§Ãµes OFAC SDN e ONU combinada com telemetria on-chain de carteiras EVM na Base',
            'endpoint' => '/api/v1/ofac',
            'category' => 'Global AML',
            'type' => 'on_demand',
            'price_usdc' => '0.0200',
        ],
        'notarize' => [
            'id' => 'notarize',
            'name' => 'NotarizaÃ§Ã£o e Carimbo de Tempo M2M',
            'description' => 'Certificado criptogrÃ¡fico de existÃªncia no tempo (SHA-256 + ECDSA secp256k1) para auditar decisÃµes e contratos de agentes autÃ´nomos',
            'endpoint' => '/api/v1/notarize',
            'category' => 'SeguranÃ§a',
            'type' => 'on_demand',
            'price_usdc' => '0.0050',
        ],
        'global-markets' => [
            'id' => 'global-markets',
            'name' => 'CalendÃ¡rio Mundial e CÃ¢mbio BCE (G10)',
            'description' => 'Expediente bancÃ¡rio e feriados oficiais (NYSE/Fed, BCE/TARGET2, LSE, B3) e cotaÃ§Ãµes de 30+ moedas do Banco Central Europeu',
            'endpoint' => '/api/v1/global-markets',
            'category' => 'Global FX',
            'type' => 'cached',
            'price_usdc' => '0.0050',
        ],
        'pass' => [
            'id' => 'pass',
            'name' => 'x402 Prepaid Session Pass (Alta FrequÃªncia)',
            'description' => 'EmissÃ£o de passe de sessÃ£o prÃ©-pago assinado (100 a 1.000 crÃ©ditos) para chamadas M2M instantÃ¢neas (<15ms) sem aguardar bloco',
            'endpoint' => '/api/v1/pass',
            'category' => 'M2M Turbo',
            'type' => 'on_demand',
            'price_usdc' => '0.5000',
        ],
        'verify' => [
            'id' => 'verify',
            'name' => 'Verificador CriptogrÃ¡fico',
            'description' => 'ValidaÃ§Ã£o matemÃ¡tica local e instantÃ¢nea de assinatura ECDSA secp256k1 e prova de orÃ¡culo (_oracle_proof)',
            'endpoint' => '/api/v1/verify',
            'category' => 'SeguranÃ§a',
            'type' => 'on_demand',
            'price_usdc' => 'Gratuito',
        ],
    ];
}

function grid402_get_services(): array {
    return grid402_get_utility_services();
}

// 6. CatÃ¡logo de Alvos Complementares / Monitoramento
function grid402_get_targets(): array {
    return [
        // --- SEFAZ (Autorizadores Fiscais) ---
        [
            'id' => 'sefaz_sp',
            'name' => 'SEFAZ SÃ£o Paulo',
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
            'category' => 'Bancos e PIX',
            'url' => 'https://olinda.bcb.gov.br/olinda/servico/PTAX/versao/v1/odata/Moedas?$top=1&$format=json',
            'method' => 'GET',
            'expected_codes' => [200, 403],
        ],
        [
            'id' => 'openfinance_dir',
            'name' => 'DiretÃ³rio Open Finance Brasil',
            'category' => 'Bancos e PIX',
            'url' => 'https://data.directory.openbankingbrasil.org.br/participants',
            'method' => 'GET',
            'expected_codes' => [200],
        ],
        [
            'id' => 'nubank_status',
            'name' => 'Nubank Public Gateway',
            'category' => 'Bancos e PIX',
            'url' => 'https://prod-global-auth.nubank.com.br/api/discovery',
            'method' => 'GET',
            'expected_codes' => [200, 401, 404],
        ],
        [
            'id' => 'inter_api',
            'name' => 'Banco Inter Developers',
            'category' => 'Bancos e PIX',
            'url' => 'https://cdpj.partners.bancointer.com.br/',
            'method' => 'GET',
            'expected_codes' => [200, 403, 404],
        ],

        // --- TELECOM E CONECTIVIDADE BRASIL ---
        [
            'id' => 'cloudflare_sp',
            'name' => 'Cloudflare Edge SP (GRU)',
            'category' => 'Telecom e Infra',
            'url' => 'https://1.1.1.1/cdn-cgi/trace',
            'method' => 'GET',
            'expected_codes' => [200, 404],
        ],
        [
            'id' => 'aws_sa_east',
            'name' => 'AWS SÃ£o Paulo (sa-east-1)',
            'category' => 'Telecom e Infra',
            'url' => 'https://dynamodb.sa-east-1.amazonaws.com',
            'method' => 'GET',
            'expected_codes' => [200, 400, 403, 404],
        ],
        [
            'id' => 'google_cloud_br',
            'name' => 'Google Cloud South America',
            'category' => 'Telecom e Infra',
            'url' => 'https://southamerica-east1-docker.pkg.dev/v2/',
            'method' => 'GET',
            'expected_codes' => [200, 401, 405],
        ],
        [
            'id' => 'rnp_ix_br',
            'name' => 'IX.br / NIC.br PTT Ponto de Troca',
            'category' => 'Telecom e Infra',
            'url' => 'https://ix.br/',
            'method' => 'GET',
            'expected_codes' => [200, 301, 302],
        ],
    ];
}
