import { createRequire } from 'module';
const require = createRequire(import.meta.url);

// Replica exata das funções do site (linhas 4090-4145)
async function x402SignTransferAuthorization(provider, userAddress, amountRaw) {
    const usdcContract = '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913';
    const recipient = '0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90'.toLowerCase().replace('0x', '');
    const now = Math.floor(Date.now() / 1000);
    const validAfter = String(Math.max(0, now - 600));
    const validBefore = String(now + 86400);
    const nonceBytes = new Uint8Array(32);
    crypto.getRandomValues(nonceBytes);
    const nonce = '0x' + Array.from(nonceBytes).map(b => b.toString(16).padStart(2, '0')).join('');
    const typedData = {
        domain: { name: 'USD Coin', version: '2', chainId: 8453, verifyingContract: usdcContract },
        types: {
            TransferWithAuthorization: [
                { name: 'from', type: 'address' },
                { name: 'to', type: 'address' },
                { name: 'value', type: 'uint256' },
                { name: 'validAfter', type: 'uint256' },
                { name: 'validBefore', type: 'uint256' },
                { name: 'nonce', type: 'bytes32' },
            ],
        },
        primaryType: 'TransferWithAuthorization',
        message: {
            from: userAddress.toLowerCase(),
            to: recipient.toLowerCase(),
            value: String(amountRaw),
            validAfter,
            validBefore,
            nonce,
        },
    };
    const signature = await provider.request({
        method: 'eth_signTypedData_v4',
        params: [userAddress, JSON.stringify(typedData)],
    });
    const envelope = {
        x402Version: 2,
        accepted: {
            scheme: 'exact',
            network: 'eip155:8453',
            asset: usdcContract,
            amount: String(amountRaw),
            maxTimeoutSeconds: 300,
            extra: { name: 'USD Coin', version: '2' },
        },
        payload: {
            signature,
            authorization: {
                from: userAddress.toLowerCase(),
                to: recipient.toLowerCase(),
                value: String(amountRaw),
                validAfter,
                validBefore,
                nonce,
            },
        },
    };
    return btoa(JSON.stringify(envelope));
}

// mock provider
const mockProvider = {
    request: async (req) => {
        console.log('PROVIDER method:', req.method);
        if (req.method === 'eth_signTypedData_v4') {
            return '0x' + 'a'.repeat(130); // fake 65-byte sig
        }
        if (req.method === 'eth_requestAccounts') {
            return ['0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90'];
        }
        if (req.method === 'wallet_switchEthereumChain' || req.method === 'wallet_addEthereumChain') {
            return null;
        }
        throw new Error('mockProvider: unknown method ' + req.method);
    },
};

try {
    const b64 = await x402SignTransferAuthorization(mockProvider, '0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90', 5000);
    console.log('OK, b64 len:', b64.length);
    console.log('decoded head:', Buffer.from(b64, 'base64').toString('utf8').slice(0, 200));
} catch (e) {
    console.log('THREW:', e.name, '-', e.message);
    console.log(e.stack);
}
