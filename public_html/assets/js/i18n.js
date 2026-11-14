/**
 * Grid402 // Modulo de Internacionalizacao (i18n) Client-Side
 * Suporte a Portugues do Brasil (PT-BR) e Ingles (EN)
 * Regra: Todos os '&' em textos foram substituidos por 'e' (ou 'and' em ingles)
 */

const GRID402_I18N = {
    pt: {
        // Navegacao e Header
        'nav.services': 'ServiÃ§os',
        'nav.topup': 'Abastecer Saldo',
        'nav.savings': 'Economia 98%',
        'nav.playground': 'Playground',
        'nav.console': 'Console',
        'nav.agents': 'Agentes IA',
        'nav.about': 'Sobre',
        'nav.contact': 'Contato',
        'nav.terms': 'Termos',
        'nav.privacy': 'Privacidade',
        'badge.live': 'Base Mainnet (8453) â€¢ {count}/{total} ServiÃ§os Operantes',

        // Hero
        'hero.tag': 'Hub Global de Micro-ServiÃ§os e OrÃ¡culo M2M para Agentes de IA',
        'hero.title': 'Grid402: Infraestrutura Global de Dados PÃºblicos e UtilitÃ¡rios M2M para Agentes de IA',
        'hero.desc': 'Acesse 22 endpoints globais e brasileiros: identidade corporativa mundial (GLEIF LEI em 200+ paÃ­ses), sanÃ§Ãµes OFAC SDN + AML Web3 na Base, notarizaÃ§Ã£o criptogrÃ¡fica ECDSA, auditoria KYB completa (CNPJ + OFAC + LEI), auditoria de domÃ­nio (DNS + SSL + ASN), snapshot consolidado de mercado (Forex + Cripto + Indicadores), bolsas mundiais e 30+ moedas do BCE, cÃ¢mbio, cripto, compliance 360, Selic/CDI/IPCA, feriados ANBIMA, DNS, SSL, BGP/ASN, CEP e CNPJ em milissegundos sem cadastro ou API keys. LiquidaÃ§Ã£o via x402 ou Session Pass prÃ©-pago (<15ms).',
        'hero.btn.topup': 'ðŸ’Ž Abastecer Saldo Web3 (Base)',
        'hero.btn.agents': 'ðŸ¤– Central de Agentes IA',
        'hero.btn.playground': 'âš¡ Testar no Playground',
        'hero.btn.console': 'Console e SDKs',
        'hero.btn.llms': 'DocumentaÃ§Ã£o llms.txt',
        'hero.btn.status': 'Resumo Gratuito (JSON)',

        // Topup Tiers Web3
        'topup.tag': 'âš¡ LiquidaÃ§Ã£o InstantÃ¢nea em Lote na Base (USDC)',
        'topup.title': 'AbasteÃ§a seu Saldo Web3 em 1 Clique (Session Passes)',
        'topup.desc': 'Pague uma Ãºnica vez em USDC na rede Base Mainnet e receba na hora um token pass_g402_... com saldo de milhares de requisiÃ§Ãµes de alta velocidade (<15ms) sem assinar transaÃ§Ãµes a cada GET.',
        'topup.starter.tag': 'Starter Pass',
        'topup.starter.desc': 'Ideal para desenvolvedores individuais, testes rÃ¡pidos e bots pontuais.',
        'topup.starter.btn': 'Comprar Starter (2 USDC)',
        'topup.pro.badge': 'Mais Escolhido',
        'topup.pro.tag': 'Pro / Agent Pass',
        'topup.pro.desc': 'Para agentes autÃ´nomos de IA e esteiras de automaÃ§Ã£o contÃ­nua.',
        'topup.pro.btn': 'Comprar Pro (5 USDC)',
        'topup.fleet.badge': '+10.000 BÃ´nus',
        'topup.fleet.tag': 'Fleet / Enterprise',
        'topup.fleet.desc': 'Frotas de agentes autÃ´nomos, sistemas ERP corporativos e alta demanda.',
        'topup.fleet.btn': 'Comprar Fleet (20 USDC)',

        // Cards dos 14 Servicos Publicos
        'svc.forex.name': 'CÃ¢mbio e Moedas',
        'svc.forex.category': 'Mercado',
        'svc.forex.desc': 'CotaÃ§Ãµes em tempo real de USD, EUR, GBP para BRL com variaÃ§Ã£o diÃ¡ria',
        'svc.crypto.name': 'Cripto Tickers',
        'svc.crypto.category': 'Mercado',
        'svc.crypto.desc': 'PreÃ§os de mercado de BTC, ETH, SOL, USDC e BASE em USD e BRL',
        'svc.dns.name': 'DNS Records Lookup',
        'svc.dns.category': 'Rede',
        'svc.dns.desc': 'ResoluÃ§Ã£o autoritativa de registros A, AAAA, MX, TXT, NS e CNAME',
        'svc.ssl.name': 'Inspetor TLS/SSL',
        'svc.ssl.category': 'Rede',
        'svc.ssl.desc': 'InspeÃ§Ã£o de cadeia X.509, emissor, SANs e dias atÃ© expiraÃ§Ã£o na porta 443',
        'svc.asn.name': 'ASN e IP Info',
        'svc.asn.category': 'Rede',
        'svc.asn.desc': 'Metadados de roteamento BGP, nÃºmero de ASN, bloco IP e paÃ­s',
        'svc.cep.name': 'CEP e IBGE',
        'svc.cep.category': 'Dados Abertos',
        'svc.cep.desc': 'ResoluÃ§Ã£o de logradouro, bairro, municÃ­pio, UF e cÃ³digo IBGE oficial',
        'svc.cnpj.name': 'Consulta Cadastral CNPJ',
        'svc.cnpj.category': 'Corporativo',
        'svc.cnpj.desc': 'SituaÃ§Ã£o cadastral, razÃ£o social, CNAE, data de abertura e endereÃ§o da PJ',
        'svc.indicators.name': 'Indicadores EconÃ´micos',
        'svc.indicators.category': 'Mercado',
        'svc.indicators.desc': 'Taxas oficiais Selic (meta e diÃ¡ria), CDI anual e diÃ¡rio, IPCA acumulado 12m e mensal (BCB e IBGE)',
        'svc.holidays.name': 'Feriados e CalendÃ¡rio BancÃ¡rio',
        'svc.holidays.category': 'Dados Abertos',
        'svc.holidays.desc': 'Feriados nacionais oficiais e dias sem expediente bancÃ¡rio (ANBIMA e Febraban) com cÃ¡lculo determinÃ­stico',
        'svc.workdays.name': 'Calculadora de Dias Ãšteis ANBIMA',
        'svc.workdays.category': 'Financeiro',
        'svc.workdays.desc': 'CÃ¡lculo de dias Ãºteis bancÃ¡rios (contagem entre datas, projeÃ§Ã£o de prazos e checagem pontual) no padrÃ£o ANBIMA',
        'svc.sanctions.name': 'SanÃ§Ãµes e Inidoneidade (CEIS/CNEP)',
        'svc.sanctions.category': 'Compliance',
        'svc.sanctions.desc': 'Consulta cadastros oficiais da CGU (CEIS e CNEP) sobre puniÃ§Ãµes e impedimentos de contratar',
        'svc.simples.name': 'Simples Nacional e MEI',
        'svc.simples.category': 'Fiscal',
        'svc.simples.desc': 'Consulta oficial de enquadramento tributÃ¡rio no Simples Nacional e SIMEI na Receita Federal',
        'svc.compliance.name': 'Compliance Score 360',
        'svc.compliance.category': 'Compliance',
        'svc.compliance.desc': 'Score unificado de risco cadastral (0 a 100) cruzando Receita Federal, CGU e histÃ³rico societÃ¡rio',
        'svc.verify.name': 'Verificador CriptogrÃ¡fico',
        'svc.verify.category': 'SeguranÃ§a',
        'svc.verify.desc': 'ValidaÃ§Ã£o matemÃ¡tica local e instantÃ¢nea de assinatura ECDSA secp256k1 e prova de orÃ¡culo (_oracle_proof)',
        'svc.lei.name': 'Identidade Corporativa Global (LEI)',
        'svc.lei.category': 'Global (CC0)',
        'svc.lei.desc': 'Consulta oficial de empresas em 200+ jurisdiÃ§Ãµes via GLEIF LEI (ISO 17442, licenÃ§a CC0 1.0 Universal)',
        'svc.ofac.name': 'SanÃ§Ãµes Globais (OFAC/ONU) e AML Web3',
        'svc.ofac.category': 'Global Compliance',
        'svc.ofac.desc': 'Screening em listas OFAC SDN (US Treasury) e ONU + inspeÃ§Ã£o on-chain de carteiras EVM na Base Mainnet',
        'svc.notarize.name': 'Carimbo do Tempo e NotarizaÃ§Ã£o M2M',
        'svc.notarize.category': 'Criptografia',
        'svc.notarize.desc': 'Emite certificado de existÃªncia e timestamp UTC assinado com ECDSA secp256k1 para contratos, logs e outputs de agentes',
        'svc.global-markets.name': 'Bolsas Mundiais e 30+ Moedas (ECB)',
        'svc.global-markets.category': 'Mercado Global',
        'svc.global-markets.desc': 'CalendÃ¡rio de liquidaÃ§Ã£o de bolsas (US NYSE/Fed, EU TARGET2, UK LSE, BR B3) + 30+ moedas oficiais do Banco Central Europeu',
        'svc.pass.name': 'x402 Prepaid Session Pass',
        'svc.pass.category': 'Alta FrequÃªncia',
        'svc.pass.desc': 'Compre 100 a 1.000 crÃ©ditos prÃ©-pagos em 1 transaÃ§Ã£o USDC na Base e execute chamadas em <15ms sem aguardar blocos',

        // ROI Comparador
        'roi.tag': 'ReduÃ§Ã£o Real de Custos para Startups e Agentes',
        'roi.title': 'Por que pagar R$ 500 por mÃªs se o seu agente pode pagar R$ 0,01 por consulta?',
        'roi.desc': 'Compare o modelo arcaico dos birÃ´s tradicionais (mensalidades obrigatÃ³rias, anÃ¡lise de crÃ©dito e fidelidade de 12 meses) com o modelo aberto M2M do Grid402 liquidado via protocolo x402 na Base.',
        'roi.table.title': 'Comparativo TÃ©cnico e Comercial',
        'roi.table.th.criteria': 'CritÃ©rio',
        'roi.table.th.trad': 'BirÃ´s Tradicionais',
        'roi.table.th.grid': 'Grid402 (x402)',
        'roi.table.cnpj': 'Consulta CNPJ',
        'roi.table.cnpj.trad': 'R$ 0,50 a R$ 2,50',
        'roi.table.cnpj.grid': 'R$ 0,01 (0.0200 USDC)',
        'roi.table.monthly': 'Mensalidade MÃ­nima',
        'roi.table.monthly.trad': 'R$ 500 a R$ 2.000 / mÃªs',
        'roi.table.monthly.grid': 'R$ 0,00 (Zero mensalidade)',
        'roi.table.contract': 'Burocracia e Contrato',
        'roi.table.contract.trad': 'Fidelidade 12 meses',
        'roi.table.contract.grid': 'Zero contrato, sem cadastro',
        'roi.table.auth': 'AutenticaÃ§Ã£o',
        'roi.table.auth.trad': 'API Keys que expiram',
        'roi.table.auth.grid': 'Protocolo x402 nativo',
        'roi.table.math': 'Garantia MatemÃ¡tica',
        'roi.table.math.trad': 'Nenhuma (dados crus)',
        'roi.table.math.grid': 'Assinatura ECDSA secp256k1',
        'roi.table.disclaimer': '* Valores de mercado baseados em planos de entrada dos principais birÃ´s de dados e compliance no Brasil.',
        'roi.calc.title': 'Simulador de Economia em CNPJ e Dados',
        'roi.calc.subtitle': 'Ajuste o volume mensal de consultas do seu robÃ´ ou sistema:',
        'roi.calc.label': 'Volume Mensal de Consultas:',
        'roi.calc.cost.trad': 'Custo Tradicional',
        'roi.calc.cost.grid': 'Custo no Grid402',
        'roi.calc.btn.agents': 'ðŸ¤– Integrar Agente IA',
        'roi.calc.btn.test': 'âš¡ Testar CNPJ',

        // Playground
        'playground.tag': 'Auditoria CriptogrÃ¡fica M2M',
        'playground.title': 'Playground Interativo e Verificador de OrÃ¡culo',
        'playground.desc': 'Execute consultas reais nos micro-serviÃ§os do Grid402 em modo Sandbox gratuito ou simule o desafio x402. Valide instantaneamente a atestaÃ§Ã£o criptogrÃ¡fica ECDSA secp256k1 (_oracle_proof) com o motor matemÃ¡tico em tempo real.',
        'playground.btn.challenge': 'ðŸ”’ Desafio x402 (HTTP 402)',
        'playground.btn.sandbox': 'ðŸ§ª Sandbox (30 consultas/h)',
        'playground.btn.manual': 'âœï¸ Validar JSON Externo',
        'playground.route': 'Rota:',
        'playground.btn.run': 'âš¡ Executar Consulta',
        'playground.config.title': 'ConfiguraÃ§Ã£o da Consulta',
        'playground.manual.desc': 'Cole abaixo qualquer resposta JSON retornada pelo Grid402 ou utilize um dos modelos de teste:',
        'playground.btn.template.valid': 'Carregar Modelo VÃ¡lido',
        'playground.btn.template.tampered': 'Carregar Modelo Adulterado (Falso)',
        'playground.btn.audit.manual': 'ðŸ” Auditar Assinatura deste JSON',
        'playground.viewer.title': 'Retorno da API',
        'playground.status.ready': 'Pronto',
        'playground.audit.title': 'Motor de Auditoria MatemÃ¡tica ECDSA (Browser)',
        'playground.audit.s1.title': '1. Canonicidade do Payload e Hash SHA-256',
        'playground.audit.s1.desc': 'ReconstrÃ³i o payload normalizado em formato canÃ´nico e calcula o hash criptogrÃ¡fico:',
        'playground.audit.s2.title': '2. VerificaÃ§Ã£o da Assinatura secp256k1',
        'playground.audit.s2.desc': 'Valida matematicamente a assinatura digital DER/ECDSA contra o hash canÃ´nico.',
        'playground.audit.s3.title': '3. RecuperaÃ§Ã£o de EndereÃ§o do OrÃ¡culo',
        'playground.audit.s3.desc': 'Confirma que a chave recuperada corresponde exatamente Ã  identidade pÃºblica do orÃ¡culo oficial:',
        'playground.audit.oracle.expected': 'OrÃ¡culo Oficial Esperado:',
        'playground.audit.oracle.recovered': 'Chave Recuperada On-Chain:',
        'playground.btn.copy': 'Copiar JSON',
        'playground.viewer.placeholder': '// Clique em "Executar Consulta" ou selecione um micro-serviÃ§o acima para auditar...',
        'playground.unlock.badge': 'ðŸ”’ DADOS COMPLETOS PROTEGIDOS (HTTP 402)',
        'playground.unlock.desc': 'A amostra gratuita acima foi validada. Desbloqueie agora os campos ocultos e a prova criptogrÃ¡fica ECDSA (_oracle_proof):',
        'playground.unlock.cta_web3': 'âš¡ Desbloquear com Carteira Web3 (1 Clique)',
        'playground.unlock.cta_smart_wallet': 'ðŸ”‘ Passkey / Biometria (Coinbase Smart Wallet)',
        'playground.unlock.cta_qr': 'ðŸ“± QR Code / Celular',
        'playground.unlock.cta_sandbox': 'ðŸ§ª Ver Amostra Completa (Sandbox)',

        // Console
        'console.title': 'Console de Teste e EspecificaÃ§Ãµes de IntegraÃ§Ã£o',
        'console.desc': 'Experimente as requisiÃ§Ãµes em tempo real ou copie os snippets para seus agentes autÃ´nomos.',
        'console.tab.interactive': 'âš¡ Console Interativo',
        'console.tab.smithery': 'ðŸ“¦ Smithery MCP (1 Clique)',
        'console.tab.agentkit': 'ðŸª™ Coinbase AgentKit (M2M)',
        'console.tab.python': 'ðŸ Python SDK',
        'console.tab.node': 'ðŸŸ¢ Node.js SDK',
        'console.tab.curl': 'ðŸ’» cURL CLI',
        'console.tab.badges': 'ðŸ›¡ï¸ Badges SVG',
        'console.act.summary': 'Resumo Gratuito (/status)',
        'console.act.forex': 'CÃ¢mbio (/api/v1/forex)',
        'console.act.crypto': 'Cripto (/api/v1/crypto)',
        'console.act.dns': 'DNS (/api/v1/dns)',
        'console.act.ssl': 'SSL (/api/v1/ssl)',
        'console.act.asn': 'ASN (/api/v1/asn)',
        'console.act.cep': 'CEP (/api/v1/cep)',
        'console.act.cnpj': 'CNPJ (/api/v1/cnpj)',
        'console.act.indicators': 'Indicadores (/api/v1/indicators)',
        'console.act.holidays': 'Feriados (/api/v1/holidays)',
        'console.act.workdays': 'Dias Ãšteis (/api/v1/workdays)',
        'console.act.sanctions': 'SanÃ§Ãµes CGU (/api/v1/sanctions)',
        'console.act.simples': 'Simples Nacional (/api/v1/simples)',
        'console.act.compliance': 'Score 360 (/api/v1/compliance)',
        'console.act.lei': 'ðŸŒ LEI Global (/api/v1/lei)',
        'console.act.ofac': 'ðŸ›¡ï¸ OFAC e AML (/api/v1/ofac)',
        'console.act.notarize': 'â±ï¸ Notarizar (/api/v1/notarize)',
        'console.act.global-markets': 'ðŸŒ Bolsas e ECB (/api/v1/global-markets)',
        'console.act.pass': 'âš¡ Session Pass (/api/v1/pass)',
        'console.btn.send_payment': 'Enviar com X-Payment',
        'console.btn.web3_pay': 'âš¡ Pagar com Carteira Web3 (1 Clique)',
        'console.btn.smart_wallet': 'ðŸ”‘ Passkey / Biometria (Smart Wallet)',
        'console.btn.web3_qr': 'ðŸ“± QR Code / Celular',
        'console.btn.sandbox_demo': 'ðŸ§ª Simular Sandbox (demo)',
        'console.input.placeholder': "Cole seu Session Pass (pass_g402_...), hash de pagamento (0x...) ou assine via Web3",
        'console.teaser.badge': 'AMOSTRA GRATUITA ENCONTRADA',
        'console.teaser.locked': 'ðŸ”’ Campos protegidos que serÃ£o liberados no HTTP 200:',
        'console.teaser.unlock_btn': 'âš¡ Desbloquear Dados Completos com Web3',
        'console.teaser.close_btn': 'âœ• Fechar Amostra',
        'console.status.idle': 'Selecione uma das aÃ§Ãµes acima para testar o retorno da API.',
        'console.output.placeholder': '// Resposta da API aparecerÃ¡ aqui...',

        // Console Tabs Conteudo
        'console.smithery.title': 'ðŸ“¦ InstalaÃ§Ã£o em 1 Clique via Smithery.ai',
        'console.smithery.desc': 'O Grid402 estÃ¡ catalogado no registro global da Smithery.ai. VocÃª pode registrar instantaneamente o servidor de ferramentas no seu Claude Desktop ou Cursor sem precisar editar arquivos de configuraÃ§Ã£o manualmente.',
        'console.smithery.cmd_claude': 'Comando de InstalaÃ§Ã£o no Claude Desktop:',
        'console.smithery.cmd_cursor': 'Comando de InstalaÃ§Ã£o no Cursor:',
        'console.smithery.params': '<strong style="color: #fff;">ðŸ’¡ ParÃ¢metros Suportados no smithery.yaml:</strong><ul style="margin: 0.5rem 0 0 1.2rem; padding: 0;"><li><code>sandbox: true</code> â€” Ativa o Modo Sandbox gratuito (30 consultas por hora por IP) para testes sem necessidade de carteira.</li><li><code>privateKey: 0x...</code> â€” Chave privada EVM opcional para auto-liquidaÃ§Ã£o em USDC na Base (0.0050 a 0.0200 USDC por chamada).</li></ul>',

        'console.agentkit.title': 'ðŸª™ IntegraÃ§Ã£o Nativa Coinbase AgentKit (CDP)',
        'console.agentkit.desc': 'Permita que agentes de IA com carteiras CDP na Base consultem dados pÃºblicos e utilitÃ¡rios de rede pagando tarifas x402 de forma 100% autÃ´noma, sem interrupÃ§Ã£o humana. O provedor de aÃ§Ãµes intercepta o desafio 402, transfere o USDC e reenvia a requisiÃ§Ã£o de forma transparente.',
        'console.agentkit.example_ts': 'Exemplo em TypeScript (@coinbase/agentkit):',
        'console.agentkit.example_py': 'Exemplo em Python (cdp-agentkit-core):',
        'console.agentkit.docs': '<strong style="color: #fff;">ðŸ“‚ DocumentaÃ§Ã£o Completa e CÃ³digo-Fonte:</strong><p style="margin: 0.4rem 0 0 0;">Consulte o diretÃ³rio <code>integrations/agentkit/</code> no repositÃ³rio para implementaÃ§Ãµes prontas com LangChain, CrewAI e guias de configuraÃ§Ã£o.</p>',

        'console.badges.intro': 'Badges em SVG dinÃ¢micos para embeddar no README do seu repositÃ³rio no GitHub:',
        'console.badges.label': 'Exemplo de Badge em Tempo Real:',
        'console.badges.btn.copy': 'ðŸ“‹ Copiar CÃ³digo Markdown',
        'console.python.code': `from grid402 import Grid402Client

client = Grid402Client()

# 1. Resumo gratuito do hub (22 serviÃ§os globais e regionais)
summary = client.get_summary()
print("ServiÃ§os operantes:", summary["status"])

# 2. Identidade Corporativa Global (GLEIF LEI - 200+ paÃ­ses)
lei_info = client.get("/api/v1/lei?lei=5493006MHB84DD0ZWV18", payment="demo")

# 3. Modo Sandbox ("X-Payment: demo") ou Session Pass ("X-Payment: pass_g402_...")`,
        'console.node.code': `import { Grid402Client } from 'grid402';

const client = new Grid402Client();

// 1. Consulta gratuita
const summary = await client.getSummary();
console.log('Status do hub:', summary.status);

// 2. Inspetor SSL ou SanÃ§Ãµes OFAC / AML Web3
const ssl = await client.getSsl('base.org');
console.log('Expira em dias:', ssl.certificate.days_remaining);

// 3. Modo Sandbox ('X-Payment': 'demo') ou Session Pass ('X-Payment': 'pass_g402_...')`,
        'console.curl.code': `# 1. Resumo gratuito dos 22 micro-serviÃ§os
curl -i https://grid402.online/status

# 2. Desafio HTTP 402 com Amostra Preliminar Gratuita (GLEIF LEI ou CNPJ)
curl -i "https://grid402.online/api/v1/lei?lei=5493006MHB84DD0ZWV18"

# 3. RequisiÃ§Ã£o liquidada na Base Mainnet (TxHash) ou Session Pass (<15ms)
curl "https://grid402.online/api/v1/lei?lei=5493006MHB84DD0ZWV18" \\
  -H "X-Payment: pass_g402_SEU_TOKEN_OU_TX_HASH"

# 4. Modo Sandbox gratuito para testes e CI/CD
curl "https://grid402.online/api/v1/lei?lei=5493006MHB84DD0ZWV18" \\
  -H "X-Payment: demo"`,

        // Secao Sobre
        'about.title': 'Sobre o Grid402',
        'about.p1': 'O <strong>Grid402</strong> Ã© uma infraestrutura pÃºblica de orÃ¡culo de dados e utilitÃ¡rios tÃ©cnicos de rede desenhada especificamente para a Internet dos Agentes (Agentic Web) e sistemas autÃ´nomos Machine-to-Machine (M2M).',
        'about.p2': 'Tradicionalmente, consultar dados oficiais de empresas, endereÃ§amento postal ou status de infraestrutura exige cadastro prÃ©vio, cartÃµes de crÃ©dito internacionais, aprovaÃ§Ã£o de tokens e chaves estÃ¡ticas de API com risco de vazamento. O Grid402 elimina toda a fricÃ§Ã£o burocrÃ¡tica adotando o padrÃ£o nativo da web <strong>HTTP 402 Payment Required</strong> com liquidaÃ§Ã£o instantÃ¢nea de micropagamentos em USDC na rede Base Mainnet.',
        'about.p3': 'Todo o ecossistema opera de maneira estritamente Zero-DB e Stateless, processando apenas informaÃ§Ãµes de domÃ­nio pÃºblico com transparÃªncia computacional, propriedade intelectual e arquitetura protegidas por direitos autorais reservados (Leis nÂº 9.609/1998 e nÂº 9.610/1998) e total conformidade com a legislaÃ§Ã£o nacional e internacional (LGPD, GDPR e Marco Civil da Internet).',

        // Secao Contato
        'contact.title': 'Contato e Canais Oficiais',
        'contact.subtitle': 'Disponibilizamos canais diretos de suporte tÃ©cnico, integraÃ§Ã£o para desenvolvedores de IA e governanÃ§a de dados:',
        'contact.support': 'Suporte TÃ©cnico e Geral',
        'contact.dpo': 'Encarregado de Dados (DPO)',
        'contact.phone': 'Central TelefÃ´nica',
        'contact.github': 'CÃ³digo-Fonte no GitHub',
        'contact.npm': 'Pacote npm (Node.js)',
        'contact.pypi': 'Pacote PyPI (Python)',
        'contact.mcp': 'Servidor MCP no npm',
        'contact.oracle': 'Carteira OrÃ¡culo na Base',

        // Footer e Modais
        'footer.hub': 'Grid402 // Open Data e Utility Hub â€¢ Base Mainnet',
        'footer.copyright': 'Â© {year} Grid402 Network Intelligence. Todos os direitos reservados.',
        'footer.fee': 'Taxa: US$ {price} / consulta',
        'footer.ip.title': 'Direitos Autorais e Propriedade Intelectual Reservados:',
        'footer.ip.desc': 'Â© 2026 Grid402 Network Intelligence. Todos os direitos reservados (Lei nÂº 9.609/1998 e Lei nÂº 9.610/1998). O software da plataforma, motor de liquidaÃ§Ã£o HTTP 402, arquitetura de Session Pass, marca, design e documentaÃ§Ãµes sÃ£o de propriedade exclusiva do Grid402, sendo vedada a reproduÃ§Ã£o ou redistribuiÃ§Ã£o nÃ£o autorizada. Os dados tÃ©cnicos consultados provÃªm de registros pÃºblicos governamentais abertos, licenÃ§as CC0 e protocolos livres da internet (DNS, BGP/ASN, TLS/SSL), sobre os quais nÃ£o reivindicamos exclusividade proprietÃ¡ria.',
        'footer.disclaimer.title': 'IsenÃ§Ã£o de Responsabilidade e Dados PÃºblicos:',
        'footer.disclaimer.desc': 'O Grid402 Ã© um hub utilitÃ¡rio de acesso a dados de domÃ­nio pÃºblico e protocolos abertos de rede fornecido "NO ESTADO EM QUE SE ENCONTRA" (AS-IS). NÃ£o tratamos dados pessoais sensÃ­veis (PII) e operamos em estrita observÃ¢ncia Ã  Lei Geral de ProteÃ§Ã£o de Dados (Lei nÂº 13.709/2018), ao GDPR (UE 2016/679) e ao Marco Civil da Internet (Lei nÂº 12.965/2014).',
        'footer.contributors': 'Â© 2026 Grid402 Network Intelligence. Todos os direitos reservados. Protegido pela Lei nÂº 9.609/1998 e Lei nÂº 9.610/1998.',

        // Modal Legal
        'modal.terms.title': 'Termos de Uso',
        'modal.terms.tab': 'Termos de Uso',
        'modal.privacy.tab': 'Privacidade e LGPD',

        // Modal Web3
        'modal.web3.title': 'Pagamento Web3 (Base Mainnet)',
        'modal.web3.no_wallet.title': 'Nenhuma extensÃ£o Web3 ativa detectada nesta aba',
        'modal.web3.no_wallet.desc': 'Para pagar com 1 clique direto na pÃ¡gina, Ã© necessÃ¡rio ter a extensÃ£o MetaMask, Rabby ou Coinbase Wallet instalada e ativa. Ou pague pelo celular via QR Code abaixo.',
        'modal.web3.col1.title': 'ðŸ“± Pagar pelo Celular',
        'modal.web3.col1.desc': 'Escaneie pelo app da MetaMask, Rainbow ou Coinbase Wallet e envie <strong><span id="web3-modal-price">{price}</span> USDC</strong> na rede <strong>Base</strong>.',
        'modal.web3.col2.title': 'ðŸ”‘ Sem ExtensÃ£o? Use Passkey',
        'modal.web3.col2.desc': 'Crie ou conecte uma Coinbase Smart Wallet em 5 segundos usando biometria/Passkey do celular ou computador, sem instalar extensÃ£o nem anotar seed phrase.',
        'modal.web3.col2.btn.smart_wallet': 'ðŸ”‘ Conectar via Passkey (Smart Wallet)',
        'modal.web3.col2.btn.install': 'ðŸ¦Š Ou Instalar ExtensÃ£o MetaMask',
        'modal.web3.col2.btn.reload': 'ðŸ”„ JÃ¡ instalei, recarregar',
        'modal.web3.network': 'Rede:',
        'modal.web3.token': 'Token:',
        'modal.web3.recipient': 'EndereÃ§o Destino (OrÃ¡culo):',
        'modal.web3.amount': 'Valor Exato:',
        'modal.web3.confirm.title': 'JÃ¡ enviou a transaÃ§Ã£o?',
        'modal.web3.confirm.desc': 'Cole o Hash (Tx Hash) da transferÃªncia gerado na sua carteira para liberar os dados imediatamente:',
        'modal.web3.confirm.btn': 'Confirmar Pagamento e Desbloquear',

        // Mensagens JS
        'msg.loading': 'Carregando requisiÃ§Ã£o...',
        'msg.insert_tx': 'Por favor, insira o hash da transaÃ§Ã£o na Base no campo acima.',
        'msg.copy_success': 'Snippet copiado para a Ã¡rea de transferÃªncia!',
        'msg.copied': 'âœ“ Copiado!',
        'msg.audit_empty': 'Por favor, cole um JSON no campo de texto para auditar.',
        'msg.audit_invalid_json': 'O texto informado nÃ£o Ã© um JSON vÃ¡lido. Verifique a sintaxe e tente novamente.',
        'msg.tx_sent': 'TransaÃ§Ã£o enviada com sucesso na Base Mainnet!',
        'msg.waiting_sign': 'Aguardando assinatura de {price} USDC...',
        'msg.unlocking': 'Desbloqueando dados...',
        'msg.wallet_req': 'Solicitando aprovaÃ§Ã£o na carteira para transferÃªncia de {price} USDC na Base para o orÃ¡culo...\nCarteira destino: 0x{recipient}',
        'msg.tx_fail': 'Falha na transaÃ§Ã£o Web3: {error}',
        'msg.op_canceled': 'OperaÃ§Ã£o cancelada ou falhou',

        // Pagina /agents
        'agents.brand.sub': 'Central Global de Agentes IA e MCP',
        'agents.nav.home': 'InÃ­cio',
        'agents.hero.badge': 'x402 Global Autonomous Agent Hub â€¢ 22 ServiÃ§os',
        'agents.hero.title': 'Infraestrutura Global de Dados e UtilitÃ¡rios M2M para Agentes de IA',
        'agents.hero.lead': 'O Grid402 permite que agentes autÃ´nomos de IA (Claude, Cursor, LangChain, CrewAI e Coinbase AgentKit) consultem 22 endpoints globais e brasileiros de domÃ­nio pÃºblico via <strong>HTTP 402</strong> ou <strong>Session Pass prÃ©-pago (&lt;15ms)</strong>, liquidando micropagamentos em USDC na rede Base com <strong>prova criptogrÃ¡fica ECDSA</strong>.',
        'agents.f1.title': 'Zero Cadastro Humano',
        'agents.f1.desc': 'NÃ£o requer chaves estÃ¡ticas de API, cartÃ£o de crÃ©dito ou login. Agentes de IA contratam serviÃ§os diretamente via carteira Web3 on-chain ou Passkey.',
        'agents.f2.title': 'OrÃ¡culo CriptogrÃ¡fico',
        'agents.f2.desc': 'Cada resposta inclui assinatura digital ECDSA secp256k1 determinÃ­stica (chave _oracle_proof), verificÃ¡vel sem estado em menos de 1 ms.',
        'agents.f3.title': 'x402 Session Pass (<15ms)',
        'agents.f3.desc': 'Compre 100 a 1.000 crÃ©ditos em uma Ãºnica transaÃ§Ã£o USDC na Base e execute chamadas M2M de alta frequÃªncia em menos de 15ms sem aguardar blocos.',
        'agents.s1.title': '1. IntegraÃ§Ã£o com Model Context Protocol (MCP)',
        'agents.s1.sub': 'Adicione todas as 22 ferramentas globais e brasileiras ao Claude Desktop ou Cursor com 1 clique.',
        'agents.s2.title': '2. CÃ³digo Python para Agentes AutÃ´nomos',
        'agents.s2.sub': 'Consumo direto tratando o desafio HTTP 402 ou usando Session Pass (pass_g402_...).',
        'agents.s3.title': '3. Teste RÃ¡pido no Terminal (cURL)',
        'agents.s3.sub': 'Inspecione desafios x402, emita um Session Pass ou valide provas criptogrÃ¡ficas diretamente no terminal.',
        'agents.table.title': 'CatÃ¡logo dos 22 Endpoints Globais e Tarifas',
        'agents.table.sub': 'Valores por chamada em USDC na rede Base Mainnet ou em crÃ©ditos de Session Pass.',
        'agents.th.service': 'ServiÃ§o / Fonte PÃºblica',
        'agents.th.price': 'Tarifa (USDC / Pass)',
        'agents.th.proof': 'Prova ECDSA',
        'agents.manifests.title': 'Manifestos para Descoberta AutomÃ¡tica',
        'agents.manifests.sub': 'PadrÃµes abertos para que modelos de linguagem e agentes mapeiem o Grid402.',

        // Paginas Institucionais (/about, /contact, /terms, /privacy)
        'inst.footer.copy': 'Â© 2026 Grid402 Network Intelligence. Todos os direitos reservados.',
        'inst.about.body': `
            <p>O <strong>Grid402</strong> Ã© uma infraestrutura global de micro-serviÃ§os pÃºblicos e orÃ¡culo de utilitÃ¡rios de rede desenhada especificamente para sistemas autÃ´nomos, robÃ´s e agentes de IA sob o padrÃ£o HTTP 402 com liquidaÃ§Ã£o em USDC na rede Base Mainnet.</p>
            <h2>PropÃ³sito e Arquitetura Global</h2>
            <p>O projeto elimina chaves estÃ¡ticas de API e burocracia de cadastros para agentes de software no mundo inteiro. Toda requisiÃ§Ã£o tÃ©cnica a dados oficiais (GLEIF LEI mundial, OFAC SDN, BCE 30+ moedas, notarizaÃ§Ã£o criptogrÃ¡fica ECDSA, Receita Federal, CEP, Banco Central, DNS, SSL e BGP) pode ser liquidada diretamente on-chain ou via Session Pass prÃ©-pago em menos de 15ms.</p>
            <ul>
                <li><strong>Zero-DB e Stateless:</strong> Sem persistÃªncia de bancos de dados relacionais; operaÃ§Ã£o 100% em memÃ³ria e cache atÃ´mico.</li>
                <li><strong>Protocolo x402 + Session Pass:</strong> ImplementaÃ§Ã£o nativa do cÃ³digo HTTP 402 Payment Required para Machine-to-Machine (M2M) e passes de alta frequÃªncia.</li>
                <li><strong>DomÃ­nio PÃºblico e CC0:</strong> Uso estrito de fontes pÃºblicas abertas e licenÃ§as CC0 sem dados pessoais sensÃ­veis (Zero PII).</li>
            </ul>
            <h2>InformaÃ§Ãµes Institucionais</h2>
            <div class="card-box">
                <p><strong>OrganizaÃ§Ã£o:</strong> Grid402 Network Intelligence</p>
                <p><strong>Email de Suporte:</strong> <a href="mailto:contato@grid402.online">contato@grid402.online</a></p>
                <p><strong>Central TelefÃ´nica:</strong> <a href="tel:+5511958325848">+55 11 95832-5848</a></p>
                <p><strong>Carteira OrÃ¡culo (Base):</strong> <a href="https://basescan.org/address/0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90" target="_blank" rel="noopener noreferrer">0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90</a></p>
            </div>
        `,
        'inst.contact.body': `
            <p>Disponibilizamos canais diretos e estruturados para suporte tÃ©cnico, parcerias para integradores de IA e contato com o Encarregado de Tratamento de Dados Pessoais (DPO):</p>
            <h2>Canais Oficiais de Atendimento</h2>
            <div class="card-box">
                <p><strong>Email de Suporte Geral e IntegraÃ§Ãµes:</strong> <a href="mailto:contato@grid402.online">contato@grid402.online</a></p>
                <p><strong>Encarregado de Dados (DPO / LGPD / GDPR):</strong> <a href="mailto:contato@grid402.online">contato@grid402.online</a></p>
                <p><strong>Central TelefÃ´nica:</strong> <a href="tel:+5511958325848">+55 11 95832-5848</a></p>
            </div>
            <h2>Ecossistema e CÃ³digo Aberto</h2>
            <ul>
                <li><strong>GitHub Oficial:</strong> <a href="https://github.com/oliveiratc/Grid402" target="_blank" rel="noopener noreferrer">github.com/oliveiratc/Grid402</a></li>
                <li><strong>Pacote Node.js (npm):</strong> <a href="https://www.npmjs.com/package/grid402" target="_blank" rel="noopener noreferrer">npmjs.com/package/grid402</a></li>
                <li><strong>Pacote Python (PyPI):</strong> <a href="https://pypi.org/project/grid402/" target="_blank" rel="noopener noreferrer">pypi.org/project/grid402</a></li>
                <li><strong>Servidor MCP (Model Context Protocol):</strong> <a href="https://www.npmjs.com/package/grid402-mcp" target="_blank" rel="noopener noreferrer">npmjs.com/package/grid402-mcp</a></li>
            </ul>
        `,
        'inst.terms.body': `
            <p>Termos de serviÃ§o, licenciamento de dados pÃºblicos e conformidade regulatÃ³ria internacional do hub global de micro-serviÃ§os e utilitÃ¡rios M2M Grid402.</p>
            <h2>1. Fornecimento no Estado em que se Encontra (AS-IS)</h2>
            <p>O Grid402 Ã© uma infraestrutura tÃ©cnica de orÃ¡culo M2M fornecida estritamente "NO ESTADO EM QUE SE ENCONTRA" (AS-IS), sem garantias de adequaÃ§Ã£o a finalidades comerciais exclusivas, aconselhamento jurÃ­dico ou decisÃµes de crÃ©dito.</p>
            <h2>2. Base Legal de Dados PÃºblicos, DomÃ­nio PÃºblico e LicenÃ§as Abertas</h2>
            <p>Todas as 22 rotas do Grid402 processam exclusivamente informaÃ§Ãµes de domÃ­nio pÃºblico, fatos econÃ´micos e registros governamentais abertos, livres de restriÃ§Ãµes proprietÃ¡rias:</p>
            <ul>
                <li><strong>Global LEI (/api/v1/lei):</strong> Global Legal Entity Identifier Foundation (GLEIF) sob licenÃ§a <strong>CC0 1.0 Universal (Public Domain Dedication)</strong>, padrÃ£o ISO 17442.</li>
                <li><strong>SanÃ§Ãµes Internacionais (/api/v1/ofac):</strong> US Treasury OFAC SDN (domÃ­nio pÃºblico federal sob <strong>17 U.S.C. Â§ 105</strong>) e listas consolidadas pÃºblicas do Conselho de SeguranÃ§a da ONU.</li>
                <li><strong>Mercados Globais e CÃ¢mbio (/api/v1/global-markets, /api/v1/forex, /api/v1/crypto):</strong> Taxas de referÃªncia do Banco Central Europeu (<strong>EU Open Data Directive 2019/1024</strong>), Banco Central do Brasil (Decreto nÂº 8.777/2016) e fatos econÃ´micos de mercado nÃ£o passÃ­veis de copyright (<strong>Lei nÂº 9.610/1998 Art. 8Âº, I</strong> e <strong>17 U.S.C. Â§ 102(b)</strong>).</li>
                <li><strong>Dados Corporativos e Feriados Nacionais (/api/v1/cnpj, /api/v1/sanctions, /api/v1/simples, /api/v1/compliance, /api/v1/indicators, /api/v1/holidays, /api/v1/workdays, /api/v1/cep):</strong> Lei de Acesso Ã  InformaÃ§Ã£o (<strong>Lei nÂº 12.527/2011 - LAI</strong>), PolÃ­tica de Dados Abertos (<strong>Decreto nÂº 8.777/2016</strong>), Lei AnticorrupÃ§Ã£o (Lei nÂº 12.846/2013) e algoritmos matemÃ¡ticos de calendÃ¡rio civil (Lei nÂº 9.610/1998 Art. 8Âº, II e IV).</li>
                <li><strong>Telemetria de Rede e NotarizaÃ§Ã£o (/api/v1/dns, /api/v1/ssl, /api/v1/asn, /api/v1/notarize):</strong> Protocolos abertos da Internet (IETF RFC 1035, RFC 5280, RFC 4271 e RFC 8785).</li>
            </ul>
            <h2>3. Micropagamentos x402 e Session Passes</h2>
            <p>Pagamentos efetuados via protocolo HTTP 402 e emissÃµes de Session Pass liquidados em USDC na Base Mainnet (EVM 8453) remuneram exclusivamente a infraestrutura computacional, normalizaÃ§Ã£o e assinatura criptogrÃ¡fica ECDSA secp256k1 do OrÃ¡culo, sendo definitivos e irreversÃ­veis on-chain.</p>
            <h2>4. Direitos Autorais e Propriedade Intelectual Reservados</h2>
            <p>Â© 2026 <strong>Grid402 Network Intelligence</strong>. <strong>Todos os direitos reservados.</strong> O software da plataforma Grid402, motor de liquidaÃ§Ã£o x402, arquitetura de Session Pass, design visual, marca e documentaÃ§Ãµes sÃ£o de propriedade exclusiva do Grid402, protegidos pela <strong>Lei do Software (Lei nÂº 9.609/1998)</strong>, <strong>Lei de Direitos Autorais (Lei nÂº 9.610/1998)</strong> e tratados internacionais (ConvenÃ§Ã£o de Berna). Ã‰ vedada a reproduÃ§Ã£o, redistribuiÃ§Ã£o ou engenharia reversa da plataforma sem autorizaÃ§Ã£o expressa. Os dados tÃ©cnicos retornados pelas APIs provÃªm de registros pÃºblicos abertos e fatos de domÃ­nio pÃºblico, sobre os quais nÃ£o reivindicamos exclusividade proprietÃ¡ria.</p>
        `,
        'inst.privacy.body': `
            <p>Diretrizes de privacidade, proteÃ§Ã£o de dados e conformidade regulatÃ³ria nacional e internacional (LGPD, GDPR e CCPA) do hub Grid402.</p>
            <h2>1. Arquitetura Zero-PII e ExclusÃ£o de Dados de Pessoas FÃ­sicas (LGPD Art. 1Âº / GDPR Recital 14)</h2>
            <p>O Grid402 opera estritamente com dados de <strong>Pessoas JurÃ­dicas</strong>, indicadores macroeconÃ´micos e telemetria de rede. Em conformidade com o <strong>Art. 1Âº da LGPD (Lei nÂº 13.709/2018)</strong> e o <strong>Considerando 14 (Recital 14) do GDPR Europeu (Regulamento UE 2016/679)</strong>, registros de pessoas jurÃ­dicas nÃ£o constituem dados pessoais de pessoas naturais. Em consultas de CNPJ, o Grid402 <strong>filtra e descarta na origem</strong> o Quadro de SÃ³cios e Administradores (QSA), nomes de sÃ³cios, CPFs, telefones pessoais e e-mails privados.</p>
            <h2>2. MinimizaÃ§Ã£o, PseudonimizaÃ§Ã£o e Zero-Retention (/api/v1/notarize)</h2>
            <ul>
                <li><strong>Mascaramento e HMAC de IP:</strong> EndereÃ§os IP em mÃ©tricas operacionais sÃ£o truncados de forma irreversÃ­vel (ex: <code>189.40.xxx.xxx</code>) e chaves de controle de taxa de Sandbox utilizam hash criptogrÃ¡fico <code>HMAC-SHA256</code> (pseudonimizaÃ§Ã£o conforme Art. 13 da LGPD e Art. 25 do GDPR).</li>
                <li><strong>NotarizaÃ§Ã£o Zero-Retention:</strong> No endpoint <code>/api/v1/notarize</code>, o conteÃºdo enviado Ã© processado exclusivamente em memÃ³ria RAM volÃ¡til para cÃ¡lculo do digest <code>SHA-256</code> e descartado imediatamente, sem gravaÃ§Ã£o ou exibiÃ§Ã£o de texto bruto.</li>
            </ul>
            <h2>3. Guarda Legal de Registros e PrevenÃ§Ã£o a Fraudes</h2>
            <p>Registros tÃ©cnicos estritos de conexÃ£o sÃ£o mantidos sob sigilo pelo prazo legal em cumprimento ao <strong>Art. 15 do Marco Civil da Internet (Lei nÂº 12.965/2014)</strong>, e hashes pÃºblicos de transaÃ§Ãµes na Base Mainnet (<code>X-Payment</code>) sÃ£o retidos em cache temporÃ¡rio de 7 dias exclusivamente para prevenÃ§Ã£o de ataques de repetiÃ§Ã£o (Replay Attack), com base no legÃ­timo interesse e seguranÃ§a cibernÃ©tica (Art. 7Âº, IX da LGPD e Art. 6(1)(f) do GDPR).</p>
            <h2>4. Direitos do Titular e Encarregado de Dados (DPO)</h2>
            <p>O Grid402 nÃ£o utiliza cookies de rastreamento publicitÃ¡rio e nÃ£o vende ou compartilha informaÃ§Ãµes pessoais (CCPA/CPRA). Para qualquer solicitaÃ§Ã£o de conformidade, auditoria ou exercÃ­cio de direitos perante o Encarregado de Dados (DPO), contate <a href="mailto:contato@grid402.online">contato@grid402.online</a>.</p>
        `
    },
    en: {
        // Navigation and Header
        'nav.services': 'Services',
        'nav.topup': 'Web3 Top-Up',
        'nav.savings': '98% Savings',
        'nav.playground': 'Playground',
        'nav.console': 'Console',
        'nav.agents': 'AI Agents',
        'nav.about': 'About',
        'nav.contact': 'Contact',
        'nav.terms': 'Terms',
        'nav.privacy': 'Privacy',
        'badge.live': 'Base Mainnet (8453) â€¢ {count}/{total} Services Operational',

        // Hero
        'hero.tag': 'Global Microservices and M2M Oracle Hub for Autonomous AI Agents',
        'hero.title': 'Grid402: Global Open-Data and Network Utilities Infrastructure for AI Agents',
        'hero.desc': 'Access 22 global and regional endpoints: worldwide corporate identity (GLEIF LEI across 200+ countries), US Treasury OFAC SDN sanctions + Base Web3 AML, ECDSA timestamp notarization, full KYB audit (CNPJ + OFAC + LEI), full domain audit (DNS + SSL + ASN), consolidated market snapshot (FX + crypto + indicators), world exchange calendars and 30+ ECB currencies, live FX, crypto spot, Compliance 360, DNS, SSL, BGP/ASN, CEP, and CNPJ in milliseconds with zero signup or API keys. Settled via x402 on Base or Prepaid Session Pass (<15ms).',
        'hero.btn.topup': 'ðŸ’Ž Web3 Top-Up (Base)',
        'hero.btn.agents': 'ðŸ¤– AI Agents Hub',
        'hero.btn.playground': 'âš¡ Test in Playground',
        'hero.btn.console': 'Console and SDKs',
        'hero.btn.llms': 'llms.txt Documentation',
        'hero.btn.status': 'Free Status (JSON)',

        // Topup Tiers Web3
        'topup.tag': 'âš¡ Instant Batch Settlement on Base (USDC)',
        'topup.title': '1-Click Web3 Credit Top-Up (Session Passes)',
        'topup.desc': 'Pay once in USDC on Base Mainnet and instantly receive a pass_g402_... token with thousands of high-speed (<15ms) requests without signing transactions on every single GET.',
        'topup.starter.tag': 'Starter Pass',
        'topup.starter.desc': 'Ideal for solo developers, quick prototypes, and lightweight bots.',
        'topup.starter.btn': 'Buy Starter (2 USDC)',
        'topup.pro.badge': 'Most Popular',
        'topup.pro.tag': 'Pro / Agent Pass',
        'topup.pro.desc': 'For autonomous AI agents and continuous automation pipelines.',
        'topup.pro.btn': 'Buy Pro (5 USDC)',
        'topup.fleet.badge': '+10,000 Bonus',
        'topup.fleet.tag': 'Fleet / Enterprise',
        'topup.fleet.desc': 'Autonomous agent fleets, enterprise ERPs, and heavy-duty pipelines.',
        'topup.fleet.btn': 'Buy Fleet (20 USDC)',

        // 14 Services Cards
        'svc.forex.name': 'Forex and Currencies',
        'svc.forex.category': 'Market',
        'svc.forex.desc': 'Real-time exchange rates for USD, EUR, GBP to BRL with daily fluctuation',
        'svc.crypto.name': 'Crypto Tickers',
        'svc.crypto.category': 'Market',
        'svc.crypto.desc': 'Live spot prices for BTC, ETH, SOL, USDC, and BASE in USD and BRL',
        'svc.dns.name': 'DNS Records Lookup',
        'svc.dns.category': 'Network',
        'svc.dns.desc': 'Authoritative resolution of A, AAAA, MX, TXT, NS, and CNAME zone records',
        'svc.ssl.name': 'TLS/SSL Inspector',
        'svc.ssl.category': 'Network',
        'svc.ssl.desc': 'X.509 certificate chain inspection, issuer, SANs, and days until expiration on port 443',
        'svc.asn.name': 'ASN and IP Info',
        'svc.asn.category': 'Network',
        'svc.asn.desc': 'BGP routing telemetry, Autonomous System Number (ASN), CIDR IP block, and country',
        'svc.cep.name': 'CEP and Postal Directory',
        'svc.cep.category': 'Open Data',
        'svc.cep.desc': 'Street address, neighborhood, municipality, state code, and official IBGE resolution',
        'svc.cnpj.name': 'Company Registry (CNPJ)',
        'svc.cnpj.category': 'Corporate',
        'svc.cnpj.desc': 'Registration status, legal name, tax activity (CNAE), opening date, and corporate address',
        'svc.indicators.name': 'Economic Indicators',
        'svc.indicators.category': 'Market',
        'svc.indicators.desc': 'Official central bank rates (Selic target and daily), annual and daily CDI, 12m and monthly IPCA',
        'svc.holidays.name': 'Banking Holidays Calendar',
        'svc.holidays.category': 'Open Data',
        'svc.holidays.desc': 'Official national holidays and banking non-business days (ANBIMA and Febraban) deterministically computed',
        'svc.workdays.name': 'ANBIMA Business Days Calculator',
        'svc.workdays.category': 'Financial',
        'svc.workdays.desc': 'Banking business days calculator (date interval count, D+N projection, and holiday checks)',
        'svc.sanctions.name': 'Sanctions and Debarment (CEIS/CNEP)',
        'svc.sanctions.category': 'Compliance',
        'svc.sanctions.desc': 'Official CGU government registry check for administrative penalties and contracting bans',
        'svc.simples.name': 'Simples Nacional and MEI',
        'svc.simples.category': 'Tax Registry',
        'svc.simples.desc': 'Official Brazilian Federal Revenue tax classification for Simples Nacional and SIMEI',
        'svc.compliance.name': 'Compliance Score 360',
        'svc.compliance.category': 'Compliance',
        'svc.compliance.desc': 'Unified entity integrity score (0 to 100) combining Federal Revenue, CGU, and corporate checks',
        'svc.verify.name': 'Cryptographic Verifier',
        'svc.verify.category': 'Security',
        'svc.verify.desc': 'Instant client-side verification of secp256k1 ECDSA digital signature and oracle proof',
        'svc.lei.name': 'Global Corporate Identity (GLEIF LEI)',
        'svc.lei.category': 'Global (CC0)',
        'svc.lei.desc': 'Official legal entity lookup across 200+ jurisdictions via GLEIF LEI (ISO 17442, CC0 1.0 Universal license)',
        'svc.ofac.name': 'Global Sanctions (OFAC/UN) and Web3 AML',
        'svc.ofac.category': 'Global Compliance',
        'svc.ofac.desc': 'US Treasury OFAC SDN and UN Consolidated screening + live Base Mainnet EVM wallet AML inspection',
        'svc.notarize.name': 'M2M Timestamp and Notarization',
        'svc.notarize.category': 'Cryptography',
        'svc.notarize.desc': 'Issues a proof-of-existence and UTC timestamp certificate signed with secp256k1 ECDSA for agent logs and contracts',
        'svc.global-markets.name': 'World Markets and 30+ FX (ECB)',
        'svc.global-markets.category': 'Global Market',
        'svc.global-markets.desc': 'Global exchange settlement calendar (US NYSE/Fed, EU TARGET2, UK LSE, BR B3) + 30+ official ECB reference currencies',
        'svc.pass.name': 'x402 Prepaid Session Pass',
        'svc.pass.category': 'High Frequency',
        'svc.pass.desc': 'Purchase 100 to 1,000 prepaid credits in 1 USDC transaction on Base and execute sub-15ms calls with zero block wait',

        // ROI Comparator
        'roi.tag': 'Real Cost Reduction for Startups and Autonomous Agents',
        'roi.title': 'Why pay $100+/month when your agent can pay $0.001 per query?',
        'roi.desc': 'Compare traditional data bureaus (mandatory monthly retainers, credit checks, and 12-month lock-in contracts) with Grid402â€™s open M2M model settled via x402 on Base.',
        'roi.table.title': 'Technical and Commercial Comparison',
        'roi.table.th.criteria': 'Criteria',
        'roi.table.th.trad': 'Traditional Bureaus',
        'roi.table.th.grid': 'Grid402 (x402)',
        'roi.table.cnpj': 'CNPJ Query',
        'roi.table.cnpj.trad': '$0.10 to $0.50',
        'roi.table.cnpj.grid': '$0.0200 USDC (~$0.002)',
        'roi.table.monthly': 'Minimum Monthly Fee',
        'roi.table.monthly.trad': '$100 to $400 / month',
        'roi.table.monthly.grid': '$0.00 (Zero retainer)',
        'roi.table.contract': 'Bureaucracy and Contracts',
        'roi.table.contract.trad': '12-month lock-in contract',
        'roi.table.contract.grid': 'Zero contracts, no signup',
        'roi.table.auth': 'Authentication',
        'roi.table.auth.trad': 'Expiring API keys',
        'roi.table.auth.grid': 'Native x402 protocol',
        'roi.table.math': 'Mathematical Guarantee',
        'roi.table.math.trad': 'None (raw data)',
        'roi.table.math.grid': 'secp256k1 ECDSA signature',
        'roi.table.disclaimer': '* Market benchmarks based on entry tiers from traditional corporate data providers.',
        'roi.calc.title': 'Data and CNPJ Cost Savings Simulator',
        'roi.calc.subtitle': 'Tune the monthly query volume of your bot or autonomous system:',
        'roi.calc.label': 'Monthly Query Volume:',
        'roi.calc.cost.trad': 'Traditional Cost',
        'roi.calc.cost.grid': 'Grid402 Cost',
        'roi.calc.btn.agents': 'ðŸ¤– Integrate AI Agent',
        'roi.calc.btn.test': 'âš¡ Test CNPJ Query',

        // Playground
        'playground.tag': 'M2M Cryptographic Verification',
        'playground.title': 'Interactive Playground and Oracle Verifier',
        'playground.desc': 'Run real queries across Grid402 microservices using free Sandbox mode or simulate the x402 challenge. Instantly verify ECDSA secp256k1 cryptographic attestations (_oracle_proof) using our browser-based math engine in real time.',
        'playground.btn.challenge': 'ðŸ”’ x402 Challenge (HTTP 402)',
        'playground.btn.sandbox': 'ðŸ§ª Sandbox (3 tests/hr)',
        'playground.btn.manual': 'âœï¸ Validate External JSON',
        'playground.route': 'Route:',
        'playground.btn.run': 'âš¡ Run Query',
        'playground.config.title': 'Query Configuration',
        'playground.manual.desc': 'Paste any JSON response returned by Grid402 below or load one of the test templates:',
        'playground.btn.template.valid': 'Load Valid Template',
        'playground.btn.template.tampered': 'Load Tampered Template (Counterfeit)',
        'playground.btn.audit.manual': 'ðŸ” Audit Signature of this JSON',
        'playground.viewer.title': 'API Response',
        'playground.status.ready': 'Ready',
        'playground.audit.title': 'Browser ECDSA Mathematical Audit Engine',
        'playground.audit.s1.title': '1. Payload Canonicalization and SHA-256 Hash',
        'playground.audit.s1.desc': 'Reconstructs the normalized payload in canonical form and computes the cryptographic hash:',
        'playground.audit.s2.title': '2. secp256k1 Signature Verification',
        'playground.audit.s2.desc': 'Mathematically validates the DER/ECDSA digital signature against the canonical hash.',
        'playground.audit.s3.title': '3. Oracle Address Recovery',
        'playground.audit.s3.desc': 'Confirms recovered key matches the official public identity of the Grid402 oracle:',
        'playground.audit.oracle.expected': 'Expected Official Oracle:',
        'playground.audit.oracle.recovered': 'Recovered On-Chain Key:',
        'playground.btn.copy': 'Copy JSON',
        'playground.viewer.placeholder': '// Click "Run Query" or select a microservice above to audit...',
        'playground.unlock.badge': 'ðŸ”’ FULL DATA PROTECTED (HTTP 402)',
        'playground.unlock.desc': 'The free sample above is verified. Unlock the hidden fields and ECDSA cryptographic proof (_oracle_proof) now:',
        'playground.unlock.cta_web3': 'âš¡ Unlock with Web3 Wallet (1-Click)',
        'playground.unlock.cta_smart_wallet': 'ðŸ”‘ Passkey / Biometrics (Coinbase Smart Wallet)',
        'playground.unlock.cta_qr': 'ðŸ“± Mobile QR Code',
        'playground.unlock.cta_sandbox': 'ðŸ§ª View Full Sample (Sandbox)',

        // Console
        'console.title': 'Testing Console and Integration Specs',
        'console.desc': 'Test requests in real time or copy code snippets directly into your autonomous agents.',
        'console.tab.interactive': 'âš¡ Interactive Console',
        'console.tab.smithery': 'ðŸ“¦ Smithery MCP (1-Click)',
        'console.tab.agentkit': 'ðŸª™ Coinbase AgentKit (M2M)',
        'console.tab.python': 'ðŸ Python SDK',
        'console.tab.node': 'ðŸŸ¢ Node.js SDK',
        'console.tab.curl': 'ðŸ’» cURL CLI',
        'console.tab.badges': 'ðŸ›¡ï¸ SVG Badges',
        'console.act.summary': 'Free Summary (/status)',
        'console.act.forex': 'Forex (/api/v1/forex)',
        'console.act.crypto': 'Crypto (/api/v1/crypto)',
        'console.act.dns': 'DNS (/api/v1/dns)',
        'console.act.ssl': 'SSL (/api/v1/ssl)',
        'console.act.asn': 'ASN (/api/v1/asn)',
        'console.act.cep': 'CEP (/api/v1/cep)',
        'console.act.cnpj': 'CNPJ (/api/v1/cnpj)',
        'console.act.indicators': 'Indicators (/api/v1/indicators)',
        'console.act.holidays': 'Holidays (/api/v1/holidays)',
        'console.act.workdays': 'Workdays (/api/v1/workdays)',
        'console.act.sanctions': 'Sanctions (/api/v1/sanctions)',
        'console.act.simples': 'Simples (/api/v1/simples)',
        'console.act.compliance': 'Score 360 (/api/v1/compliance)',
        'console.act.lei': 'ðŸŒ Global LEI (/api/v1/lei)',
        'console.act.ofac': 'ðŸ›¡ï¸ OFAC and AML (/api/v1/ofac)',
        'console.act.notarize': 'â±ï¸ Notarize (/api/v1/notarize)',
        'console.act.global-markets': 'ðŸŒ Markets and ECB (/api/v1/global-markets)',
        'console.act.pass': 'âš¡ Session Pass (/api/v1/pass)',
        'console.btn.send_payment': 'Send with X-Payment',
        'console.btn.web3_pay': 'âš¡ Pay with Web3 Wallet (1-Click)',
        'console.btn.smart_wallet': 'ðŸ”‘ Passkey / Biometrics (Smart Wallet)',
        'console.btn.web3_qr': 'ðŸ“± Mobile QR Code',
        'console.btn.sandbox_demo': 'ðŸ§ª Simulate Sandbox (demo)',
        'console.input.placeholder': "Paste Session Pass (pass_g402_...), Base tx hash (0x...) or sign with Web3",
        'console.teaser.badge': 'FREE SAMPLE FOUND',
        'console.teaser.locked': 'ðŸ”’ Protected fields unlocked on HTTP 200:',
        'console.teaser.unlock_btn': 'âš¡ Unlock Full Data with Web3',
        'console.teaser.close_btn': 'âœ• Close Preview',
        'console.status.idle': 'Select one of the actions above to test API output.',
        'console.output.placeholder': '// API response will appear here...',

        // Console Tabs Content
        'console.smithery.title': 'ðŸ“¦ 1-Click Installation via Smithery.ai',
        'console.smithery.desc': 'Grid402 is indexed in the global Smithery.ai registry. You can instantly register the MCP tools server in your Claude Desktop or Cursor without editing configuration files manually.',
        'console.smithery.cmd_claude': 'Installation Command for Claude Desktop:',
        'console.smithery.cmd_cursor': 'Installation Command for Cursor:',
        'console.smithery.params': '<strong style="color: #fff;">ðŸ’¡ Supported Parameters in smithery.yaml:</strong><ul style="margin: 0.5rem 0 0 1.2rem; padding: 0;"><li><code>sandbox: true</code> â€” Enables free Sandbox Mode (20 queries/hour per IP) to test without a wallet.</li><li><code>privateKey: 0x...</code> â€” Optional EVM private key for auto-settlement in USDC on Base (0.0050 to 0.0200 USDC per call).</li></ul>',

        'console.agentkit.title': 'ðŸª™ Native Coinbase AgentKit Integration (CDP)',
        'console.agentkit.desc': 'Allow autonomous AI agents equipped with CDP wallets on Base to fetch public data and network utilities paying x402 rates 100% autonomously, with zero human intervention. The action provider intercepts HTTP 402, transfers USDC, and retries the request seamlessly.',
        'console.agentkit.example_ts': 'TypeScript Example (@coinbase/agentkit):',
        'console.agentkit.example_py': 'Python Example (cdp-agentkit-core):',
        'console.agentkit.docs': '<strong style="color: #fff;">ðŸ“‚ Complete Documentation and Source Code:</strong><p style="margin: 0.4rem 0 0 0;">Inspect the <code>integrations/agentkit/</code> directory in the repository for drop-in LangChain, CrewAI, and setup guides.</p>',

        'console.badges.intro': 'Dynamic SVG badges ready to embed into your GitHub repository README:',
        'console.badges.label': 'Live Badge Preview:',
        'console.badges.btn.copy': 'ðŸ“‹ Copy Markdown Code',
        'console.python.code': `from grid402 import Grid402Client

client = Grid402Client()

# 1. Free hub status summary (22 global and regional services)
summary = client.get_summary()
print("Operational status:", summary["status"])

# 2. Global Corporate Identity (GLEIF LEI across 200+ countries)
lei_info = client.get("/api/v1/lei?lei=5493006MHB84DD0ZWV18", payment="demo")

# 3. Free Sandbox ("X-Payment: demo") or Prepaid Session Pass ("X-Payment: pass_g402_...")`,
        'console.node.code': `import { Grid402Client } from 'grid402';

const client = new Grid402Client();

// 1. Free status summary
const summary = await client.getSummary();
console.log('Hub status:', summary.status);

// 2. SSL Inspector or Global OFAC / Web3 AML
const ssl = await client.getSsl('base.org');
console.log('Days remaining:', ssl.certificate.days_remaining);

// 3. Free Sandbox ('X-Payment': 'demo') or Prepaid Session Pass ('X-Payment': 'pass_g402_...')`,
        'console.curl.code': `# 1. Free status summary of all 22 microservices
curl -i https://grid402.online/status

# 2. HTTP 402 Challenge with Free Teaser Sample (GLEIF LEI or OFAC)
curl -i "https://grid402.online/api/v1/lei?lei=5493006MHB84DD0ZWV18"

# 3. Settled request on Base Mainnet (TxHash) or Prepaid Session Pass (<15ms)
curl "https://grid402.online/api/v1/lei?lei=5493006MHB84DD0ZWV18" \\
  -H "X-Payment: pass_g402_YOUR_TOKEN_OR_BASE_TX_HASH"

# 4. Free Sandbox mode for local testing and CI/CD
curl "https://grid402.online/api/v1/lei?lei=5493006MHB84DD0ZWV18" \\
  -H "X-Payment: demo"`,

        // About Section
        'about.title': 'About Grid402',
        'about.p1': '<strong>Grid402</strong> is an open-data oracle infrastructure and technical network utility gateway designed specifically for the Agentic Web and Machine-to-Machine (M2M) autonomous systems.',
        'about.p2': 'Traditionally, fetching official entity records, postal geocoding, or infrastructure status requires advance account signup, international credit cards, token approval, and static API keys prone to leaks. Grid402 removes all bureaucratic friction by adopting the native web standard <strong>HTTP 402 Payment Required</strong> with instant micropayment settlement in USDC on Base Mainnet.',
        'about.p3': 'The entire ecosystem operates strictly Zero-DB and Stateless, processing only public domain information with computational transparency, proprietary software and architecture protected by copyright (All Rights Reserved â€” Laws 9.609/1998 & 9.610/1998), and full regulatory compliance (LGPD, GDPR, CCPA, and Brazilian Civil Rights Framework for the Internet).',

        // Contact Section
        'contact.title': 'Contact and Official Channels',
        'contact.subtitle': 'We provide direct channels for technical support, AI developer integration, and data governance:',
        'contact.support': 'Technical and General Support',
        'contact.dpo': 'Data Protection Officer (DPO)',
        'contact.phone': 'Phone Hotline',
        'contact.github': 'GitHub Source Code',
        'contact.npm': 'npm Package (Node.js)',
        'contact.pypi': 'PyPI Package (Python)',
        'contact.mcp': 'MCP Server on npm',
        'contact.oracle': 'Oracle Wallet on Base',

        // Footer and Modals
        'footer.hub': 'Grid402 // Open Data and Utility Hub â€¢ Base Mainnet',
        'footer.copyright': 'Â© {year} Grid402 Network Intelligence. All rights reserved.',
        'footer.fee': 'Rate: US$ {price} / query',
        'footer.ip.title': 'Copyright and All Rights Reserved:',
        'footer.ip.desc': 'Â© 2026 Grid402 Network Intelligence. All rights reserved (Protected by Copyright & Software Laws 9.609/1998, 9.610/1998 & Berne Convention). The Grid402 platform software, HTTP 402 settlement engine, Session Pass architecture, brand, design, and documentation are the exclusive property of Grid402; unauthorized reproduction or redistribution is prohibited. Returned technical data originates from open public records, CC0 registries, and internet standards (DNS, BGP/ASN, TLS/SSL), free of proprietary exclusivity.',
        'footer.disclaimer.title': 'Disclaimer and Open Public Data:',
        'footer.disclaimer.desc': 'Grid402 is an open utility gateway providing access to public domain data and standard network protocols provided "AS IS". We do not process sensitive personally identifiable information (PII) and operate in compliance with international data protection laws (GDPR, LGPD, and CCPA).',
        'footer.contributors': 'Â© 2026 Grid402 Network Intelligence. All rights reserved. Protected by Copyright Laws 9.609/1998 & 9.610/1998.',

        // Legal Modal
        'modal.terms.title': 'Terms of Use',
        'modal.terms.tab': 'Terms of Use',
        'modal.privacy.tab': 'Privacy Policy',

        // Modal Web3
        'modal.web3.title': 'Web3 Payment (Base Mainnet)',
        'modal.web3.no_wallet.title': 'No active Web3 extension detected in this tab',
        'modal.web3.no_wallet.desc': 'For 1-click on-page payment, install MetaMask, Rabby, or Coinbase Wallet. Alternatively, pay from your mobile device using the QR code below.',
        'modal.web3.col1.title': 'ðŸ“± Pay via Mobile',
        'modal.web3.col1.desc': 'Scan with MetaMask, Rainbow, or Coinbase Wallet app and send <strong><span id="web3-modal-price">{price}</span> USDC</strong> on <strong>Base</strong>.',
        'modal.web3.col2.title': 'ðŸ”‘ No Extension? Use Passkey',
        'modal.web3.col2.desc': 'Create or connect a Coinbase Smart Wallet in 5 seconds using your phone or computer biometrics/Passkey, with zero browser extensions or seed phrases.',
        'modal.web3.col2.btn.smart_wallet': 'ðŸ”‘ Connect via Passkey (Smart Wallet)',
        'modal.web3.col2.btn.install': 'ðŸ¦Š Or Install MetaMask Extension',
        'modal.web3.col2.btn.reload': 'ðŸ”„ Reload Page',
        'modal.web3.network': 'Network:',
        'modal.web3.token': 'Token:',
        'modal.web3.recipient': 'Destination Address (Oracle):',
        'modal.web3.amount': 'Exact Amount:',
        'modal.web3.confirm.title': 'Already sent the transaction?',
        'modal.web3.confirm.desc': 'Paste the transaction hash (Tx Hash) from your wallet to unlock full data immediately:',
        'modal.web3.confirm.btn': 'Confirm Payment and Unlock',

        // JS Messages
        'msg.loading': 'Loading request...',
        'msg.insert_tx': 'Please insert the Base transaction hash in the input above.',
        'msg.copy_success': 'Snippet copied to clipboard!',
        'msg.copied': 'âœ“ Copied!',
        'msg.audit_empty': 'Please paste a JSON payload into the text area to audit.',
        'msg.audit_invalid_json': 'Provided text is not valid JSON. Please check syntax and try again.',
        'msg.tx_sent': 'Transaction successfully broadcast on Base Mainnet!',
        'msg.waiting_sign': 'Awaiting signature of {price} USDC...',
        'msg.unlocking': 'Unlocking data...',
        'msg.wallet_req': 'Requesting wallet approval for {price} USDC transfer on Base to oracle...\nRecipient: 0x{recipient}',
        'msg.tx_fail': 'Web3 Transaction Failed: {error}',
        'msg.op_canceled': 'Operation cancelled or failed',

        // /agents Page
        'agents.brand.sub': 'Global AI Agents and MCP Hub',
        'agents.nav.home': 'Home',
        'agents.hero.badge': 'x402 Global Autonomous Agent Hub â€¢ 22 Services',
        'agents.hero.title': 'Global Open-Data and M2M Utility Infrastructure for AI Agents',
        'agents.hero.lead': 'Grid402 enables autonomous AI agents (Claude, Cursor, LangChain, CrewAI, and Coinbase AgentKit) to query 22 global and regional public-domain endpoints via <strong>HTTP 402</strong> or <strong>Prepaid Session Pass (&lt;15ms)</strong>, settling USDC micropayments on Base with <strong>ECDSA cryptographic proofs</strong>.',
        'agents.f1.title': 'Zero Human Signup',
        'agents.f1.desc': 'No static API keys, credit cards, or accounts required. AI agents purchase oracle data autonomously via on-chain Web3 wallets or Passkeys.',
        'agents.f2.title': 'Cryptographic Oracle',
        'agents.f2.desc': 'Every response carries a deterministic secp256k1 ECDSA digital signature (_oracle_proof), verifiable statelessly in under 1 ms.',
        'agents.f3.title': 'x402 Session Pass (<15ms)',
        'agents.f3.desc': 'Purchase 100 to 1,000 prepaid credits in a single Base USDC transaction and execute high-frequency M2M calls in under 15ms with zero block wait.',
        'agents.s1.title': '1. Model Context Protocol (MCP) Integration',
        'agents.s1.sub': 'Add all 22 global and regional oracle tools to Claude Desktop or Cursor in 1 click.',
        'agents.s2.title': '2. Python Code for Autonomous Agents',
        'agents.s2.sub': 'Direct consumption handling HTTP 402 challenges or using a Prepaid Session Pass (pass_g402_...).',
        'agents.s3.title': '3. Quick Terminal Test (cURL)',
        'agents.s3.sub': 'Inspect x402 challenges, issue a Session Pass, or verify cryptographic oracle proofs directly from your terminal.',
        'agents.table.title': 'Global 22-Endpoint Catalog and Pricing',
        'agents.table.sub': 'Pay-per-call rates in USDC on Base Mainnet or Prepaid Session Pass credits.',
        'agents.th.service': 'Service / Open-Data Source',
        'agents.th.price': 'Rate (USDC / Pass)',
        'agents.th.proof': 'ECDSA Proof',
        'agents.manifests.title': 'Autonomous Discovery Manifests',
        'agents.manifests.sub': 'Open standards for LLMs, crawlers, and autonomous agents to map Grid402 capabilities.',

        // Institutional Pages (/about, /contact, /terms, /privacy)
        'inst.footer.copy': 'Â© 2026 Grid402 Network Intelligence. All rights reserved.',
        'inst.about.body': `
            <p><strong>Grid402</strong> is a global open-data microservices and network utility oracle designed specifically for autonomous systems, bots, and AI agents under the HTTP 402 standard with instant USDC settlement on Base Mainnet.</p>
            <h2>Global Purpose and Architecture</h2>
            <p>Grid402 eliminates static API keys and bureaucratic signups for software agents worldwide. Every technical query to official registries (worldwide GLEIF LEI, US Treasury OFAC SDN, 30+ ECB currencies, ECDSA cryptographic notarization, corporate registries, DNS, SSL, and BGP) can be settled directly on-chain or via a Prepaid Session Pass in under 15ms.</p>
            <ul>
                <li><strong>Zero-DB and Stateless:</strong> No relational database lock-in; 100% in-memory and atomic file cache operation.</li>
                <li><strong>x402 Protocol + Session Pass:</strong> Native HTTP 402 Payment Required implementation for Machine-to-Machine (M2M) and high-frequency prepaid passes.</li>
                <li><strong>Public Domain and CC0:</strong> Strict reliance on open public registries and CC0 licenses with zero sensitive personal data (Zero PII).</li>
            </ul>
            <h2>Institutional Information</h2>
            <div class="card-box">
                <p><strong>Organization:</strong> Grid402 Network Intelligence</p>
                <p><strong>Support Email:</strong> <a href="mailto:contato@grid402.online">contato@grid402.online</a></p>
                <p><strong>Phone Hotline:</strong> <a href="tel:+5511958325848">+55 11 95832-5848</a></p>
                <p><strong>Oracle Wallet (Base):</strong> <a href="https://basescan.org/address/0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90" target="_blank" rel="noopener noreferrer">0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90</a></p>
            </div>
        `,
        'inst.contact.body': `
            <p>We provide direct, structured channels for technical support, AI integrator partnerships, and Data Protection Officer (DPO) inquiries:</p>
            <h2>Official Support Channels</h2>
            <div class="card-box">
                <p><strong>General Support and Integrations:</strong> <a href="mailto:contato@grid402.online">contato@grid402.online</a></p>
                <p><strong>Data Protection Officer (DPO / GDPR / LGPD):</strong> <a href="mailto:contato@grid402.online">contato@grid402.online</a></p>
                <p><strong>Phone Hotline:</strong> <a href="tel:+5511958325848">+55 11 95832-5848</a></p>
            </div>
            <h2>Open Source Ecosystem</h2>
            <ul>
                <li><strong>Official GitHub:</strong> <a href="https://github.com/oliveiratc/Grid402" target="_blank" rel="noopener noreferrer">github.com/oliveiratc/Grid402</a></li>
                <li><strong>Node.js Package (npm):</strong> <a href="https://www.npmjs.com/package/grid402" target="_blank" rel="noopener noreferrer">npmjs.com/package/grid402</a></li>
                <li><strong>Python Package (PyPI):</strong> <a href="https://pypi.org/project/grid402/" target="_blank" rel="noopener noreferrer">pypi.org/project/grid402</a></li>
                <li><strong>MCP Server (Model Context Protocol):</strong> <a href="https://www.npmjs.com/package/grid402-mcp" target="_blank" rel="noopener noreferrer">npmjs.com/package/grid402-mcp</a></li>
            </ul>
        `,
        'inst.terms.body': `
            <p>Terms of Service, Open Data Legal Basis, and International Regulatory Compliance for the Grid402 global M2M microservices hub.</p>
            <h2>1. Provided "AS-IS"</h2>
            <p>Grid402 is a technical M2M oracle infrastructure provided strictly "AS-IS", without warranties of fitness for a particular commercial purpose, legal counsel, or credit underwriting.</p>
            <h2>2. Public Domain Legal Basis and Open Data Licenses</h2>
            <p>All 22 Grid402 endpoints process exclusively public-domain records, non-copyrightable facts, and open government registries:</p>
            <ul>
                <li><strong>Global LEI (/api/v1/lei):</strong> Global Legal Entity Identifier Foundation (GLEIF) under <strong>CC0 1.0 Universal (Public Domain Dedication)</strong>, ISO 17442 standard.</li>
                <li><strong>Global Sanctions (/api/v1/ofac):</strong> US Treasury OFAC SDN (US Federal Public Domain under <strong>17 U.S.C. Â§ 105</strong>) and United Nations Security Council Consolidated Lists.</li>
                <li><strong>World Markets and FX (/api/v1/global-markets, /api/v1/forex, /api/v1/crypto):</strong> European Central Bank reference rates (<strong>EU Open Data Directive 2019/1024</strong>), Central Bank of Brazil (Decree 8.777/2016), and non-copyrightable market facts (<strong>17 U.S.C. Â§ 102(b)</strong> and <strong>Brazilian Law 9.610/1998 Art. 8, I</strong>).</li>
                <li><strong>Corporate & Statutory Calendars (/api/v1/cnpj, /api/v1/sanctions, /api/v1/simples, /api/v1/compliance, /api/v1/indicators, /api/v1/holidays, /api/v1/workdays, /api/v1/cep):</strong> Brazilian Access to Information Act (<strong>Law 12.527/2011 - LAI</strong>), Open Data Policy (<strong>Decree 8.777/2016</strong>), Anti-Corruption Act (Law 12.846/2013), and mathematical calendar algorithms (Law 9.610/1998 Art. 8, II & IV).</li>
                <li><strong>Network Telemetry & Notarization (/api/v1/dns, /api/v1/ssl, /api/v1/asn, /api/v1/notarize):</strong> Open Internet Engineering Task Force standards (IETF RFC 1035, RFC 5280, RFC 4271, and RFC 8785).</li>
            </ul>
            <h2>3. x402 Micropayments and Session Passes</h2>
            <p>Micropayments settled via HTTP 402 and Prepaid Session Passes issued on Base Mainnet (EVM 8453) compensate strictly for computational normalization and ECDSA secp256k1 cryptographic attestation, and are final and non-refundable on-chain.</p>
            <h2>4. Copyright and All Rights Reserved (Intellectual Property)</h2>
            <p>Â© 2026 <strong>Grid402 Network Intelligence</strong>. <strong>All rights reserved.</strong> The Grid402 platform software, HTTP 402 settlement engine, Session Pass architecture, visual design, brand, and documentation are the exclusive intellectual property of Grid402, protected by the <strong>Software Protection Act (Law 9.609/1998)</strong>, <strong>Copyright Act (Law 9.610/1998)</strong>, and international treaties (Berne Convention). Unauthorized reproduction, redistribution, or reverse engineering of the platform is strictly prohibited. We claim no proprietary exclusivity over government registries or public-domain facts returned by the APIs.</p>
        `,
        'inst.privacy.body': `
            <p>Privacy, data protection, and international regulatory compliance guidelines (GDPR, LGPD, and CCPA/CPRA) for the Grid402 hub.</p>
            <h2>1. Zero-PII Architecture and Corporate-Only Scope (GDPR Recital 14 / LGPD Art. 1)</h2>
            <p>Grid402 operates strictly on <strong>Legal Entity (corporate)</strong> records, macroeconomic indicators, and network telemetry. Pursuant to <strong>Recital 14 of the EU GDPR (Regulation (EU) 2016/679)</strong> and <strong>Article 1 of the Brazilian LGPD (Law 13.709/2018)</strong>, data concerning legal persons does not constitute personal data of natural persons. Across corporate lookup endpoints (<code>/api/v1/cnpj</code>, <code>/api/v1/compliance</code>), Grid402 <strong>strictly strips and discards at ingestion</strong> shareholder/director lists (QSA), individual tax IDs (CPFs), personal phone numbers, and private emails.</p>
            <h2>2. Data Minimization, IP Pseudonymization, and Zero-Retention (/api/v1/notarize)</h2>
            <ul>
                <li><strong>IP Masking & HMAC Pseudonymization:</strong> IP addresses in operational telemetry are irreversibly truncated (e.g., <code>189.40.xxx.xxx</code>) and Sandbox rate-limiter keys use salted <code>HMAC-SHA256</code> cryptographic hashes (compliant with GDPR Art. 25 & LGPD Art. 13).</li>
                <li><strong>Zero-Retention Notarization:</strong> In <code>/api/v1/notarize</code>, submitted payloads are processed exclusively in volatile RAM to compute the <code>SHA-256</code> digest and immediately discarded, with zero disk retention or raw text echoing.</li>
            </ul>
            <h2>3. Statutory Logs and Anti-Fraud Security</h2>
            <p>Minimal technical connection logs are kept confidentially as required by <strong>Art. 15 of the Brazilian Civil Rights Framework for the Internet (Law 12.965/2014)</strong>, and public Base Mainnet transaction hashes (<code>X-Payment</code>) are cached for 7 days strictly for anti-replay security (GDPR Art. 6(1)(f) & LGPD Art. 7, IX).</p>
            <h2>4. Data Subject Rights & Data Protection Officer (DPO)</h2>
            <p>Grid402 uses zero advertising/tracking cookies and never sells or shares personal information (CCPA/CPRA). For any privacy, compliance, or DPO inquiry, contact <a href="mailto:contato@grid402.online">contato@grid402.online</a>.</p>
        `
    }
};

// Dicionario dos Presets do Playground em Ingles
const playgroundPresets_en = {
    lei: {
        name: 'Global LEI (GLEIF)',
        desc: 'Global Corporate Identity (ISO 17442) in 200+ jurisdictions via GLEIF (CC0 1.0 Public Domain).',
        params: [
            { key: 'lei', label: 'LEI Code (20 chars) or Company Name', value: '5493006MHB84DD0ZWV18' }
        ]
    },
    ofac: {
        name: 'OFAC and Web3 AML',
        desc: 'Global Sanctions (OFAC SDN / UN) + live Base Mainnet EVM wallet AML inspection.',
        params: [
            { key: 'target', label: '0x EVM Wallet, LEI, or Entity Name', value: '0x8589427373d6d84e98730d7795d8f6f8731fda16' }
        ]
    },
    notarize: {
        name: 'M2M Notarization',
        desc: 'Proof-of-Existence and UTC Timestamp signed with ECDSA secp256k1 for agent logs and contracts.',
        params: [
            { key: 'content', label: 'Text or SHA-256 Hash to Notarize', value: 'Grid402-M2M-Autonomous-Contract-v1' }
        ]
    },
    'global-markets': {
        name: 'World Markets and ECB',
        desc: 'Global Exchange Settlement Calendar (US NYSE/Fed, EU TARGET2, UK LSE, BR B3) + 30+ ECB FX rates.',
        params: [
            { key: 'market', label: 'Market (US, EU, UK, BR)', value: 'US' },
            { key: 'base', label: 'Base Currency (USD, EUR, GBP, BRL)', value: 'USD' }
        ]
    },
    pass: {
        name: 'x402 Session Pass',
        desc: 'Prepaid M2M credit pass (100 to 1,000 calls) for sub-15ms execution with zero block wait.',
        params: [
            { key: 'status', label: 'Check Existing Pass (optional)', value: '' }
        ]
    },
    compliance: {
        name: 'Compliance 360',
        desc: 'Unified risk and entity integrity score (0 to 100) cross-checking Federal Revenue and CGU.',
        params: [
            { key: 'cnpj', label: 'Company CNPJ Number', value: '00000000000191' }
        ]
    },
    sanctions: {
        name: 'CGU Sanctions',
        desc: 'Checks administrative sanctions and contracting bans in official CEIS and CNEP databases.',
        params: [
            { key: 'cnpj', label: 'Company CNPJ Number', value: '00000000000191' }
        ]
    },
    simples: {
        name: 'Simples Nacional',
        desc: 'Official tax regime inquiry for Simples Nacional and SIMEI with Federal Revenue.',
        params: [
            { key: 'cnpj', label: 'Company CNPJ Number', value: '00000000000191' }
        ]
    },
    workdays: {
        name: 'ANBIMA Workdays',
        desc: 'Banking business days calculator with date interval counts and D+N target date projection.',
        params: [
            { key: 'start_date', label: 'Start Date (YYYY-MM-DD)', value: '2026-11-06' },
            { key: 'days', label: 'Business Days to Add', value: '5' }
        ]
    },
    indicators: {
        name: 'BACEN Indicators',
        desc: 'Official Central Bank interest rates (Selic target and daily), annual/daily CDI, and 12m IPCA.',
        params: []
    },
    holidays: {
        name: 'National Holidays',
        desc: 'Official calendar of banking and statutory holidays according to Brazilian legislation.',
        params: [
            { key: 'year', label: 'Calendar Year', value: '2026' }
        ]
    },
    cnpj: {
        name: 'Federal Revenue CNPJ',
        desc: 'Corporate registration and tax standing query with the Brazilian Federal Revenue.',
        params: [
            { key: 'cnpj', label: 'Company CNPJ Number', value: '00000000000191' }
        ]
    },
    cep: {
        name: 'National Postal Code (CEP)',
        desc: 'Complete Brazilian postal address, street lookup, and IBGE city code resolution.',
        params: [
            { key: 'code', label: 'Postal Code (CEP)', value: '01310100' }
        ]
    },
    forex: {
        name: 'Official PTAX Forex',
        desc: 'Official exchange rates for fiat currencies USD, EUR, and GBP to BRL.',
        params: []
    },
    crypto: {
        name: 'Spot Crypto Rates',
        desc: 'Real-time spot rates for digital assets BTC and ETH in USD and BRL.',
        params: [
            { key: 'symbol', label: 'Asset Symbol', value: 'BTC' }
        ]
    },
    dns: {
        name: 'DNS Resolution',
        desc: 'Authoritative resolution of DNS zone records A, AAAA, MX, TXT, NS, and CNAME.',
        params: [
            { key: 'domain', label: 'Domain Name', value: 'base.org' }
        ]
    },
    ssl: {
        name: 'TLS and SSL Certificate',
        desc: 'Cryptographic TLS certificate audit on port 443, certificate chain, and validity.',
        params: [
            { key: 'host', label: 'Audit Host', value: 'base.org' }
        ]
    },
    asn: {
        name: 'BGP and ASN Intelligence',
        desc: 'BGP Autonomous System Identification, CIDR IP address block, and network operator.',
        params: [
            { key: 'ip', label: 'IP Address', value: '1.1.1.1' }
        ]
    },
    summary: {
        name: 'Operational Summary',
        desc: 'Consolidated telemetry and real-time operational status of all Grid402 services.',
        params: []
    },
    verify: {
        name: 'Oracle Verifier',
        desc: 'Instant mathematical validation of secp256k1 signatures and cryptographic oracle proofs.',
        params: []
    }
};

let currentLanguage = 'pt';

/**
 * Traduz uma chave com substituicao opcional de parametros
 */
function t(key, params = {}) {
    let str = (GRID402_I18N[currentLanguage] && GRID402_I18N[currentLanguage][key]) 
           || (GRID402_I18N['pt'] && GRID402_I18N['pt'][key]) 
           || key;
    for (const [k, v] of Object.entries(params)) {
        str = str.replace(new RegExp(`\\{${k}\\}`, 'g'), v);
    }
    return str;
}

/**
 * Aplica o idioma em todos os elementos com data-i18n
 */
function setLanguage(lang) {
    if (!GRID402_I18N[lang]) lang = 'pt';
    currentLanguage = lang;
    try {
        localStorage.setItem('grid402_lang', lang);
    } catch (_) {}

    document.documentElement.lang = (lang === 'en') ? 'en' : 'pt-BR';

    // Atualiza classes do switcher
    const ptBtn = document.getElementById('lang-btn-pt');
    const enBtn = document.getElementById('lang-btn-en');
    if (ptBtn && enBtn) {
        if (lang === 'pt') {
            ptBtn.classList.add('active');
            enBtn.classList.remove('active');
        } else {
            enBtn.classList.add('active');
            ptBtn.classList.remove('active');
        }
    }

    // Traducao de texto em elementos simples
    document.querySelectorAll('[data-i18n]').forEach(el => {
        const key = el.getAttribute('data-i18n');
        if (GRID402_I18N[lang][key]) {
            el.textContent = GRID402_I18N[lang][key];
        }
    });

    // Traducao de elementos com HTML interno
    document.querySelectorAll('[data-i18n-html]').forEach(el => {
        const key = el.getAttribute('data-i18n-html');
        if (GRID402_I18N[lang][key]) {
            el.innerHTML = GRID402_I18N[lang][key];
        }
    });

    // Traducao de placeholders
    document.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
        const key = el.getAttribute('data-i18n-placeholder');
        if (GRID402_I18N[lang][key]) {
            el.placeholder = GRID402_I18N[lang][key];
        }
    });

    // Traducao de titulos (title attribute)
    document.querySelectorAll('[data-i18n-title]').forEach(el => {
        const key = el.getAttribute('data-i18n-title');
        if (GRID402_I18N[lang][key]) {
            el.title = GRID402_I18N[lang][key];
        }
    });

    // Atualiza contadores e textos dinamicos do header
    const badgeText = document.getElementById('badge-live-text');
    if (badgeText) {
        const count = badgeText.getAttribute('data-i18n-count') || '22';
        badgeText.textContent = (lang === 'en')
            ? `Base Mainnet (8453) â€¢ ${count}/${count} Services Operational`
            : `Base Mainnet (8453) â€¢ ${count}/${count} ServiÃ§os Operantes`;
    }

    // Atualiza presets do playground se a funcao existir
    if (typeof selectPlaygroundPreset === 'function' && typeof currentPlaygroundPreset !== 'undefined') {
        selectPlaygroundPreset(currentPlaygroundPreset);
    }

    // Atualiza botoes do preset bar no playground
    if (typeof playgroundPresets !== 'undefined') {
        Object.keys(playgroundPresets).forEach(k => {
            const btn = document.getElementById('preset-btn-' + k);
            if (btn) {
                const nameSpan = btn.querySelector('.preset-btn-name span:last-child');
                if (nameSpan) {
                    nameSpan.textContent = (lang === 'en' && playgroundPresets_en[k])
                        ? playgroundPresets_en[k].name
                        : playgroundPresets[k].name;
                }
            }
        });
    }

    // Recalcula calculadora de ROI para atualizar textos monetarios
    if (typeof updateRoiCalc === 'function') {
        const slider = document.getElementById('roi-slider-input');
        if (slider) updateRoiCalc(slider.value);
    }

    // Atualiza botoes de preset da calculadora ROI
    const roiPresetBtns = document.querySelectorAll('.roi-presets-row .roi-preset-btn');
    if (roiPresetBtns && roiPresetBtns.length === 4) {
        const vals = [500, 1000, 5000, 15000];
        roiPresetBtns.forEach((b, idx) => {
            const v = vals[idx];
            b.textContent = (lang === 'en')
                ? v.toLocaleString('en-US') + '/mo'
                : v.toLocaleString('pt-BR') + '/mÃªs';
        });
    }

    // Atualiza textos de rodape e modal Web3
    const footerCopy = document.getElementById('footer-copyright-text');
    if (footerCopy) {
        const yr = new Date().getFullYear();
        footerCopy.textContent = t('footer.copyright', { year: yr });
    }
    const footerFee = document.getElementById('footer-fee-text');
    if (footerFee) {
        footerFee.textContent = t('footer.fee', { price: '0.0050' });
    }
    const auditBadge = document.getElementById('audit-overall-badge');
    if (auditBadge && auditBadge.classList.contains('badge-pending') && (auditBadge.textContent.includes('AGUARDANDO') || auditBadge.textContent.includes('AWAITING'))) {
        auditBadge.textContent = (lang === 'en') ? 'AWAITING ANALYSIS' : 'AGUARDANDO ANÃLISE';
    }
    ['audit-step-1-status', 'audit-step-2-status', 'audit-step-3-status'].forEach(id => {
        const st = document.getElementById(id);
        if (st && (st.textContent === 'Aguardando' || st.textContent === 'Pending')) {
            st.textContent = (lang === 'en') ? 'Pending' : 'Aguardando';
        }
    });
    const copyOracleBtn = document.getElementById('btn-copy-oracle');
    if (copyOracleBtn && !copyOracleBtn.textContent.includes('âœ“')) {
        copyOracleBtn.textContent = (lang === 'en') ? 'ðŸ“‹ Copy' : 'ðŸ“‹ Copiar';
    }
    const web3HashInp = document.getElementById('web3-hash-direct-input');
    if (web3HashInp) {
        web3HashInp.placeholder = (lang === 'en')
            ? '0x... (Transaction hash on Base Mainnet)'
            : '0x... (Hash da transaÃ§Ã£o gerado na Base)';
    }
    const manualInp = document.getElementById('playground-manual-input');
    if (manualInp) {
        manualInp.placeholder = (lang === 'en')
            ? 'Paste the JSON response containing the _oracle_proof block...'
            : 'Cole o JSON da resposta contendo o bloco _oracle_proof...';
    }
}

/**
 * Inicializacao automatica na carga
 */
document.addEventListener('DOMContentLoaded', () => {
    let saved = 'pt';
    try {
        saved = localStorage.getItem('grid402_lang') || 'pt';
    } catch (_) {}
    setLanguage(saved);
});
