#!/usr/bin/env node

/**
 * Grid402 Official Model Context Protocol (MCP) Server
 * Exposes 7 tools for real-time open data and network utilities with Base x402 settlement.
 */

import { Server } from '@modelcontextprotocol/sdk/server/index.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import { CallToolRequestSchema, ListToolsRequestSchema } from '@modelcontextprotocol/sdk/types.js';
import { MCP_TOOLS_DEFINITIONS, executeGrid402Request, DEFAULT_ENDPOINT } from './tools.mjs';

const server = new Server(
  {
    name: 'grid402-mcp-server',
    version: '1.0.0',
  },
  {
    capabilities: {
      tools: {},
    },
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

