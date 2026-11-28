<?php
/**
 * Grid402 - Micro-serviço Premium de Dossiê 360º KYB para Agentes Autônomos
 *
 * Consolida em chamada atômica única:
 * - Dados Cadastrais da Receita Federal (CNPJ — estritamente Zero PII)
 * - Enquadramento Tributário (Simples Nacional e MEI)
 * - Sanções Administrativas e Inidoneidade (CEIS e CNEP da CGU)
 * - Compliance Score 360 (0 a 100)
 * - Veredito Sintetizado para IA (APPROVED, FLAGGED, REJECTED)
 */

declare(strict_types=1);

if (!defined('GRID402_EXEC')) {
    define('GRID402_EXEC', true);
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/cnpj.php';
require_once __DIR__ . '/compliance.php';

/**
 * Motor determinístico de veredito para tomada de decisão por agentes autônomos de IA.
 */
function grid402_dossier_compute_verdict(array $company, array $taxRegime, array $sanctions, array $complianceData): array {
    $situacao = strtoupper(trim((string) ($company['situacao_cadastral'] ?? 'DESCONHECIDA')));
    $hasSanctions = !empty($sanctions['has_sanctions']);
    $totalSanctions = (int) ($sanctions['total_sanctions'] ?? 0);
    $score = (int) ($complianceData['score'] ?? 100);
    $complianceFlags = is_array($complianceData['flags'] ?? null) ? $complianceData['flags'] : [];

    $alerts = [];
    $hasAttentionAlert = false;

    if ($situacao !== 'ATIVA') {
        $alerts[] = [
            'severity' => 'CRITICAL',
            'code' => 'COMPANY_NOT_ACTIVE',
            'message' => "Situação cadastral na Receita Federal não está ativa ({$situacao}).",
        ];
    }

    if ($hasSanctions) {
        $alerts[] = [
            'severity' => 'CRITICAL',
            'code' => 'CGU_SANCTION_ACTIVE',
            'message' => "Empresa possui {$totalSanctions} registro(s) de sanção administrativa ativa no CEIS/CNEP da CGU.",
        ];
    }

    foreach ($complianceFlags as $flag) {
        if (!is_array($flag)) {
            continue;
        }
        $code = (string) ($flag['codigo'] ?? '');
        if ($code === 'EMPRESA_INATIVA' || $code === 'SANCAO_CGU_ATIVA') {
            continue;
        }
        $tipo = strtoupper((string) ($flag['tipo'] ?? 'ATENCAO'));
        $alerts[] = [
            'severity' => ($tipo === 'CRITICO') ? 'CRITICAL' : 'WARNING',
            'code' => $code !== '' ? $code : 'COMPLIANCE_NOTICE',
            'message' => (string) ($flag['descricao'] ?? 'Ponto de atenção detectado na análise de compliance.'),
        ];
        $hasAttentionAlert = true;
    }

    $capitalSocial = (float) ($company['capital_social'] ?? 0);
    if ($situacao === 'ATIVA' && $capitalSocial <= 0 && empty($taxRegime['opcao_pelo_mei'])) {
        $alerts[] = [
            'severity' => 'WARNING',
            'code' => 'ZERO_DECLARED_CAPITAL',
            'message' => 'Capital social declarado zerado ou não informado na base pública.',
        ];
        $hasAttentionAlert = true;
    }

    $score = max(0, min(100, $score));

    if ($situacao !== 'ATIVA' || $hasSanctions) {
        $status = 'REJECTED';
        $riskLevel = 'HIGH';
        if ($hasSanctions && $situacao !== 'ATIVA') {
            $summary = "Contratação não recomendada: empresa com situação {$situacao} na Receita Federal e sanção ativa registrada no CEIS/CNEP da CGU (score {$score}/100).";
        } elseif ($hasSanctions) {
            $summary = "Contratação não recomendada: empresa consta com {$totalSanctions} sanção(ões) ativa(s) no CEIS/CNEP da CGU (score {$score}/100).";
        } else {
            $summary = "Contratação não recomendada: empresa encontra-se com situação cadastral {$situacao} na Receita Federal (score {$score}/100).";
        }
    } elseif ($score < 70 || $hasAttentionAlert) {
        $status = 'FLAGGED';
        $riskLevel = 'MEDIUM';
        $summary = "Empresa ATIVA na Receita Federal e sem sanções no CEIS/CNEP, porém com pontos de atenção que recomendam revisão antes de aprovação automática (score {$score}/100).";
    } else {
        $status = 'APPROVED';
        $riskLevel = 'LOW';
        $summary = "Empresa ATIVA na Receita Federal, sem sanções registradas no CEIS/CNEP da CGU e com alta integridade cadastral (score {$score}/100).";
    }

    return [
        'status' => $status,
        'score' => $score,
        'risk_level' => $riskLevel,
        'alerts' => $alerts,
        'summary' => $summary,
    ];
}

/**
 * Executa a agregação completa do Dossiê 360º KYB para um CNPJ (Zero-DB com cache atômico 24h).
 */
function grid402_service_dossier(string $cnpj, bool $bypassCache = false): array {
    $clean = preg_replace('/\D/', '', trim($cnpj));

    if (!grid402_validate_cnpj_digits($clean)) {
        return [
            'success' => false,
            'error' => 'InvalidCNPJ',
            'message' => 'O CNPJ informado é matematicamente inválido ou possui dígitos verificadores incorretos.',
            'cnpj' => $cnpj,
            'code' => 400,
        ];
    }

    $cacheFile = GRID402_PRIVATE_DIR . '/dossier_' . $clean . '.json';
    $cacheTtlSeconds = 86400; // 24 horas

    if (!$bypassCache && file_exists($cacheFile)) {
        $mtime = @filemtime($cacheFile);
        $age = time() - ($mtime ?: 0);
        if ($mtime && $age < $cacheTtlSeconds) {
            $rawContent = @file_get_contents($cacheFile);
            if ($rawContent) {
                $cached = @json_decode($rawContent, true);
                if (is_array($cached) && isset($cached['verdict']['status'])) {
                    $cached['cached'] = true;
                    $cached['cache_age_seconds'] = $age;
                    return $cached;
                }
            }
        }
    }

    // 1. Consulta Cadastral Receita Federal
    $cnpjData = grid402_service_cnpj($clean, $bypassCache);
    if (!($cnpjData['success'] ?? false)) {
        return $cnpjData;
    }

    // 2. Consulta Enquadramento Tributário (Simples Nacional e MEI)
    $simplesData = grid402_service_simples($clean, $bypassCache);

    // 3. Consulta Sanções CGU (CEIS e CNEP)
    $sanctionsData = grid402_service_sanctions($clean, $bypassCache);

    // 4. Consulta Compliance Score 360 consolidado
    $complianceData = grid402_service_compliance($clean, $bypassCache);

    // Projeção estrita por allowlist de Pessoa Jurídica (Garantia absoluta de Zero PII / sem QSA ou CPF)
    $endereco = is_array($cnpjData['endereco'] ?? null) ? $cnpjData['endereco'] : [];
    $company = [
        'razao_social' => (string) ($cnpjData['razao_social'] ?? ''),
        'nome_fantasia' => (string) ($cnpjData['nome_fantasia'] ?? ''),
        'situacao_cadastral' => strtoupper((string) ($cnpjData['situacao_cadastral'] ?? 'ATIVA')),
        'data_abertura' => (string) ($cnpjData['data_abertura'] ?? ($cnpjData['data_inicio_atividade'] ?? '')),
        'natureza_juridica' => (string) ($cnpjData['natureza_juridica'] ?? ''),
        'cnae_fiscal' => $cnpjData['cnae_fiscal'] ?? null,
        'cnae_fiscal_descricao' => (string) ($cnpjData['cnae_fiscal_descricao'] ?? ''),
        'capital_social' => (float) ($cnpjData['capital_social'] ?? 0),
        'municipio' => (string) ($endereco['municipio'] ?? ($cnpjData['municipio'] ?? '')),
        'uf' => (string) ($endereco['uf'] ?? ($cnpjData['uf'] ?? '')),
    ];

    $optSimples = !empty($simplesData['optante_simples']);
    $optMei = !empty($simplesData['optante_simei']);
    $regimeResumo = (string) ($simplesData['situacao_atual'] ?? ($optMei ? 'Optante pelo SIMEI (MEI)' : ($optSimples ? 'Optante pelo Simples Nacional' : 'Lucro Real / Presumido (Não optante pelo Simples)')));

    $taxRegime = [
        'opcao_pelo_simples' => $optSimples,
        'opcao_pelo_mei' => $optMei,
        'regime_resumido' => $regimeResumo,
    ];

    $sanctionsRecords = is_array($sanctionsData['sanctions'] ?? null) ? $sanctionsData['sanctions'] : [];
    $sanctions = [
        'has_sanctions' => !empty($sanctionsData['has_sanctions']),
        'total_sanctions' => (int) ($sanctionsData['total_sanctions'] ?? count($sanctionsRecords)),
        'records' => $sanctionsRecords,
    ];

    $verdict = grid402_dossier_compute_verdict($company, $taxRegime, $sanctions, $complianceData);

    $result = [
        'success' => true,
        'service' => 'dossier',
        'cnpj' => grid402_format_cnpj($clean),
        'cnpj_raw' => $clean,
        'verdict' => $verdict,
        'company' => $company,
        'tax_regime' => $taxRegime,
        'sanctions' => $sanctions,
        'compliance_score' => [
            'score' => (int) ($complianceData['score'] ?? $verdict['score']),
            'factors' => is_array($complianceData['flags'] ?? null) ? $complianceData['flags'] : [],
        ],
        'legal_license' => 'Dados Abertos Governamentais (Lei nº 12.527/2011 - LAI e Decreto nº 8.777/2016)',
        'data_protection_compliance' => 'LGPD Art. 1º e GDPR Recital 14 (Exclusivo para Pessoa Jurídica — Zero PII / Sem QSA ou CPF)',
        'cached' => false,
        'checked_at' => gmdate('Y-m-d\TH:i:s\Z'),
    ];

    grid402_atomic_write($cacheFile, $result);

    return $result;
}

/**
 * Gera prévia estrutural estática/mascarada (Teaser) do Dossiê 360º para exibição no desafio HTTP 402 antes do pagamento.
 * Nunca consulta Receita Federal nem CGU no 402 e nunca vaza has_sanctions, razao_social ou situacao_cadastral reais.
 */
function grid402_teaser_dossier(string $cnpj): array {
    $clean = preg_replace('/\D/', '', trim($cnpj));
    $formattedCnpj = grid402_validate_cnpj_digits($clean) ? grid402_format_cnpj($clean) : $cnpj;
    $preview = [
        'cnpj' => $formattedCnpj,
        'modelo_analise' => 'Dossiê 360º KYB + Veredito Sintetizado para IA',
        'fontes_cruzadas' => 'Receita Federal (CNPJ), Simples Nacional/MEI e CGU (CEIS/CNEP)',
        'veredito_tipo' => 'APPROVED, FLAGGED ou REJECTED',
        'preview_mode' => 'static_schema_sample',
        'sample_schema' => [
            'verdict' => [
                'status' => 'APPROVED | FLAGGED | REJECTED',
                'score' => '0-100',
                'risk_level' => 'LOW | MEDIUM | HIGH',
            ],
            'company' => [
                'razao_social' => '[LOCKED — unlock via x402 or Session Pass]',
                'situacao_cadastral' => '[LOCKED]',
                'municipio' => '[LOCKED]',
                'uf' => '[LOCKED]',
            ],
            'tax_regime' => [
                'opcao_pelo_simples' => '[LOCKED]',
                'opcao_pelo_mei' => '[LOCKED]',
            ],
            'sanctions' => [
                'has_sanctions' => '[LOCKED]',
                'total_sanctions' => '[LOCKED]',
            ],
        ],
    ];

    return [
        'preview' => $preview,
        'locked_fields' => [
            'verdict',
            'company.razao_social',
            'company.situacao_cadastral',
            'company',
            'tax_regime',
            'sanctions.has_sanctions',
            'sanctions.records',
            'compliance_score',
            '_oracle_proof',
        ],
    ];
}
