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
    description: 'Consulta o resumo consolidado de saúde e disponibilidade dos 25 micro-serviços do Grid402 (gratuito, sem cobrança x402).',
    inputSchema: {
      type: 'object',
      properties: {},
      required: [],
    },
  },
  {
    name: 'grid402_forex',
    description: 'Consulta cotações fiduciárias oficiais em tempo real de USD, EUR e GBP para BRL com variação percentual diária (0.0050 USDC / 1 crédito).',
    inputSchema: {
      type: 'object',
      properties: {
        payment_tx_hash: {
          type: 'string',
          description: 'Comprovante opcional de pagamento Multi-Chain USDC (Base, Polygon, Arbitrum ou Solana: 0x..., polygon:0x..., arbitrum:0x..., solana:<base58>, payload x402 base64 ou token pass_g402_...).',
        },
      },
      required: [],
    },
  },
  {
    name: 'grid402_crypto',
    description: 'Consulta preços de mercado spot em tempo real de BTC, ETH, SOL, USDC e BASE em USD e BRL (0.0050 USDC / 1 crédito).',
    inputSchema: {
      type: 'object',
      properties: {
        payment_tx_hash: {
          type: 'string',
          description: 'Comprovante opcional de pagamento Multi-Chain USDC (Base, Polygon, Arbitrum ou Solana: 0x..., polygon:0x..., arbitrum:0x..., solana:<base58>, payload x402 base64 ou token pass_g402_...).',
        },
      },
      required: [],
    },
  },
  {
    name: 'grid402_dns',
    description: 'Executa resolução DNS autoritativa de registros A, AAAA, MX, TXT, NS e CNAME para qualquer domínio da internet (0.0050 USDC / 1 crédito).',
    inputSchema: {
      type: 'object',
      properties: {
        domain: {
          type: 'string',
          description: 'Nome do domínio a ser consultado (ex: "github.com", "base.org").',
        },
        payment_tx_hash: {
          type: 'string',
          description: 'Comprovante opcional de pagamento Multi-Chain USDC (Base, Polygon, Arbitrum ou Solana: 0x..., polygon:0x..., arbitrum:0x..., solana:<base58>, payload x402 base64 ou token pass_g402_...).',
        },
      },
      required: ['domain'],
    },
  },
  {
    name: 'grid402_ssl',
    description: 'Inspeciona e valida o certificado TLS/SSL na porta 443 de um host (emissor, validade, SANs e contagem de dias restantes até expirar) (0.0050 USDC / 1 crédito).',
    inputSchema: {
      type: 'object',
      properties: {
        host: {
          type: 'string',
          description: 'Hostname ou domínio alvo para inspeção TLS (ex: "base.org", "google.com").',
        },
        payment_tx_hash: {
          type: 'string',
          description: 'Comprovante opcional de pagamento Multi-Chain USDC (Base, Polygon, Arbitrum ou Solana: 0x..., polygon:0x..., arbitrum:0x..., solana:<base58>, payload x402 base64 ou token pass_g402_...).',
        },
      },
      required: ['host'],
    },
  },
  {
    name: 'grid402_asn',
    description: 'Consulta inteligência de rede BGP, identificando número de ASN, bloco IP alocado, provedor/organização e país de origem (0.0050 USDC / 1 crédito).',
    inputSchema: {
      type: 'object',
      properties: {
        ip: {
          type: 'string',
          description: 'Endereço IPv4 ou IPv6 a inspecionar (ex: "1.1.1.1"). Se omitido, consulta o IP público do requisitante.',
        },
        payment_tx_hash: {
          type: 'string',
          description: 'Comprovante opcional de pagamento Multi-Chain USDC (Base, Polygon, Arbitrum ou Solana: 0x..., polygon:0x..., arbitrum:0x..., solana:<base58>, payload x402 base64 ou token pass_g402_...).',
        },
      },
      required: [],
    },
  },
  {
    name: 'grid402_domain_audit',
    description: 'Auditoria completa de infraestrutura de domínio (DNS + TLS/SSL + BGP/ASN) em chamada única consolidada (0.0150 USDC / 3 créditos).',
    inputSchema: {
      type: 'object',
      properties: {
        domain: {
          type: 'string',
          description: 'Domínio alvo para auditoria completa (ex: "base.org").',
        },
        payment_tx_hash: {
          type: 'string',
          description: 'Comprovante opcional de pagamento Multi-Chain USDC (0.0150 USDC em Base, Polygon, Arbitrum ou Solana) ou token pass_g402_...',
        },
      },
      required: ['domain'],
    },
  },
  {
    name: 'grid402_kyb_audit',
    description: 'Auditoria corporativa global KYB em chamada única: Receita Federal CNPJ + Sanções OFAC/ONU + Identidade Global GLEIF LEI (0.0200 USDC / 4 créditos).',
    inputSchema: {
      type: 'object',
      properties: {
        cnpj: {
          type: 'string',
          description: 'CNPJ de 14 dígitos da empresa (ex: "00000000000191").',
        },
        payment_tx_hash: {
          type: 'string',
          description: 'Comprovante opcional de pagamento Multi-Chain USDC (0.0200 USDC em Base, Polygon, Arbitrum ou Solana) ou token pass_g402_...',
        },
      },
      required: ['cnpj'],
    },
  },
  {
    name: 'grid402_get_dossier_360',
    description: 'Dossiê 360º KYB Premium com Veredito Sintetizado para IA (APPROVED, FLAGGED, REJECTED), score 0-100, Receita Federal (CNPJ), Simples/MEI e Sanções CGU (CEIS/CNEP) em chamada única (0.0500 USDC / 10 créditos).',
    inputSchema: {
      type: 'object',
      properties: {
        cnpj: {
          type: 'string',
          description: 'CNPJ de 14 dígitos da empresa brasileira (com ou sem pontuação, ex: "00000000000191").',
        },
        payment_tx_hash: {
          type: 'string',
          description: 'Comprovante opcional de pagamento Multi-Chain USDC (0.0500 USDC em Base, Polygon, Arbitrum ou Solana) ou token pass_g402_...',
        },
      },
      required: ['cnpj'],
    },
  },
  {
    name: 'grid402_market_snapshot',
    description: 'Panorama financeiro e macroeconômico consolidado em chamada única: Câmbio + Cripto + Selic/CDI/IPCA + Calendário ANBIMA (0.0150 USDC / 3 créditos).',
    inputSchema: {
      type: 'object',
      properties: {
        payment_tx_hash: {
          type: 'string',
          description: 'Comprovante opcional de pagamento Multi-Chain USDC (0.0150 USDC em Base, Polygon, Arbitrum ou Solana) ou token pass_g402_...',
        },
      },
      required: [],
    },
  },
  {
    name: 'grid402_cep',
    description: 'Consulta a base pública de endereçamento postal brasileiro retornando logradouro, bairro, município, UF e código IBGE oficial (0.0050 USDC / 1 crédito).',
    inputSchema: {
      type: 'object',
      properties: {
        code: {
          type: 'string',
          description: 'Código de CEP brasileiro com 8 dígitos numéricos (ex: "01310100" para Av. Paulista).',
        },
        payment_tx_hash: {
          type: 'string',
          description: 'Comprovante opcional de pagamento Multi-Chain USDC (Base, Polygon, Arbitrum ou Solana: 0x..., polygon:0x..., arbitrum:0x..., solana:<base58>, payload x402 base64 ou token pass_g402_...).',
        },
      },
      required: ['code'],
    },
  },
  {
    name: 'grid402_cnpj',
    description: 'Consulta dados cadastrais públicos de Pessoa Jurídica da Receita Federal (razão social, nome fantasia, situação cadastral, CNAE, endereço e capital social). Requer 0.0200 USDC (4 créditos).',
    inputSchema: {
      type: 'object',
      properties: {
        cnpj: {
          type: 'string',
          description: 'Número de CNPJ com 14 dígitos com ou sem formatação (ex: "00.000.000/0001-91" ou "00000000000191").',
        },
        payment_tx_hash: {
          type: 'string',
          description: 'Comprovante opcional de pagamento Multi-Chain USDC (0.0200 USDC em Base, Polygon, Arbitrum ou Solana) ou token pass_g402_...',
        },
      },
      required: ['cnpj'],
    },
  },
  {
    name: 'grid402_indicators',
    description: 'Consulta taxas oficiais Selic meta e diária, CDI anual e diário, IPCA acumulado 12m e mensal do Banco Central e IBGE (0.0050 USDC / 1 crédito).',
    inputSchema: {
      type: 'object',
      properties: {
        payment_tx_hash: {
          type: 'string',
          description: 'Comprovante opcional de pagamento Multi-Chain USDC (Base, Polygon, Arbitrum ou Solana: 0x..., polygon:0x..., arbitrum:0x..., solana:<base58>, payload x402 base64 ou token pass_g402_...).',
        },
      },
      required: [],
    },
  },
  {
    name: 'grid402_holidays',
    description: 'Consulta o calendário oficial de feriados nacionais e dias sem expediente bancário (ANBIMA e Febraban) com cálculo de feriados móveis (0.0050 USDC / 1 crédito).',
    inputSchema: {
      type: 'object',
      properties: {
        year: {
          type: 'integer',
          description: 'Ano calendário de 4 dígitos entre 1900 e 2100 (ex: 2026). Se omitido, consulta o ano corrente.',
        },
        payment_tx_hash: {
          type: 'string',
          description: 'Comprovante opcional de pagamento Multi-Chain USDC (Base, Polygon, Arbitrum ou Solana: 0x..., polygon:0x..., arbitrum:0x..., solana:<base58>, payload x402 base64 ou token pass_g402_...).',
        },
      },
      required: [],
    },
  },
  {
    name: 'grid402_workdays',
    description: 'Calcula dias úteis financeiros e projeção de vencimentos na convenção ANBIMA (D+0 exclusivo no início e inclusivo no fim) ou verifica se uma data é dia útil bancário (0.0050 USDC / 1 crédito).',
    inputSchema: {
      type: 'object',
      properties: {
        start_date: {
          type: 'string',
          description: 'Data de início no formato AAAA-MM-DD para contagem de intervalo ou projeção de prazo.',
        },
        end_date: {
          type: 'string',
          description: 'Data de término no formato AAAA-MM-DD para contagem de intervalo de dias úteis.',
        },
        days: {
          type: 'integer',
          description: 'Quantidade inteira de dias úteis para projeção de vencimento (positivo para futuro, negativo para passado).',
        },
        date: {
          type: 'string',
          description: 'Data pontual no formato AAAA-MM-DD para verificação de dia útil.',
        },
        payment_tx_hash: {
          type: 'string',
          description: 'Comprovante opcional de pagamento Multi-Chain USDC (0.0050 USDC em Base, Polygon, Arbitrum ou Solana) ou token pass_g402_...',
        },
      },
      required: [],
    },
  },
  {
    name: 'grid402_sanctions',
    description: 'Consulta o Cadastro de Empresas Inidoneas e Suspensas (CEIS) e Cadastro Nacional de Empresas Punidas (CNEP) da CGU para um CNPJ (0.0100 USDC / 2 créditos).',
    inputSchema: {
      type: 'object',
      properties: {
        cnpj: {
          type: 'string',
          description: 'CNPJ da Pessoa Juridica com 14 digitos para checagem de sancoes.',
        },
      },
      required: ['cnpj'],
    },
  },
  {
    name: 'grid402_simples',
    description: 'Consulta o enquadramento de um CNPJ no regime tributario do Simples Nacional e se e Microempreendedor Individual (MEI) (0.0100 USDC / 2 créditos).',
    inputSchema: {
      type: 'object',
      properties: {
        cnpj: {
          type: 'string',
          description: 'CNPJ da Pessoa Juridica com 14 digitos para checagem tributaria.',
        },
      },
      required: ['cnpj'],
    },
  },
  {
    name: 'grid402_compliance',
    description: 'Calcula o Compliance Score 360 consolidado (0 a 100) e status de aprovacao de um CNPJ cruzando Receita Federal, CGU e historico (0.0300 USDC / 6 créditos).',
    inputSchema: {
      type: 'object',
      properties: {
        cnpj: {
          type: 'string',
          description: 'CNPJ da Pessoa Juridica com 14 digitos para score de integridade.',
        },
      },
      required: ['cnpj'],
    },
  },
  {
    name: 'grid402_lei',
    description: 'Consulta identidade corporativa internacional oficial (ISO 17442) em mais de 200 jurisdições via GLEIF (0.0200 USDC / 4 créditos).',
    inputSchema: {
      type: 'object',
      properties: {
        lei: {
          type: 'string',
          description: 'Código LEI de 20 caracteres (ex: "5493006MHB84DD0ZWV18") ou razão social.',
        },
        payment_tx_hash: {
          type: 'string',
          description: 'Comprovante opcional de pagamento Multi-Chain USDC (0.0200 USDC em Base, Polygon, Arbitrum ou Solana) ou token pass_g402_...',
        },
      },
      required: ['lei'],
    },
  },
  {
    name: 'grid402_ofac',
    description: 'Triagem de sanções globais (OFAC SDN / ONU) e risco AML Web3 na Base Mainnet para carteiras 0x, códigos LEI ou entidades (0.0200 USDC / 4 créditos).',
    inputSchema: {
      type: 'object',
      properties: {
        target: {
          type: 'string',
          description: 'Carteira EVM 0x, código LEI ou razão social para triagem OFAC/AML.',
        },
        payment_tx_hash: {
          type: 'string',
          description: 'Comprovante opcional de pagamento Multi-Chain USDC (0.0200 USDC em Base, Polygon, Arbitrum ou Solana) ou token pass_g402_...',
        },
      },
      required: ['target'],
    },
  },
  {
    name: 'grid402_notarize',
    description: 'Carimbo do tempo criptográfico M2M e prova de existência SHA-256 assinada com ECDSA secp256k1 (0.0050 USDC / 1 crédito).',
    inputSchema: {
      type: 'object',
      properties: {
        content: {
          type: 'string',
          description: 'Conteúdo textual ou hash SHA-256 hex de 64 caracteres para notarizar.',
        },
        payment_tx_hash: {
          type: 'string',
          description: 'Comprovante opcional de pagamento Multi-Chain USDC (Base, Polygon, Arbitrum ou Solana: 0x..., polygon:0x..., arbitrum:0x..., solana:<base58>, payload x402 base64 ou token pass_g402_...).',
        },
      },
      required: ['content'],
    },
  },
  {
    name: 'grid402_global_markets',
    description: 'Calendário de liquidação de bolsas globais (US NYSE/Fed, EU TARGET2, UK LSE, BR B3) e mais de 30 taxas de câmbio de referência do BCE (0.0050 USDC / 1 crédito).',
    inputSchema: {
      type: 'object',
      properties: {
        market: {
          type: 'string',
          description: 'Código do mercado: US, EU, UK ou BR (padrão: US).',
        },
        base: {
          type: 'string',
          description: 'Moeda base para taxas do BCE (padrão: USD).',
        },
        date: {
          type: 'string',
          description: 'Data opcional no formato AAAA-MM-DD.',
        },
        payment_tx_hash: {
          type: 'string',
          description: 'Comprovante opcional de pagamento Multi-Chain USDC (Base, Polygon, Arbitrum ou Solana: 0x..., polygon:0x..., arbitrum:0x..., solana:<base58>, payload x402 base64 ou token pass_g402_...).',
        },
      },
      required: [],
    },
  },
  {
    name: 'grid402_extract',
    description: 'Extrai páginas web públicas HTTP/HTTPS com proteção SSRF e devolve Markdown limpo pronto para LLMs e RAG, incluindo título, descrição e links absolutos (0.0050 USDC / 1 crédito).',
    inputSchema: {
      type: 'object',
      properties: {
        url: {
          type: 'string',
          description: 'URL pública HTTP ou HTTPS a ser extraída para Markdown limpo.',
        },
        max_chars: {
          type: 'integer',
          description: 'Limite opcional de caracteres no Markdown retornado (500 a 25000; padrão: 12000).',
        },
        payment_tx_hash: {
          type: 'string',
          description: 'Hash opcional de transação USDC ou token pass_g402_...',
        },
      },
      required: ['url'],
    },
  },
  {
    name: 'grid402_email_verify',
    description: 'Audita entregabilidade de e-mail B2B estritamente Zero-PII: sintaxe RFC, DNS MX, SPF, DMARC, detecção de domínio descartável (disposable), provedor gratuito e role-based (0.0050 USDC / 1 crédito).',
    inputSchema: {
      type: 'object',
      properties: {
        email: {
          type: 'string',
          description: 'Endereço de e-mail corporativo a auditar sem armazenamento em disco.',
        },
        payment_tx_hash: {
          type: 'string',
          description: 'Hash opcional de transação USDC ou token pass_g402_...',
        },
      },
      required: ['email'],
    },
  },
  {
    name: 'grid402_pass',
    description: 'Emite um Session Pass pré-pago x402 (Starter 2 USDC = 400 créditos, Pro 5 USDC = 1.000 créditos, Fleet 20 USDC = 6.000 créditos; 1 crédito = 0.0050 USDC) ou consulta status via ?status=pass_g402_...',
    inputSchema: {
      type: 'object',
      properties: {
        status: {
          type: 'string',
          description: 'Token pass_g402_... opcional para consulta gratuita de saldo.',
        },
        payment_tx_hash: {
          type: 'string',
          description: 'Comprovante opcional de pagamento Multi-Chain USDC (Base, Polygon, Arbitrum, Optimism, Ethereum ou Solana) para emissão de novo passe.',
        },
      },
      required: [],
    },
  },
  {
    name: 'grid402_pass_balance',
    description: 'Consulta gratuita em tempo real do saldo de créditos, validade e Spend Controls (daily_credit_limit, credits_used_today, allowed_endpoints) de um Session Pass.',
    inputSchema: {
      type: 'object',
      properties: {
        token: {
          type: 'string',
          description: 'Token pass_g402_... a consultar (ou utiliza GRID402_SESSION_PASS do ambiente).',
        },
      },
      required: [],
    },
  },
  {
    name: 'grid402_verify',
    description: 'Valida a integridade matematica da prova criptografica de oraculo (_oracle_proof) secp256k1 em uma resposta do Grid402 (gratuito).',
    inputSchema: {
      type: 'object',
      properties: {
        payload: {
          type: 'string',
          description: 'Payload JSON serializado da resposta da API contendo a chave _oracle_proof.',
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
    starter_400_credits: 'https://grid402.online/pass?tier=starter&autopix=1 (R$ 12,00 via Pix = 400 créditos)',
    pro_1000_credits: 'https://grid402.online/pass?tier=pro&autopix=1 (R$ 28,00 via Pix = 1.000 créditos)',
    fleet_6000_credits: 'https://grid402.online/pass?tier=fleet&autopix=1 (R$ 110,00 via Pix = 6.000 créditos)',
    how_to_use: 'Defina GRID402_SESSION_PASS=pass_g402_... no ambiente do MCP para chamadas em <15ms sem chave privada.',
  },
  rapidapi_credit_card_usd: {
    plans: 'PRO $9.90/mês (2.500 chamadas) | ULTRA $29.90/mês (10.000 chamadas) | MEGA $79.90/mês (50.000 chamadas)',
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
  };

  if (params.idempotency_key) {
    headers['Idempotency-Key'] = String(params.idempotency_key);
  }

  const isExplicitlyNotSandbox = process.env.GRID402_SANDBOX === 'false' || process.env.GRID402_SANDBOX === '0';
  const isExplicitSandbox = process.env.GRID402_SANDBOX === 'true' || process.env.GRID402_SANDBOX === '1';
  const useAutoTrial = Boolean(
    options.autoTrial &&
    !params.payment_tx_hash &&
    !process.env.GRID402_SESSION_PASS &&
    !privateKey &&
    !isExplicitlyNotSandbox
  );

  if (params.payment_tx_hash) {
    headers['X-Payment'] = params.payment_tx_hash;
  } else if (process.env.GRID402_SESSION_PASS) {
    headers['X-Payment'] = process.env.GRID402_SESSION_PASS.trim();
  } else if (isExplicitSandbox || useAutoTrial) {
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
      message: limitData.message || 'Suas 3 consultas gratuitas do dia foram utilizadas. Escolha uma opção abaixo para continuar imediatamente sem limite de taxa:',
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
        headers['X-Payment'] = paymentResult.paymentHeader || paymentResult.txHash;
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
