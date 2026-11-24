'use strict';

class Grid402 {
  constructor() {
    this.description = {
      displayName: 'Grid402 (CNPJ, KYB 360, ANBIMA, Web & Network)',
      name: 'grid402',
      icon: 'file:grid402.svg',
      group: ['transform'],
      version: 1,
      subtitle: '={{$parameter["operation"]}}',
      description: '25 APIs públicas verificáveis (Zero-PII): Dossiê KYB 360 IA, Receita Federal CNPJ, Simples/MEI, Sanções CGU, Dias Úteis ANBIMA, CEP, Câmbio PTAX, Web-to-Markdown, Email Verify B2B, GLEIF LEI, OFAC e DNS/SSL.',
      defaults: {
        name: 'Grid402',
      },
      inputs: ['main'],
      outputs: ['main'],
      usableAsTool: true,
      credentials: [
        {
          name: 'grid402Api',
          required: false,
        },
      ],
      properties: [
        {
          displayName: 'Operação',
          name: 'operation',
          type: 'options',
          noDataExpression: true,
          default: 'dossier',
          options: [
            { name: 'Dossiê 360º KYB Brasil + Veredito IA (10 cr)', value: 'dossier', description: 'Receita Federal CNPJ + Simples/MEI + Sanções CGU CEIS/CNEP + Score 0-100 e Veredito IA (APPROVED/FLAGGED/REJECTED)' },
            { name: 'Consultar CNPJ Receita Federal (2 cr)', value: 'cnpj', description: 'Dados cadastrais públicos de Pessoa Jurídica (Zero-PII: sem QSA/CPF)' },
            { name: 'Simples Nacional e MEI por CNPJ (2 cr)', value: 'simples', description: 'Verifica opção pelo Simples Nacional e enquadramento SIMEI (MEI)' },
            { name: 'Sanções CGU CEIS e CNEP por CNPJ (2 cr)', value: 'sanctions', description: 'Consulta Cadastro de Empresas Inidôneas e Suspensas (CEIS) e Punidas (CNEP)' },
            { name: 'Compliance Score 360 por CNPJ (6 cr)', value: 'compliance', description: 'Score de integridade corporativa 0-100 cruzando Receita Federal e CGU' },
            { name: 'Auditoria KYB Global: CNPJ + OFAC + LEI (5 cr)', value: 'kyb-audit', description: 'Receita Federal CNPJ + Sanções OFAC/ONU + Identidade Global GLEIF LEI' },
            { name: 'Verificar E-mail Corporativo B2B Zero-PII (1 cr)', value: 'email-verify', description: 'Valida sintaxe RFC, DNS MX, SPF, DMARC, domínio descartável e score 0-100 sem reter PII' },
            { name: 'Extrair Site para Markdown Limpo / RAG (1 cr)', value: 'extract', description: 'Converte URL pública HTTP/HTTPS em Markdown limpo para LLMs com proteção anti-SSRF' },
            { name: 'Consultar CEP Brasileiro + Código IBGE (1 cr)', value: 'cep', description: 'Logradouro, bairro, município, UF e código oficial IBGE' },
            { name: 'Dias Úteis Bancários ANBIMA D+N (1 cr)', value: 'workdays', description: 'Calcula dias úteis financeiros ou projeta vencimentos na convenção ANBIMA' },
            { name: 'Feriados Nacionais e Bancários ANBIMA (1 cr)', value: 'holidays', description: 'Calendário oficial de feriados nacionais e bancários por ano' },
            { name: 'Indicadores BCB: Selic, CDI e IPCA (1 cr)', value: 'indicators', description: 'Taxas oficiais Selic meta/diária, CDI anual/diário e IPCA 12m/mensal' },
            { name: 'Câmbio PTAX BCB: USD, EUR, GBP para BRL (1 cr)', value: 'forex', description: 'Cotações oficiais de câmbio com variação diária' },
            { name: 'Preços Cripto Spot: BTC, ETH, SOL, USDC (1 cr)', value: 'crypto', description: 'Preços spot em tempo real em USD e BRL' },
            { name: 'Panorama Financeiro Consolidado (3 cr)', value: 'market-snapshot', description: 'Câmbio PTAX + Cripto Spot + Selic/CDI/IPCA + Calendário ANBIMA em 1 chamada' },
            { name: 'Mercados Globais e 30+ Moedas BCE (1 cr)', value: 'global-markets', description: 'Calendário NYSE, TARGET2, LSE, B3 e taxas de referência do Banco Central Europeu' },
            { name: 'Resolução DNS Autoritativa (1 cr)', value: 'dns', description: 'Registros A, AAAA, MX, TXT, NS e CNAME de um domínio' },
            { name: 'Inspeção de Certificado TLS/SSL (1 cr)', value: 'ssl', description: 'Emissor, validade, SANs e dias restantes até expirar na porta 443' },
            { name: 'Inteligência BGP e ASN de IP (1 cr)', value: 'asn', description: 'Número ASN, bloco CIDR, organização e país de origem' },
            { name: 'Auditoria Completa de Domínio: DNS + SSL + ASN (3 cr)', value: 'domain-audit', description: 'Auditoria técnica consolidada de domínio em 1 chamada' },
            { name: 'Identidade Corporativa Global GLEIF LEI (2 cr)', value: 'lei', description: 'Consulta código ISO 17442 LEI em 200+ jurisdições (CC0 1.0)' },
            { name: 'Triagem Global OFAC SDN / ONU e AML Web3 (4 cr)', value: 'ofac', description: 'Triagem de sanções internacionais para carteiras 0x ou entidades' },
            { name: 'Notarização Criptográfica SHA-256 + ECDSA (1 cr)', value: 'notarize', description: 'Carimbo do tempo M2M e prova de existência Zero-Retention' },
            { name: 'Consultar Saldo do Session Pass (Gratuito)', value: 'pass-balance', description: 'Verifica créditos restantes, validade e Spend Controls do seu pass_g402_...' },
            { name: 'Status Geral dos 25 Serviços (Gratuito)', value: 'summary', description: 'Resumo de saúde e latência de todos os 25 serviços do Grid402' },
          ],
        },
        {
          displayName: 'CNPJ',
          name: 'cnpj',
          type: 'string',
          default: '',
          placeholder: '00.000.000/0001-91',
          description: 'CNPJ de 14 dígitos (com ou sem pontuação)',
          displayOptions: {
            show: {
              operation: ['dossier', 'cnpj', 'simples', 'sanctions', 'compliance', 'kyb-audit'],
            },
          },
        },
        {
          displayName: 'E-mail Corporativo',
          name: 'email',
          type: 'string',
          default: '',
          placeholder: 'contato@empresa.com.br',
          description: 'Endereço de e-mail para auditoria Zero-PII de entregabilidade (MX/SPF/DMARC)',
          displayOptions: {
            show: {
              operation: ['email-verify'],
            },
          },
        },
        {
          displayName: 'URL Pública (HTTP/HTTPS)',
          name: 'url',
          type: 'string',
          default: '',
          placeholder: 'https://empresa.com.br',
          description: 'Página pública a ser convertida em Markdown limpo para IA/RAG',
          displayOptions: {
            show: {
              operation: ['extract'],
            },
          },
        },
        {
          displayName: 'Limite Máximo de Caracteres',
          name: 'maxChars',
          type: 'number',
          default: 12000,
          description: 'Tamanho máximo do Markdown retornado (500 a 25000)',
          displayOptions: {
            show: {
              operation: ['extract'],
            },
          },
        },
        {
          displayName: 'CEP',
          name: 'code',
          type: 'string',
          default: '',
          placeholder: '01310100',
          description: 'CEP brasileiro de 8 dígitos',
          displayOptions: {
            show: {
              operation: ['cep'],
            },
          },
        },
        {
          displayName: 'Domínio ou Host',
          name: 'domain',
          type: 'string',
          default: '',
          placeholder: 'github.com',
          description: 'Domínio ou hostname alvo',
          displayOptions: {
            show: {
              operation: ['dns', 'ssl', 'domain-audit'],
            },
          },
        },
        {
          displayName: 'Endereço IP (IPv4 ou IPv6)',
          name: 'ip',
          type: 'string',
          default: '1.1.1.1',
          description: 'Endereço IP para consulta de ASN/BGP',
          displayOptions: {
            show: {
              operation: ['asn'],
            },
          },
        },
        {
          displayName: 'Data Inicial (AAAA-MM-DD)',
          name: 'startDate',
          type: 'string',
          default: '',
          placeholder: '2026-11-21',
          description: 'Data de início para cálculo ou projeção de dias úteis ANBIMA',
          displayOptions: {
            show: {
              operation: ['workdays'],
            },
          },
        },
        {
          displayName: 'Data Final (AAAA-MM-DD) — Opcional',
          name: 'endDate',
          type: 'string',
          default: '',
          placeholder: '2026-12-31',
          description: 'Preencha para contar dias úteis entre Data Inicial e Data Final',
          displayOptions: {
            show: {
              operation: ['workdays'],
            },
          },
        },
        {
          displayName: 'Dias Úteis a Projetar (D+N) — Opcional',
          name: 'days',
          type: 'number',
          default: 5,
          description: 'Quantidade de dias úteis a somar à Data Inicial (usado quando Data Final está vazia)',
          displayOptions: {
            show: {
              operation: ['workdays'],
            },
          },
        },
        {
          displayName: 'Ano Calendário',
          name: 'year',
          type: 'number',
          default: 2026,
          description: 'Ano de 4 dígitos para consulta de feriados ANBIMA',
          displayOptions: {
            show: {
              operation: ['holidays'],
            },
          },
        },
        {
          displayName: 'Código LEI (ISO 17442)',
          name: 'lei',
          type: 'string',
          default: '',
          placeholder: '5493006MHB84DD0ZWV18',
          description: 'Código LEI de 20 caracteres alfanuméricos',
          displayOptions: {
            show: {
              operation: ['lei'],
            },
          },
        },
        {
          displayName: 'Alvo OFAC (Carteira 0x ou Razão Social)',
          name: 'target',
          type: 'string',
          default: '',
          placeholder: '0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90',
          description: 'Carteira EVM ou nome da entidade para triagem OFAC SDN / ONU',
          displayOptions: {
            show: {
              operation: ['ofac'],
            },
          },
        },
        {
          displayName: 'Conteúdo ou Hash SHA-256 para Notarizar',
          name: 'content',
          type: 'string',
          default: '',
          description: 'Texto ou hash SHA-256 para carimbo do tempo ECDSA Zero-Retention',
          displayOptions: {
            show: {
              operation: ['notarize'],
            },
          },
        },
        {
          displayName: 'Mercado Global (US, EU, UK, BR)',
          name: 'market',
          type: 'string',
          default: 'US',
          displayOptions: {
            show: {
              operation: ['global-markets'],
            },
          },
        },
      ],
    };
  }

  async execute() {
    const items = this.getInputData ? this.getInputData() : [{}];
    const returnData = [];

    let creds = {};
    if (this.getCredentials) {
      try {
        creds = (await this.getCredentials('grid402Api')) || {};
      } catch (_) {
        creds = {};
      }
    }

    const baseUrl = String(creds.baseUrl || 'https://grid402.online').replace(/\/$/, '');
    const defaultRapidApiHost = 'grid402-open-data-kyb-ai-utilities.p.rapidapi.com';
    const rapidApiHost = String(creds.rapidApiHost || defaultRapidApiHost)
      .replace(/^https?:\/\//i, '')
      .replace(/\/.*$/, '')
      .trim() || defaultRapidApiHost;
    const authMode = creds.authMode || '';
    const canContinueOnFail = () => Boolean(typeof this.continueOnFail === 'function' && this.continueOnFail());

    for (let i = 0; i < items.length; i++) {
      const getParam = (name, fallback = '') => {
        if (this.getNodeParameter) {
          try {
            return this.getNodeParameter(name, i, fallback);
          } catch (_) {
            return fallback;
          }
        }
        return items[i][name] !== undefined ? items[i][name] : fallback;
      };

      const operation = getParam('operation', 'dossier');
      const isFreeOperation = operation === 'summary' || operation === 'pass-balance';
      const qs = new URLSearchParams();
      let path = `/api/v1/${operation}`;

      if (operation === 'summary') {
        path = '/status';
      } else if (operation === 'pass-balance') {
        path = '/api/v1/pass/balance';
        if (creds.sessionPass) qs.set('token', String(creds.sessionPass).trim());
      } else if (['dossier', 'cnpj', 'simples', 'sanctions', 'compliance', 'kyb-audit'].includes(operation)) {
        const cleanCnpj = String(getParam('cnpj', '')).replace(/\D/g, '');
        qs.set('cnpj', cleanCnpj);
      } else if (operation === 'email-verify') {
        qs.set('email', String(getParam('email', '')).trim());
      } else if (operation === 'extract') {
        qs.set('url', String(getParam('url', '')).trim());
        const maxChars = getParam('maxChars', 12000);
        if (maxChars) qs.set('max_chars', String(maxChars));
      } else if (operation === 'cep') {
        qs.set('code', String(getParam('code', '')).replace(/\D/g, ''));
      } else if (operation === 'dns' || operation === 'domain-audit') {
        qs.set('domain', String(getParam('domain', '')).trim());
      } else if (operation === 'ssl') {
        qs.set('host', String(getParam('domain', '')).trim());
      } else if (operation === 'asn') {
        const ip = String(getParam('ip', '')).trim();
        if (ip) qs.set('ip', ip);
      } else if (operation === 'workdays') {
        const startDate = String(getParam('startDate', '')).trim();
        const endDate = String(getParam('endDate', '')).trim();
        const days = getParam('days', 5);
        if (startDate) qs.set('start_date', startDate);
        if (endDate) {
          qs.set('end_date', endDate);
        } else if (days !== '' && days !== null && days !== undefined) {
          qs.set('days', String(days));
        }
      } else if (operation === 'holidays') {
        const year = getParam('year', 2026);
        if (year) qs.set('year', String(year));
      } else if (operation === 'lei') {
        qs.set('lei', String(getParam('lei', '')).trim());
      } else if (operation === 'ofac') {
        qs.set('target', String(getParam('target', '')).trim());
      } else if (operation === 'notarize') {
        qs.set('content', String(getParam('content', '')));
      } else if (operation === 'global-markets') {
        qs.set('market', String(getParam('market', 'US')).trim());
      }

      const headers = {
        'Accept': 'application/json',
        'User-Agent': 'n8n-nodes-grid402/1.1.0',
      };

      let effectiveBaseUrl = baseUrl;

      if (!isFreeOperation) {
        if (authMode === 'sessionPass') {
          const token = String(creds.sessionPass || '').trim();
          if (!token) {
            const errMsg = 'Credencial Grid402 incompleta: modo Session Pass selecionado, mas o token pass_g402_... está vazio. Adquira via Pix/Cartão em https://grid402.online/pass?tier=starter&autopix=1 ou selecione explicitamente o modo Sandbox.';
            if (canContinueOnFail()) {
              returnData.push({ json: { error: 'MissingSessionPass', message: errMsg }, pairedItem: { item: i } });
              continue;
            }
            throw new Error(errMsg);
          }
          headers['X-Payment'] = token;
        } else if (authMode === 'rapidApi') {
          const key = String(creds.rapidApiKey || '').trim();
          if (!key) {
            const errMsg = 'Credencial Grid402 incompleta: modo RapidAPI selecionado, mas a X-RapidAPI-Key está vazia. Informe sua chave RapidAPI Hub ou troque o modo de autenticação.';
            if (canContinueOnFail()) {
              returnData.push({ json: { error: 'MissingRapidApiKey', message: errMsg }, pairedItem: { item: i } });
              continue;
            }
            throw new Error(errMsg);
          }
          let hostHeader = rapidApiHost;
          if (baseUrl.includes('.p.rapidapi.com')) {
            try {
              hostHeader = new URL(baseUrl).host;
            } catch (_) {
              hostHeader = rapidApiHost;
            }
          } else {
            effectiveBaseUrl = `https://${rapidApiHost}`;
          }
          headers['X-RapidAPI-Key'] = key;
          headers['X-RapidAPI-Host'] = hostHeader;
        } else if (authMode === 'sandbox') {
          headers['X-Payment'] = 'demo';
        } else {
          const errMsg = 'Credencial Grid402 não configurada para operação paga. Configure Grid402 API com Session Pass (https://grid402.online/pass), RapidAPI Hub ou selecione explicitamente o modo Sandbox (3 req/dia).';
          if (canContinueOnFail()) {
            returnData.push({ json: { error: 'MissingCredentials', message: errMsg }, pairedItem: { item: i } });
            continue;
          }
          throw new Error(errMsg);
        }
      }

      const queryString = qs.toString();
      const fullUrl = `${effectiveBaseUrl}${path}${queryString ? '?' + queryString : ''}`;

      const response = await fetch(fullUrl, { method: 'GET', headers });
      const data = await response.json().catch(() => ({ status: response.status }));

      if (response.status === 402 || response.status === 429) {
        data._n8n_upgrade_instructions = {
          pix_instant_10s_brl: 'https://grid402.online/pass?tier=starter&autopix=1 (R$ 12 = 400 créditos)',
          rapidapi_card_usd: 'https://rapidapi.com/oliveiratc/api/grid402-open-data-kyb-ai-utilities',
          how_to_configure: 'Copie seu token pass_g402_... após pagar o Pix e cole na credencial Grid402 API do n8n.',
        };
        const statusLabel = response.status === 402 ? 'HTTP 402 Payment Required' : 'HTTP 429 Rate Limit Exceeded';
        const actionableMsg = `[Grid402 ${statusLabel}] ${data.message || data.reason || data.error || 'Créditos ou assinatura necessários.'} — Adquira Session Pass via Pix/Cartão em https://grid402.online/pass?tier=starter&autopix=1 ou assine no RapidAPI Hub.`;
        if (canContinueOnFail()) {
          returnData.push({ json: { ...data, http_status: response.status, error_message: actionableMsg }, pairedItem: { item: i } });
          continue;
        }
        throw new Error(actionableMsg);
      }

      if (response.status >= 400) {
        const errMsg = `[Grid402 HTTP ${response.status}] ${data.message || data.error || 'Falha na requisição'}`;
        if (canContinueOnFail()) {
          returnData.push({ json: { ...data, http_status: response.status, error_message: errMsg }, pairedItem: { item: i } });
          continue;
        }
        throw new Error(errMsg);
      }

      returnData.push({ json: data, pairedItem: { item: i } });
    }

    return [returnData];
  }
}

module.exports = { Grid402 };
