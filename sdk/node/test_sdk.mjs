import { Grid402Client, PaymentRequiredError } from './grid402.mjs';

const client = new Grid402Client();

console.log('[TEST SDK NODE.JS] 1. Testando getSummary()...');
const summary = await client.getSummary();
if (summary.protocol !== 'x402') throw new Error('Protocolo inválido');
if (!summary.categories) throw new Error('Categorias ausentes');
console.log(`  ✓ Resumo obtido com sucesso! Status do sistema: ${summary.system_status}`);

console.log('[TEST SDK NODE.JS] 2. Testando isHealthy("Gateways")...');
const isGatewaysOk = await client.isHealthy('Gateways');
console.log(`  ✓ Saúde dos Gateways: ${isGatewaysOk ? 'UP' : 'DOWN'}`);

console.log('[TEST SDK NODE.JS] 3. Testando getChallenge()...');
const challenge = await client.getChallenge();
if (challenge.error !== 'PaymentRequired') throw new Error('Esperado PaymentRequired');
if (challenge.recipient_wallet !== '0xa31c31069c43ac1c0075a93c325b0dcd7744356c') {
    throw new Error('Carteira divergente no desafio');
}
console.log(`  ✓ Desafio x402 validado! Carteira destino: ${challenge.recipient_wallet}`);

console.log('[TEST SDK NODE.JS] 4. Testando guard() Circuit Breaker...');
let executed = false;
const result = await client.guard('Gateways', async () => {
    executed = true;
    return 'Transações liquidadas com sucesso!';
});
if (!executed) throw new Error('Ação não foi executada');
console.log(`  ✓ Circuit Breaker guard() executou com sucesso: '${result}'`);

console.log('\n✓ Todos os testes do SDK Node.js passaram com 100% de sucesso!');

