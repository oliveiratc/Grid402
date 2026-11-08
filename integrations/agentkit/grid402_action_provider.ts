/**
 * Grid402 Action Provider para Coinbase AgentKit
 * 
 * Permite que agentes autonomos com carteiras CDP (Coinbase Developer Platform)
 * na rede Base consultem dados cadastrais e utilitarios de rede com auto-liquidacao x402.
 */

export interface Grid402Config {
  baseUrl?: string;
  sandbox?: boolean;
}

export const GRID402_ORACLE_WALLET = '0xa31c31069c43ac1c0075a93c325b0dcd7744356c';
export const BASE_USDC_CONTRACT = '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913';

export class Grid402ActionProvider {
  private baseUrl: string;
  private sandbox: boolean;

  constructor(config?: Grid402Config) {
    this.baseUrl = config?.baseUrl || 'https://grid402.com.br';
    this.sandbox = config?.sandbox || false;
  }

  /**
   * Executa a requisicao HTTP com tratamento de auto-liquidacao via carteira CDP
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
      'User-Agent': 'Grid402-CoinbaseAgentKit-Provider/1.0',
    };

    if (this.sandbox) {
      headers['X-Payment'] = 'demo';
    }

    // 1. Primeira tentativa de consulta
    let res = await fetch(url.toString(), { headers });
    let json = await res.json();

    if (res.status === 200) {
      return json;
    }

    // 2. Se retornou HTTP 402 Payment Required e temos um walletProvider
    if (res.status === 402 && walletProvider && !this.sandbox) {
      const priceDisplay = json.price_usdc || '0.0005';
      const recipient = json.recipient_wallet || GRID402_ORACLE_WALLET;

      // Executa transferencia de USDC na Base usando a carteira do agente CDP
      const tx = await walletProvider.transfer({
        to: recipient,
        amount: priceDisplay,
        assetId: 'usdc',
      });

      const txHash = tx.getTransactionHash ? tx.getTransactionHash() : (tx.hash || tx);

      // Reenvia a requisicao com o comprovante de pagamento
      headers['X-Payment'] = txHash;
      res = await fetch(url.toString(), { headers });
      json = await res.json();

      return {
        ...json,
        _x402_settlement: {
          settled: true,
          tx_hash: txHash,
          price_usdc: priceDisplay,
          network: 'Base',
        },
      };
    }

    return json;
  }

  // --- Ferramentas Expostas ao Agente ---

  /**
   * Consulta cadastral oficial de Pessoa Juridica na Receita Federal
   */
  async queryCnpj(cnpj: string, walletProvider?: any): Promise<any> {
    return this.executeWithAutoSettlement('/api/v1/cnpj', { cnpj }, walletProvider);
  }

  /**
   * Resolucao de endereco postal e codigo IBGE a partir do CEP
   */
  async queryCep(code: string, walletProvider?: any): Promise<any> {
    return this.executeWithAutoSettlement('/api/v1/cep', { code }, walletProvider);
  }

  /**
   * Cotacoes cambiais oficiais de USD, EUR e GBP para BRL
   */
  async queryForex(walletProvider?: any): Promise<any> {
    return this.executeWithAutoSettlement('/api/v1/forex', {}, walletProvider);
  }

  /**
   * Precos spot de mercado de BTC, ETH, SOL e USDC
   */
  async queryCrypto(walletProvider?: any): Promise<any> {
    return this.executeWithAutoSettlement('/api/v1/crypto', {}, walletProvider);
  }

  /**
   * Resolucao autoritativa de registros DNS (A, AAAA, MX, TXT, NS, CNAME)
   */
  async queryDns(domain: string, walletProvider?: any): Promise<any> {
    return this.executeWithAutoSettlement('/api/v1/dns', { domain }, walletProvider);
  }

  /**
   * Inspecao e auditoria de certificados TLS/SSL de hosts remotos
   */
  async querySsl(host: string, walletProvider?: any): Promise<any> {
    return this.executeWithAutoSettlement('/api/v1/ssl', { host }, walletProvider);
  }

  /**
   * Identificacao de ASN e organizacao titular de roteamento BGP
   */
  async queryAsn(ip: string, walletProvider?: any): Promise<any> {
    return this.executeWithAutoSettlement('/api/v1/asn', { ip }, walletProvider);
  }
  /**
   * Resumo consolidado do hub de dados e utilitarios (gratuito)
   */
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
}

export function grid402ActionProvider(config?: Grid402Config): Grid402ActionProvider {
  return new Grid402ActionProvider(config);
}
