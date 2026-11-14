/**
 * Grid402 // Modulo de Internacionalizacao (i18n) Client-Side
 * Suporte a Portugues do Brasil (PT-BR) e Ingles (EN)
 * Regra: Todos os '&' em textos foram substituidos por 'e' (ou 'and' em ingles)
 */

const GRID402_I18N = {
    pt: {
        // Navegacao e Header
        'nav.services': 'Serviços',
        'nav.topup': 'Abastecer Saldo',
        'nav.savings': 'Economia 98%',
        'nav.playground': 'Playground',
        'nav.console': 'Console',
        'nav.agents': 'Agentes IA',
        'nav.about': 'Sobre',
        'nav.contact': 'Contato',
        'nav.terms': 'Termos',
        'nav.privacy': 'Privacidade',
        'badge.live': 'Base Mainnet (8453) • {count}/{total} Serviços Operantes',

        // Hero
        'hero.tag': 'Hub Global de Micro-Serviços e Oráculo M2M para Agentes de IA',
        'hero.title': 'Grid402: Infraestrutura Global de Dados Públicos e Utilitários M2M para Agentes de IA',
        'hero.desc': 'Acesse 22 endpoints globais e brasileiros: identidade corporativa mundial (GLEIF LEI em 200+ países), sanções OFAC SDN + AML Web3 na Base, notarização criptográfica ECDSA, auditoria KYB completa (CNPJ + OFAC + LEI), auditoria de domínio (DNS + SSL + ASN), snapshot consolidado de mercado (Forex + Cripto + Indicadores), bolsas mundiais e 30+ moedas do BCE, câmbio, cripto, compliance 360, Selic/CDI/IPCA, feriados ANBIMA, DNS, SSL, BGP/ASN, CEP e CNPJ em milissegundos sem cadastro ou API keys. Liquidação via x402 ou Session Pass pré-pago (<15ms).',
        'hero.btn.topup': '💎 Abastecer Saldo Web3 (Base)',
        'hero.btn.agents': '🤖 Central de Agentes IA',
        'hero.btn.playground': '⚡ Testar no Playground',
        'hero.btn.console': 'Console e SDKs',
        'hero.btn.llms': 'Documentação llms.txt',
        'hero.btn.status': 'Resumo Gratuito (JSON)',

        // Topup Tiers Web3
        'topup.tag': '⚡ Liquidação Instantânea em Lote na Base (USDC)',
        'topup.title': 'Abasteça seu Saldo Web3 em 1 Clique (Session Passes)',
        'topup.desc': 'Pague uma única vez em USDC na rede Base Mainnet e receba na hora um token pass_g402_... com saldo de milhares de requisições de alta velocidade (<15ms) sem assinar transações a cada GET.',
        'topup.starter.tag': 'Starter Pass',
        'topup.starter.desc': 'Ideal para desenvolvedores individuais, testes rápidos e bots pontuais.',
        'topup.starter.btn': 'Comprar Starter (2 USDC)',
        'topup.pro.badge': 'Mais Escolhido',
        'topup.pro.tag': 'Pro / Agent Pass',
        'topup.pro.desc': 'Para agentes autônomos de IA e esteiras de automação contínua.',
        'topup.pro.btn': 'Comprar Pro (5 USDC)',
        'topup.fleet.badge': '+10.000 Bônus',
        'topup.fleet.tag': 'Fleet / Enterprise',
        'topup.fleet.desc': 'Frotas de agentes autônomos, sistemas ERP corporativos e alta demanda.',
        'topup.fleet.btn': 'Comprar Fleet (20 USDC)',

        // Cards dos 14 Servicos Publicos
        'svc.forex.name': 'Câmbio e Moedas',
        'svc.forex.category': 'Mercado',
        'svc.forex.desc': 'Cotações em tempo real de USD, EUR, GBP para BRL com variação diária',
        'svc.crypto.name': 'Cripto Tickers',
        'svc.crypto.category': 'Mercado',
        'svc.crypto.desc': 'Preços de mercado de BTC, ETH, SOL, USDC e BASE em USD e BRL',
        'svc.dns.name': 'DNS Records Lookup',
        'svc.dns.category': 'Rede',
        'svc.dns.desc': 'Resolução autoritativa de registros A, AAAA, MX, TXT, NS e CNAME',
        'svc.ssl.name': 'Inspetor TLS/SSL',
        'svc.ssl.category': 'Rede',
        'svc.ssl.desc': 'Inspeção de cadeia X.509, emissor, SANs e dias até expiração na porta 443',
        'svc.asn.name': 'ASN e IP Info',
        'svc.asn.category': 'Rede',
        'svc.asn.desc': 'Metadados de roteamento BGP, número de ASN, bloco IP e país',
        'svc.cep.name': 'CEP e IBGE',
        'svc.cep.category': 'Dados Abertos',
        'svc.cep.desc': 'Resolução de logradouro, bairro, município, UF e código IBGE oficial',
        'svc.cnpj.name': 'Consulta Cadastral CNPJ',
        'svc.cnpj.category': 'Corporativo',
        'svc.cnpj.desc': 'Situação cadastral, razão social, CNAE, data de abertura e endereço da PJ',
        'svc.indicators.name': 'Indicadores Econômicos',
        'svc.indicators.category': 'Mercado',
        'svc.indicators.desc': 'Taxas oficiais Selic (meta e diária), CDI anual e diário, IPCA acumulado 12m e mensal (BCB e IBGE)',
        'svc.holidays.name': 'Feriados e Calendário Bancário',
        'svc.holidays.category': 'Dados Abertos',
        'svc.holidays.desc': 'Feriados nacionais oficiais e dias sem expediente bancário (ANBIMA e Febraban) com cálculo determinístico',
        'svc.workdays.name': 'Calculadora de Dias Úteis ANBIMA',
        'svc.workdays.category': 'Financeiro',
        'svc.workdays.desc': 'Cálculo de dias úteis bancários (contagem entre datas, projeção de prazos e checagem pontual) no padrão ANBIMA',
        'svc.sanctions.name': 'Sanções e Inidoneidade (CEIS/CNEP)',
        'svc.sanctions.category': 'Compliance',
        'svc.sanctions.desc': 'Consulta cadastros oficiais da CGU (CEIS e CNEP) sobre punições e impedimentos de contratar',
        'svc.simples.name': 'Simples Nacional e MEI',
        'svc.simples.category': 'Fiscal',
        'svc.simples.desc': 'Consulta oficial de enquadramento tributário no Simples Nacional e SIMEI na Receita Federal',
        'svc.compliance.name': 'Compliance Score 360',
        'svc.compliance.category': 'Compliance',
        'svc.compliance.desc': 'Score unificado de risco cadastral (0 a 100) cruzando Receita Federal, CGU e histórico societário',
        'svc.verify.name': 'Verificador Criptográfico',
        'svc.verify.category': 'Segurança',
        'svc.verify.desc': 'Validação matemática local e instantânea de assinatura ECDSA secp256k1 e prova de oráculo (_oracle_proof)',
        'svc.lei.name': 'Identidade Corporativa Global (LEI)',
        'svc.lei.category': 'Global (CC0)',
        'svc.lei.desc': 'Consulta oficial de empresas em 200+ jurisdições via GLEIF LEI (ISO 17442, licença CC0 1.0 Universal)',
        'svc.ofac.name': 'Sanções Globais (OFAC/ONU) e AML Web3',
        'svc.ofac.category': 'Global Compliance',
        'svc.ofac.desc': 'Screening em listas OFAC SDN (US Treasury) e ONU + inspeção on-chain de carteiras EVM na Base Mainnet',
        'svc.notarize.name': 'Carimbo do Tempo e Notarização M2M',
        'svc.notarize.category': 'Criptografia',
        'svc.notarize.desc': 'Emite certificado de existência e timestamp UTC assinado com ECDSA secp256k1 para contratos, logs e outputs de agentes',
        'svc.global-markets.name': 'Bolsas Mundiais e 30+ Moedas (ECB)',
        'svc.global-markets.category': 'Mercado Global',
        'svc.global-markets.desc': 'Calendário de liquidação de bolsas (US NYSE/Fed, EU TARGET2, UK LSE, BR B3) + 30+ moedas oficiais do Banco Central Europeu',
        'svc.pass.name': 'x402 Prepaid Session Pass',
        'svc.pass.category': 'Alta Frequência',
        'svc.pass.desc': 'Compre 100 a 1.000 créditos pré-pagos em 1 transação USDC na Base e execute chamadas em <15ms sem aguardar blocos',

        // ROI Comparador
        'roi.tag': 'Redução Real de Custos para Startups e Agentes',
        'roi.title': 'Por que pagar R$ 500 por mês se o seu agente pode pagar R$ 0,01 por consulta?',
        'roi.desc': 'Compare o modelo arcaico dos birôs tradicionais (mensalidades obrigatórias, análise de crédito e fidelidade de 12 meses) com o modelo aberto M2M do Grid402 liquidado via protocolo x402 na Base.',
        'roi.table.title': 'Comparativo Técnico e Comercial',
        'roi.table.th.criteria': 'Critério',
        'roi.table.th.trad': 'Birôs Tradicionais',
        'roi.table.th.grid': 'Grid402 (x402)',
        'roi.table.cnpj': 'Consulta CNPJ',
        'roi.table.cnpj.trad': 'R$ 0,50 a R$ 2,50',
        'roi.table.cnpj.grid': 'R$ 0,01 (0.0200 USDC)',
        'roi.table.monthly': 'Mensalidade Mínima',
        'roi.table.monthly.trad': 'R$ 500 a R$ 2.000 / mês',
        'roi.table.monthly.grid': 'R$ 0,00 (Zero mensalidade)',
        'roi.table.contract': 'Burocracia e Contrato',
        'roi.table.contract.trad': 'Fidelidade 12 meses',
        'roi.table.contract.grid': 'Zero contrato, sem cadastro',
        'roi.table.auth': 'Autenticação',
        'roi.table.auth.trad': 'API Keys que expiram',
        'roi.table.auth.grid': 'Protocolo x402 nativo',
        'roi.table.math': 'Garantia Matemática',
        'roi.table.math.trad': 'Nenhuma (dados crus)',
        'roi.table.math.grid': 'Assinatura ECDSA secp256k1',
        'roi.table.disclaimer': '* Valores de mercado baseados em planos de entrada dos principais birôs de dados e compliance no Brasil.',
        'roi.calc.title': 'Simulador de Economia em CNPJ e Dados',
        'roi.calc.subtitle': 'Ajuste o volume mensal de consultas do seu robô ou sistema:',
        'roi.calc.label': 'Volume Mensal de Consultas:',
        'roi.calc.cost.trad': 'Custo Tradicional',
        'roi.calc.cost.grid': 'Custo no Grid402',
        'roi.calc.btn.agents': '🤖 Integrar Agente IA',
        'roi.calc.btn.test': '⚡ Testar CNPJ',

        // Playground
        'playground.tag': 'Auditoria Criptográfica M2M',
        'playground.title': 'Playground Interativo e Verificador de Oráculo',
        'playground.desc': 'Execute consultas reais nos micro-serviços do Grid402 em modo Sandbox gratuito ou simule o desafio x402. Valide instantaneamente a atestação criptográfica ECDSA secp256k1 (_oracle_proof) com o motor matemático em tempo real.',
        'playground.btn.challenge': '🔒 Desafio x402 (HTTP 402)',
        'playground.btn.sandbox': '🧪 Sandbox (30 consultas/h)',
        'playground.btn.manual': '✍️ Validar JSON Externo',
        'playground.route': 'Rota:',
        'playground.btn.run': '⚡ Executar Consulta',
        'playground.config.title': 'Configuração da Consulta',
        'playground.manual.desc': 'Cole abaixo qualquer resposta JSON retornada pelo Grid402 ou utilize um dos modelos de teste:',
        'playground.btn.template.valid': 'Carregar Modelo Válido',
        'playground.btn.template.tampered': 'Carregar Modelo Adulterado (Falso)',
        'playground.btn.audit.manual': '🔍 Auditar Assinatura deste JSON',
        'playground.viewer.title': 'Retorno da API',
        'playground.status.ready': 'Pronto',
        'playground.audit.title': 'Motor de Auditoria Matemática ECDSA (Browser)',
        'playground.audit.s1.title': '1. Canonicidade do Payload e Hash SHA-256',
        'playground.audit.s1.desc': 'Reconstrói o payload normalizado em formato canônico e calcula o hash criptográfico:',
        'playground.audit.s2.title': '2. Verificação da Assinatura secp256k1',
        'playground.audit.s2.desc': 'Valida matematicamente a assinatura digital DER/ECDSA contra o hash canônico.',
        'playground.audit.s3.title': '3. Recuperação de Endereço do Oráculo',
        'playground.audit.s3.desc': 'Confirma que a chave recuperada corresponde exatamente à identidade pública do oráculo oficial:',
        'playground.audit.oracle.expected': 'Oráculo Oficial Esperado:',
        'playground.audit.oracle.recovered': 'Chave Recuperada On-Chain:',
        'playground.btn.copy': 'Copiar JSON',
        'playground.viewer.placeholder': '// Clique em "Executar Consulta" ou selecione um micro-serviço acima para auditar...',
        'playground.unlock.badge': '🔒 DADOS COMPLETOS PROTEGIDOS (HTTP 402)',
        'playground.unlock.desc': 'A amostra gratuita acima foi validada. Desbloqueie agora os campos ocultos e a prova criptográfica ECDSA (_oracle_proof):',
        'playground.unlock.cta_web3': '⚡ Desbloquear com Carteira Web3 (1 Clique)',
        'playground.unlock.cta_smart_wallet': '🔑 Passkey / Biometria (Coinbase Smart Wallet)',
        'playground.unlock.cta_qr': '📱 QR Code / Celular',
        'playground.unlock.cta_sandbox': '🧪 Ver Amostra Completa (Sandbox)',

        // Console
        'console.title': 'Console de Teste e Especificações de Integração',
        'console.desc': 'Experimente as requisições em tempo real ou copie os snippets para seus agentes autônomos.',
        'console.tab.interactive': '⚡ Console Interativo',
        'console.tab.smithery': '📦 Smithery MCP (1 Clique)',
        'console.tab.agentkit': '🪙 Coinbase AgentKit (M2M)',
        'console.tab.python': '🐍 Python SDK',
        'console.tab.node': '🟢 Node.js SDK',
        'console.tab.curl': '💻 cURL CLI',
        'console.tab.badges': '🛡️ Badges SVG',
        'console.act.summary': 'Resumo Gratuito (/status)',
        'console.act.forex': 'Câmbio (/api/v1/forex)',
        'console.act.crypto': 'Cripto (/api/v1/crypto)',
        'console.act.dns': 'DNS (/api/v1/dns)',
        'console.act.ssl': 'SSL (/api/v1/ssl)',
        'console.act.asn': 'ASN (/api/v1/asn)',
        'console.act.cep': 'CEP (/api/v1/cep)',
        'console.act.cnpj': 'CNPJ (/api/v1/cnpj)',
        'console.act.indicators': 'Indicadores (/api/v1/indicators)',
        'console.act.holidays': 'Feriados (/api/v1/holidays)',
        'console.act.workdays': 'Dias Úteis (/api/v1/workdays)',
        'console.act.sanctions': 'Sanções CGU (/api/v1/sanctions)',
        'console.act.simples': 'Simples Nacional (/api/v1/simples)',
        'console.act.compliance': 'Score 360 (/api/v1/compliance)',
        'console.act.lei': '🌍 LEI Global (/api/v1/lei)',
        'console.act.ofac': '🛡️ OFAC e AML (/api/v1/ofac)',
        'console.act.notarize': '⏱️ Notarizar (/api/v1/notarize)',
        'console.act.global-markets': '🌐 Bolsas e ECB (/api/v1/global-markets)',
        'console.act.pass': '⚡ Session Pass (/api/v1/pass)',
        'console.btn.send_payment': 'Enviar com X-Payment',
        'console.btn.web3_pay': '⚡ Pagar com Carteira Web3 (1 Clique)',
        'console.btn.smart_wallet': '🔑 Passkey / Biometria (Smart Wallet)',
        'console.btn.web3_qr': '📱 QR Code / Celular',
        'console.btn.sandbox_demo': '🧪 Simular Sandbox (demo)',
        'console.input.placeholder': "Cole seu Session Pass (pass_g402_...), hash de pagamento (0x...) ou assine via Web3",
        'console.teaser.badge': 'AMOSTRA GRATUITA ENCONTRADA',
        'console.teaser.locked': '🔒 Campos protegidos que serão liberados no HTTP 200:',
        'console.teaser.unlock_btn': '⚡ Desbloquear Dados Completos com Web3',
        'console.teaser.close_btn': '✕ Fechar Amostra',
        'console.status.idle': 'Selecione uma das ações acima para testar o retorno da API.',
        'console.output.placeholder': '// Resposta da API aparecerá aqui...',

        // Console Tabs Conteudo
        'console.smithery.title': '📦 Instalação em 1 Clique via Smithery.ai',
        'console.smithery.desc': 'O Grid402 está catalogado no registro global da Smithery.ai. Você pode registrar instantaneamente o servidor de ferramentas no seu Claude Desktop ou Cursor sem precisar editar arquivos de configuração manualmente.',
        'console.smithery.cmd_claude': 'Comando de Instalação no Claude Desktop:',
        'console.smithery.cmd_cursor': 'Comando de Instalação no Cursor:',
        'console.smithery.params': '<strong style="color: #fff;">💡 Parâmetros Suportados no smithery.yaml:</strong><ul style="margin: 0.5rem 0 0 1.2rem; padding: 0;"><li><code>sandbox: true</code> — Ativa o Modo Sandbox gratuito (30 consultas por hora por IP) para testes sem necessidade de carteira.</li><li><code>privateKey: 0x...</code> — Chave privada EVM opcional para auto-liquidação em USDC na Base (0.0050 a 0.0200 USDC por chamada).</li></ul>',

        'console.agentkit.title': '🪙 Integração Nativa Coinbase AgentKit (CDP)',
        'console.agentkit.desc': 'Permita que agentes de IA com carteiras CDP na Base consultem dados públicos e utilitários de rede pagando tarifas x402 de forma 100% autônoma, sem interrupção humana. O provedor de ações intercepta o desafio 402, transfere o USDC e reenvia a requisição de forma transparente.',
        'console.agentkit.example_ts': 'Exemplo em TypeScript (@coinbase/agentkit):',
        'console.agentkit.example_py': 'Exemplo em Python (cdp-agentkit-core):',
        'console.agentkit.docs': '<strong style="color: #fff;">📂 Documentação Completa e Código-Fonte:</strong><p style="margin: 0.4rem 0 0 0;">Consulte o diretório <code>integrations/agentkit/</code> no repositório para implementações prontas com LangChain, CrewAI e guias de configuração.</p>',

        'console.badges.intro': 'Badges em SVG dinâmicos para embeddar no README do seu repositório no GitHub:',
        'console.badges.label': 'Exemplo de Badge em Tempo Real:',
        'console.badges.btn.copy': '📋 Copiar Código Markdown',
        'console.python.code': `from grid402 import Grid402Client

client = Grid402Client()

# 1. Resumo gratuito do hub (22 serviços globais e regionais)
summary = client.get_summary()
print("Serviços operantes:", summary["status"])

# 2. Identidade Corporativa Global (GLEIF LEI - 200+ países)
lei_info = client.get("/api/v1/lei?lei=5493006MHB84DD0ZWV18", payment="demo")

# 3. Modo Sandbox ("X-Payment: demo") ou Session Pass ("X-Payment: pass_g402_...")`,
        'console.node.code': `import { Grid402Client } from 'grid402';

const client = new Grid402Client();

// 1. Consulta gratuita
const summary = await client.getSummary();
console.log('Status do hub:', summary.status);

// 2. Inspetor SSL ou Sanções OFAC / AML Web3
const ssl = await client.getSsl('base.org');
console.log('Expira em dias:', ssl.certificate.days_remaining);

// 3. Modo Sandbox ('X-Payment': 'demo') ou Session Pass ('X-Payment': 'pass_g402_...')`,
        'console.curl.code': `# 1. Resumo gratuito dos 22 micro-serviços
curl -i https://grid402.online/status

# 2. Desafio HTTP 402 com Amostra Preliminar Gratuita (GLEIF LEI ou CNPJ)
curl -i "https://grid402.online/api/v1/lei?lei=5493006MHB84DD0ZWV18"

# 3. Requisição liquidada na Base Mainnet (TxHash) ou Session Pass (<15ms)
curl "https://grid402.online/api/v1/lei?lei=5493006MHB84DD0ZWV18" \\
  -H "X-Payment: pass_g402_SEU_TOKEN_OU_TX_HASH"

# 4. Modo Sandbox gratuito para testes e CI/CD
curl "https://grid402.online/api/v1/lei?lei=5493006MHB84DD0ZWV18" \\
  -H "X-Payment: demo"`,

        // Secao Sobre
        'about.title': 'Sobre o Grid402',
        'about.p1': 'O <strong>Grid402</strong> é uma infraestrutura pública de oráculo de dados e utilitários técnicos de rede desenhada especificamente para a Internet dos Agentes (Agentic Web) e sistemas autônomos Machine-to-Machine (M2M).',
        'about.p2': 'Tradicionalmente, consultar dados oficiais de empresas, endereçamento postal ou status de infraestrutura exige cadastro prévio, cartões de crédito internacionais, aprovação de tokens e chaves estáticas de API com risco de vazamento. O Grid402 elimina toda a fricção burocrática adotando o padrão nativo da web <strong>HTTP 402 Payment Required</strong> com liquidação instantânea de micropagamentos em USDC na rede Base Mainnet.',
        'about.p3': 'Todo o ecossistema opera de maneira estritamente Zero-DB e Stateless, processando apenas informações de domínio público com transparência computacional, propriedade intelectual e arquitetura protegidas por direitos autorais reservados (Leis nº 9.609/1998 e nº 9.610/1998) e total conformidade com a legislação nacional e internacional (LGPD, GDPR e Marco Civil da Internet).',

        // Secao Contato
        'contact.title': 'Contato e Canais Oficiais',
        'contact.subtitle': 'Disponibilizamos canais diretos de suporte técnico, integração para desenvolvedores de IA e governança de dados:',
        'contact.support': 'Suporte Técnico e Geral',
        'contact.dpo': 'Encarregado de Dados (DPO)',
        'contact.phone': 'Central Telefônica',
        'contact.github': 'Código-Fonte no GitHub',
        'contact.npm': 'Pacote npm (Node.js)',
        'contact.pypi': 'Pacote PyPI (Python)',
        'contact.mcp': 'Servidor MCP no npm',
        'contact.oracle': 'Carteira Oráculo na Base',

        // Footer e Modais
        'footer.hub': 'Grid402 // Open Data e Utility Hub • Base Mainnet',
        'footer.copyright': '© {year} Grid402 Network Intelligence. Todos os direitos reservados.',
        'footer.fee': 'Taxa: US$ {price} / consulta',
        'footer.ip.title': 'Direitos Autorais e Propriedade Intelectual Reservados:',
        'footer.ip.desc': '© 2026 Grid402 Network Intelligence. Todos os direitos reservados (Lei nº 9.609/1998 e Lei nº 9.610/1998). O software da plataforma, motor de liquidação HTTP 402, arquitetura de Session Pass, marca, design e documentações são de propriedade exclusiva do Grid402, sendo vedada a reprodução ou redistribuição não autorizada. Os dados técnicos consultados provêm de registros públicos governamentais abertos, licenças CC0 e protocolos livres da internet (DNS, BGP/ASN, TLS/SSL), sobre os quais não reivindicamos exclusividade proprietária.',
        'footer.disclaimer.title': 'Isenção de Responsabilidade e Dados Públicos:',
        'footer.disclaimer.desc': 'O Grid402 é um hub utilitário de acesso a dados de domínio público e protocolos abertos de rede fornecido "NO ESTADO EM QUE SE ENCONTRA" (AS-IS). Não tratamos dados pessoais sensíveis (PII) e operamos em estrita observância à Lei Geral de Proteção de Dados (Lei nº 13.709/2018), ao GDPR (UE 2016/679) e ao Marco Civil da Internet (Lei nº 12.965/2014).',
        'footer.contributors': '© 2026 Grid402 Network Intelligence. Todos os direitos reservados. Protegido pela Lei nº 9.609/1998 e Lei nº 9.610/1998.',

        // Modal Legal
        'modal.terms.title': 'Termos de Uso',
        'modal.terms.tab': 'Termos de Uso',
        'modal.privacy.tab': 'Privacidade e LGPD',

        // Modal Web3
        'modal.web3.title': 'Pagamento Web3 (Base Mainnet)',
        'modal.web3.no_wallet.title': 'Nenhuma extensão Web3 ativa detectada nesta aba',
        'modal.web3.no_wallet.desc': 'Para pagar com 1 clique direto na página, é necessário ter a extensão MetaMask, Rabby ou Coinbase Wallet instalada e ativa. Ou pague pelo celular via QR Code abaixo.',
        'modal.web3.col1.title': '📱 Pagar pelo Celular',
        'modal.web3.col1.desc': 'Escaneie pelo app da MetaMask, Rainbow ou Coinbase Wallet e envie <strong><span id="web3-modal-price">{price}</span> USDC</strong> na rede <strong>Base</strong>.',
        'modal.web3.col2.title': '🔑 Sem Extensão? Use Passkey',
        'modal.web3.col2.desc': 'Crie ou conecte uma Coinbase Smart Wallet em 5 segundos usando biometria/Passkey do celular ou computador, sem instalar extensão nem anotar seed phrase.',
        'modal.web3.col2.btn.smart_wallet': '🔑 Conectar via Passkey (Smart Wallet)',
        'modal.web3.col2.btn.install': '🦊 Ou Instalar Extensão MetaMask',
        'modal.web3.col2.btn.reload': '🔄 Já instalei, recarregar',
        'modal.web3.network': 'Rede:',
        'modal.web3.token': 'Token:',
        'modal.web3.recipient': 'Endereço Destino (Oráculo):',
        'modal.web3.amount': 'Valor Exato:',
        'modal.web3.confirm.title': 'Já enviou a transação?',
        'modal.web3.confirm.desc': 'Cole o Hash (Tx Hash) da transferência gerado na sua carteira para liberar os dados imediatamente:',
        'modal.web3.confirm.btn': 'Confirmar Pagamento e Desbloquear',

        // Mensagens JS
        'msg.loading': 'Carregando requisição...',
        'msg.insert_tx': 'Por favor, insira o hash da transação na Base no campo acima.',
        'msg.copy_success': 'Snippet copiado para a área de transferência!',
        'msg.copied': '✓ Copiado!',
        'msg.audit_empty': 'Por favor, cole um JSON no campo de texto para auditar.',
        'msg.audit_invalid_json': 'O texto informado não é um JSON válido. Verifique a sintaxe e tente novamente.',
        'msg.tx_sent': 'Transação enviada com sucesso na Base Mainnet!',
        'msg.waiting_sign': 'Aguardando assinatura de {price} USDC...',
        'msg.unlocking': 'Desbloqueando dados...',
        'msg.wallet_req': 'Solicitando aprovação na carteira para transferência de {price} USDC na Base para o oráculo...\nCarteira destino: 0x{recipient}',
        'msg.tx_fail': 'Falha na transação Web3: {error}',
        'msg.op_canceled': 'Operação cancelada ou falhou',

        // Pagina /agents
        'agents.brand.sub': 'Central Global de Agentes IA e MCP',
        'agents.nav.home': 'Início',
        'agents.hero.badge': 'x402 Global Autonomous Agent Hub • 22 Serviços',
        'agents.hero.title': 'Infraestrutura Global de Dados e Utilitários M2M para Agentes de IA',
        'agents.hero.lead': 'O Grid402 permite que agentes autônomos de IA (Claude, Cursor, LangChain, CrewAI e Coinbase AgentKit) consultem 22 endpoints globais e brasileiros de domínio público via <strong>HTTP 402</strong> ou <strong>Session Pass pré-pago (&lt;15ms)</strong>, liquidando micropagamentos em USDC na rede Base com <strong>prova criptográfica ECDSA</strong>.',
        'agents.f1.title': 'Zero Cadastro Humano',
        'agents.f1.desc': 'Não requer chaves estáticas de API, cartão de crédito ou login. Agentes de IA contratam serviços diretamente via carteira Web3 on-chain ou Passkey.',
        'agents.f2.title': 'Oráculo Criptográfico',
        'agents.f2.desc': 'Cada resposta inclui assinatura digital ECDSA secp256k1 determinística (chave _oracle_proof), verificável sem estado em menos de 1 ms.',
        'agents.f3.title': 'x402 Session Pass (<15ms)',
        'agents.f3.desc': 'Compre 100 a 1.000 créditos em uma única transação USDC na Base e execute chamadas M2M de alta frequência em menos de 15ms sem aguardar blocos.',
        'agents.s1.title': '1. Integração com Model Context Protocol (MCP)',
        'agents.s1.sub': 'Adicione todas as 22 ferramentas globais e brasileiras ao Claude Desktop ou Cursor com 1 clique.',
        'agents.s2.title': '2. Código Python para Agentes Autônomos',
        'agents.s2.sub': 'Consumo direto tratando o desafio HTTP 402 ou usando Session Pass (pass_g402_...).',
        'agents.s3.title': '3. Teste Rápido no Terminal (cURL)',
        'agents.s3.sub': 'Inspecione desafios x402, emita um Session Pass ou valide provas criptográficas diretamente no terminal.',
        'agents.table.title': 'Catálogo dos 22 Endpoints Globais e Tarifas',
        'agents.table.sub': 'Valores por chamada em USDC na rede Base Mainnet ou em créditos de Session Pass.',
        'agents.th.service': 'Serviço / Fonte Pública',
        'agents.th.price': 'Tarifa (USDC / Pass)',
        'agents.th.proof': 'Prova ECDSA',
        'agents.manifests.title': 'Manifestos para Descoberta Automática',
        'agents.manifests.sub': 'Padrões abertos para que modelos de linguagem e agentes mapeiem o Grid402.',

        // Paginas Institucionais (/about, /contact, /terms, /privacy)
        'inst.footer.copy': '© 2026 Grid402 Network Intelligence. Todos os direitos reservados.',
        'inst.about.body': `
            <p>O <strong>Grid402</strong> é uma infraestrutura global de micro-serviços públicos e oráculo de utilitários de rede desenhada especificamente para sistemas autônomos, robôs e agentes de IA sob o padrão HTTP 402 com liquidação em USDC na rede Base Mainnet.</p>
            <h2>Propósito e Arquitetura Global</h2>
            <p>O projeto elimina chaves estáticas de API e burocracia de cadastros para agentes de software no mundo inteiro. Toda requisição técnica a dados oficiais (GLEIF LEI mundial, OFAC SDN, BCE 30+ moedas, notarização criptográfica ECDSA, Receita Federal, CEP, Banco Central, DNS, SSL e BGP) pode ser liquidada diretamente on-chain ou via Session Pass pré-pago em menos de 15ms.</p>
            <ul>
                <li><strong>Zero-DB e Stateless:</strong> Sem persistência de bancos de dados relacionais; operação 100% em memória e cache atômico.</li>
                <li><strong>Protocolo x402 + Session Pass:</strong> Implementação nativa do código HTTP 402 Payment Required para Machine-to-Machine (M2M) e passes de alta frequência.</li>
                <li><strong>Domínio Público e CC0:</strong> Uso estrito de fontes públicas abertas e licenças CC0 sem dados pessoais sensíveis (Zero PII).</li>
            </ul>
            <h2>Informações Institucionais</h2>
            <div class="card-box">
                <p><strong>Organização:</strong> Grid402 Network Intelligence</p>
                <p><strong>Email de Suporte:</strong> <a href="mailto:contato@grid402.online">contato@grid402.online</a></p>
                <p><strong>Central Telefônica:</strong> <a href="tel:+5511958325848">+55 11 95832-5848</a></p>
                <p><strong>Carteira Oráculo (Base):</strong> <a href="https://basescan.org/address/0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90" target="_blank" rel="noopener noreferrer">0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90</a></p>
            </div>
        `,
        'inst.contact.body': `
            <p>Disponibilizamos canais diretos e estruturados para suporte técnico, parcerias para integradores de IA e contato com o Encarregado de Tratamento de Dados Pessoais (DPO):</p>
            <h2>Canais Oficiais de Atendimento</h2>
            <div class="card-box">
                <p><strong>Email de Suporte Geral e Integrações:</strong> <a href="mailto:contato@grid402.online">contato@grid402.online</a></p>
                <p><strong>Encarregado de Dados (DPO / LGPD / GDPR):</strong> <a href="mailto:contato@grid402.online">contato@grid402.online</a></p>
                <p><strong>Central Telefônica:</strong> <a href="tel:+5511958325848">+55 11 95832-5848</a></p>
            </div>
            <h2>Ecossistema e Código Aberto</h2>
            <ul>
                <li><strong>GitHub Oficial:</strong> <a href="https://github.com/oliveiratc/Grid402" target="_blank" rel="noopener noreferrer">github.com/oliveiratc/Grid402</a></li>
                <li><strong>Pacote Node.js (npm):</strong> <a href="https://www.npmjs.com/package/grid402" target="_blank" rel="noopener noreferrer">npmjs.com/package/grid402</a></li>
                <li><strong>Pacote Python (PyPI):</strong> <a href="https://pypi.org/project/grid402/" target="_blank" rel="noopener noreferrer">pypi.org/project/grid402</a></li>
                <li><strong>Servidor MCP (Model Context Protocol):</strong> <a href="https://www.npmjs.com/package/grid402-mcp" target="_blank" rel="noopener noreferrer">npmjs.com/package/grid402-mcp</a></li>
            </ul>
        `,
        'inst.terms.body': `
            <p>Termos de serviço, licenciamento de dados públicos e conformidade regulatória internacional do hub global de micro-serviços e utilitários M2M Grid402.</p>
            <h2>1. Fornecimento no Estado em que se Encontra (AS-IS)</h2>
            <p>O Grid402 é uma infraestrutura técnica de oráculo M2M fornecida estritamente "NO ESTADO EM QUE SE ENCONTRA" (AS-IS), sem garantias de adequação a finalidades comerciais exclusivas, aconselhamento jurídico ou decisões de crédito.</p>
            <h2>2. Base Legal de Dados Públicos, Domínio Público e Licenças Abertas</h2>
            <p>Todas as 22 rotas do Grid402 processam exclusivamente informações de domínio público, fatos econômicos e registros governamentais abertos, livres de restrições proprietárias:</p>
            <ul>
                <li><strong>Global LEI (/api/v1/lei):</strong> Global Legal Entity Identifier Foundation (GLEIF) sob licença <strong>CC0 1.0 Universal (Public Domain Dedication)</strong>, padrão ISO 17442.</li>
                <li><strong>Sanções Internacionais (/api/v1/ofac):</strong> US Treasury OFAC SDN (domínio público federal sob <strong>17 U.S.C. § 105</strong>) e listas consolidadas públicas do Conselho de Segurança da ONU.</li>
                <li><strong>Mercados Globais e Câmbio (/api/v1/global-markets, /api/v1/forex, /api/v1/crypto):</strong> Taxas de referência do Banco Central Europeu (<strong>EU Open Data Directive 2019/1024</strong>), Banco Central do Brasil (Decreto nº 8.777/2016) e fatos econômicos de mercado não passíveis de copyright (<strong>Lei nº 9.610/1998 Art. 8º, I</strong> e <strong>17 U.S.C. § 102(b)</strong>).</li>
                <li><strong>Dados Corporativos e Feriados Nacionais (/api/v1/cnpj, /api/v1/sanctions, /api/v1/simples, /api/v1/compliance, /api/v1/indicators, /api/v1/holidays, /api/v1/workdays, /api/v1/cep):</strong> Lei de Acesso à Informação (<strong>Lei nº 12.527/2011 - LAI</strong>), Política de Dados Abertos (<strong>Decreto nº 8.777/2016</strong>), Lei Anticorrupção (Lei nº 12.846/2013) e algoritmos matemáticos de calendário civil (Lei nº 9.610/1998 Art. 8º, II e IV).</li>
                <li><strong>Telemetria de Rede e Notarização (/api/v1/dns, /api/v1/ssl, /api/v1/asn, /api/v1/notarize):</strong> Protocolos abertos da Internet (IETF RFC 1035, RFC 5280, RFC 4271 e RFC 8785).</li>
            </ul>
            <h2>3. Micropagamentos x402 e Session Passes</h2>
            <p>Pagamentos efetuados via protocolo HTTP 402 e emissões de Session Pass liquidados em USDC na Base Mainnet (EVM 8453) remuneram exclusivamente a infraestrutura computacional, normalização e assinatura criptográfica ECDSA secp256k1 do Oráculo, sendo definitivos e irreversíveis on-chain.</p>
            <h2>4. Direitos Autorais e Propriedade Intelectual Reservados</h2>
            <p>© 2026 <strong>Grid402 Network Intelligence</strong>. <strong>Todos os direitos reservados.</strong> O software da plataforma Grid402, motor de liquidação x402, arquitetura de Session Pass, design visual, marca e documentações são de propriedade exclusiva do Grid402, protegidos pela <strong>Lei do Software (Lei nº 9.609/1998)</strong>, <strong>Lei de Direitos Autorais (Lei nº 9.610/1998)</strong> e tratados internacionais (Convenção de Berna). É vedada a reprodução, redistribuição ou engenharia reversa da plataforma sem autorização expressa. Os dados técnicos retornados pelas APIs provêm de registros públicos abertos e fatos de domínio público, sobre os quais não reivindicamos exclusividade proprietária.</p>
        `,
        'inst.privacy.body': `
            <p>Diretrizes de privacidade, proteção de dados e conformidade regulatória nacional e internacional (LGPD, GDPR e CCPA) do hub Grid402.</p>
            <h2>1. Arquitetura Zero-PII e Exclusão de Dados de Pessoas Físicas (LGPD Art. 1º / GDPR Recital 14)</h2>
            <p>O Grid402 opera estritamente com dados de <strong>Pessoas Jurídicas</strong>, indicadores macroeconômicos e telemetria de rede. Em conformidade com o <strong>Art. 1º da LGPD (Lei nº 13.709/2018)</strong> e o <strong>Considerando 14 (Recital 14) do GDPR Europeu (Regulamento UE 2016/679)</strong>, registros de pessoas jurídicas não constituem dados pessoais de pessoas naturais. Em consultas de CNPJ, o Grid402 <strong>filtra e descarta na origem</strong> o Quadro de Sócios e Administradores (QSA), nomes de sócios, CPFs, telefones pessoais e e-mails privados.</p>
            <h2>2. Minimização, Pseudonimização e Zero-Retention (/api/v1/notarize)</h2>
            <ul>
                <li><strong>Mascaramento e HMAC de IP:</strong> Endereços IP em métricas operacionais são truncados de forma irreversível (ex: <code>189.40.xxx.xxx</code>) e chaves de controle de taxa de Sandbox utilizam hash criptográfico <code>HMAC-SHA256</code> (pseudonimização conforme Art. 13 da LGPD e Art. 25 do GDPR).</li>
                <li><strong>Notarização Zero-Retention:</strong> No endpoint <code>/api/v1/notarize</code>, o conteúdo enviado é processado exclusivamente em memória RAM volátil para cálculo do digest <code>SHA-256</code> e descartado imediatamente, sem gravação ou exibição de texto bruto.</li>
            </ul>
            <h2>3. Guarda Legal de Registros e Prevenção a Fraudes</h2>
            <p>Registros técnicos estritos de conexão são mantidos sob sigilo pelo prazo legal em cumprimento ao <strong>Art. 15 do Marco Civil da Internet (Lei nº 12.965/2014)</strong>, e hashes públicos de transações na Base Mainnet (<code>X-Payment</code>) são retidos em cache temporário de 7 dias exclusivamente para prevenção de ataques de repetição (Replay Attack), com base no legítimo interesse e segurança cibernética (Art. 7º, IX da LGPD e Art. 6(1)(f) do GDPR).</p>
            <h2>4. Direitos do Titular e Encarregado de Dados (DPO)</h2>
            <p>O Grid402 não utiliza cookies de rastreamento publicitário e não vende ou compartilha informações pessoais (CCPA/CPRA). Para qualquer solicitação de conformidade, auditoria ou exercício de direitos perante o Encarregado de Dados (DPO), contate <a href="mailto:contato@grid402.online">contato@grid402.online</a>.</p>
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
        'badge.live': 'Base Mainnet (8453) • {count}/{total} Services Operational',

        // Hero
        'hero.tag': 'Global Microservices and M2M Oracle Hub for Autonomous AI Agents',
        'hero.title': 'Grid402: Global Open-Data and Network Utilities Infrastructure for AI Agents',
        'hero.desc': 'Access 22 global and regional endpoints: worldwide corporate identity (GLEIF LEI across 200+ countries), US Treasury OFAC SDN sanctions + Base Web3 AML, ECDSA timestamp notarization, full KYB audit (CNPJ + OFAC + LEI), full domain audit (DNS + SSL + ASN), consolidated market snapshot (FX + crypto + indicators), world exchange calendars and 30+ ECB currencies, live FX, crypto spot, Compliance 360, DNS, SSL, BGP/ASN, CEP, and CNPJ in milliseconds with zero signup or API keys. Settled via x402 on Base or Prepaid Session Pass (<15ms).',
        'hero.btn.topup': '💎 Web3 Top-Up (Base)',
        'hero.btn.agents': '🤖 AI Agents Hub',
        'hero.btn.playground': '⚡ Test in Playground',
        'hero.btn.console': 'Console and SDKs',
        'hero.btn.llms': 'llms.txt Documentation',
        'hero.btn.status': 'Free Status (JSON)',

        // Topup Tiers Web3
        'topup.tag': '⚡ Instant Batch Settlement on Base (USDC)',
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
        'roi.desc': 'Compare traditional data bureaus (mandatory monthly retainers, credit checks, and 12-month lock-in contracts) with Grid402’s open M2M model settled via x402 on Base.',
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
        'roi.calc.btn.agents': '🤖 Integrate AI Agent',
        'roi.calc.btn.test': '⚡ Test CNPJ Query',

        // Playground
        'playground.tag': 'M2M Cryptographic Verification',
        'playground.title': 'Interactive Playground and Oracle Verifier',
        'playground.desc': 'Run real queries across Grid402 microservices using free Sandbox mode or simulate the x402 challenge. Instantly verify ECDSA secp256k1 cryptographic attestations (_oracle_proof) using our browser-based math engine in real time.',
        'playground.btn.challenge': '🔒 x402 Challenge (HTTP 402)',
        'playground.btn.sandbox': '🧪 Sandbox (3 tests/hr)',
        'playground.btn.manual': '✍️ Validate External JSON',
        'playground.route': 'Route:',
        'playground.btn.run': '⚡ Run Query',
        'playground.config.title': 'Query Configuration',
        'playground.manual.desc': 'Paste any JSON response returned by Grid402 below or load one of the test templates:',
        'playground.btn.template.valid': 'Load Valid Template',
        'playground.btn.template.tampered': 'Load Tampered Template (Counterfeit)',
        'playground.btn.audit.manual': '🔍 Audit Signature of this JSON',
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
        'playground.unlock.badge': '🔒 FULL DATA PROTECTED (HTTP 402)',
        'playground.unlock.desc': 'The free sample above is verified. Unlock the hidden fields and ECDSA cryptographic proof (_oracle_proof) now:',
        'playground.unlock.cta_web3': '⚡ Unlock with Web3 Wallet (1-Click)',
        'playground.unlock.cta_smart_wallet': '🔑 Passkey / Biometrics (Coinbase Smart Wallet)',
        'playground.unlock.cta_qr': '📱 Mobile QR Code',
        'playground.unlock.cta_sandbox': '🧪 View Full Sample (Sandbox)',

        // Console
        'console.title': 'Testing Console and Integration Specs',
        'console.desc': 'Test requests in real time or copy code snippets directly into your autonomous agents.',
        'console.tab.interactive': '⚡ Interactive Console',
        'console.tab.smithery': '📦 Smithery MCP (1-Click)',
        'console.tab.agentkit': '🪙 Coinbase AgentKit (M2M)',
        'console.tab.python': '🐍 Python SDK',
        'console.tab.node': '🟢 Node.js SDK',
        'console.tab.curl': '💻 cURL CLI',
        'console.tab.badges': '🛡️ SVG Badges',
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
        'console.act.lei': '🌍 Global LEI (/api/v1/lei)',
        'console.act.ofac': '🛡️ OFAC and AML (/api/v1/ofac)',
        'console.act.notarize': '⏱️ Notarize (/api/v1/notarize)',
        'console.act.global-markets': '🌐 Markets and ECB (/api/v1/global-markets)',
        'console.act.pass': '⚡ Session Pass (/api/v1/pass)',
        'console.btn.send_payment': 'Send with X-Payment',
        'console.btn.web3_pay': '⚡ Pay with Web3 Wallet (1-Click)',
        'console.btn.smart_wallet': '🔑 Passkey / Biometrics (Smart Wallet)',
        'console.btn.web3_qr': '📱 Mobile QR Code',
        'console.btn.sandbox_demo': '🧪 Simulate Sandbox (demo)',
        'console.input.placeholder': "Paste Session Pass (pass_g402_...), Base tx hash (0x...) or sign with Web3",
        'console.teaser.badge': 'FREE SAMPLE FOUND',
        'console.teaser.locked': '🔒 Protected fields unlocked on HTTP 200:',
        'console.teaser.unlock_btn': '⚡ Unlock Full Data with Web3',
        'console.teaser.close_btn': '✕ Close Preview',
        'console.status.idle': 'Select one of the actions above to test API output.',
        'console.output.placeholder': '// API response will appear here...',

        // Console Tabs Content
        'console.smithery.title': '📦 1-Click Installation via Smithery.ai',
        'console.smithery.desc': 'Grid402 is indexed in the global Smithery.ai registry. You can instantly register the MCP tools server in your Claude Desktop or Cursor without editing configuration files manually.',
        'console.smithery.cmd_claude': 'Installation Command for Claude Desktop:',
        'console.smithery.cmd_cursor': 'Installation Command for Cursor:',
        'console.smithery.params': '<strong style="color: #fff;">💡 Supported Parameters in smithery.yaml:</strong><ul style="margin: 0.5rem 0 0 1.2rem; padding: 0;"><li><code>sandbox: true</code> — Enables free Sandbox Mode (20 queries/hour per IP) to test without a wallet.</li><li><code>privateKey: 0x...</code> — Optional EVM private key for auto-settlement in USDC on Base (0.0050 to 0.0200 USDC per call).</li></ul>',

        'console.agentkit.title': '🪙 Native Coinbase AgentKit Integration (CDP)',
        'console.agentkit.desc': 'Allow autonomous AI agents equipped with CDP wallets on Base to fetch public data and network utilities paying x402 rates 100% autonomously, with zero human intervention. The action provider intercepts HTTP 402, transfers USDC, and retries the request seamlessly.',
        'console.agentkit.example_ts': 'TypeScript Example (@coinbase/agentkit):',
        'console.agentkit.example_py': 'Python Example (cdp-agentkit-core):',
        'console.agentkit.docs': '<strong style="color: #fff;">📂 Complete Documentation and Source Code:</strong><p style="margin: 0.4rem 0 0 0;">Inspect the <code>integrations/agentkit/</code> directory in the repository for drop-in LangChain, CrewAI, and setup guides.</p>',

        'console.badges.intro': 'Dynamic SVG badges ready to embed into your GitHub repository README:',
        'console.badges.label': 'Live Badge Preview:',
        'console.badges.btn.copy': '📋 Copy Markdown Code',
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
        'about.p3': 'The entire ecosystem operates strictly Zero-DB and Stateless, processing only public domain information with computational transparency, proprietary software and architecture protected by copyright (All Rights Reserved — Laws 9.609/1998 & 9.610/1998), and full regulatory compliance (LGPD, GDPR, CCPA, and Brazilian Civil Rights Framework for the Internet).',

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
        'footer.hub': 'Grid402 // Open Data and Utility Hub • Base Mainnet',
        'footer.copyright': '© {year} Grid402 Network Intelligence. All rights reserved.',
        'footer.fee': 'Rate: US$ {price} / query',
        'footer.ip.title': 'Copyright and All Rights Reserved:',
        'footer.ip.desc': '© 2026 Grid402 Network Intelligence. All rights reserved (Protected by Copyright & Software Laws 9.609/1998, 9.610/1998 & Berne Convention). The Grid402 platform software, HTTP 402 settlement engine, Session Pass architecture, brand, design, and documentation are the exclusive property of Grid402; unauthorized reproduction or redistribution is prohibited. Returned technical data originates from open public records, CC0 registries, and internet standards (DNS, BGP/ASN, TLS/SSL), free of proprietary exclusivity.',
        'footer.disclaimer.title': 'Disclaimer and Open Public Data:',
        'footer.disclaimer.desc': 'Grid402 is an open utility gateway providing access to public domain data and standard network protocols provided "AS IS". We do not process sensitive personally identifiable information (PII) and operate in compliance with international data protection laws (GDPR, LGPD, and CCPA).',
        'footer.contributors': '© 2026 Grid402 Network Intelligence. All rights reserved. Protected by Copyright Laws 9.609/1998 & 9.610/1998.',

        // Legal Modal
        'modal.terms.title': 'Terms of Use',
        'modal.terms.tab': 'Terms of Use',
        'modal.privacy.tab': 'Privacy Policy',

        // Modal Web3
        'modal.web3.title': 'Web3 Payment (Base Mainnet)',
        'modal.web3.no_wallet.title': 'No active Web3 extension detected in this tab',
        'modal.web3.no_wallet.desc': 'For 1-click on-page payment, install MetaMask, Rabby, or Coinbase Wallet. Alternatively, pay from your mobile device using the QR code below.',
        'modal.web3.col1.title': '📱 Pay via Mobile',
        'modal.web3.col1.desc': 'Scan with MetaMask, Rainbow, or Coinbase Wallet app and send <strong><span id="web3-modal-price">{price}</span> USDC</strong> on <strong>Base</strong>.',
        'modal.web3.col2.title': '🔑 No Extension? Use Passkey',
        'modal.web3.col2.desc': 'Create or connect a Coinbase Smart Wallet in 5 seconds using your phone or computer biometrics/Passkey, with zero browser extensions or seed phrases.',
        'modal.web3.col2.btn.smart_wallet': '🔑 Connect via Passkey (Smart Wallet)',
        'modal.web3.col2.btn.install': '🦊 Or Install MetaMask Extension',
        'modal.web3.col2.btn.reload': '🔄 Reload Page',
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
        'msg.copied': '✓ Copied!',
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
        'agents.hero.badge': 'x402 Global Autonomous Agent Hub • 22 Services',
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
        'inst.footer.copy': '© 2026 Grid402 Network Intelligence. All rights reserved.',
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
                <li><strong>Global Sanctions (/api/v1/ofac):</strong> US Treasury OFAC SDN (US Federal Public Domain under <strong>17 U.S.C. § 105</strong>) and United Nations Security Council Consolidated Lists.</li>
                <li><strong>World Markets and FX (/api/v1/global-markets, /api/v1/forex, /api/v1/crypto):</strong> European Central Bank reference rates (<strong>EU Open Data Directive 2019/1024</strong>), Central Bank of Brazil (Decree 8.777/2016), and non-copyrightable market facts (<strong>17 U.S.C. § 102(b)</strong> and <strong>Brazilian Law 9.610/1998 Art. 8, I</strong>).</li>
                <li><strong>Corporate & Statutory Calendars (/api/v1/cnpj, /api/v1/sanctions, /api/v1/simples, /api/v1/compliance, /api/v1/indicators, /api/v1/holidays, /api/v1/workdays, /api/v1/cep):</strong> Brazilian Access to Information Act (<strong>Law 12.527/2011 - LAI</strong>), Open Data Policy (<strong>Decree 8.777/2016</strong>), Anti-Corruption Act (Law 12.846/2013), and mathematical calendar algorithms (Law 9.610/1998 Art. 8, II & IV).</li>
                <li><strong>Network Telemetry & Notarization (/api/v1/dns, /api/v1/ssl, /api/v1/asn, /api/v1/notarize):</strong> Open Internet Engineering Task Force standards (IETF RFC 1035, RFC 5280, RFC 4271, and RFC 8785).</li>
            </ul>
            <h2>3. x402 Micropayments and Session Passes</h2>
            <p>Micropayments settled via HTTP 402 and Prepaid Session Passes issued on Base Mainnet (EVM 8453) compensate strictly for computational normalization and ECDSA secp256k1 cryptographic attestation, and are final and non-refundable on-chain.</p>
            <h2>4. Copyright and All Rights Reserved (Intellectual Property)</h2>
            <p>© 2026 <strong>Grid402 Network Intelligence</strong>. <strong>All rights reserved.</strong> The Grid402 platform software, HTTP 402 settlement engine, Session Pass architecture, visual design, brand, and documentation are the exclusive intellectual property of Grid402, protected by the <strong>Software Protection Act (Law 9.609/1998)</strong>, <strong>Copyright Act (Law 9.610/1998)</strong>, and international treaties (Berne Convention). Unauthorized reproduction, redistribution, or reverse engineering of the platform is strictly prohibited. We claim no proprietary exclusivity over government registries or public-domain facts returned by the APIs.</p>
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
            ? `Base Mainnet (8453) • ${count}/${count} Services Operational`
            : `Base Mainnet (8453) • ${count}/${count} Serviços Operantes`;
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
                : v.toLocaleString('pt-BR') + '/mês';
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
        auditBadge.textContent = (lang === 'en') ? 'AWAITING ANALYSIS' : 'AGUARDANDO ANÁLISE';
    }
    ['audit-step-1-status', 'audit-step-2-status', 'audit-step-3-status'].forEach(id => {
        const st = document.getElementById(id);
        if (st && (st.textContent === 'Aguardando' || st.textContent === 'Pending')) {
            st.textContent = (lang === 'en') ? 'Pending' : 'Aguardando';
        }
    });
    const copyOracleBtn = document.getElementById('btn-copy-oracle');
    if (copyOracleBtn && !copyOracleBtn.textContent.includes('✓')) {
        copyOracleBtn.textContent = (lang === 'en') ? '📋 Copy' : '📋 Copiar';
    }
    const web3HashInp = document.getElementById('web3-hash-direct-input');
    if (web3HashInp) {
        web3HashInp.placeholder = (lang === 'en')
            ? '0x... (Transaction hash on Base Mainnet)'
            : '0x... (Hash da transação gerado na Base)';
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
