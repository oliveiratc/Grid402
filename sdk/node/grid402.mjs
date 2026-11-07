/**
 * Grid402 Client SDK (Node.js / ESM / Browser)
 * Zero-dependency client for autonomous AI agents and M2M applications.
 */

export const DEFAULT_GRID402_URL = 'https://orangered-jackal-900744.hostingersite.com';

export class Grid402Error extends Error {
    constructor(message) {
        super(message);
        this.name = 'Grid402Error';
    }
}

export class PaymentRequiredError extends Grid402Error {
    constructor(challenge) {
        super(challenge.message || 'Pagamento x402 requerido');
        this.name = 'PaymentRequiredError';
        this.challenge = challenge;
        this.recipientWallet = challenge.recipient_wallet;
        this.priceUsdc = challenge.price_usdc;
        this.priceRawUnits = challenge.price_raw_units;
        this.network = challenge.network;
        this.chainId = challenge.chain_id;
        this.tokenContract = challenge.token_contract;
    }
}

export class ReplayAttackError extends Grid402Error {
    constructor(message) {
        super(message || 'Replay attack detectado');
        this.name = 'ReplayAttackError';
    }
}

export class Grid402Client {
    /**
     * @param {Object} [options]
     * @param {string} [options.baseUrl]
     * @param {number} [options.timeout]
     */
    constructor(options = {}) {
        this.baseUrl = (options.baseUrl || DEFAULT_GRID402_URL).replace(/\/$/, '');
        this.timeout = options.timeout || 5000;
    }

    async _request(endpoint, headers = {}) {
        const url = `${this.baseUrl}${endpoint}`;
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), this.timeout);

        try {
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'User-Agent': 'Grid402-Node-SDK/1.0',
                    ...headers,
                },
                signal: controller.signal,
            });

            const text = await response.text();
            let data;
            try {
                data = JSON.parse(text);
            } catch {
                data = { error: 'InvalidJSON', message: text };
            }

            if (response.status === 402) {
                throw new PaymentRequiredError(data);
            } else if (response.status === 403 && data.error === 'ReplayAttackDetected') {
                throw new ReplayAttackError(data.message);
            } else if (!response.ok) {
                throw new Grid402Error(`HTTP ${response.status}: ${data.message || response.statusText}`);
            }

            return data;
        } catch (err) {
            if (err instanceof Grid402Error) throw err;
            throw new Grid402Error(`Erro de conexão com Grid402: ${err.message}`);
        } finally {
            clearTimeout(timer);
        }
    }

    /**
     * Obtém o resumo executivo gratuito (status e latências médias por categoria).
     * @returns {Promise<Object>}
     */
    async getSummary() {
        return this._request('/status?format=json');
    }

    /**
     * Verifica rapidamente se um alvo específico ou categoria está saudável (UP).
     * @param {string} targetOrCategory
     * @returns {Promise<boolean>}
     */
    async isHealthy(targetOrCategory) {
        const summary = await this.getSummary();
        const categories = summary.categories || {};

        if (categories[targetOrCategory]) {
            return categories[targetOrCategory].status === 'UP';
        }

        const lower = targetOrCategory.toLowerCase();
        if (lower.includes('sefaz')) {
            return categories['SEFAZ']?.status === 'UP';
        } else if (['mercado', 'asaas', 'stripe', 'pagbank', 'cora'].some(g => lower.includes(g))) {
            return categories['Gateways']?.status === 'UP';
        } else if (['bacen', 'pix', 'nubank', 'inter', 'openfinance'].some(b => lower.includes(b))) {
            return categories['Bancos & PIX']?.status === 'UP';
        }

        return summary.system_status === 'UP';
    }

    /**
     * Obtém o desafio x402 com as diretrizes de liquidação.
     * @returns {Promise<Object>}
     */
    async getChallenge() {
        try {
            await this._request('/telemetry');
            throw new Grid402Error('Esperado desafio HTTP 402, mas a rota respondeu sem restrição.');
        } catch (err) {
            if (err instanceof PaymentRequiredError) {
                return err.challenge;
            }
            throw err;
        }
    }

    /**
     * Resgata a telemetria detalhada enviando o comprovante da transação on-chain.
     * @param {string} paymentTxHash
     * @returns {Promise<Object>}
     */
    async getDetailedTelemetry(paymentTxHash) {
        if (!paymentTxHash.startsWith('0x') || paymentTxHash.length !== 66) {
            throw new Error('O paymentTxHash deve ser um hash hexadecimal válido de 32 bytes (66 caracteres).');
        }
        return this._request('/telemetry', { 'X-Payment': paymentTxHash });
    }

    /**
     * Circuit Breaker: Só executa a função action se o serviço estiver UP.
     * @param {string} targetOrCategory
     * @param {Function} action
     * @param {Function} [fallback]
     * @returns {Promise<any>}
     */
    async guard(targetOrCategory, action, fallback) {
        const healthy = await this.isHealthy(targetOrCategory);
        if (healthy) {
            return action();
        }
        if (fallback) {
            return fallback();
        }
        throw new Grid402Error(`Circuito aberto: O serviço '${targetOrCategory}' está instável ou indisponível no momento.`);
    }
}

