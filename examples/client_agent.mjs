/**
 * Grid402 - Exemplo de Integração de Agente em Node.js / JavaScript (ESM)
 * 
 * Uso:
 *   node examples/client_agent.mjs
 */

const GRID402_BASE_URL = process.env.GRID402_URL || 'https://www.grid402.com.br';

async function main() {
    console.log('[AGENTE M2M] 1. Consultando disponibilidade preliminar (Free Tier)...');
    
    try {
        const freeRes = await fetch(`${GRID402_BASE_URL}/status?format=json`, {
            headers: { 'Accept': 'application/json' }
        });
        const freeData = await freeRes.json();
        console.log(`  -> Status do Sistema: ${freeData.system_status}`);
        console.log(`  -> Alvos Saudáveis: ${freeData.healthy_targets}/${freeData.total_targets}`);
        
        console.log('\n[AGENTE M2M] 2. Solicitando telemetria detalhada (x402 Challenge)...');
        const paidRes = await fetch(`${GRID402_BASE_URL}/telemetry`, {
            headers: { 'Accept': 'application/json' }
        });

        if (paidRes.status === 402) {
            const challenge = await paidRes.json();
            console.log('  -> Recebido HTTP 402 Payment Required:');
            console.log(`     • Rede: ${challenge.network} (Chain: ${challenge.chain_id})`);
            console.log(`     • Preço: US$ ${challenge.price_usdc} USDC`);
            console.log(`     • Carteira Destino: ${challenge.recipient_wallet}`);
            console.log('     • Instrução: Transferir 0.0005 USDC e reenviar com cabeçalho X-Payment: <tx_hash>');
        }
    } catch (err) {
        console.error('Erro na chamada:', err.message);
    }
}

main();
