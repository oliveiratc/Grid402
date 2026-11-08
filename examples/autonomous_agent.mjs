/**
 * Grid402 - Exemplo de Agente Autônomo com Auto-Liquidação x402 (Node.js)
 * 
 * Demonstra como um robô ou agente de IA pode consumir dados e utilitários
 * pagos do Grid402 na Base Mainnet de forma 100% autônoma, sem intervenção humana.
 */

let ethers = null;
try {
  const m = await import('ethers');
  ethers = m.ethers || m;
} catch {
  try {
    const m = await import('../mcp/node_modules/ethers/lib.esm/index.js');
    ethers = m.ethers || m;
  } catch {
    // Ethers não instalado globalmente
  }
}

// Endpoint oficial do oráculo
const GRID402_ENDPOINT = process.env.GRID402_ENDPOINT || 'https://orangered-jackal-900744.hostingersite.com';

// Chave privada da carteira do agente (carregada via variável de ambiente)
const AGENT_PRIVATE_KEY = process.env.AGENT_PRIVATE_KEY || null;

const BASE_RPC_URL = 'https://mainnet.base.org';
const ERC20_ABI = [
  'function transfer(address to, uint256 amount) returns (bool)',
];

/**
 * Executa uma consulta inteligente com auto-liquidação via protocolo x402
 */
async function queryGrid402WithAutoPayment(servicePath, queryParams = {}) {
  const url = new URL(servicePath, GRID402_ENDPOINT);
  Object.entries(queryParams).forEach(([k, v]) => url.searchParams.set(k, v));

  console.log(`\n🤖 [AGENTE] Iniciando requisição para: ${url.pathname}${url.search}`);

  // 1. Primeira tentativa sem cabeçalho de pagamento
  const initialRes = await fetch(url.toString(), {
    headers: { 'Accept': 'application/json' },
  });

  // Se retornou 200 direto (ex: endpoint gratuito /status)
  if (initialRes.status === 200) {
    const data = await initialRes.json();
    console.log('✅ [AGENTE] Resposta gratuita recebida com sucesso!');
    return data;
  }

  // 2. Se retornou 402 Payment Required
  if (initialRes.status === 402) {
    const challenge = await initialRes.json();
    console.log(`⚡ [AGENTE] Desafio x402 recebido: requer ${challenge.price_usdc} USDC na rede Base`);
    console.log(`📍 [AGENTE] Carteira recebedora: ${challenge.recipient_wallet}`);

    if (!AGENT_PRIVATE_KEY) {
      console.warn('⚠️ [AGENTE] AGENT_PRIVATE_KEY não informada. Exibindo apenas o desafio 402:');
      return challenge;
    }

    console.log('💳 [AGENTE] Assinando e enviando micro-transferência on-chain na Base...');

    const provider = new ethers.JsonRpcProvider(BASE_RPC_URL, 8453);
    const wallet = new ethers.Wallet(AGENT_PRIVATE_KEY, provider);

    const usdcContract = new ethers.Contract(challenge.token_contract, ERC20_ABI, wallet);
    const rawUnits = BigInt(challenge.price_raw_units || 500);

    const tx = await usdcContract.transfer(challenge.recipient_wallet, rawUnits);
    console.log(`🚀 [AGENTE] Transação enviada na Base! Hash: ${tx.hash}`);
    console.log(`🔗 [AGENTE] BaseScan: https://basescan.org/tx/${tx.hash}`);
    console.log('⏳ [AGENTE] Aguardando confirmação do bloco...');

    await tx.wait(1);
    console.log('✨ [AGENTE] Pagamento confirmado on-chain!');

    // 3. Segunda tentativa fornecendo o comprovante no cabeçalho X-Payment
    console.log('🔓 [AGENTE] Reenviando requisição com X-Payment...');
    const paidRes = await fetch(url.toString(), {
      headers: {
        'Accept': 'application/json',
        'X-Payment': tx.hash,
      },
    });

    if (paidRes.status === 200) {
      const paidData = await paidRes.json();
      console.log('🎉 [AGENTE] Consulta liberada com sucesso pelo oráculo:');
      return paidData;
    } else {
      const err = await paidRes.text();
      throw new Error(`Falha após liquidação: HTTP ${paidRes.status} - ${err}`);
    }
  }

  throw new Error(`Resposta inesperada: HTTP ${initialRes.status}`);
}

// Execução de Demonstração
async function main() {
  console.log('========================================================');
  console.log('   GRID402 - DEMONSTRAÇÃO DE AGENTE AUTÔNOMO M2M (x402)  ');
  console.log('========================================================');

  try {
    // Exemplo 1: Validação Corporativa de CNPJ (0.0020 USDC)
    const cnpjTarget = '00000000000191'; // Banco do Brasil SA
    const cnpjResult = await queryGrid402WithAutoPayment('/api/v1/cnpj', { cnpj: cnpjTarget });
    console.log(JSON.stringify(cnpjResult, null, 2));

    // Exemplo 2: Auditoria de Certificado TLS/SSL (0.0005 USDC)
    // const sslResult = await queryGrid402WithAutoPayment('/api/v1/ssl', { host: 'base.org' });
    // console.log(JSON.stringify(sslResult, null, 2));

  } catch (error) {
    console.error('❌ Erro na execução do agente:', error.message);
  }
}

main();
