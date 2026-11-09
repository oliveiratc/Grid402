# Dockerfile para Glama MCP Registry e Firecracker MicroVM
FROM node:20-alpine

WORKDIR /app

# Instala dependencias de producao
COPY mcp/package.json ./
RUN npm install --omit=dev

# Copia arquivos do servidor MCP
COPY mcp/index.mjs mcp/tools.mjs mcp/x402_payer.mjs ./

# Configuracao de ambiente padrao
ENV NODE_ENV=production
ENV GRID402_ENDPOINT=https://grid402.online

# Executa servidor via stdio
ENTRYPOINT ["node", "index.mjs"]
