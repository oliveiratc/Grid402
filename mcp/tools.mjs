/**
 * Grid402 MCP Server - Catálogo de Ferramentas e Despachador HTTP
 */

import { settleX402Payment } from './x402_payer.mjs';

export const DEFAULT_ENDPOINT = process.env.GRID402_ENDPOINT || process.env.GRID402_API_URL || 'https://grid402.online';

/**
 * Definições formais das 27 ferramentas no padrão MCP (25 micro-serviços + summary + pass_balance)
 */
export const MCP_TOOLS_DEFINITIONS = [
  {
    name: 'grid402_summary',
    description: 'Get consolidated live health, latency and 30-day uptime summary for all 25 Grid402 public-data services (free, no x402 charge).',
    inputSchema: {
      type: 'object',
      properties: {},
      required: [],
    },
  },
  {
    name: 'grid402_forex',
    description: 'Get official commercial fiat exchange rates (USD, EUR, GBP to BRL) with daily percentage change and ECDSA oracle proof (0.0050 USDC / 1 credit).',
    inputSchema: {
      type: 'object',
      properties: {
        payment_tx_hash: {
          type: 'string',
          description: 'Optional payment proof: Multi-Chain USDC tx hash (0x..., polygon:0x..., arbitrum:0x..., solana:<sig>), x402 base64 payload, Session Pass token (pass_g402_...), or "demo" for Sandbox.',
        },
      },
      required: [],
    },
  },
  {
    name: 'grid402_crypto',
    description: 'Get real-time spot prices and 24h changes for BTC, ETH, SOL, USDC and BASE in USD and BRL (0.0050 USDC / 1 credit).',
    inputSchema: {
      type: 'object',
      properties: {
        payment_tx_hash: {
          type: 'string',
          description: 'Optional payment proof: Multi-Chain USDC tx hash, x402 base64 payload, Session Pass token (pass_g402_...), or "demo" for Sandbox.',
        },
      },
      required: [],
    },
  },
  {
    name: 'grid402_dns',
    description: 'Resolve authoritative DNS records (A, AAAA, MX, TXT, NS, CNAME) for any domain with query latency (0.0050 USDC / 1 credit).',
    inputSchema: {
      type: 'object',
      properties: {
        domain: {
          type: 'string',
          description: 'Target domain name to resolve (e.g. "github.com", "base.org").',
        },
        payment_tx_hash: {
          type: 'string',
          description: 'Optional payment proof: Multi-Chain USDC tx hash, x402 base64 payload, Session Pass token (pass_g402_...), or "demo" for Sandbox.',
        },
      },
      required: ['domain'],
    },
  },
  {
    name: 'grid402_ssl',
    description: 'Inspect and validate port 443 TLS/SSL certificate (issuer, validity, SANs, cipher suite and days until expiration) (0.0050 USDC / 1 credit).',
    inputSchema: {
      type: 'object',
      properties: {
        host: {
          type: 'string',
          description: 'Target hostname or domain to inspect on port 443 (e.g. "base.org", "google.com").',
        },
        payment_tx_hash: {
          type: 'string',
          description: 'Optional payment proof: Multi-Chain USDC tx hash, x402 base64 payload, Session Pass token (pass_g402_...), or "demo" for Sandbox.',
        },
      },
      required: ['host'],
    },
  },
  {
    name: 'grid402_asn',
    description: 'Query BGP routing intelligence: ASN number, allocated IP prefix, organization/ISP and country for any IPv4/IPv6 address (0.0050 USDC / 1 credit).',
    inputSchema: {
      type: 'object',
      properties: {
        ip: {
          type: 'string',
          description: 'IPv4 or IPv6 address to inspect (e.g. "1.1.1.1"). Defaults to requester IP if omitted.',
        },
        payment_tx_hash: {
          type: 'string',
          description: 'Optional payment proof: Multi-Chain USDC tx hash, x402 base64 payload, Session Pass token (pass_g402_...), or "demo" for Sandbox.',
        },
      },
      required: [],
    },
  },
  {
    name: 'grid402_domain_audit',
    description: 'Consolidated domain security and infrastructure audit (DNS + TLS/SSL + BGP/ASN) with risk score in a single call (0.0150 USDC / 3 credits).',
    inputSchema: {
      type: 'object',
      properties: {
        domain: {
          type: 'string',
          description: 'Target domain name to audit (e.g. "base.org").',
        },
        payment_tx_hash: {
          type: 'string',
          description: 'Optional payment proof (0.0150 USDC on Base, Polygon, Arbitrum or Solana), Session Pass token (pass_g402_...), or "demo".',
        },
      },
      required: ['domain'],
    },
  },
  {
    name: 'grid402_kyb_audit',
    description: 'Global corporate KYB audit in one call: Brazil Receita Federal CNPJ + US OFAC/UN Sanctions + GLEIF ISO 17442 LEI (0.0250 USDC / 5 credits).',
    inputSchema: {
      type: 'object',
      properties: {
        cnpj: {
          type: 'string',
          description: '14-digit Brazilian CNPJ number (e.g. "00000000000191").',
        },
        payment_tx_hash: {
          type: 'string',
          description: 'Optional payment proof (0.0250 USDC on Base, Polygon, Arbitrum or Solana), Session Pass token (pass_g402_...), or "demo".',
        },
      },
      required: ['cnpj'],
    },
  },
  {
    name: 'grid402_get_dossier_360',
    description: 'Premium 360-degree Brazil KYB Dossier with deterministic AI verdict (APPROVED, FLAGGED, REJECTED), 0-100 risk score, Receita Federal CNPJ, Simples Nacional/MEI and CGU CEIS/CNEP sanctions (0.0500 USDC / 10 credits).',
    inputSchema: {
      type: 'object',
      properties: {
        cnpj: {
          type: 'string',
          description: '14-digit Brazilian CNPJ number (with or without mask, e.g. "00000000000191").',
        },
        payment_tx_hash: {
          type: 'string',
          description: 'Optional payment proof (0.0500 USDC on Base, Polygon, Arbitrum or Solana), Session Pass token (pass_g402_...), or "demo".',
        },
      },
      required: ['cnpj'],
    },
  },
  {
    name: 'grid402_market_snapshot',
    description: 'Consolidated financial and macro market snapshot in one call: Forex + Crypto Spot + Brazil Selic/CDI/IPCA + ANBIMA calendar (0.0150 USDC / 3 credits).',
    inputSchema: {
      type: 'object',
      properties: {
        payment_tx_hash: {
          type: 'string',
          description: 'Optional payment proof (0.0150 USDC on Base, Polygon, Arbitrum or Solana), Session Pass token (pass_g402_...), or "demo".',
        },
      },
      required: [],
    },
  },
  {
    name: 'grid402_cep',
    description: 'Lookup Brazilian postal code (CEP) returning street, neighborhood, city, state and official IBGE municipality code (0.0050 USDC / 1 credit).',
    inputSchema: {
      type: 'object',
      properties: {
        code: {
          type: 'string',
          description: '8-digit Brazilian CEP postal code (e.g. "01310100").',
        },
        payment_tx_hash: {
          type: 'string',
          description: 'Optional payment proof: Multi-Chain USDC tx hash, x402 base64 payload, Session Pass token (pass_g402_...), or "demo" for Sandbox.',
        },
      },
      required: ['code'],
    },
  },
  {
    name: 'grid402_cnpj',
    description: 'Lookup official Brazil Receita Federal CNPJ corporate registry data (legal name, trade name, active status, CNAE, address and share capital) (0.0100 USDC / 2 credits).',
    inputSchema: {
      type: 'object',
      properties: {
        cnpj: {
          type: 'string',
          description: '14-digit Brazilian CNPJ number with or without punctuation (e.g. "00000000000191").',
        },
        payment_tx_hash: {
          type: 'string',
          description: 'Optional payment proof (0.0100 USDC on Base, Polygon, Arbitrum or Solana), Session Pass token (pass_g402_...), or "demo".',
        },
      },
      required: ['cnpj'],
    },
  },
  {
    name: 'grid402_indicators',
    description: 'Get official Brazil Central Bank (BCB) and IBGE macro indicators: Selic target/daily, annual/daily CDI and 12-month IPCA inflation (0.0050 USDC / 1 credit).',
    inputSchema: {
      type: 'object',
      properties: {
        payment_tx_hash: {
          type: 'string',
          description: 'Optional payment proof: Multi-Chain USDC tx hash, x402 base64 payload, Session Pass token (pass_g402_...), or "demo" for Sandbox.',
        },
      },
      required: [],
    },
  },
  {
    name: 'grid402_holidays',
    description: 'Get official Brazil national and ANBIMA/Febraban banking holidays calendar for any year including movable holidays (0.0050 USDC / 1 credit).',
    inputSchema: {
      type: 'object',
      properties: {
        year: {
          type: 'integer',
          description: '4-digit calendar year between 1900 and 2100 (e.g. 2026). Defaults to current year.',
        },
        payment_tx_hash: {
          type: 'string',
          description: 'Optional payment proof: Multi-Chain USDC tx hash, x402 base64 payload, Session Pass token (pass_g402_...), or "demo" for Sandbox.',
        },
      },
      required: [],
    },
  },
  {
    name: 'grid402_workdays',
    description: 'Calculate Brazilian ANBIMA banking business days, D+N settlement maturity dates, or check if a date is a banking business day (0.0050 USDC / 1 credit).',
    inputSchema: {
      type: 'object',
      properties: {
        start_date: {
          type: 'string',
          description: 'Start date in YYYY-MM-DD format for interval counting or D+N projection.',
        },
        end_date: {
          type: 'string',
          description: 'End date in YYYY-MM-DD format for business-day interval counting.',
        },
        days: {
          type: 'integer',
          description: 'Integer business days to project (positive for future, negative for past).',
        },
        date: {
          type: 'string',
          description: 'Single date in YYYY-MM-DD format to check whether it is a banking business day.',
        },
        payment_tx_hash: {
          type: 'string',
          description: 'Optional payment proof (0.0050 USDC on Base, Polygon, Arbitrum or Solana), Session Pass token (pass_g402_...), or "demo".',
        },
      },
      required: [],
    },
  },
  {
    name: 'grid402_sanctions',
    description: 'Check Brazil CGU administrative sanctions registries (CEIS and CNEP) for a given CNPJ (0.0100 USDC / 2 credits).',
    inputSchema: {
      type: 'object',
      properties: {
        cnpj: {
          type: 'string',
          description: '14-digit Brazilian CNPJ number to check against CEIS/CNEP sanctions.',
        },
        payment_tx_hash: {
          type: 'string',
          description: 'Optional payment proof (0.0100 USDC on Base, Polygon, Arbitrum or Solana), x402 base64 payload, Session Pass token (pass_g402_...), or "demo" for Sandbox.',
        },
      },
      required: ['cnpj'],
    },
  },
  {
    name: 'grid402_simples',
    description: 'Check Brazil Simples Nacional and SIMEI (MEI) tax regime enrollment status for a CNPJ (0.0100 USDC / 2 credits).',
    inputSchema: {
      type: 'object',
      properties: {
        cnpj: {
          type: 'string',
          description: '14-digit Brazilian CNPJ number for tax regime verification.',
        },
        payment_tx_hash: {
          type: 'string',
          description: 'Optional payment proof (0.0100 USDC on Base, Polygon, Arbitrum or Solana), x402 base64 payload, Session Pass token (pass_g402_...), or "demo" for Sandbox.',
        },
      },
      required: ['cnpj'],
    },
  },
  {
    name: 'grid402_compliance',
    description: 'Compute consolidated Brazil Compliance Score 360 (0-100) and approval status for a CNPJ cross-checking Receita Federal and CGU (0.0300 USDC / 6 credits).',
    inputSchema: {
      type: 'object',
      properties: {
        cnpj: {
          type: 'string',
          description: '14-digit Brazilian CNPJ number for 360 compliance scoring.',
        },
        payment_tx_hash: {
          type: 'string',
          description: 'Optional payment proof (0.0300 USDC on Base, Polygon, Arbitrum or Solana), x402 base64 payload, Session Pass token (pass_g402_...), or "demo" for Sandbox.',
        },
      },
      required: ['cnpj'],
    },
  },
  {
    name: 'grid402_lei',
    description: 'Lookup Global Legal Entity Identifier (ISO 17442) corporate identity via GLEIF across 200+ jurisdictions (0.0100 USDC / 2 credits).',
    inputSchema: {
      type: 'object',
      properties: {
        lei: {
          type: 'string',
          description: '20-character ISO 17442 LEI code (e.g. "5493006MHB84DD0ZWV18") or legal entity name.',
        },
        payment_tx_hash: {
          type: 'string',
          description: 'Optional payment proof (0.0100 USDC on Base, Polygon, Arbitrum or Solana), Session Pass token (pass_g402_...), or "demo".',
        },
      },
      required: ['lei'],
    },
  },
  {
    name: 'grid402_ofac',
    description: 'Screen EVM wallet addresses (0x...), LEI codes or entities against US Treasury OFAC SDN, UN Consolidated Sanctions and Base Mainnet AML risk (0.0200 USDC / 4 credits).',
    inputSchema: {
      type: 'object',
      properties: {
        target: {
          type: 'string',
          description: 'EVM wallet address (0x...), LEI code or legal entity name to screen against OFAC/UN lists.',
        },
        payment_tx_hash: {
          type: 'string',
          description: 'Optional payment proof (0.0200 USDC on Base, Polygon, Arbitrum or Solana), Session Pass token (pass_g402_...), or "demo".',
        },
      },
      required: ['target'],
    },
  },
  {
    name: 'grid402_notarize',
    description: 'Create an M2M cryptographic timestamp and SHA-256 proof-of-existence signed with ECDSA secp256k1 (0.0050 USDC / 1 credit).',
    inputSchema: {
      type: 'object',
      properties: {
        content: {
          type: 'string',
          description: 'Text payload or 64-char hex SHA-256 hash to timestamp and notarize.',
        },
        payment_tx_hash: {
          type: 'string',
          description: 'Optional payment proof: Multi-Chain USDC tx hash, x402 base64 payload, Session Pass token (pass_g402_...), or "demo" for Sandbox.',
        },
      },
      required: ['content'],
    },
  },
  {
    name: 'grid402_global_markets',
    description: 'Query global exchange settlement calendars (US NYSE/Fed, EU TARGET2, UK LSE, BR B3) and 30+ European Central Bank (ECB) FX reference rates (0.0050 USDC / 1 credit).',
    inputSchema: {
      type: 'object',
      properties: {
        market: {
          type: 'string',
          description: 'Market code: US, EU, UK or BR (default: US).',
        },
        base: {
          type: 'string',
          description: 'Base currency for ECB FX reference rates (default: USD).',
        },
        date: {
          type: 'string',
          description: 'Optional target date in YYYY-MM-DD format.',
        },
        payment_tx_hash: {
          type: 'string',
          description: 'Optional payment proof: Multi-Chain USDC tx hash, x402 base64 payload, Session Pass token (pass_g402_...), or "demo" for Sandbox.',
        },
      },
      required: [],
    },
  },
  {
    name: 'grid402_extract',
    description: 'SSRF-hardened web page URL to clean LLM-ready Markdown extractor for RAG and AI agents, returning title, metadata and absolute links (0.0050 USDC / 1 credit).',
    inputSchema: {
      type: 'object',
      properties: {
        url: {
          type: 'string',
          description: 'Public HTTP or HTTPS web page URL to fetch and convert to clean Markdown.',
        },
        max_chars: {
          type: 'integer',
          description: 'Optional max output character length (500 to 25000; default: 12000).',
        },
        payment_tx_hash: {
          type: 'string',
          description: 'Optional USDC tx hash, Session Pass token (pass_g402_...), or "demo".',
        },
      },
      required: ['url'],
    },
  },
  {
    name: 'grid402_email_verify',
    description: 'Zero-PII B2B email deliverability and risk audit: RFC syntax, DNS MX, SPF, DMARC, disposable domain detection, free provider and role-based score (0.0050 USDC / 1 credit).',
    inputSchema: {
      type: 'object',
      properties: {
        email: {
          type: 'string',
          description: 'Corporate or business email address to audit without persisting PII.',
        },
        payment_tx_hash: {
          type: 'string',
          description: 'Optional USDC tx hash, Session Pass token (pass_g402_...), or "demo".',
        },
      },
      required: ['email'],
    },
  },
  {
    name: 'grid402_pass',
    description: 'Issue an x402 prepaid Session Pass (Starter 2 USDC = 400 credits/180d, Pro 5 USDC = 1,200 credits/180d, Fleet 20 USDC = 6,000 credits/365d; 1 credit = 0.0050 USDC) or check pass status.',
    inputSchema: {
      type: 'object',
      properties: {
        status: {
          type: 'string',
          description: 'Optional existing pass_g402_... token for free balance and status lookup.',
        },
        payment_tx_hash: {
          type: 'string',
          description: 'Optional Multi-Chain USDC payment proof (Base, Polygon, Arbitrum, Optimism, Ethereum or Solana) to issue a new Session Pass.',
        },
      },
      required: [],
    },
  },
  {
    name: 'grid402_pass_balance',
    description: 'Free real-time lookup of Session Pass credit balance, expiration and Spend Controls (daily_credit_limit, credits_used_today, allowed_endpoints).',
    inputSchema: {
      type: 'object',
      properties: {
        token: {
          type: 'string',
          description: 'Session Pass token (pass_g402_...) to inspect (or uses GRID402_SESSION_PASS env var).',
        },
      },
      required: [],
    },
  },
  {
    name: 'grid402_verify',
    description: 'Stateless cryptographic verification of an _oracle_proof ECDSA secp256k1 signature on any Grid402 API response (free).',
    inputSchema: {
      type: 'object',
      properties: {
        payload: {
          type: 'string',
          description: 'Serialized JSON API response containing the _oracle_proof block to verify.',
        },
      },
      required: ['payload'],
    },
  },
];

/**
 * Despachante de requisição HTTP com liquidação automática de desafio x402
 */
export const CHECKOUT_OPTIONS = {
  pix_instant_brl: {
    starter_400_credits: 'https://grid402.online/pass?tier=starter&autopix=1 (R$ 12,00 via Pix = 400 créditos · 180 dias)',
    pro_1200_credits: 'https://grid402.online/pass?tier=pro&autopix=1 (R$ 29,00 via Pix = 1.200 créditos · 180 dias)',
    fleet_6000_credits: 'https://grid402.online/pass?tier=fleet&autopix=1 (R$ 115,00 via Pix = 6.000 créditos · 365 dias)',
    how_to_use: 'Defina GRID402_SESSION_PASS=pass_g402_... no ambiente do MCP para chamadas em <15ms sem chave privada.',
  },
  rapidapi_credit_card_usd: {
    plans: 'PRO $9.90/mês (1.500 chamadas) | ULTRA $29.00/mês (6.000 chamadas) | MEGA $79.00/mês (25.000 chamadas)',
    all_25_apis: 'https://rapidapi.com/oliveiratc/api/grid402-open-data-kyb-ai-utilities',
    web_markdown_email_network: 'https://rapidapi.com/oliveiratc/api/grid402-web-to-markdown-email-verify-network-utilities',
    brazil_kyb_cnpj_anbima: 'https://rapidapi.com/oliveiratc/api/brazil-kyb-cnpj-cgu-sanctions-anbima-workdays-api',
  },
  web3_multichain_usdc: {
    networks: 'Base, Polygon, Arbitrum e Solana SPL USDC',
    how_to_use: 'Defina GRID402_PRIVATE_KEY (EVM) ou GRID402_SOLANA_PRIVATE_KEY (Solana) e GRID402_PAYMENT_NETWORK=base|polygon|arbitrum|solana.',
  },
};

export async function executeGrid402Request(service, params = {}, options = {}) {
  const endpoint = (options.endpoint || DEFAULT_ENDPOINT).replace(/\/$/, '');
  const paymentNetwork = options.network || params.payment_network || process.env.GRID402_PAYMENT_NETWORK || null;
  const solanaPrivateKey = options.solanaPrivateKey || process.env.GRID402_SOLANA_PRIVATE_KEY || null;
  const privateKey = options.privateKey
    || (String(paymentNetwork || '').toLowerCase() === 'solana' && solanaPrivateKey ? solanaPrivateKey : null)
    || process.env.GRID402_PRIVATE_KEY
    || solanaPrivateKey
    || null;
  const preferredRpc = options.rpcUrl || process.env.GRID402_RPC_URL || process.env.GRID402_BASE_RPC_URL || null;

  let path = '';
  const queryParams = new URLSearchParams();

  switch (service) {
    case 'summary':
      path = '/status';
      break;
    case 'forex':
      path = '/api/v1/forex';
      break;
    case 'crypto':
      path = '/api/v1/crypto';
      break;
    case 'dns':
      path = '/api/v1/dns';
      if (params.domain) queryParams.set('domain', params.domain);
      break;
    case 'ssl':
      path = '/api/v1/ssl';
      if (params.host) queryParams.set('host', params.host);
      break;
    case 'asn':
      path = '/api/v1/asn';
      if (params.ip) queryParams.set('ip', params.ip);
      break;
    case 'domain-audit':
    case 'domain_audit':
      path = '/api/v1/domain-audit';
      if (params.domain) queryParams.set('domain', params.domain);
      break;
    case 'kyb-audit':
    case 'kyb_audit':
      path = '/api/v1/kyb-audit';
      if (params.cnpj) queryParams.set('cnpj', params.cnpj);
      break;
    case 'dossier':
    case 'get_dossier_360':
      path = '/api/v1/dossier';
      if (params.cnpj) queryParams.set('cnpj', params.cnpj);
      break;
    case 'market-snapshot':
    case 'market_snapshot':
      path = '/api/v1/market-snapshot';
      break;
    case 'cep':
      path = '/api/v1/cep';
      if (params.code) queryParams.set('code', params.code);
      break;
    case 'cnpj':
      path = '/api/v1/cnpj';
      if (params.cnpj) queryParams.set('cnpj', params.cnpj);
      break;
    case 'indicators':
      path = '/api/v1/indicators';
      break;
    case 'holidays':
      path = '/api/v1/holidays';
      if (params.year) queryParams.set('year', params.year);
      break;
    case 'workdays':
      path = '/api/v1/workdays';
      if (params.start_date || params.startDate) queryParams.set('start_date', params.start_date || params.startDate);
      if (params.end_date || params.endDate) queryParams.set('end_date', params.end_date || params.endDate);
      if (params.days !== undefined) {
        if (params.days !== null) {
          queryParams.set('days', params.days);
        }
      }
      if (params.date) queryParams.set('date', params.date);
      break;
    case 'sanctions':
      path = '/api/v1/sanctions';
      if (params.cnpj) queryParams.set('cnpj', params.cnpj);
      break;
    case 'simples':
      path = '/api/v1/simples';
      if (params.cnpj) queryParams.set('cnpj', params.cnpj);
      break;
    case 'compliance':
      path = '/api/v1/compliance';
      if (params.cnpj) queryParams.set('cnpj', params.cnpj);
      break;
    case 'lei':
      path = '/api/v1/lei';
      if (params.lei) queryParams.set('lei', params.lei);
      break;
    case 'ofac':
      path = '/api/v1/ofac';
      if (params.target) queryParams.set('target', params.target);
      break;
    case 'notarize':
      path = '/api/v1/notarize';
      if (params.content) queryParams.set('content', params.content);
      break;
    case 'global-markets':
    case 'global_markets':
      path = '/api/v1/global-markets';
      if (params.market) queryParams.set('market', params.market);
      if (params.base) queryParams.set('base', params.base);
      if (params.date) queryParams.set('date', params.date);
      break;
    case 'extract':
      path = '/api/v1/extract';
      if (params.url) queryParams.set('url', params.url);
      if (params.max_chars !== undefined && params.max_chars !== null) queryParams.set('max_chars', String(params.max_chars));
      break;
    case 'email-verify':
    case 'email_verify':
      path = '/api/v1/email-verify';
      if (params.email) queryParams.set('email', params.email);
      break;
    case 'pass':
      path = '/api/v1/pass';
      if (params.status) queryParams.set('status', params.status);
      break;
    case 'pass-balance':
    case 'pass_balance':
      path = '/api/v1/pass/balance';
      if (params.token) queryParams.set('token', params.token);
      break;
    case 'verify':
      path = '/api/v1/verify';
      break;
    default:
      throw new Error(`Servico desconhecido: ${service}`);
  }

  // Verificacao de oraculo via POST sem estado
  if (service === 'verify') {
    const postPayload = typeof params.payload === 'string' ? params.payload : JSON.stringify(params.payload || params);
    const verifyRes = await fetch(`${endpoint}/api/v1/verify`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'User-Agent': 'Grid402-MCP-Server/2.0.0',
      },
      body: postPayload,
    });
    return await verifyRes.json();
  }

  const queryString = queryParams.toString();
  const url = `${endpoint}${path}${queryString ? '?' + queryString : ''}`;

  const headers = {
    'Accept': 'application/json',
    'User-Agent': 'Grid402-MCP-Server/2.0.0',
    'X-Grid402-Channel': 'mcp',
  };

  if (params.idempotency_key) {
    headers['Idempotency-Key'] = String(params.idempotency_key);
  }

  const isPassService = service === 'pass';
  const isExplicitlyNotSandbox = process.env.GRID402_SANDBOX === 'false' || process.env.GRID402_SANDBOX === '0';
  const isExplicitSandbox = process.env.GRID402_SANDBOX === 'true' || process.env.GRID402_SANDBOX === '1';
  const useAutoTrial = Boolean(
    !isPassService &&
    options.autoTrial &&
    !params.payment_tx_hash &&
    !process.env.GRID402_SESSION_PASS &&
    !privateKey &&
    !isExplicitlyNotSandbox
  );

  if (params.payment_tx_hash) {
    const manualProof = String(params.payment_tx_hash).trim();
    const manualLower = manualProof.toLowerCase();
    if (isPassService && (manualLower === 'demo' || manualLower === 'sandbox' || manualLower.startsWith('pass_g402_'))) {
      return {
        payment_required: true,
        error: 'PassPaymentModeForbidden',
        message: 'grid402_pass requires real USDC payment (x402 or on-chain txHash). Sandbox/demo mode and existing Session Passes cannot be used to issue a new pass.',
        checkout_options: CHECKOUT_OPTIONS,
      };
    }
    headers['X-Payment'] = manualProof;
  } else if (process.env.GRID402_SESSION_PASS && !isPassService) {
    headers['X-Payment'] = process.env.GRID402_SESSION_PASS.trim();
  } else if (!isPassService && (isExplicitSandbox || useAutoTrial)) {
    headers['X-Payment'] = 'demo';
  }

  // 1. Primeira tentativa de requisição
  const response = await fetch(url, { headers });

  if (response.status === 200) {
    const data = await response.json();
    if (useAutoTrial && data && typeof data === 'object') {
      const rem = data?.sandbox_info?.quota_remaining_today ?? null;
      return {
        ...data,
        _trial_upgrade_notice: {
          mode: 'FREE_DAILY_TRIAL (3 queries/day per IP)',
          remaining_free_queries_today: rem,
          upgrade_instant_pix_brl: CHECKOUT_OPTIONS.pix_instant_brl.starter_400_credits,
          upgrade_credit_card_usd: CHECKOUT_OPTIONS.rapidapi_credit_card_usd.all_25_apis,
          upgrade_session_pass_portal: 'https://grid402.online/pass',
        },
      };
    }
    return data;
  }

  // 1.1 Se atingiu o limite diário de 3 consultas grátis do Sandbox (HTTP 429), devolve paywall estruturado com links Pix, RapidAPI e Web3
  if (response.status === 429) {
    const limitData = await response.json().catch(() => ({}));
    return {
      ...limitData,
      payment_required: true,
      error: limitData.error || 'SandboxRateLimitExceeded',
      message: limitData.message || 'Your daily free sandbox quota (3 requests/day) has been reached. Continue immediately with x402 USDC payment, a prepaid Session Pass (USDC or Pix/Card), or RapidAPI:',
      checkout_options: CHECKOUT_OPTIONS,
    };
  }

  // 2. Se retornou 402 Payment Required
  if (response.status === 402) {
    const challenge = await response.json();

    // Se temos uma chave privada configurada (EVM ou Solana), tenta pagamento Multi-Chain automático
    if (privateKey) {
      try {
        const paymentResult = await settleX402Payment(challenge, privateKey, preferredRpc, {
          network: paymentNetwork,
          gasless: options.gasless,
        });

        // Refaz a requisição com o comprovante Multi-Chain (Base, Polygon, Arbitrum ou Solana)
        const proofValue = paymentResult.paymentHeader || paymentResult.txHash;
        if (paymentResult.mode === 'x402_gasless') {
          headers['PAYMENT-SIGNATURE'] = proofValue;
        }
        headers['X-Payment'] = proofValue;
        if (paymentResult.network && paymentResult.network !== 'base') {
          headers['X-Payment-Network'] = paymentResult.network;
        }
        const retryResponse = await fetch(url, { headers });

        if (retryResponse.status === 200) {
          const data = await retryResponse.json();
          return {
            ...data,
            _x402_settlement: {
              status: paymentResult.mode === 'x402_gasless' ? 'SETTLED_VIA_FACILITATOR' : 'CONFIRMED_ON_CHAIN',
              network: paymentResult.networkName || 'Base',
              tx_hash: paymentResult.txHash,
              block_number: paymentResult.blockNumber,
              amount_usdc: paymentResult.amountUsdc,
            },
          };
        }

        // Nunca mascarar erro pós-pagamento com mensagem genérica de 402
        const retryRawBody = await retryResponse.text().catch(() => '');
        let retryParsedBody = retryRawBody;
        try {
          retryParsedBody = retryRawBody ? JSON.parse(retryRawBody) : null;
        } catch {
          // mantém texto bruto
        }
        return {
          payment_required: retryResponse.status === 402,
          payment_attempted: true,
          error: `Post-payment request failed with HTTP ${retryResponse.status}`,
          http_status: retryResponse.status,
          tx_hash: paymentResult.txHash,
          network: paymentResult.networkName || paymentResult.network || 'Base',
          settlement_mode: paymentResult.mode,
          amount_usdc: paymentResult.amountUsdc,
          server_response: retryParsedBody,
        };
      } catch (payError) {
        return {
          payment_required: true,
          error: `Falha na liquidação automática do pagamento x402: ${payError.message}`,
          challenge,
        };
      }
    }

    // Se não há chave privada ou session pass configurados, retorna o desafio de pagamento estruturado com checkout Pix, Cartão RapidAPI e Web3
    return {
      payment_required: true,
      message: 'Esta consulta requer micropagamento em USDC Multi-Chain (Base, Polygon, Arbitrum ou Solana), Session Pass pré-pago (Pix/Cartão) ou assinatura RapidAPI.',
      challenge,
      checkout_options: CHECKOUT_OPTIONS,
      instructions: 'Opções de acesso imediato: (1) Pix/Cartão em 10s (R$ 12 = 400 créditos): https://grid402.online/pass?tier=starter&autopix=1 e defina GRID402_SESSION_PASS=pass_g402_...; (2) Cartão Internacional em USD ($9.90/mês) no RapidAPI Hub: https://rapidapi.com/oliveiratc/api/grid402-open-data-kyb-ai-utilities; (3) Auto-liquidação x402 Multi-Chain: defina GRID402_PRIVATE_KEY (Base/Polygon/Arbitrum) ou GRID402_SOLANA_PRIVATE_KEY (Solana SPL USDC); (4) Teste grátis (3/dia): defina GRID402_SANDBOX=true.',
    };
  }

  const errText = await response.text();
  try {
    return JSON.parse(errText);
  } catch {
    throw new Error(`Erro na API Grid402 (HTTP ${response.status}): ${errText}`);
  }
}
