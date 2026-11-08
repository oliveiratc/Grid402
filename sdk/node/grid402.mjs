/**
 * Grid402 Client SDK para Node.js, TypeScript e Navegadores (ESM)
 * Biblioteca oficial para micro-servicos de dados abertos e rede sob o protocolo HTTP 402 na Base.
 * 
 * Suporte nativo para Modo Sandbox gratuito, Teaser Data e Coinbase AgentKit.
 */

export const DEFAULT_GRID402_URL = 'https://grid402.com.br';
export const GRID402_ORACLE_WALLET = '0xa31c31069c43ac1c0075a93c325b0dcd7744356c';
export const BASE_USDC_CONTRACT = '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913';

/**
 * Excecao base de erros do Grid402
 */
export class Grid402Error extends Error {
  constructor(message) {
    super(message);
    this.name = 'Grid402Error';
  }
}

/**
 * Excecao lancada quando a requisicao requer micropagamento sob o protocolo x402
 */
export class PaymentRequiredError extends Grid402Error {
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

/**
 * Excecao lancada em tentativa de reuso de transacao (replay attack)
 */
export class ReplayAttackError extends Grid402Error {
  constructor(message) {
    super(message || 'Replay attack detectado: comprovante ja utilizado');
    this.name = 'ReplayAttackError';
  }
}

/**
 * Cliente oficial para consumo dos servicos do Grid402
 */
export class Grid402Client {
  /**
   * @param {Object} [options]
   * @param {string} [options.baseUrl] - URL base da API Grid402
   * @param {boolean} [options.sandbox] - Se true, ativa o Modo Sandbox gratuito de testes
   * @param {string} [options.paymentTxHash] - Hash padrao opcional de transacao na Base
   * @param {number} [options.timeout] - Timeout em milissegundos (padrao: 10000ms)
   * @param {boolean} [options.raiseForPayment] - Se true, lanca PaymentRequiredError no HTTP 402 (padrao: true)
   */
  constructor(options = {}) {
    this.baseUrl = (options.baseUrl || DEFAULT_GRID402_URL).replace(/\/$/, '');
    this.sandbox = Boolean(options.sandbox);
    this.paymentTxHash = options.paymentTxHash || null;
    this.timeout = options.timeout || 10000;
    this.raiseForPayment = options.raiseForPayment !== false;
  }

  /**
   * Executa a requisicao HTTP para o endpoint especificado
   * @private
   */
  async _request(endpoint, headers = {}) {
    const url = `${this.baseUrl}${endpoint}`;
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), this.timeout);

    const mergedHeaders = {
      'Accept': 'application/json',
      'User-Agent': 'Grid402-Node-SDK/2.3',
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

  /**
   * Obtem o resumo operacional gratuito do hub e disponibilidade dos servicos
   * @returns {Promise<Object>}
   */
  async getSummary() {
    return this._request('/status');
  }

  /**
   * Dispara consulta para obter o desafio x402 e parametros da Base
   * @param {string} [endpoint]
   * @returns {Promise<Object>}
   */
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

  /**
   * Consulta cotacoes cambiais oficiais em tempo real (USD, EUR, GBP para BRL)
   * @param {string} [paymentTxHash]
   * @returns {Promise<Object>}
   */
  async getForex(paymentTxHash) {
    const headers = paymentTxHash ? { 'X-Payment': paymentTxHash } : {};
    return this._request('/api/v1/forex', headers);
  }

  /**
   * Consulta precos de mercado spot de criptoativos (BTC, ETH, SOL, USDC, BASE)
   * @param {string} [paymentTxHash]
   * @param {string} [symbol]
   * @returns {Promise<Object>}
   */
  async getCrypto(paymentTxHash, symbol) {
    const headers = paymentTxHash ? { 'X-Payment': paymentTxHash } : {};
    const query = symbol ? `?symbol=${encodeURIComponent(symbol)}` : '';
    return this._request(`/api/v1/crypto${query}`, headers);
  }

  /**
   * Executa resolucao autoritativa de registros DNS (A, AAAA, MX, TXT, NS, CNAME)
   * @param {string} domain
   * @param {string} [paymentTxHash]
   * @returns {Promise<Object>}
   */
  async getDns(domain, paymentTxHash) {
    const headers = paymentTxHash ? { 'X-Payment': paymentTxHash } : {};
    const query = `?domain=${encodeURIComponent(domain)}`;
    return this._request(`/api/v1/dns${query}`, headers);
  }

  /**
   * Inspeciona e valida o certificado TLS e SSL na porta 443 de um host
   * @param {string} host
   * @param {string} [paymentTxHash]
   * @returns {Promise<Object>}
   */
  async getSsl(host, paymentTxHash) {
    const headers = paymentTxHash ? { 'X-Payment': paymentTxHash } : {};
    const query = `?host=${encodeURIComponent(host)}`;
    return this._request(`/api/v1/ssl${query}`, headers);
  }

  /**
   * Consulta inteligencia de rede BGP, identificando numero de ASN e organizacao titular
   * @param {string} [ip]
   * @param {string} [paymentTxHash]
   * @returns {Promise<Object>}
   */
  async getAsn(ip, paymentTxHash) {
    const headers = paymentTxHash ? { 'X-Payment': paymentTxHash } : {};
    const query = ip ? `?ip=${encodeURIComponent(ip)}` : '';
    return this._request(`/api/v1/asn${query}`, headers);
  }

  /**
   * Consulta a base publica de enderecamento postal brasileiro e codigo IBGE por CEP
   * @param {string} code
   * @param {string} [paymentTxHash]
   * @returns {Promise<Object>}
   */
  async getCep(code, paymentTxHash) {
    const headers = paymentTxHash ? { 'X-Payment': paymentTxHash } : {};
    const query = `?code=${encodeURIComponent(code)}`;
    return this._request(`/api/v1/cep${query}`, headers);
  }

  /**
   * Consulta dados cadastrais publicos de Pessoa Juridica na Receita Federal por CNPJ
   * @param {string} cnpj
   * @param {string} [paymentTxHash]
   * @returns {Promise<Object>}
   */
  async getCnpj(cnpj, paymentTxHash) {
    const headers = paymentTxHash ? { 'X-Payment': paymentTxHash } : {};
    const query = `?cnpj=${encodeURIComponent(cnpj)}`;
    return this._request(`/api/v1/cnpj${query}`, headers);
  }

  /**
   * Consulta indicadores economicos oficiais (Selic, CDI e IPCA) do Banco Central e IBGE
   * @param {string} [paymentTxHash]
   * @returns {Promise<Object>}
   */
  async getIndicators(paymentTxHash) {
    const headers = paymentTxHash ? { 'X-Payment': paymentTxHash } : {};
    return this._request('/api/v1/indicators', headers);
  }

  /**
   * Consulta calendario oficial de feriados nacionais e bancarios (ANBIMA e Febraban)
   * @param {number} [year]
   * @param {string} [paymentTxHash]
   * @returns {Promise<Object>}
   */
  async getHolidays(year, paymentTxHash) {
    const headers = paymentTxHash ? { 'X-Payment': paymentTxHash } : {};
    const query = year ? `?year=${encodeURIComponent(year)}` : '';
    return this._request(`/api/v1/holidays${query}`, headers);
  }

  /**
   * Verifica se o hub ou um servico especifico esta operacional
   * @param {string} [serviceName]
   * @returns {Promise<boolean>}
   */
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

  /**
   * Circuit breaker: executa a acao somente se o servico estiver UP
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
    throw new Grid402Error(`Circuito aberto: o servico '${serviceName}' esta indisponivel no momento.`);
  }
}

/**
 * Provedor de acoes compativel com Coinbase Developer Platform (CDP) AgentKit, LangChain.js e ElizaOS
 */
export class Grid402ActionProvider {
  /**
   * @param {Object} [options]
   * @param {string} [options.baseUrl] - URL base da API Grid402
   * @param {boolean} [options.sandbox] - Se true, opera em modo Sandbox sem consumir saldo real
   */
  constructor(options = {}) {
    this.baseUrl = (options.baseUrl || DEFAULT_GRID402_URL).replace(/\/$/, '');
    this.sandbox = Boolean(options.sandbox);
  }

  /**
   * Executa a chamada com auto-liquidacao de micropagamentos on-chain via wallet_provider
   * @private
   */
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
      'User-Agent': 'Grid402-CoinbaseAgentKit-Node/2.3',
    };

    if (this.sandbox) {
      headers['X-Payment'] = 'demo';
    }

    // 1. Primeira requisicao
    const response = await fetch(url, { method: 'GET', headers });

    if (response.status === 200) {
      return await response.json();
    }

    // 2. Se retornou HTTP 402 Payment Required
    if (response.status === 402) {
      const challenge = await response.json();

      // Se temos walletProvider e nao estamos em sandbox, auto-liquida on-chain na Base
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

  // Aliases de conveniencia
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
