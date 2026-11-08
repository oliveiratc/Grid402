/**
 * Grid402 Client SDK para CommonJS (CJS)
 * Permite integracao imediata via require('grid402') em projetos Node.js legados ou CJS.
 */

const DEFAULT_GRID402_URL = 'https://grid402.com.br';
const GRID402_ORACLE_WALLET = '0xa31c31069c43ac1c0075a93c325b0dcd7744356c';
const BASE_USDC_CONTRACT = '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913';

class Grid402Error extends Error {
  constructor(message) {
    super(message);
    this.name = 'Grid402Error';
  }
}

class PaymentRequiredError extends Grid402Error {
  constructor(challenge) {
    const defaultMsg = challenge?.message || 'Pagamento x402 obrigatorio';
    super(defaultMsg);
    this.name = 'PaymentRequiredError';
    this.challenge = challenge || {};
    this.recipientWallet = challenge?.recipient_wallet || GRID402_ORACLE_WALLET;
    this.priceUsdc = challenge?.price_usdc || '0.0005';
    this.network = challenge?.network || 'Base';
    this.chainId = challenge?.chain_id || 8453;
    this.token = challenge?.token || 'USDC';
    this.tokenContract = challenge?.token_contract || BASE_USDC_CONTRACT;
    this.previewData = challenge?.preview_data || null;
    this.paymentDetails = challenge?.payment_details || null;
  }
}

class ReplayAttackError extends Grid402Error {
  constructor(message) {
    super(message || 'Replay attack detectado: comprovante ja utilizado');
    this.name = 'ReplayAttackError';
  }
}

class Grid402Client {
  constructor(options = {}) {
    this.baseUrl = (options.baseUrl || DEFAULT_GRID402_URL).replace(/\/$/, '');
    this.sandbox = Boolean(options.sandbox);
    this.paymentTxHash = options.paymentTxHash || null;
    this.timeout = options.timeout || 10000;
    this.raiseForPayment = options.raiseForPayment !== false;
  }

  async _request(endpoint, headers = {}) {
    const url = `${this.baseUrl}${endpoint}`;
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), this.timeout);

    const mergedHeaders = {
      'Accept': 'application/json',
      'User-Agent': 'Grid402-Node-CJS-SDK/2.3',
      ...headers,
    };

    if (this.paymentTxHash) {
      if (!mergedHeaders['X-Payment']) {
        mergedHeaders['X-Payment'] = this.paymentTxHash;
      }
    } else if (this.sandbox) {
      if (!mergedHeaders['X-Payment']) {
        mergedHeaders['X-Payment'] = 'demo';
      }
    }

    try {
      const response = await fetch(url, {
        method: 'GET',
        headers: mergedHeaders,
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
        if (this.raiseForPayment) {
          throw new PaymentRequiredError(data);
        }
        data.payment_required = true;
        return data;
      }

      if (response.status === 403) {
        if (data.error === 'ReplayAttackDetected') {
          throw new ReplayAttackError(data.message);
        }
      }

      if (!response.ok) {
        throw new Grid402Error(`HTTP ${response.status}: ${data.message || data.error || response.statusText}`);
      }

      return data;
    } catch (err) {
      if (err instanceof Grid402Error) throw err;
      throw new Grid402Error(`Erro de conexao com Grid402: ${err.message}`);
    } finally {
      clearTimeout(timer);
    }
  }

  async getSummary() {
    return this._request('/status');
  }

  async getChallenge(endpoint = '/api/v1/forex') {
    const prevRaise = this.raiseForPayment;
    this.raiseForPayment = true;
    try {
      await this._request(endpoint);
      throw new Grid402Error('Esperado desafio HTTP 402, mas a rota respondeu sem restricao.');
    } catch (err) {
      if (err instanceof PaymentRequiredError) {
        return err.challenge;
      }
      throw err;
    } finally {
      this.raiseForPayment = prevRaise;
    }
  }

  async getForex(paymentTxHash) {
    const headers = paymentTxHash ? { 'X-Payment': paymentTxHash } : {};
    return this._request('/api/v1/forex', headers);
  }

  async getCrypto(paymentTxHash, symbol) {
    const headers = paymentTxHash ? { 'X-Payment': paymentTxHash } : {};
    const query = symbol ? `?symbol=${encodeURIComponent(symbol)}` : '';
    return this._request(`/api/v1/crypto${query}`, headers);
  }

  async getDns(domain, paymentTxHash) {
    const headers = paymentTxHash ? { 'X-Payment': paymentTxHash } : {};
    const query = `?domain=${encodeURIComponent(domain)}`;
    return this._request(`/api/v1/dns${query}`, headers);
  }

  async getSsl(host, paymentTxHash) {
    const headers = paymentTxHash ? { 'X-Payment': paymentTxHash } : {};
    const query = `?host=${encodeURIComponent(host)}`;
    return this._request(`/api/v1/ssl${query}`, headers);
  }

  async getAsn(ip, paymentTxHash) {
    const headers = paymentTxHash ? { 'X-Payment': paymentTxHash } : {};
    const query = ip ? `?ip=${encodeURIComponent(ip)}` : '';
    return this._request(`/api/v1/asn${query}`, headers);
  }

  async getCep(code, paymentTxHash) {
    const headers = paymentTxHash ? { 'X-Payment': paymentTxHash } : {};
    const query = `?code=${encodeURIComponent(code)}`;
    return this._request(`/api/v1/cep${query}`, headers);
  }

  async getCnpj(cnpj, paymentTxHash) {
    const headers = paymentTxHash ? { 'X-Payment': paymentTxHash } : {};
    const query = `?cnpj=${encodeURIComponent(cnpj)}`;
    return this._request(`/api/v1/cnpj${query}`, headers);
  }

  async getIndicators(paymentTxHash) {
    const headers = paymentTxHash ? { 'X-Payment': paymentTxHash } : {};
    return this._request('/api/v1/indicators', headers);
  }

  async getHolidays(year, paymentTxHash) {
    const headers = paymentTxHash ? { 'X-Payment': paymentTxHash } : {};
    const query = year ? `?year=${encodeURIComponent(year)}` : '';
    return this._request(`/api/v1/holidays${query}`, headers);
  }

  async isHealthy(serviceName = 'forex') {
    try {
      const summary = await this.getSummary();
      const services = summary.services || {};
      if (services[serviceName]) {
        return services[serviceName].status === 'UP';
      }
      return summary.status === 'OPERATIONAL';
    } catch {
      return false;
    }
  }

  async guard(serviceName, action, fallback) {
    const healthy = await this.isHealthy(serviceName);
    if (healthy) {
      return action();
    }
    if (fallback) {
      return fallback();
    }
    throw new Grid402Error(`Circuito aberto: o servico '${serviceName}' esta indisponivel no momento.`);
  }
}

class Grid402ActionProvider {
  constructor(options = {}) {
    this.baseUrl = (options.baseUrl || DEFAULT_GRID402_URL).replace(/\/$/, '');
    this.sandbox = Boolean(options.sandbox);
  }

  async _executeWithSettlement(path, params = {}, walletProvider = null) {
    const queryParams = new URLSearchParams();
    for (const [k, v] of Object.entries(params)) {
      if (v !== undefined) {
        if (v !== null) {
          if (v !== '') {
            queryParams.set(k, String(v));
          }
        }
      }
    }

    const queryString = queryParams.toString();
    const url = `${this.baseUrl}${path}${queryString ? '?' + queryString : ''}`;

    const headers = {
      'Accept': 'application/json',
      'User-Agent': 'Grid402-CoinbaseAgentKit-CJS/2.3',
    };

    if (this.sandbox) {
      headers['X-Payment'] = 'demo';
    }

    const response = await fetch(url, { method: 'GET', headers });

    if (response.status === 200) {
      return await response.json();
    }

    if (response.status === 402) {
      const challenge = await response.json();

      if (walletProvider) {
        if (!this.sandbox) {
          const priceUsdc = challenge.price_usdc || '0.0005';
          const recipient = challenge.recipient_wallet || GRID402_ORACLE_WALLET;

          try {
            const transferResult = await walletProvider.transfer({
              to: recipient,
              amount: priceUsdc,
              assetId: 'usdc',
            });

            const txHash = transferResult?.transactionHash || transferResult?.hash || String(transferResult);

            headers['X-Payment'] = txHash;
            const retryResponse = await fetch(url, { method: 'GET', headers });

            if (retryResponse.status === 200) {
              const data = await retryResponse.json();
              data._x402_settlement = {
                settled: true,
                tx_hash: txHash,
                price_usdc: priceUsdc,
                network: 'Base',
                token: 'USDC',
              };
              return data;
            }
          } catch (payErr) {
            challenge.payment_error = payErr.message || String(payErr);
            challenge.payment_required = true;
            return challenge;
          }
        }
      }

      challenge.payment_required = true;
      return challenge;
    }

    const errText = await response.text();
    try {
      return JSON.parse(errText);
    } catch {
      throw new Grid402Error(`Erro na API Grid402 (HTTP ${response.status}): ${errText}`);
    }
  }

  async getSummary() {
    return this._executeWithSettlement('/status');
  }

  async queryForex(walletProvider = null) {
    return this._executeWithSettlement('/api/v1/forex', {}, walletProvider);
  }

  async queryCrypto(walletProvider = null, symbol = null) {
    const params = symbol ? { symbol } : {};
    return this._executeWithSettlement('/api/v1/crypto', params, walletProvider);
  }

  async queryDns(domain, walletProvider = null) {
    return this._executeWithSettlement('/api/v1/dns', { domain }, walletProvider);
  }

  async querySsl(host, walletProvider = null) {
    return this._executeWithSettlement('/api/v1/ssl', { host }, walletProvider);
  }

  async queryAsn(ip = null, walletProvider = null) {
    const params = ip ? { ip } : {};
    return this._executeWithSettlement('/api/v1/asn', params, walletProvider);
  }

  async queryCep(code, walletProvider = null) {
    return this._executeWithSettlement('/api/v1/cep', { code }, walletProvider);
  }

  async queryCnpj(cnpj, walletProvider = null) {
    return this._executeWithSettlement('/api/v1/cnpj', { cnpj }, walletProvider);
  }

  async queryIndicators(walletProvider = null) {
    return this._executeWithSettlement('/api/v1/indicators', {}, walletProvider);
  }

  async queryHolidays(year = null, walletProvider = null) {
    const params = year ? { year } : {};
    return this._executeWithSettlement('/api/v1/holidays', params, walletProvider);
  }

  get getCnpj() { return this.queryCnpj; }
  get getCep() { return this.queryCep; }
  get getForex() { return this.queryForex; }
  get getCrypto() { return this.queryCrypto; }
  get getDns() { return this.queryDns; }
  get getSsl() { return this.querySsl; }
  get getAsn() { return this.queryAsn; }
  get getIndicators() { return this.queryIndicators; }
  get getHolidays() { return this.queryHolidays; }
}

module.exports = {
  DEFAULT_GRID402_URL,
  GRID402_ORACLE_WALLET,
  BASE_USDC_CONTRACT,
  Grid402Error,
  PaymentRequiredError,
  ReplayAttackError,
  Grid402Client,
  Grid402ActionProvider,
};
