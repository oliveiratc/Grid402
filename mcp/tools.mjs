/**
 * Grid402 MCP Server - Catálogo de Ferramentas e Despachador HTTP
 */

import { settleX402Payment } from './x402_payer.mjs';

export const DEFAULT_ENDPOINT = process.env.GRID402_ENDPOINT || process.env.GRID402_API_URL || 'https://orangered-jackal-900744.hostingersite.com';

/**
 * Definições formais das 11 ferramentas no padrão MCP
 */
export const MCP_TOOLS_DEFINITIONS = [
  {
    name: 'grid402_summary',
    description: 'Consulta o resumo consolidado de saúde e disponibilidade dos 6 micro-serviços do Grid402 (gratuito, sem cobrança x402).',
    inputSchema: {
      type: 'object',
      properties: {},
      required: [],
    },
  },
  {
    name: 'grid402_forex',
    description: 'Consulta cotações fiduciárias oficiais em tempo real de USD, EUR e GBP para BRL com variação percentual diária.',
    inputSchema: {
      type: 'object',
      properties: {
        payment_tx_hash: {
          type: 'string',
          description: 'Hash opcional de transação na Base Mainnet comprovando o pagamento de 0.0005 USDC caso não utilize liquidação automática.',
        },
      },
      required: [],
    },
  },
  {
    name: 'grid402_crypto',
    description: 'Consulta preços de mercado spot em tempo real de BTC, ETH, SOL, USDC e BASE em USD e BRL.',
    inputSchema: {
      type: 'object',
      properties: {
        payment_tx_hash: {
          type: 'string',
          description: 'Hash opcional de transação na Base Mainnet com 0.0005 USDC.',
        },
      },
      required: [],
    },
  },
  {
    name: 'grid402_dns',
    description: 'Executa resolução DNS autoritativa de registros A, AAAA, MX, TXT, NS e CNAME para qualquer domínio da internet.',
    inputSchema: {
      type: 'object',
      properties: {
        domain: {
          type: 'string',
          description: 'Nome do domínio a ser consultado (ex: "github.com", "base.org").',
        },
        payment_tx_hash: {
          type: 'string',
          description: 'Hash opcional de transação na Base Mainnet com 0.0005 USDC.',
        },
      },
      required: ['domain'],
    },
  },
  {
    name: 'grid402_ssl',
    description: 'Inspeciona e valida o certificado TLS/SSL na porta 443 de um host (emissor, validade, SANs e contagem de dias restantes até expirar).',
    inputSchema: {
      type: 'object',
      properties: {
        host: {
          type: 'string',
          description: 'Hostname ou domínio alvo para inspeção TLS (ex: "base.org", "google.com").',
        },
        payment_tx_hash: {
          type: 'string',
          description: 'Hash opcional de transação na Base Mainnet com 0.0005 USDC.',
        },
      },
      required: ['host'],
    },
  },
  {
    name: 'grid402_asn',
    description: 'Consulta inteligência de rede BGP, identificando número de ASN, bloco IP alocado, provedor/organização e país de origem.',
    inputSchema: {
      type: 'object',
      properties: {
        ip: {
          type: 'string',
          description: 'Endereço IPv4 ou IPv6 a inspecionar (ex: "1.1.1.1"). Se omitido, consulta o IP público do requisitante.',
        },
        payment_tx_hash: {
          type: 'string',
          description: 'Hash opcional de transação na Base Mainnet com 0.0005 USDC.',
        },
      },
      required: [],
    },
  },
  {
    name: 'grid402_cep',
    description: 'Consulta a base pública de endereçamento postal brasileiro retornando logradouro, bairro, município, UF e código IBGE oficial.',
    inputSchema: {
      type: 'object',
      properties: {
        code: {
          type: 'string',
          description: 'Código de CEP brasileiro com 8 dígitos numéricos (ex: "01310100" para Av. Paulista).',
        },
        payment_tx_hash: {
          type: 'string',
          description: 'Hash opcional de transação na Base Mainnet com 0.0005 USDC.',
        },
      },
      required: ['code'],
    },
  },
  {
    name: 'grid402_cnpj',
    description: 'Consulta dados cadastrais públicos de Pessoa Jurídica da Receita Federal (razão social, nome fantasia, situação cadastral, CNAE, endereço e capital social). Requer 0.0020 USDC.',
    inputSchema: {
      type: 'object',
      properties: {
        cnpj: {
          type: 'string',
          description: 'Número de CNPJ com 14 dígitos com ou sem formatação (ex: "00.000.000/0001-91" ou "00000000000191").',
        },
        payment_tx_hash: {
          type: 'string',
          description: 'Hash opcional de transação na Base Mainnet com 0.0020 USDC.',
        },
      },
      required: ['cnpj'],
    },
  },
  {
    name: 'grid402_indicators',
    description: 'Consulta taxas oficiais Selic meta e diária, CDI anual e diário, IPCA acumulado 12m e mensal do Banco Central e IBGE.',
    inputSchema: {
      type: 'object',
      properties: {
        payment_tx_hash: {
          type: 'string',
          description: 'Hash opcional de transação na Base Mainnet comprovando o pagamento de 0.0005 USDC caso não utilize liquidação automática.',
        },
      },
      required: [],
    },
  },
  {
    name: 'grid402_holidays',
    description: 'Consulta o calendário oficial de feriados nacionais e dias sem expediente bancário (ANBIMA e Febraban) com cálculo de feriados móveis.',
    inputSchema: {
      type: 'object',
      properties: {
        year: {
          type: 'integer',
          description: 'Ano calendário de 4 dígitos entre 1900 e 2100 (ex: 2026). Se omitido, consulta o ano corrente.',
        },
        payment_tx_hash: {
          type: 'string',
          description: 'Hash opcional de transação na Base Mainnet comprovando o pagamento de 0.0005 USDC caso não utilize liquidação automática.',
        },
      },
      required: [],
    },
  },
  {
    name: 'grid402_workdays',
    description: 'Calcula dias úteis financeiros e projeção de vencimentos na convenção ANBIMA (D+0 exclusivo no início e inclusivo no fim) ou verifica se uma data é dia útil bancário.',
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
          description: 'Hash opcional de transacao na Base Mainnet comprovando o pagamento de 0.0005 USDC caso nao utilize liquidacao automatica.',
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
export async function executeGrid402Request(service, params = {}, options = {}) {
  const endpoint = (options.endpoint || DEFAULT_ENDPOINT).replace(/\/$/, '');
  const privateKey = options.privateKey || process.env.GRID402_PRIVATE_KEY || null;
  const preferredRpc = options.rpcUrl || process.env.GRID402_BASE_RPC_URL || null;

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
      if (params.days !== undefined && params.days !== null) queryParams.set('days', params.days);
      if (params.date) queryParams.set('date', params.date);
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
        'User-Agent': 'Grid402-MCP-Server/1.6',
      },
      body: postPayload,
    });
    return await verifyRes.json();
  }

  const queryString = queryParams.toString();
  const url = `${endpoint}${path}${queryString ? '?' + queryString : ''}`;

  const headers = {
    'Accept': 'application/json',
    'User-Agent': 'Grid402-MCP-Server/1.6',
  };

  if (params.payment_tx_hash) {
    headers['X-Payment'] = params.payment_tx_hash;
  } else if (process.env.GRID402_SANDBOX === 'true' || process.env.GRID402_SANDBOX === '1') {
    headers['X-Payment'] = 'demo';
  }

  // 1. Primeira tentativa de requisição
  const response = await fetch(url, { headers });

  if (response.status === 200) {
    return await response.json();
  }

  // 2. Se retornou 402 Payment Required
  if (response.status === 402) {
    const challenge = await response.json();

    // Se temos uma chave privada configurada, tenta pagamento on-chain automático
    if (privateKey) {
      try {
        const paymentResult = await settleX402Payment(challenge, privateKey, preferredRpc);

        // Refaz a requisição com o hash da transação confirmada na Base
        headers['X-Payment'] = paymentResult.txHash;
        const retryResponse = await fetch(url, { headers });

        if (retryResponse.status === 200) {
          const data = await retryResponse.json();
          return {
            ...data,
            _x402_settlement: {
              status: 'CONFIRMED_ON_CHAIN',
              network: 'Base',
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

    // Se não há chave privada configurada, retorna o desafio de pagamento estruturado
    return {
      payment_required: true,
      message: 'Esta consulta requer micropagamento de 0.0005 USDC na rede Base via protocolo x402.',
      challenge,
      instructions: 'Para liquidação automática, defina a variável de ambiente GRID402_PRIVATE_KEY no seu arquivo de configuração MCP.',
    };
  }

  const errText = await response.text();
  try {
    return JSON.parse(errText);
  } catch {
    throw new Error(`Erro na API Grid402 (HTTP ${response.status}): ${errText}`);
  }
}

