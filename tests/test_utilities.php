<?php
/**
 * Testes unitários para os 6 micro-serviços de dados públicos e utilitários
 */

declare(strict_types=1);

echo "=== Testando Micro-serviços do Grid402 ===\n";

require_once __DIR__ . '/../public_html/services/forex.php';
require_once __DIR__ . '/../public_html/services/crypto.php';
require_once __DIR__ . '/../public_html/services/dns.php';
require_once __DIR__ . '/../public_html/services/ssl.php';
require_once __DIR__ . '/../public_html/services/asn.php';
require_once __DIR__ . '/../public_html/services/cep.php';

// 1. Câmbio
echo "[1/6] Testando Câmbio (forex)... ";
$forex = grid402_service_forex();
assert($forex['status'] === 'UP', "Forex status deve ser UP");
assert(isset($forex['rates']['USD']['bid']), "Forex deve conter cotação USD");
assert($forex['rates']['USD']['bid'] > 0, "Cotação USD deve ser maior que zero");
echo "OK (USD: R$ {$forex['rates']['USD']['bid']})\n";

// 2. Cripto
echo "[2/6] Testando Cripto Tickers (crypto)... ";
$crypto = grid402_service_crypto();
assert($crypto['status'] === 'UP', "Crypto status deve ser UP");
assert(isset($crypto['tickers']['BTC']['price_usd']), "Crypto deve conter BTC");
assert($crypto['tickers']['BTC']['price_usd'] > 0, "Preço BTC deve ser maior que zero");
echo "OK (BTC: \$" . number_format($crypto['tickers']['BTC']['price_usd'], 2) . ")\n";

// 3. DNS Lookup
echo "[3/6] Testando DNS Lookup (dns)... ";
$dns = grid402_service_dns('github.com');
assert($dns['success'] === true, "DNS deve responder com sucesso");
assert(!empty($dns['records']['A']), "DNS deve conter registros A");
$invalidDns = grid402_service_dns('invalid domain with spaces');
assert($invalidDns['success'] === false, "DNS inválido deve ser recusado");
echo "OK (Registros A encontrados: " . count($dns['records']['A']) . ")\n";

// 4. SSL Inspector
echo "[4/6] Testando Inspetor SSL (ssl)... ";
$ssl = grid402_service_ssl('base.org');
assert($ssl['success'] === true, "SSL deve conectar com sucesso em base.org");
assert($ssl['certificate']['is_valid'] === true, "Certificado deve ser válido");
assert($ssl['certificate']['days_remaining'] > 0, "Dias restantes devem ser > 0");
echo "OK (Emissor: {$ssl['certificate']['issuer']}, Expira em: {$ssl['certificate']['days_remaining']} dias)\n";

// 5. ASN e IP Info
echo "[5/6] Testando ASN e IP Info (asn)... ";
$asn = grid402_service_asn('1.1.1.1');
assert($asn['success'] === true, "ASN deve responder com sucesso");
assert($asn['ip'] === '1.1.1.1', "IP deve coincidir");
assert(!empty($asn['asn']), "ASN não deve estar vazio");
echo "OK (ASN: {$asn['asn']} - {$asn['org']})\n";

// 6. CEP e IBGE
echo "[6/6] Testando CEP e IBGE (cep)... ";
$cep = grid402_service_cep('01310100'); // Av. Paulista
assert($cep['success'] === true, "CEP deve resolver com sucesso");
assert($cep['cidade'] === 'São Paulo', "Cidade deve ser São Paulo");
assert($cep['uf'] === 'SP', "UF deve ser SP");
assert($cep['ibge'] === '3550308', "Código IBGE deve ser 3550308");
echo "OK (Logradouro: {$cep['logradouro']}, IBGE: {$cep['ibge']})\n";

echo "\n=== Todos os 6 micro-serviços foram homologados com 100% de sucesso! ===\n";

