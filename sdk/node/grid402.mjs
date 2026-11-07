/**
 * Grid402 Client SDK (Node.js / ESM / Browser)
 * Zero-dependency client for autonomous AI agents and M2M applications.
 * Access public open data and network utilities via x402 micropayments on Base.
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

/**
 * Cliente oficial para integração com o Hub de Utilitários e Dados Públicos Grid402.
 * 
 * AVISO LEGAL (AS-IS):
 * Software e dados fornecidos 'NO ESTADO EM QUE SE ENCONTRAM' (AS-IS),
 * sem garantias de qualquer natureza. O usuário assume total responsabilidade
 * pelas decisões e operações tomadas com base nestes dados.
 */
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
                    'User-Agent': 'Grid402-Node-SDK/2.0',
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
     * Obtém o resumo executivo gratuito (status e latências dos 6 micro-serviços).
     * @returns {Promise<Object>}
     */
    async getSummary() {
        return this._request('/api/v1/summary');
    }

    /**
     * Dispara consulta técnica sem pagamento para obter o desafio x402 e parâmetros da Base.
     * @param {string} [endpoint]
     * @returns {Promise<Object>}
     */
    async getChallenge(endpoint = '/api/v1/forex') {
        try {
            await this._request(endpoint);
            throw new Grid402Error('Esperado desafio HTTP 402, mas a rota respondeu sem restrição.');
        } catch (err) {
            if (err instanceof PaymentRequiredError) {
                return err.challenge;
            }
            throw err;
        }
    }

    /**
     * Consulta cotações fiduciárias em tempo real (USD, EUR, GBP para BRL).
     * @param {string} [paymentTxHash]
     * @returns {Promise<Object>}
     */
    async getForex(paymentTxHash) {
        const headers = paymentTxHash ? { 'X-Payment': paymentTxHash } : {};
        return this._request('/api/v1/forex', headers);
    }

    /**
     * Consulta preços de mercado spot de criptoativos (BTC, ETH, SOL, USDC, BASE).
     * @param {string} [paymentTxHash]
     * @returns {Promise<Object>}
     */
    async getCrypto(paymentTxHash) {
        const headers = paymentTxHash ? { 'X-Payment': paymentTxHash } : {};
        return this._request('/api/v1/crypto', headers);
    }

    /**
     * Resolve registros DNS (A, AAAA, MX, TXT, NS, CNAME) para um domínio.
     * @param {string} domain
     * @param {string} [paymentTxHash]
     * @returns {Promise<Object>}
     */
    async getDns(domain, paymentTxHash) {
        const headers = paymentTxHash ? { 'X-Payment': paymentTxHash } : {};
        const q = new URLSearchParams({ domain }).toString();
        return this._request(`/api/v1/dns?${q}`, headers);
    }

    /**
     * Inspeciona certificado TLS/SSL na porta 443 de um host (emissor, validade e dias restantes).
     * @param {string} host
     * @param {string} [paymentTxHash]
     * @returns {Promise<Object>}
     */
    async getSsl(host, paymentTxHash) {
        const headers = paymentTxHash ? { 'X-Payment': paymentTxHash } : {};
        const q = new URLSearchParams({ host }).toString();
        return this._request(`/api/v1/ssl?${q}`, headers);
    }

    /**
     * Resolve metadados BGP, ASN e país para um endereço IP.
     * @param {string} [ip]
     * @param {string} [paymentTxHash]
     * @returns {Promise<Object>}
     */
    async getAsn(ip, paymentTxHash) {
        const headers = paymentTxHash ? { 'X-Payment': paymentTxHash } : {};
        const q = ip ? `?${new URLSearchParams({ ip }).toString()}` : '';
        return this._request(`/api/v1/asn${q}`, headers);
    }

    /**
     * Resolve endereço público e código IBGE a partir de um CEP brasileiro.
     * @param {string} code
     * @param {string} [paymentTxHash]
     * @returns {Promise<Object>}
     */
    async getCep(code, paymentTxHash) {
        const headers = paymentTxHash ? { 'X-Payment': paymentTxHash } : {};
        const q = new URLSearchParams({ code }).toString();
        return this._request(`/api/v1/cep?${q}`, headers);
    }

    /**
     * Verifica rapidamente se um serviço específico ou o hub está saudável (UP).
     * @param {string} [serviceName]
     * @returns {Promise<boolean>}
     */
    async isHealthy(serviceName = 'forex') {
        const summary = await this.getSummary();
        const services = summary.services || {};
        if (services[serviceName]) {
            return services[serviceName].status === 'UP';
        }
        return summary.status === 'OPERATIONAL';
    }

    /**
     * Circuit Breaker: Só executa a função action se o serviço estiver UP.
     * @param {string} serviceName
     * @param {Function} action
     * @param {Function} [fallback]
     * @returns {Promise<any>}
     */
    async guard(serviceName, action, fallback) {
        const healthy = await this.isHealthy(serviceName);
        if (healthy) {
            return action();
        }
        if (fallback) {
            return fallback();
        }
        throw new Grid402Error(`Circuito aberto: O serviço '${serviceName}' está indisponível no momento.`);
    }
}
