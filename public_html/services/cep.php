<?php
/**
 * Grid402 - Micro-serviço de CEP e IBGE
 * Resolução de logradouro, bairro, cidade, UF e código IBGE oficial via base pública.
 */

declare(strict_types=1);

if (!defined('GRID402_EXEC')) {
    define('GRID402_EXEC', true);
}
require_once __DIR__ . '/../config.php';

function grid402_service_cep(string $code): array {
    $cleanCode = preg_replace('/\D/', '', trim($code));

    if (strlen($cleanCode) !== 8) {
        return [
            'success' => false,
            'error' => 'InvalidCEP',
            'message' => 'O parâmetro code deve conter um CEP brasileiro válido de 8 dígitos (ex: code=01310100).',
            'code' => 400,
        ];
    }

    $cacheFile = GRID402_PRIVATE_DIR . '/cep_' . $cleanCode . '.json';
    if (file_exists($cacheFile)) {
        $cached = @json_decode((string) @file_get_contents($cacheFile), true);
        if (is_array($cached) && isset($cached['cep'])) {
            return $cached;
        }
    }

    // Consulta à base pública aberta ViaCEP
    $apiUrl = "https://viacep.com.br/ws/{$cleanCode}/json/";
    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 3,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_USERAGENT => 'Grid402-CEP/2.0',
        CURLOPT_SSL_VERIFYPEER => false,
    ]);

    $raw = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && is_string($raw)) {
        $data = @json_decode($raw, true);
        if (is_array($data) && !isset($data['erro'])) {
            $formattedCep = substr($cleanCode, 0, 5) . '-' . substr($cleanCode, 5, 3);
            $payload = [
                'success' => true,
                'service' => 'cep',
                'cep' => $formattedCep,
                'logradouro' => (string) ($data['logradouro'] ?? ''),
                'complemento' => (string) ($data['complemento'] ?? ''),
                'bairro' => (string) ($data['bairro'] ?? ''),
                'cidade' => (string) ($data['localidade'] ?? ''),
                'uf' => (string) ($data['uf'] ?? ''),
                'ibge' => (string) ($data['ibge'] ?? ''),
                'ddd' => (string) ($data['ddd'] ?? ''),
                'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
                'source' => 'Base Pública Aberta Nacional e Diretório Municipal IBGE',
                'legal_license' => 'Dados Públicos Abertos de Endereçamento e Geocodificação IBGE (Sem vinculação a moradores ou pessoas físicas — Zero PII)',
            ];

            @file_put_contents($cacheFile, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
            return $payload;
        }
    }

    return [
        'success' => false,
        'error' => 'CEPNotFound',
        'message' => "CEP {$cleanCode} não encontrado na base pública nacional.",
        'code' => 404,
    ];
}

/**
 * Extrai a amostra parcial (teaser data) para inclusao no desafio HTTP 402
 * Retorna array com ['success' => true, 'preview' => [...], 'locked_fields' => [...]]
 * ou ['success' => false, 'code' => 400|404, ...] em caso de erro cadastral
 */
function grid402_get_cep_preview(string $code): array {
    $full = grid402_service_cep($code);
    if (!($full['success'] ?? false)) {
        return $full;
    }

    return [
        'success' => true,
        'preview' => [
            'cep' => $full['cep'] ?? $code,
            'cidade' => $full['cidade'] ?? '',
            'uf' => $full['uf'] ?? '',
        ],
        'locked_fields' => [
            'logradouro',
            'bairro',
            'complemento',
            'ddd',
            'ibge',
        ],
    ];
}

