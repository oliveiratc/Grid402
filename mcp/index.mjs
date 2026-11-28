#!/usr/bin/env node

/**
 * Grid402 Official Model Context Protocol (MCP) Server
 * Exposes 33 tools for real-time open data, SEC EDGAR, vulnerability audits, chain telemetry, token security, prediction markets and batch orchestration with Multi-Chain x402 USDC settlement.
 */

import { Server } from '@modelcontextprotocol/sdk/server/index.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import { CallToolRequestSchema, ListToolsRequestSchema } from '@modelcontextprotocol/sdk/types.js';
import { MCP_TOOLS_DEFINITIONS, executeGrid402Request, DEFAULT_ENDPOINT } from './tools.mjs';

export const GRID402_MCP_INSTRUCTIONS = [
  'Grid402 is a verifiable public-data and network utility M2M oracle settled via HTTP x402 (gasless PayAI exact on Base, Polygon, Arbitrum, and Solana; plus Optimism and Ethereum Session Pass top-ups).',
  'Tool selection: use free tools first (`grid402_summary` for 31-service health, `grid402_pass_balance` for prepaid pass quota/spend-controls, `grid402_verify` for offline-equivalent ECDSA secp256k1 proof verification). Prefer composite bundles (`grid402_batch` to bundle 1-5 calls in one HTTP request with partial-refund protection, `grid402_domain_audit`, `grid402_kyb_audit`, `grid402_get_dossier_360`, `grid402_market_snapshot`) over multiple separate calls to reduce cost and latency, and use `grid402_sec_company` (SEC EDGAR filings), `grid402_vuln_audit` (OSV + CISA KEV), `grid402_chain_telemetry` (RPC + DeFiLlama), `grid402_token_safety` (GoPlus + DexScreener), `grid402_prediction_markets` (Polymarket + Kalshi), `grid402_extract` (URL-to-Markdown) or `grid402_email_verify` for autonomous workflows.',
  'Authentication and Payment modes: (1) Set `GRID402_SESSION_PASS=pass_g402_...` for <15ms prepaid calls (`1 credit = 0.0050 USDC`, zero per-call gas; acquire via Pix/Card at https://grid402.online/pass); (2) Set `GRID402_PRIVATE_KEY` (EVM Base, Polygon, Arbitrum, Optimism, Ethereum) or `GRID402_SOLANA_PRIVATE_KEY` (Solana SPL USDC) plus optional `GRID402_PAYMENT_NETWORK=base|polygon|arbitrum|solana` for automatic Multi-Chain settlement — Gasless EIP-3009 via PayAI is enabled by default (`GRID402_X402_GASLESS=true`, set `GRID402_X402_GASLESS=false` to force on-chain ERC-20 transfer); (3) Set `GRID402_SANDBOX=true` for free sandbox testing via `X-Payment: demo` (3 queries/day per IP; max 2 items on `grid402_batch`; never accepted for `grid402_pass`).',
  'Spend controls and error handling: paid tools return `_oracle_proof` (ECDSA secp256k1) and `_meta` (upstream source and TTL). When HTTP 402 (`payment_required: true`) is returned without credentials, present the fee (`0.0050`–`0.0500` USDC, or exact dynamic batch sum) and free `preview` teaser instead of retrying blindly. Respect HTTP 429 (`PassDailyLimitExceeded`, `SandboxRateLimitExceeded`) and HTTP 403 (`PassEndpointNotAllowed`, `PassEndpointRestricted`).',
].join(' ');

const server = new Server(
  {
    name: 'grid402-mcp-server',
    version: '2.2.0',
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
      case 'grid402_extract':
        service = 'extract';
        break;
      case 'grid402_email_verify':
        service = 'email-verify';
        break;
      case 'grid402_token_safety':
        service = 'token-safety';
        break;
      case 'grid402_prediction_markets':
        service = 'prediction-markets';
        break;
      case 'grid402_sec_company':
        service = 'sec-company';
        break;
      case 'grid402_vuln_audit':
        service = 'vuln-audit';
        break;
      case 'grid402_chain_telemetry':
        service = 'chain-telemetry';
        break;
      case 'grid402_batch':
        service = 'batch';
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

    const autoTrial = process.env.GRID402_AUTO_TRIAL !== 'false' && process.env.GRID402_AUTO_TRIAL !== '0';
    const result = await executeGrid402Request(service, args, { autoTrial });

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

