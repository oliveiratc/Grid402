#!/usr/bin/env node

/**
 * Grid402 Official Model Context Protocol (MCP) Server
 * Exposes 24 tools for real-time open data and M2M network utilities with Base x402 settlement.
 */

import { Server } from '@modelcontextprotocol/sdk/server/index.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import { CallToolRequestSchema, ListToolsRequestSchema } from '@modelcontextprotocol/sdk/types.js';
import { MCP_TOOLS_DEFINITIONS, executeGrid402Request, DEFAULT_ENDPOINT } from './tools.mjs';

export const GRID402_MCP_INSTRUCTIONS = [
  'Grid402 is a verifiable public-data and network utility M2M oracle on Base Mainnet (Chain ID 8453) settled via HTTP x402 (USDC).',
  'Tool selection: use free tools first (`grid402_summary` for 22-service health, `grid402_pass_balance` for prepaid pass quota/spend-controls, `grid402_verify` for offline-equivalent ECDSA secp256k1 proof verification). Prefer composite bundles (`grid402_domain_audit`, `grid402_kyb_audit`, `grid402_market_snapshot`) over multiple separate calls to reduce cost and latency.',
  'Authentication & Payment modes: (1) Set `GRID402_SANDBOX=true` for free sandbox testing via `X-Payment: demo` (5 queries/hour per IP); (2) Set `GRID402_SESSION_PASS=pass_g402_...` for <15ms prepaid calls (`1 credit = 0.0050 USDC`, zero per-call gas); (3) Set `GRID402_PRIVATE_KEY` using a dedicated low-balance operational Base wallet for automatic on-chain settlement.',
  'Spend controls & error handling: paid tools return `_oracle_proof` (ECDSA secp256k1) and `_meta` (upstream source and TTL). When HTTP 402 (`payment_required: true`) is returned without credentials, present the fee (`0.0050`–`0.0300` USDC) and free `preview` teaser instead of retrying blindly. Respect HTTP 429 (`PassDailyLimitExceeded`, `SandboxRateLimitExceeded`) and HTTP 403 (`PassEndpointNotAllowed`).',
].join(' ');

const server = new Server(
  {
    name: 'grid402-mcp-server',
    version: '1.8.2',
  },
  {
    capabilities: {
      tools: {},
    },
    instructions: GRID402_MCP_INSTRUCTIONS,
  }
);

// 1. Registro do catálogo de ferramentas
server.setRequestHandler(ListToolsRequestSchema, async () => {
  return {
    tools: MCP_TOOLS_DEFINITIONS,
  };
});

// 2. Registro do despachante de chamadas de ferramentas
server.setRequestHandler(CallToolRequestSchema, async (request) => {
  const toolName = request.params.name;
  const args = request.params.arguments || {};

  try {
    let service = '';
    switch (toolName) {
      case 'grid402_summary':
        service = 'summary';
        break;
      case 'grid402_forex':
        service = 'forex';
        break;
      case 'grid402_crypto':
        service = 'crypto';
        break;
      case 'grid402_dns':
        service = 'dns';
        break;
      case 'grid402_ssl':
        service = 'ssl';
        break;
      case 'grid402_asn':
        service = 'asn';
        break;
      case 'grid402_cep':
        service = 'cep';
        break;
      case 'grid402_cnpj':
        service = 'cnpj';
        break;
      case 'grid402_indicators':
        service = 'indicators';
        break;
      case 'grid402_holidays':
        service = 'holidays';
        break;
      case 'grid402_workdays':
        service = 'workdays';
        break;
      case 'grid402_sanctions':
        service = 'sanctions';
        break;
      case 'grid402_simples':
        service = 'simples';
        break;
      case 'grid402_compliance':
        service = 'compliance';
        break;
      case 'grid402_domain_audit':
        service = 'domain-audit';
        break;
      case 'grid402_kyb_audit':
        service = 'kyb-audit';
        break;
      case 'grid402_get_dossier_360':
        service = 'dossier';
        break;
      case 'grid402_market_snapshot':
        service = 'market-snapshot';
        break;
      case 'grid402_lei':
        service = 'lei';
        break;
      case 'grid402_ofac':
        service = 'ofac';
        break;
      case 'grid402_notarize':
        service = 'notarize';
        break;
      case 'grid402_global_markets':
        service = 'global-markets';
        break;
      case 'grid402_pass':
        service = 'pass';
        break;
      case 'grid402_pass_balance':
        service = 'pass-balance';
        break;
      case 'grid402_verify':
        service = 'verify';
        break;
      default:
        throw new Error(`Ferramenta MCP desconhecida: ${toolName}`);
    }

    const result = await executeGrid402Request(service, args);

    return {
      content: [
        {
          type: 'text',
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  } catch (error) {
    return {
      content: [
        {
          type: 'text',
          text: JSON.stringify(
            {
              error: 'ToolExecutionError',
              tool: toolName,
              message: error.message,
            },
            null,
            2
          ),
        },
      ],
      isError: true,
    };
  }
});

// 3. Inicialização do transporte Stdio
async function main() {
  const transport = new StdioServerTransport();
  await server.connect(transport);
  process.stderr.write(`[Grid402 MCP] Servidor iniciado via stdio. Endpoint: ${DEFAULT_ENDPOINT}\n`);
}

main().catch((err) => {
  process.stderr.write(`[Grid402 MCP] Erro fatal na inicialização: ${err.message}\n`);
  process.exit(1);
});

