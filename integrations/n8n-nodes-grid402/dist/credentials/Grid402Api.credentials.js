'use strict';

class Grid402Api {
  constructor() {
    this.name = 'grid402Api';
    this.displayName = 'Grid402 API (Session Pass Pix / RapidAPI / Sandbox)';
    this.documentationUrl = 'https://grid402.online/pass?tier=starter&autopix=1';
    this.properties = [
      {
        displayName: 'Modo de Autenticação',
        name: 'authMode',
        type: 'options',
        default: 'sessionPass',
        options: [
          {
            name: 'Session Pass Pré-Pago (Pix / Cartão / USDC — <15ms)',
            value: 'sessionPass',
            description: 'Usa um token pass_g402_... adquirido em https://grid402.online/pass (a partir de R$ 12 = 400 créditos)',
          },
          {
            name: 'Sandbox Gratuito (3 consultas/dia por IP)',
            value: 'sandbox',
            description: 'Envia X-Payment: demo para testar fluxos gratuitamente (limite de 3 consultas/dia por IP)',
          },
          {
            name: 'RapidAPI Hub (X-RapidAPI-Key)',
            value: 'rapidApi',
            description: 'Autentica via assinatura mensal em USD no RapidAPI Hub',
          },
        ],
      },
      {
        displayName: 'Session Pass Token (pass_g402_...)',
        name: 'sessionPass',
        type: 'string',
        typeOptions: {
          password: true,
        },
        default: '',
        placeholder: 'pass_g402_...',
        description: 'Adquira em 10 segundos via Pix (R$ 12 = 400 créditos) em https://grid402.online/pass?tier=starter&autopix=1',
        displayOptions: {
          show: {
            authMode: ['sessionPass'],
          },
        },
      },
      {
        displayName: 'RapidAPI Key',
        name: 'rapidApiKey',
        type: 'string',
        typeOptions: {
          password: true,
        },
        default: '',
        description: 'Chave X-RapidAPI-Key da sua assinatura no RapidAPI Hub',
        displayOptions: {
          show: {
            authMode: ['rapidApi'],
          },
        },
      },
      {
        displayName: 'Endpoint Base',
        name: 'baseUrl',
        type: 'string',
        default: 'https://grid402.online',
        description: 'URL base do gateway Grid402',
      },
    ];
  }
}

module.exports = { Grid402Api };
