<?php
/**
 * Grid402 - Micro-serviço de Consulta Cadastral de CNPJ (Pessoa Jurídica)
 * 
 * Validação prévia de dígitos verificadores (Módulo 11), upstream resiliente de dados abertos
 * e conformidade estrita com a LGPD (Risco Zero: restrito a dados da Pessoa Jurídica, sem QSA).
 */

declare(strict_types=1);

if (!defined('GRID402_EXEC')) {
    define('GRID402_EXEC', true);
}
require_once __DIR__ . '/../config.php';

/**
 * Valida os dígitos verificadores do CNPJ pelo algoritmo oficial Módulo 11
 */
function grid402_validate_cnpj_digits(string $cnpj): bool {
    $clean = preg_replace('/\D/', '', trim($cnpj));

    // CNPJ deve ter exatamente 14 dígitos numéricos
    if (strlen($clean) !== 14) {
        return false;
    }

    // Rejeita sequências de dígitos todos iguais (00000000000000, 11111111111111, etc.)
    if (preg_match('/^(\d)\1{13}$/', $clean)) {
        return false;
    }

    // 1. Cálculo do primeiro dígito verificador
    $weights1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
    $sum1 = 0;
    for ($i = 0; $i < 12; $i++) {
        $sum1 += ((int) $clean[$i]) * $weights1[$i];
    }
    $rem1 = $sum1 % 11;
    $d1 = ($rem1 < 2) ? 0 : (11 - $rem1);

    if ($d1 !== (int) $clean[12]) {
        return false;
    }

    // 2. Cálculo do segundo dígito verificador
    $weights2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
    $sum2 = 0;
    for ($i = 0; $i < 13; $i++) {
        $sum2 += ((int) $clean[$i]) * $weights2[$i];
    }
    $rem2 = $sum2 % 11;
    $d2 = ($rem2 < 2) ? 0 : (11 - $rem2);

    if ($d2 !== (int) $clean[13]) {
        return false;
    }

    return true;
}

/**
 * Formata um CNPJ de 14 dígitos no padrão XX.XXX.XXX/YYYY-ZZ
 */
function grid402_format_cnpj(string $clean): string {
    return sprintf(
        '%s.%s.%s/%s-%s',
        substr($clean, 0, 2),
        substr($clean, 2, 3),
        substr($clean, 5, 3),
        substr($clean, 8, 4),
        substr($clean, 12, 2)
    );
}

/**
 * Executa a consulta cadastral com cache local atômico de 7 dias e fallback de upstream
 */
function grid402_service_cnpj(string $cnpj, bool $bypassCache = false): array {
    $clean = preg_replace('/\D/', '', trim($cnpj));

    // Validação matemática defensiva inicial
    if (!grid402_validate_cnpj_digits($clean)) {
        return [
            'success' => false,
            'error' => 'InvalidCNPJ',
            'message' => 'O CNPJ informado é matematicamente inválido ou possui dígitos verificadores incorretos.',
            'cnpj' => $cnpj,
            'code' => 400,
        ];
    }

    $cacheFile = GRID402_PRIVATE_DIR . '/cnpj_' . $clean . '.json';
    $cacheTtlSeconds = 7 * 86400; // 7 dias

    // 1. Tentar leitura de cache local persistente
    if (!$bypassCache && file_exists($cacheFile)) {
        $mtime = @filemtime($cacheFile);
        $age = time() - ($mtime ?: 0);
        if ($mtime && $age < $cacheTtlSeconds) {
            $rawContent = @file_get_contents($cacheFile);
            if ($rawContent) {
                $cached = @json_decode($rawContent, true);
                if (is_array($cached) && isset($cached['cnpj'])) {
                    $cached['cached'] = true;
                    $cached['cache_age_seconds'] = $age;
                    return $cached;
                }
            }
        }
    }

    // 2. Consulta ao upstream primário (BrasilAPI)
    $primaryUrl = "https://brasilapi.com.br/api/cnpj/v1/{$clean}";
    $response = grid402_fetch_cnpj_url($primaryUrl, 4);

    // 3. Fallback para upstream secundário (MinhaReceita) caso o primário falhe
    if ($response['http_code'] !== 200) {
        $secondaryUrl = "https://minhareceita.org/{$clean}";
        $fallbackResponse = grid402_fetch_cnpj_url($secondaryUrl, 4);
        if ($fallbackResponse['http_code'] === 200) {
            $response = $fallbackResponse;
        }
    }

    // 4. Tratamento de respostas de erro
    if ($response['http_code'] === 404) {
        return [
            'success' => false,
            'error' => 'CNPJNotFound',
            'message' => 'O CNPJ informado não foi localizado na base de dados pública da Receita Federal.',
            'cnpj' => grid402_format_cnpj($clean),
            'cnpj_raw' => $clean,
            'code' => 404,
        ];
    }

    if ($response['http_code'] !== 200 || !is_array($response['data'])) {
        return [
            'success' => false,
            'error' => 'UpstreamError',
            'message' => 'Não foi possível consultar os dados da Receita Federal neste momento. Tente novamente em instantes.',
            'cnpj' => grid402_format_cnpj($clean),
            'code' => 502,
        ];
    }

    // 5. Normalização defensiva: estritamente dados públicos de PJ (SEM sócios/QSA)
    $raw = $response['data'];

    // Tratamento de CNAEs secundários
    $cnaesSecundarios = [];
    if (!empty($raw['cnaes_secundarios']) && is_array($raw['cnaes_secundarios'])) {
        foreach ($raw['cnaes_secundarios'] as $item) {
            if (is_array($item)) {
                $cnaesSecundarios[] = [
                    'codigo' => (int) ($item['codigo'] ?? 0),
                    'descricao' => (string) ($item['descricao'] ?? ''),
                ];
            }
        }
    }

    $payload = [
        'success' => true,
        'service' => 'cnpj',
        'cnpj' => grid402_format_cnpj($clean),
        'cnpj_raw' => $clean,
        'razao_social' => trim((string) ($raw['razao_social'] ?? $raw['nome'] ?? '')),
        'nome_fantasia' => trim((string) ($raw['nome_fantasia'] ?? $raw['fantasia'] ?? '')),
        'situacao_cadastral' => strtoupper(trim((string) ($raw['descricao_situacao_cadastral'] ?? $raw['situacao'] ?? 'DESCONHECIDA'))),
        'data_situacao' => (string) ($raw['data_situacao_cadastral'] ?? $raw['data_situacao'] ?? ''),
        'motivo_situacao' => (string) ($raw['motivo_situacao_cadastral'] ?? ''),
        'data_abertura' => (string) ($raw['data_inicio_atividade'] ?? $raw['abertura'] ?? ''),
        'natureza_juridica' => (string) ($raw['natureza_juridica'] ?? ''),
        'cnae_fiscal' => (int) ($raw['cnae_fiscal'] ?? 0),
        'cnae_fiscal_descricao' => (string) ($raw['cnae_fiscal_descricao'] ?? ''),
        'cnaes_secundarios' => $cnaesSecundarios,
        'endereco' => [
            'tipo_logradouro' => (string) ($raw['descricao_tipo_de_logradouro'] ?? ''),
            'logradouro' => (string) ($raw['logradouro'] ?? ''),
            'numero' => (string) ($raw['numero'] ?? ''),
            'complemento' => (string) ($raw['complemento'] ?? ''),
            'bairro' => (string) ($raw['bairro'] ?? ''),
            'municipio' => (string) ($raw['municipio'] ?? ''),
            'uf' => (string) ($raw['uf'] ?? ''),
            'cep' => (string) ($raw['cep'] ?? ''),
        ],
        'capital_social' => (float) ($raw['capital_social'] ?? 0.0),
        'cached' => false,
        'cache_age_seconds' => 0,
        'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
        'source' => 'Dados Públicos Oficiais da Receita Federal',
    ];

    // Gravação atômica em cache local
    if (function_exists('grid402_atomic_write')) {
        grid402_atomic_write($cacheFile, $payload);
    } else {
        @file_put_contents($cacheFile, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    return $payload;
}

/**
 * Função utilitária interna de requisição HTTP com cURL e timeout
 */
function grid402_fetch_cnpj_url(string $url, int $timeoutSeconds = 4): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeoutSeconds,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_USERAGENT => 'Grid402-CNPJ/1.0 (+https://grid402.online)',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);

    $raw = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = null;
    if ($httpCode === 200 && is_string($raw)) {
        $data = @json_decode($raw, true);
    }

    return [
        'http_code' => $httpCode,
        'data' => $data,
        'raw' => $raw,
    ];
}

/**
 * Extrai a amostra parcial (teaser data) para inclusao no desafio HTTP 402
 * Retorna array com ['success' => true, 'preview' => [...], 'locked_fields' => [...]]
 * ou ['success' => false, 'code' => 400|404, ...] em caso de erro cadastral
 */
function grid402_get_cnpj_preview(string $cnpj): array {
    $full = grid402_service_cnpj($cnpj);
    if (!($full['success'] ?? false)) {
        return $full;
    }

    return [
        'success' => true,
        'preview' => [
            'cnpj' => $full['cnpj'] ?? grid402_format_cnpj($cnpj),
            'razao_social' => $full['razao_social'] ?? '',
            'nome_fantasia' => $full['nome_fantasia'] ?? '',
            'uf' => $full['endereco']['uf'] ?? '',
            'situacao_cadastral' => $full['situacao_cadastral'] ?? 'ATIVA',
        ],
        'locked_fields' => [
            'qsa_socios_administradores',
            'capital_social',
            'natureza_juridica',
            'data_abertura',
            'cnae_fiscal_principal',
            'cnaes_secundarios',
            'telefones',
            'email',
            'endereco_completo',
        ],
    ];
}

