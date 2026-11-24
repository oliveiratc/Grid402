/**
 * Grid402 Action Provider para Coinbase AgentKit (TypeScript)
 *
 * Permite que agentes autonomos com carteiras CDP (Coinbase Developer Platform)
 * na rede Base ou Session Pass pre-pago consultem todos os 25 micro-servicos
 * do Grid402 com auto-liquidacao x402 e confirmacao on-chain.
 */

export interface Grid402Config {
  baseUrl?: string;
  sandbox?: boolean;
  sessionPass?: string;
}

export const GRID402_ORACLE_WALLET = '0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90';
export const BASE_USDC_CONTRACT = '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913';

function encodeErc20TransferData(recipient: string, rawUnits: number | bigint): string {
  const cleanRecipient = String(recipient || '').replace(/^0x/i, '').toLowerCase().padStart(64, '0');
  const amountHex = BigInt(rawUnits).toString(16).padStart(64, '0');
  return `0xa9059cbb${cleanRecipient}${amountHex}`;
}

export class Grid402ActionProvider {
  private baseUrl: string;
  private sandbox: boolean;
  private sessionPass?: string;

  constructor(config?: Grid402Config) {
    this.baseUrl = (config?.baseUrl || 'https://grid402.online').replace(/\/$/, '');
    this.sandbox = config?.sandbox || false;
    this.sessionPass =
      config?.sessionPass ||
      (typeof process !== 'undefined' && process.env ? process.env.GRID402_SESSION_PASS : undefined) ||
      undefined;
  }

  private async sendAndConfirmUsdc(
    walletProvider: any,
    recipient: string,
    priceDisplay: string,
    rawUnits: number,
    tokenContract: string
  ): Promise<string> {
    let tx: any;
    if (typeof walletProvider.sendTransaction === 'function') {
      const data = encodeErc20TransferData(recipient, rawUnits);
      tx = await walletProvider.sendTransaction({
        to: tokenContract,
        data,
        value: BigInt(0),
      });
    } else if (typeof walletProvider.transfer === 'function') {
      tx = await walletProvider.transfer({
        to: recipient,
        amount: priceDisplay,
        assetId: 'usdc',
      });
    } else {
      throw new Error('walletProvider must implement sendTransaction() (CDP AgentKit v0.1+) or transfer().');
    }

    const txHash: string =
      (typeof tx === 'string' ? tx : '') ||
      (typeof tx?.getTransactionHash === 'function' ? tx.getTransactionHash() : '') ||
      tx?.transactionHash ||
      tx?.hash ||
      String(tx);

    // Aguarda confirmacao do bloco antes do retry
    if (typeof tx?.wait === 'function') {
      await tx.wait(1);
    } else if (typeof walletProvider.waitForTransactionReceipt === 'function') {
      await walletProvider.waitForTransactionReceipt(txHash);
    } else {
      await new Promise((resolve) => setTimeout(resolve, 2500));
    }

    return txHash;
  }

  /**
   * Executa a requisicao HTTP com suporte a Session Pass e auto-liquidacao via carteira CDP
   */
  private async executeWithAutoSettlement(
    path: string,
    params: Record<string, string>,
    walletProvider?: any
  ): Promise<any> {
    const url = new URL(path, this.baseUrl);
    for (const [key, value] of Object.entries(params)) {
      if (value) url.searchParams.set(key, value);
    }

    const headers: Record<string, string> = {
      'Accept': 'application/json',
      'User-Agent': 'Grid402-CoinbaseAgentKit-Provider/2.0',
    };

    if (this.sessionPass) {
      headers['X-Payment'] = this.sessionPass.trim();
    } else if (this.sandbox) {
      headers['X-Payment'] = 'demo';
    }

    // 1. Primeira tentativa de consulta
    let res = await fetch(url.toString(), { headers });
    let json = await res.json().catch(() => ({}));

    if (res.status === 200) {
      return json;
    }

    // 2. Se retornou HTTP 402 Payment Required e temos um walletProvider
    if (res.status === 402) {
      if (walletProvider && !this.sandbox) {
        const priceDisplay = json.price_usdc || '0.0050';
        const rawUnits = Number(json.price_raw_units) || Math.round(Number(priceDisplay) * 1e6) || 5000;
        const recipient = json.recipient_wallet || GRID402_ORACLE_WALLET;
        const tokenContract = json.token_contract || BASE_USDC_CONTRACT;

        try {
          const txHash = await this.sendAndConfirmUsdc(
            walletProvider,
            recipient,
            priceDisplay,
            rawUnits,
            tokenContract
          );

          // Reenvia a requisicao com o comprovante de pagamento confirmado
          headers['X-Payment'] = txHash;
          res = await fetch(url.toString(), { headers });
          const retryText = await res.text();
          let retryJson: any;
          try {
            retryJson = JSON.parse(retryText);
          } catch {
            retryJson = { raw_body: retryText };
          }

          if (res.status === 200) {
            return {
              ...retryJson,
              _x402_settlement: {
                settled: true,
                tx_hash: txHash,
                price_usdc: priceDisplay,
                network: 'Base',
                token: 'USDC',
              },
            };
          }

          return {
            payment_required: res.status === 402,
            error: `Post-payment request failed with HTTP ${res.status}`,
            http_status: res.status,
            server_response: retryJson,
            _x402_settlement: {
              settled: false,
              tx_hash: txHash,
              price_usdc: priceDisplay,
              network: 'Base',
              token: 'USDC',
            },
          };
        } catch (payErr: any) {
          return {
            ...json,
            payment_required: true,
            payment_error: payErr?.message || String(payErr),
          };
        }
      }
    }

    return json;
  }

  // --- Ferramentas Expostas ao Agente (25 Servicos + Summary + Verify) ---

  async queryCnpj(cnpj: string, walletProvider?: any): Promise<any> {
    return this.executeWithAutoSettlement('/api/v1/cnpj', { cnpj }, walletProvider);
  }

  async queryCep(code: string, walletProvider?: any): Promise<any> {
    return this.executeWithAutoSettlement('/api/v1/cep', { code }, walletProvider);
  }

  async queryForex(walletProvider?: any): Promise<any> {
    return this.executeWithAutoSettlement('/api/v1/forex', {}, walletProvider);
  }

  async queryCrypto(walletProvider?: any): Promise<any> {
    return this.executeWithAutoSettlement('/api/v1/crypto', {}, walletProvider);
  }

  async queryDns(domain: string, walletProvider?: any): Promise<any> {
    return this.executeWithAutoSettlement('/api/v1/dns', { domain }, walletProvider);
  }

  async querySsl(host: string, walletProvider?: any): Promise<any> {
    return this.executeWithAutoSettlement('/api/v1/ssl', { host }, walletProvider);
  }

  async queryAsn(ip: string, walletProvider?: any): Promise<any> {
    return this.executeWithAutoSettlement('/api/v1/asn', { ip }, walletProvider);
  }

  async queryIndicators(walletProvider?: any): Promise<any> {
    return this.executeWithAutoSettlement('/api/v1/indicators', {}, walletProvider);
  }

  async queryHolidays(year?: number | string, walletProvider?: any): Promise<any> {
    const params: Record<string, string> = {};
    if (year !== undefined && year !== null) {
      params.year = String(year);
    }
    return this.executeWithAutoSettlement('/api/v1/holidays', params, walletProvider);
  }

  async queryWorkdays(params: Record<string, any> = {}, walletProvider?: any): Promise<any> {
    const queryMap: Record<string, string> = {};
    if (params.start_date || params.startDate) queryMap.start_date = params.start_date || params.startDate;
    if (params.end_date || params.endDate) queryMap.end_date = params.end_date || params.endDate;
    if (params.days !== undefined && params.days !== null) {
      queryMap.days = String(params.days);
    }
    if (params.date) queryMap.date = String(params.date);
    return this.executeWithAutoSettlement('/api/v1/workdays', queryMap, walletProvider);
  }

  async querySanctions(cnpj: string, walletProvider?: any): Promise<any> {
    return this.executeWithAutoSettlement('/api/v1/sanctions', { cnpj }, walletProvider);
  }

  async querySimples(cnpj: string, walletProvider?: any): Promise<any> {
    return this.executeWithAutoSettlement('/api/v1/simples', { cnpj }, walletProvider);
  }

  async queryCompliance(cnpj: string, walletProvider?: any): Promise<any> {
    return this.executeWithAutoSettlement('/api/v1/compliance', { cnpj }, walletProvider);
  }

  async queryDossier360(cnpj: string, walletProvider?: any): Promise<any> {
    return this.executeWithAutoSettlement('/api/v1/dossier', { cnpj }, walletProvider);
  }

  async queryDomainAudit(domain: string, walletProvider?: any): Promise<any> {
    return this.executeWithAutoSettlement('/api/v1/domain-audit', { domain }, walletProvider);
  }

  async queryKybAudit(cnpj: string, walletProvider?: any): Promise<any> {
    return this.executeWithAutoSettlement('/api/v1/kyb-audit', { cnpj }, walletProvider);
  }

  async queryMarketSnapshot(walletProvider?: any): Promise<any> {
    return this.executeWithAutoSettlement('/api/v1/market-snapshot', {}, walletProvider);
  }

  async queryLei(lei: string, walletProvider?: any): Promise<any> {
    return this.executeWithAutoSettlement('/api/v1/lei', { lei }, walletProvider);
  }

  async queryOfac(target: string, walletProvider?: any): Promise<any> {
    return this.executeWithAutoSettlement('/api/v1/ofac', { target }, walletProvider);
  }

  async queryNotarize(content: string, walletProvider?: any): Promise<any> {
    return this.executeWithAutoSettlement('/api/v1/notarize', { content }, walletProvider);
  }

  async queryGlobalMarkets(params: Record<string, string> = {}, walletProvider?: any): Promise<any> {
    return this.executeWithAutoSettlement('/api/v1/global-markets', params, walletProvider);
  }

  async queryExtract(url: string, maxChars?: number, walletProvider?: any): Promise<any> {
    const params: Record<string, string> = { url };
    if (maxChars !== undefined && maxChars !== null) params.max_chars = String(maxChars);
    return this.executeWithAutoSettlement('/api/v1/extract', params, walletProvider);
  }

  async queryEmailVerify(email: string, walletProvider?: any): Promise<any> {
    return this.executeWithAutoSettlement('/api/v1/email-verify', { email }, walletProvider);
  }

  async verifyProof(payload: any): Promise<any> {
    const postPayload = typeof payload === 'string' ? payload : JSON.stringify(payload);
    const res = await fetch(`${this.baseUrl}/api/v1/verify`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'User-Agent': 'Grid402-CoinbaseAgentKit-Provider/2.0',
      },
      body: postPayload,
    });
    return await res.json();
  }

  async getSummary(): Promise<any> {
    return this.executeWithAutoSettlement('/status', {});
  }

  // Aliases compativeis com a convencao snake_case das ferramentas MCP
  get_summary = this.getSummary.bind(this);
  get_cnpj = this.queryCnpj.bind(this);
  get_cep = this.queryCep.bind(this);
  get_forex = this.queryForex.bind(this);
  get_crypto = this.queryCrypto.bind(this);
  get_dns = this.queryDns.bind(this);
  get_ssl = this.querySsl.bind(this);
  get_asn = this.queryAsn.bind(this);
  get_indicators = this.queryIndicators.bind(this);
  get_holidays = this.queryHolidays.bind(this);
  get_workdays = this.queryWorkdays.bind(this);
  get_sanctions = this.querySanctions.bind(this);
  get_simples = this.querySimples.bind(this);
  get_compliance = this.queryCompliance.bind(this);
  get_dossier_360 = this.queryDossier360.bind(this);
  get_domain_audit = this.queryDomainAudit.bind(this);
  get_kyb_audit = this.queryKybAudit.bind(this);
  get_market_snapshot = this.queryMarketSnapshot.bind(this);
  get_lei = this.queryLei.bind(this);
  get_ofac = this.queryOfac.bind(this);
  notarize = this.queryNotarize.bind(this);
  get_global_markets = this.queryGlobalMarkets.bind(this);
  extract = this.queryExtract.bind(this);
  email_verify = this.queryEmailVerify.bind(this);
  verify_proof = this.verifyProof.bind(this);
}

export function grid402ActionProvider(config?: Grid402Config): Grid402ActionProvider {
  return new Grid402ActionProvider(config);
}
