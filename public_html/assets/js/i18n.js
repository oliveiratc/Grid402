/**
 * Grid402 // Modulo de Internacionalizacao (i18n) Client-Side
 * Suporte a Portugues do Brasil (PT-BR) e Ingles (EN)
 * Regra: Todos os '&' em textos foram substituidos por 'e' (ou 'and' em ingles)
 */

const GRID402_I18N = {
    pt: {
        // Navegacao e Header
        'nav.home': 'Início',
        'nav.catalog': 'Catálogo',
        'nav.pass': 'Session Pass',
        'nav.trust': 'Trust',
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
        'hero.btn.playground': '⚡ Testar agora (Sandbox)',
        'hero.btn.catalog': 'Ver preços, limites e exemplos →',
        'hero.subnote': 'Sem cartão, sem API key, sem cadastro. Sandbox: <strong>5 consultas por hora por IP</strong>. Produção: x402 ou Session Pass.',
        'hero.btn.console': 'Console e SDKs',
        'hero.btn.llms': 'Documentação llms.txt',
        'hero.btn.status': 'Resumo Gratuito (JSON)',

        // Topup Tiers Web3
        'topup.tag': '⚡ Liquidação Instantânea em Lote na Base (USDC)',
        'topup.title': 'Abasteça seu Saldo Web3 em 1 Clique (Session Passes)',
        'topup.desc': 'Pague uma única vez em USDC na rede Base Mainnet e receba na hora um token pass_g402_... com saldo de milhares de requisições de alta velocidade (<15ms) sem assinar transações a cada GET.',
        'topup.how_to_read': '<strong>Como ler o saldo:</strong> 1 crédito custa <strong>0.0050 USDC</strong> no serviço padrão; rotas de 0.0100 USDC consomem 2 créditos, 0.0200 USDC consomem 4 créditos e 0.0300 USDC consomem 6 créditos.',
        'topup.network_bar': '<strong>Rede:</strong> Base Mainnet (8453) • <strong>Moeda:</strong> USDC • <strong>Contrato:</strong> <a href="https://basescan.org/token/0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913" target="_blank" rel="noopener noreferrer" style="color: #00F0FF; font-family: var(--font-mono); text-decoration: none;">0x8335...2913</a>',
        'topup.starter.tag': 'Starter Pass',
        'topup.starter.desc': 'Ideal para desenvolvedores individuais, testes rápidos e bots pontuais.',
        'topup.starter.pix_label': 'ou R$ 12,00 via Pix / Cartão',
        'topup.starter.b1': '✓ <strong>400 créditos</strong> incluídos (1 crédito = 0.0050 USDC)',
        'topup.starter.b2': '✓ Validade de <strong>30 dias</strong>',
        'topup.starter.b3': '✓ Latência ultrarrápida (&lt;15ms)',
        'topup.starter.b4': '✓ Assinatura HMAC + Spend Controls',
        'topup.starter.btn': 'Comprar Starter (2 USDC)',
        'topup.starter.pix_btn': 'Pagar em R$ 12 (Pix / Cartão)',
        'topup.pro.badge': 'Mais Escolhido',
        'topup.pro.tag': 'Pro / Agent Pass',
        'topup.pro.desc': 'Para agentes autônomos de IA e esteiras de automação contínua.',
        'topup.pro.pix_label': 'ou R$ 29,00 via Pix / Cartão',
        'topup.pro.b1': '✓ <strong>1.000 créditos</strong> incluídos (1 crédito = 0.0050 USDC)',
        'topup.pro.b2': '✓ Validade de <strong>30 dias</strong>',
        'topup.pro.b3': '✓ Compatível com Claude MCP e AgentKit',
        'topup.pro.b4': '✓ Zero espera de bloco por chamada',
        'topup.pro.btn': 'Comprar Pro (5 USDC)',
        'topup.pro.pix_btn': 'Pagar em R$ 29 (Pix / Cartão)',
        'topup.fleet.badge': '+1.000 Bônus',
        'topup.fleet.tag': 'Fleet / Enterprise',
        'topup.fleet.desc': 'Frotas de agentes autônomos, sistemas ERP corporativos e alta demanda.',
        'topup.fleet.pix_label': 'ou R$ 115,00 via Pix / Cartão',
        'topup.fleet.b1': '✓ <strong>6.000 créditos</strong> (20% bônus; 1 crédito = 0.0050 USDC)',
        'topup.fleet.b2': '✓ Validade de <strong>60 dias</strong>',
        'topup.fleet.b3': '✓ Custo efetivo: 0.0033 USDC / crédito',
        'topup.fleet.b4': '✓ Prioridade máxima no cache atômico',
        'topup.fleet.btn': 'Comprar Fleet (20 USDC)',
        'topup.fleet.pix_btn': 'Pagar em R$ 115 (Pix / Cartão)',
        'topup.status.title': 'Aguardando confirmação na carteira...',
        'topup.status.msg': 'Aprove a transferência de USDC na rede Base Mainnet para emitir seu Session Pass instantaneamente.',
        'topup.success.title': 'Session Pass Emitido e Ativo!',
        'topup.success.sub': 'Copie seu token abaixo e envie no cabeçalho <code style="color: #00F0FF;">X-Payment</code> em qualquer requisição:',
        'topup.success.credits': 'Saldo de Créditos',
        'topup.success.tier': 'Plano (Tier)',
        'topup.success.expires': 'Validade',
        'topup.success.token_label': 'Seu Token (X-Payment):',
        'topup.success.copy_btn': '📋 Copiar Token',
        'topup.success.how_label': 'Como usar no cURL, Python, Node ou MCP:',
        'topup.manual.title': '📱 Pagamento Direto ou Mobile (Qualquer Carteira)',
        'topup.manual.badge': 'Sem Extensão Necessária',
        'topup.manual.desc': 'Prefere enviar pelo celular, hardware wallet ou outra aba? Transfira <strong>2, 5 ou 20 USDC</strong> na rede <strong>Base Mainnet</strong> para o endereço oficial do Oráculo abaixo e cole o Hash da transação (<code>0x...</code>) para ativar seu passe na hora:',
        'topup.manual.addr_label': '1. Endereço da Oracle (Rede Base):',
        'topup.manual.copy_addr': '📋 Copiar Endereço',
        'topup.manual.tx_label': '2. Hash da Transação (TxHash na Base):',
        'topup.manual.tx_placeholder': '0x... (Cole o hash da transferência de USDC)',
        'topup.manual.activate_btn': '⚡ Validar e Ativar Passe',
        'topup.footer.console_btn': '💻 Ver Console e Documentação Técnica',

        // Cards dos 22 Servicos Publicos
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
        'svc.domain-audit.name': 'Auditoria de Domínio (DNS + SSL + ASN)',
        'svc.domain-audit.category': 'Rede',
        'svc.domain-audit.desc': 'Auditoria completa de domínio em 1 chamada: registros DNS, certificado TLS/SSL e roteamento ASN/BGP',
        'svc.kyb-audit.name': 'Auditoria KYB e Compliance (CNPJ + OFAC + LEI)',
        'svc.kyb-audit.category': 'Compliance',
        'svc.kyb-audit.desc': 'Auditoria completa de entidade em 1 chamada: dados cadastrais CNPJ, triagem OFAC/sanções e registro LEI',
        'svc.market-snapshot.name': 'Snapshot de Mercado (Forex + Cripto + Indicadores)',
        'svc.market-snapshot.category': 'Financeiro',
        'svc.market-snapshot.desc': 'Snapshot consolidado de mercado em 1 chamada: câmbio Forex, criptoativos e indicadores econômicos',
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
        'svc.pass.desc': 'Compre 400 a 6.000 créditos pré-pagos (1 crédito = 0.0050 USDC) em 1 transação USDC na Base e execute chamadas em <15ms sem aguardar blocos',

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
        'playground.btn.sandbox': '🧪 Sandbox (5 consultas/h)',
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
        'console.smithery.params': '<strong style="color: #fff;">💡 Parâmetros Suportados no smithery.yaml:</strong><ul style="margin: 0.5rem 0 0 1.2rem; padding: 0;"><li><code>sandbox: true</code> — Ativa o Modo Sandbox gratuito (5 consultas por hora por IP) para testes sem necessidade de carteira.</li><li><code>privateKey: 0x...</code> — Chave privada EVM opcional para auto-liquidação em USDC na Base (0.0050 a 0.0200 USDC por chamada).</li></ul>',

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
        'modal.web3.col2.title': '🧩 Carteiras Web3 no navegador',
        'modal.web3.col2.desc': 'Funciona com MetaMask, Rabby, Coinbase Wallet, Brave, Rainbow e outras extensões compatíveis. Ao clicar em um botão Web3, a página pergunta qual carteira usar — sem custódia e sem seed phrase em lugar nenhum.',
        'modal.web3.col2.btn.install': '🦊 Instalar Extensão MetaMask',
        'modal.web3.col2.btn.reload': '🔄 Já instalei, recarregar',
        'modal.web3.network': 'Rede:',
        'modal.web3.token': 'Token:',
        'modal.web3.recipient': 'Endereço Destino (Oráculo):',
        'modal.web3.amount': 'Valor Exato:',
        'modal.web3.confirm.title': 'Já enviou a transação?',
        'modal.web3.confirm.desc': 'Cole o Hash (Tx Hash) da transferência gerado na sua carteira para liberar os dados imediatamente:',
        'modal.web3.confirm.btn': 'Confirmar Pagamento e Desbloquear',

        // Seletor de carteira (EIP-6963)
        'wallet.chooser.title': 'Escolha sua carteira',
        'wallet.chooser.desc': 'Detectamos mais de uma carteira Web3 neste navegador. Selecione qual deseja usar — sua escolha fica salva para os próximos pagamentos.',
        'wallet.chooser.forget': 'Esquecer carteira salva',

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
        'agents.f1.desc': 'Não requer chaves estáticas de API, cartão de crédito ou login. Agentes de IA contratam serviços diretamente via carteira Web3 on-chain (MetaMask, Rabby, Coinbase Wallet e outras).',
        'agents.f2.title': 'Oráculo Criptográfico',
        'agents.f2.desc': 'Cada resposta inclui assinatura digital ECDSA secp256k1 determinística (chave _oracle_proof), verificável sem estado em menos de 1 ms.',
        'agents.f3.title': 'x402 Session Pass (<15ms)',
        'agents.f3.desc': 'Compre 400 a 6.000 créditos (1 crédito = 0.0050 USDC) em uma única transação USDC na Base e execute chamadas M2M de alta frequência em menos de 15ms sem aguardar blocos.',
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
        'agents.nowallet.title': 'Entrada sem carteira Web3 — 3 minutos',
        'agents.nowallet.desc': 'Você pode testar agora com <code>X-Payment: demo</code> (5 consultas/hora por IP) ou comprar créditos em reais via Pix/cartão em <a href="/pass">/pass</a> para receber um token <code>pass_g402_...</code> na hora.',
        'agents.nowallet.btn.catalog': 'Ver preços, limites e exemplos →',
        'agents.nowallet.btn.pass': 'Comprar Session Pass →',
        'agents.eliza.title': '2. Plugin Oficial para ElizaOS (grid402-plugin-elizaos)',
        'agents.eliza.sub': 'Permita que agentes de IA autônomos construídos no ElizaOS (ai16z) consumam dados verificados e liquidem pagamentos x402 nativamente.',
        'agents.btn.copy_json': 'Copiar JSON',
        'agents.btn.copy_ts': 'Copiar TypeScript',
        'agents.btn.copy_py': 'Copiar Python',
        'agents.btn.copy_curl': 'Copiar cURL',
        'agents.yes': '✓ Sim',
        'agents.free': 'Gratuito',
        'nav.payments': 'Pagamentos',
        'nav.growth': 'Growth Program',
        'playground.unlock.flow_note': '<strong style="color:#fff;">Fluxo principal:</strong> clique em “Desbloquear agora”. Preço do endpoint (R$/USDC) está no catálogo.',
        'playground.unlock.other_ways': 'Outras formas de desbloquear',
        'playground.unlock.price_btn': 'Ver preço do endpoint',

        // Pagina /catalog
        'catalog.title': 'Catálogo de Serviços e Preços',
        'catalog.subtitle': 'Serviços, preços, limites e exemplos em uma única página. Consulta paga via <code>POST</code>/<code>GET</code> com <code>X-Payment</code>.',
        'catalog.sub': 'Fonte única da verdade para preços, consumo em créditos de Session Pass, exemplos de chamada e formato de resposta. Cotação de referência exibida: <strong>1 USDC ≈ R$ {rate}</strong>.',
        'catalog.pill.active': '22 endpoints ativos',
        'catalog.pill.price': 'USDC 0.0050 padrão',
        'catalog.pill.sandbox': 'Sandbox grátis: 5 consultas/hora por IP',
        'catalog.pill.unit': 'Unidade base: <strong>1 crédito = 0.0050 USDC</strong> (≈ R$ {brl})',
        'catalog.pill.pass': 'Session Pass: Starter 400 cr (2 USDC) · Pro 1.000 cr (5 USDC) · Fleet 6.000 cr (20 USDC)',
        'catalog.limits.title': 'Limites e autenticação',
        'catalog.limits.c1.title': 'x402 micropagamento',
        'catalog.limits.c1.desc': 'Envie <code>PAYMENT-SIGNATURE</code> (Base64, EIP-3009, token facilitador) ou tx hash para a carteira oficial <code>0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90</code>; o serviço responde em milissegundos.',
        'catalog.limits.c2.title': 'Session Pass (1 crédito = 0.0050 USDC)',
        'catalog.limits.c2.desc': 'Compre créditos em <a href="/pass" style="color:var(--primary)">/pass</a>; envie <code>X-Payment: pass_g402_...</code>. Starter (2 USDC = 400 créditos), Pro (5 USDC = 1.000 créditos), Fleet (20 USDC = 5.000 + 1.000 bônus = 6.000 créditos).',
        'catalog.limits.c3.title': 'Sandbox &amp; Idempotência',
        'catalog.limits.c3.desc': 'Para testes: <code>X-Payment: demo</code> (5 req/hora por IP). Em produção, envie <code>Idempotency-Key</code> para retentativas sem cobrança duplicada (TTL 300s).',
        'catalog.limits.c4.title': 'Rate limit e Spend Controls',
        'catalog.limits.c4.desc': 'Timeout: 2800 ms. Consulte saldo grátis em <code>GET /api/v1/pass/balance</code> e configure teto diário (<code>daily_credit_limit</code>) e whitelist de rotas.',
        'catalog.table.title': 'Endpoints, preço, proveniência, status (22/22 UP) e exemplo',
        'catalog.search.placeholder': 'Filtrar por endpoint, nome, fonte oficial ou categoria…',
        'catalog.th.service': 'Serviço &amp; Fonte Oficial',
        'catalog.th.category': 'Categoria',
        'catalog.th.price': 'Preço USDC',
        'catalog.th.status': 'Status',
        'catalog.th.endpoint': 'Endpoint',
        'catalog.th.usdc': 'Preço (USDC)',
        'catalog.th.brl': 'Referência (R$)',
        'catalog.th.credits': 'Consumo no Pass',
        'catalog.th.example': 'Exemplo',
        'catalog.source_label': 'Fonte:',
        'catalog.state.title': 'Estado dos dados',
        'catalog.state.desc': 'Status marcado como UP: dados on-chain são fatos públicos consultados em tempo real; feeds em cache podem ter até 2 minutos de defasagem.',
        'catalog.cta.trust': 'SLA, disponibilidade e metodologia →',
        'catalog.cta.agents': 'Guia para agentes →',
        'catalog.free': 'Gratuito',
        'catalog.pass.price': '2 / 5 / 20 USDC',
        'catalog.pass.brl': 'R$ 12 / R$ 29 / R$ 115 (Pix/Cartão)',
        'catalog.pass.credits': 'Emite 400 / 1.000 / 6.000 créditos',
        'catalog.s1.title': 'Exemplos de integração (copiar e testar)',
        'catalog.s1.c1.title': '1) Sandbox gratuito (sem carteira, sem cadastro)',
        'catalog.s1.c1.note': 'Limite: 5 consultas por hora por IP.',
        'catalog.s1.c2.title': '2) Com Session Pass (recomendado para produção)',
        'catalog.s1.c2.note': 'Adquira em <a href="/pass">/pass</a> com Pix, cartão ou USDC na Base.',
        'catalog.s1.c3.title': '3) Resposta assinada pelo oráculo (exemplo resumido)',
        'catalog.s2.title': 'Regras operacionais e limites',
        'catalog.s2.l1': '<strong>Sandbox:</strong> 5 consultas por hora por IP usando o cabeçalho <code>X-Payment: demo</code>.',
        'catalog.s2.l2': '<strong>Session Pass:</strong> 1 crédito equivale a <code>0.0050 USDC</code>. Endpoints de maior valor consomem múltiplos proporcionais (2, 4 ou 6 créditos).',
        'catalog.s2.l3': '<strong>Validade dos passes:</strong> Starter e Pro expiram em 30 dias; Fleet expira em 60 dias.',
        'catalog.s2.l4': '<strong>Verificação criptográfica:</strong> toda resposta paga ou de sandbox inclui <code>_oracle_proof</code> verificável gratuitamente em <code>POST /api/v1/verify</code>.',

        // Pagina /pass
        'pass.title': 'Comprar Session Pass <code>pass_g402_...</code>',
        'pass.subtitle': 'Pague uma vez em <strong>BRL (Pix ou Cartão via Mercado Pago)</strong> ou em <strong>USDC na rede Base</strong> e receba na hora um token com saldo de milhares de requisições (&lt;15ms), sem precisar assinar transação a cada chamada. Ideal para n8n, Make, Zapier, Python, Node e MCP.',
        'pass.sandbox_note': '<strong>Modo Sandbox Mercado Pago ativo:</strong> pagamentos gerados aqui são simulados para testes e não realizam cobrança real.',
        'pass.email.label': 'E-mail para recibo (opcional):',
        'pass.email.placeholder': 'dev@suaempresa.com.br',
        'pass.panel.title': 'Pagamento Mercado Pago',
        'pass.panel.waiting': 'Aguardando pagamento...',
        'pass.pix.code_label': 'Código Pix Copia e Cola:',
        'pass.pix.copy_btn': 'Copiar Pix',
        'pass.pix.instructions': 'Após pagar no aplicativo do seu banco, o token <code>pass_g402_...</code> aparecerá aqui automaticamente em alguns segundos. Não feche esta aba.',
        'pass.pix.sandbox_approve': 'Simular Aprovação Pix (Sandbox)',
        'pass.success.title': 'Session Pass emitido com sucesso!',
        'pass.success.copy_btn': 'Copiar Token',
        'pass.packages.title': 'Pacotes',
        'pass.btn.card': 'Pagar com Cartão (Mercado Pago)',
        'pass.btn.usdc': 'Pagar com USDC (Web3)',
        'pass.howto.title': 'Como usar o token',
        'pass.howto.desc': 'Ou defina <code>GRID402_SESSION_PASS=pass_g402_...</code> no servidor MCP (<code>grid402-mcp</code>) ou nas credenciais do n8n.',
        'pass.hero.badge': '⚡ Alta Frequência M2M • Sub-15ms • Base Mainnet (8453) + Pix/Cartão',
        'pass.hero.title': 'x402 Prepaid Session Pass',
        'pass.hero.sub': 'Compre créditos pré-pagos via <strong>USDC na Base</strong> ou em <strong>Reais (Pix e Cartão via Mercado Pago)</strong> e receba um token <code>pass_g402_...</code> para chamadas M2M instantâneas sem precisar assinar transações a cada requisição.',
        'pass.how_to_read': '<strong>Como ler o saldo:</strong> 1 crédito custa <strong>0.0050 USDC</strong> no serviço padrão; rotas de 0.0100 USDC consomem 2 créditos, 0.0200 USDC consomem 4 créditos e 0.0300 USDC consomem 6 créditos. Veja a tabela completa em <a href="/catalog">/catalog</a>.',
        'pass.starter.credits': '400 créditos M2M (1 crédito = 0.0050 USDC • 30 dias)',
        'pass.starter.b1': 'Equivale a 400 consultas de 0.0050 USDC',
        'pass.starter.b2': 'Ou 100 consultas corporativas (LEI / CNPJ / OFAC)',
        'pass.starter.b3': 'Latência de validação < 15ms',
        'pass.starter.b4': 'Ideal para testes de agentes e protótipos',
        'pass.pro.badge': 'MAIS POPULAR',
        'pass.pro.name': 'Pro / Agent Pass',
        'pass.pro.credits': '1.000 créditos M2M (1 crédito = 0.0050 USDC • 30 dias)',
        'pass.pro.b1': 'Equivale a 1.000 consultas de 0.0050 USDC',
        'pass.pro.b2': 'Ou 250 consultas corporativas (LEI / CNPJ / OFAC)',
        'pass.pro.b3': 'Suporte nativo no MCP Server e SDKs',
        'pass.pro.b4': 'Ideal para agentes em produção contínua',
        'pass.fleet.badge': '+20% BÔNUS',
        'pass.fleet.credits': '6.000 créditos M2M (1.000 de bônus • 60 dias)',
        'pass.fleet.b1': '5.000 créditos base + 1.000 créditos bônus grátis',
        'pass.fleet.b2': 'Equivale a 6.000 consultas de 0.0050 USDC',
        'pass.fleet.b3': 'Validade estendida de 60 dias',
        'pass.fleet.b4': 'Ideal para frotas multi-agentes e alto volume',
        'pass.box1.title': '💳 Opção 1: Comprar em Reais (Pix ou Cartão via Mercado Pago)',
        'pass.box1.desc': 'Ideal para empresas e desenvolvedores no Brasil que desejam emitir um <code>pass_g402_...</code> sem carteira Web3. Após a confirmação do Pix ou cartão, seu token é liberado automaticamente nesta tela.',
        'pass.box1.btn.starter': 'Comprar Starter — R$ {brl} (400 créditos)',
        'pass.box1.btn.pro': '⚡ Comprar Pro — R$ {brl} (1.000 créditos)',
        'pass.box1.btn.fleet': 'Comprar Fleet — R$ {brl} (6.000 créditos)',
        'pass.box2.title': '⛓️ Opção 2: Ativar Passe com USDC na Base Mainnet (On-Chain)',
        'pass.box2.s1': 'Transfira <strong>2 USDC</strong> (Starter), <strong>5 USDC</strong> (Pro) ou <strong>20 USDC</strong> (Fleet) na rede <strong>Base Mainnet (Chain ID 8453)</strong> para a carteira oficial do Oráculo:',
        'pass.box2.s2': 'Cole abaixo o <strong>Hash da Transação (0x...)</strong> confirmada na Base e clique em Emitir Session Pass:',
        'pass.box2.btn': '⚡ Emitir Session Pass',
        'pass.box3.title': '🔍 Consultar Saldo e Validade de um Session Pass',
        'pass.box3.desc': 'Insira seu token <code>pass_g402_...</code> para verificar quantos créditos restam:',
        'pass.box3.btn': 'Consultar Saldo',
        'pass.box4.title': '🤖 Como usar seu Session Pass nos Agentes e SDKs',

        // Pagina /trust
        'trust.badge': 'Transparência Operacional e Criptográfica',
        'trust.title': 'Trust Center, Governança &amp; Proveniência',
        'trust.subtitle': 'Transparência institucional, disponibilidade histórica verificável (30 dias), matriz de fontes públicas oficiais, limites de escopo e política de segurança do oráculo M2M <strong>Grid402</strong> (<code>grid402.online</code>).',
        'trust.sub': 'Verificação pública da identidade criptográfica do oráculo, disponibilidade em tempo real dos 22 micro-serviços, fontes oficiais de dados e garantias de privacidade Zero-DB.',
        'trust.kpi1': 'Uptime Histórico (30 dias) · Meta Best-Effort: 99,5%',
        'trust.kpi2': 'Serviços Operacionais (UP) no Snapshot Atual',
        'trust.kpi3': 'Taxa de Sucesso Sem Erros 5xx',
        'trust.kpi3_p95': 'Latência p95:',
        'trust.kpi4': 'Desafios HTTP 402 Emitidos + Replay Bloqueados',
        'trust.notice': '<strong>Nota de Desambiguação de Marca (<code>grid402.online</code>):</strong><br>O <strong>Grid402</strong> (operado exclusivamente no domínio canônico <code>https://grid402.online</code>) é uma infraestrutura independente de <strong>Oráculo de Dados Públicos e Utilitários de Rede M2M liquidados via HTTP 402 na blockchain Base (Chain ID 8453)</strong>. Não possui qualquer vínculo societário, técnico ou operacional com projetos homônimos de distribuição de energia elétrica (<em>smart-grid / physical power grid</em>) ou iniciativas acadêmicas de terceiros.',
        'trust.k1.label': 'Serviços Operacionais',
        'trust.k1.sub': 'Monitoramento contínuo pelo coletor e sondas ativas.',
        'trust.k2.label': 'Assinatura Criptográfica do Oráculo',
        'trust.k2.active': 'ATIVA (Chave Dedicada)',
        'trust.k2.fallback': 'MODO FALLBACK',
        'trust.k3.label': 'Liquidação On-Chain',
        'trust.k3.sub': 'Protocolo HTTP 402 (EIP-3009 USDC) e Session Pass de baixa latência.',
        'trust.k4.label': 'Arquitetura de Dados',
        'trust.k4.sub': 'Sem banco relacional, sem retenção de PII, rate-limit via HMAC-SHA256.',
        'trust.s1.title': '1. Identificação Institucional e Governança do Operador',
        'trust.s1.list': '<li><strong>Operador e Mantenedor:</strong> Grid402 Network Intelligence (Responsável Técnico: Thiago Campos — São Paulo, SP, Brasil).</li><li><strong>Carteira Oficial do Oráculo e Recebedora (Base Mainnet - Chain ID 8453):</strong> <code>0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90</code> (<a href="https://basescan.org/address/0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90" target="_blank" rel="noopener noreferrer">Auditar no BaseScan</a>).</li><li><strong>Política de Disponibilidade (SLA):</strong> Operação sob regime <em>Best-Effort</em> com meta técnica de disponibilidade de <strong>99,5%</strong> nas rotas de oráculo e cache local resiliente para proteção contra oscilações das fontes governamentais upstream.</li><li><strong>Repositório Oficial e Código Aberto dos SDKs/MCP:</strong> <a href="https://github.com/oliveiratc/Grid402" target="_blank" rel="noopener noreferrer">github.com/oliveiratc/Grid402</a> · Pacotes oficiais: <a href="https://www.npmjs.com/package/grid402" target="_blank" rel="noopener noreferrer">npm: grid402</a>, <a href="https://www.npmjs.com/package/grid402-mcp" target="_blank" rel="noopener noreferrer">npm: grid402-mcp</a>, <a href="https://pypi.org/project/grid402/" target="_blank" rel="noopener noreferrer">PyPI: grid402</a>.</li><li><strong>Contato de Suporte, DPO (LGPD/GDPR) e Disclosure de Vulnerabilidades:</strong> <a href="mailto:contato@grid402.online">contato@grid402.online</a> · Manifesto de segurança: <a href="/.well-known/security.txt">/.well-known/security.txt</a>.</li>',
        'trust.s1.desc': 'Todas as respostas pagas e de sandbox do Grid402 incluem um objeto <code>_oracle_proof</code> assinado com a curva elíptica <code>secp256k1</code> sobre o hash canônico <code>SHA-256</code> do payload. Qualquer agente ou auditor externo pode validar a prova offline ou via <code>POST /api/v1/verify</code>.',
        'trust.s1.wallet': 'Carteira Recebedora / Oráculo (Base Mainnet)',
        'trust.s1.usdc': 'Contrato Oficial USDC (Base)',
        'trust.s1.alg': 'Algoritmo de Assinatura',
        'trust.s1.verify': 'Endpoint Gratuito de Verificação',
        'trust.s1.pubkey': 'Chave Pública Secp256k1 Configurada',
        'trust.s2.title': '2. Série Histórica de Disponibilidade (Últimos 30 Dias)',
        'trust.s2.desc': 'Monitoramento contínuo dos 22 micro-serviços.',
        'trust.s2.ago': '30 dias atrás',
        'trust.s2.avg': 'Média 30d:',
        'trust.s2.today': 'Hoje',
        'trust.s2.th.service': 'Serviço',
        'trust.s2.th.endpoint': 'Endpoint',
        'trust.s2.th.source': 'Fonte Primária / Padrão',
        'trust.s2.th.ttl': 'TTL de Cache',
        'trust.s2.th.price': 'Preço / Créditos',
        'trust.s2.th.status': 'Status',
        'trust.s3.title': '3. Escopo Transparente: Dados Públicos Oficiais vs. Bureaus Privados de Crédito',
        'trust.s3.desc': 'O Grid402 posiciona-se com transparência absoluta sobre o que entrega e o que não faz parte do seu escopo:',
        'trust.s3.th1': 'Dimensão',
        'trust.s3.th2': 'O que o Grid402 Entrega (Oráculo Público M2M)',
        'trust.s3.th3': 'O que Exige Bureau de Crédito Privado (Fora do Escopo)',
        'trust.s3.tbody': '<tr><td><strong>Fontes de Dados</strong></td><td>Registros governamentais abertos (Receita Federal CNPJ/Simples, CGU CEIS/CNEP, BCB PTAX/SGS, IBGE, GLEIF LEI CC0, US Treasury OFAC SDN, BCE, DNS/TLS/BGP).</td><td>Bancos de dados privados de inadimplência comercial, protestos cartorários privados, cheques sem fundo e histórico bancário fechado (ex.: Serasa, SPC, Boa Vista, Quod).</td></tr><tr><td><strong>Garantia e Prova</strong></td><td>Normalização determinística em JSON + bloco de proveniência <code>_meta</code> + assinatura criptográfica <strong>ECDSA secp256k1</strong> (<code>_oracle_proof</code>) auditável offline.</td><td>Score de crédito proprietário fechado sem assinatura criptográfica pública de oráculo.</td></tr><tr><td><strong>Privacidade (LGPD/GDPR)</strong></td><td>Arquitetura estritamente <strong>Zero-PII</strong>: processa exclusivamente Pessoas Jurídicas e fatos macroeconômicos/rede, filtrando CPFs e sócios na origem.</td><td>Consultas a CPF de pessoas naturais, renda presumida de indivíduos e histórico de consumo pessoal.</td></tr><tr><td><strong>Modelo Comercial</strong></td><td>Zero cadastro, zero mensalidade. Liquidação por requisição via HTTP 402 (<code>0.0050 a 0.0300 USDC</code>) ou Session Pass pré-pago (<code>1 crédito = 0.0050 USDC</code>).</td><td>Contratos anuais com franquia mínima mensal, homologação cadastral manual e faturamento pós-pago.</td></tr>',
        'trust.s4.title': '4. Matriz de Proveniência e Freshness (TTL) dos 22 Serviços',
        'trust.s4.desc': 'Cada resposta HTTP 200 paga ou de sandbox inclui o objeto <code>_meta</code> assinado junto ao <code>_oracle_proof</code>, atestando a fonte upstream exata e a política de cache:',
        'trust.s4.th1': 'Endpoint',
        'trust.s4.th2': 'Serviço',
        'trust.s4.th3': 'Fonte Oficial Upstream',
        'trust.s4.th4': 'Cache TTL',
        'trust.s4.th5': 'Tarifa (USDC / Pass)',
        'trust.s5.title': '5. Histórico de Versões e Preços (Changelog Público)',
        'trust.s5.list': '<li><strong>v3.2.0 (2026-11):</strong> Observabilidade estruturada JSONL Zero-PII (<code>requests.jsonl</code> + <code>X-Request-Id</code>), latência <code>p95</code> em tempo real, handshake MCP com <code>instructions</code> (24 tools), Dual-License explícita (MIT SDKs + Gateway Proprietário) e suíte automatizada de concorrência paralela.</li><li><strong>v3.1.0 (2026-11):</strong> Unificação da matemática oficial do Session Pass (<code>1 crédito = 0.0050 USDC</code>: Starter 2 USDC = 400 créditos, Pro 5 USDC = 1.000 créditos, Fleet 20 USDC = 6.000 créditos), suporte a <code>Idempotency-Key</code> (TTL 300s), Spend Controls por passe (<code>daily_credit_limit</code>, <code>allowed_endpoints</code> e <code>GET /api/v1/pass/balance</code>), injeção do bloco <code>_meta</code> assinado, série histórica de 30 dias e cobertura de 22 ferramentas no servidor MCP.</li><li><strong>v3.0.0 (2026-11):</strong> Expansão para 22 serviços com endpoints compostos (<code>/api/v1/domain-audit</code>, <code>/api/v1/kyb-audit</code>, <code>/api/v1/market-snapshot</code>), integração nativa com facilitador x402 v2 (EIP-3009) e checkout híbrido Pix/Cartão + USDC.</li>',
        'trust.s6.title': '6. Política de Incidentes, Chaves e Responsabilidade',
        'trust.s6.list': '<li><strong>S0 (Indisponibilidade Crítica):</strong> acionamento imediato e fallback automático para snapshot assinado em cache com indicação explícita de timestamp em <code>_meta.fetched_at</code>.</li><li><strong>S1 (Degradação de Fonte Governamental Externa):</strong> comportamento <em>fail-closed</em> com erro estruturado claro, sem geração de dados sintéticos e sem consumo indevido de créditos em falhas 5xx.</li><li><strong>Gestão Não-Custodial de Chaves M2M e Spend Controls:</strong> O Grid402 nunca armazena ou solicita chaves privadas ou seed phrases de clientes. Agentes autônomos usando <code>GRID402_PRIVATE_KEY</code> devem utilizar carteiras operacionais dedicadas com baixo saldo ou Session Passes (<code>pass_g402_...</code>) limitados por <code>daily_credit_limit</code> e <code>allowed_endpoints</code>; os logs estruturados (<code>requests.jsonl</code> + <code>X-Request-Id</code>) jamais gravam tokens completos ou IPs em claro.</li><li><strong>Isenção de Decisão de Crédito:</strong> O Grid402 fornece dados técnicos públicos verificáveis e não realiza concessão de crédito, aconselhamento jurídico ou aprovação cadastral.</li>',
        'trust.s3.c1.title': 'Zero-PII e LGPD / GDPR',
        'trust.s3.c1.desc': 'Consultas corporativas (CNPJ, Compliance, KYB) descartam dados de pessoas físicas (QSA, CPFs, telefones e e-mails pessoais) na origem. Endereços IP em métricas são truncados.',
        'trust.s3.c2.title': 'Anti-Replay Atômico',
        'trust.s3.c2.desc': 'Cada transação on-chain (<code>tx_hash</code>) ou autorização EIP-3009 (<code>nonce</code>) é registrada atomicamente com bloqueio exclusivo (<code>flock</code>), impedindo reutilização.',
        'trust.s3.c3.title': 'Spend Controls e Idempotência M2M',
        'trust.s3.c3.desc': 'Session Passes suportam teto diário de créditos, lista de serviços permitidos, rotação de token e cache de idempotência (<code>Idempotency-Key</code> por 300s) sem cobrança duplicada.',

        // Outras paginas publicas (/agenthub, /reputation, /growth, /payment-dashboard)
        'agenthub.title': 'GRID402 AGENT HUB',
        'agenthub.subtitle': 'Central de integração para agentes autônomos: documentação, exemplos, preços e compatibilidade.',
        'agenthub.sub': 'Central de integração para agentes autônomos: documentação, exemplos, preços e compatibilidade.',
        'agenthub.catalog.title': 'Catálogo de serviços',
        'agenthub.catalog.link': 'Preços e tarifas →',
        'agenthub.examples.title': 'Exemplos de integração',
        'agenthub.rules.title': 'Regras de produção',
        'agenthub.rules.list': '<li>Não movimente fundos sem autorização on-chain compatível com suas políticas.</li><li>Prefira <code>demo</code> para testes sandbox (5 consultas/h/IP).</li><li>Use Session Pass para reduzir latência e custo administrativo.</li><li>Valide <code>_oracle_proof</code> para respostas sensíveis.</li>',
        'agenthub.s1': 'Catálogo de serviços',
        'agenthub.pricing_link': 'Preços e tarifas →',
        'agenthub.s2': 'Exemplos de integração',
        'agenthub.budget.desc': 'Agentes devem implementar orçamento próprio; Grid402 não autoriza movimentação de fundos sem permissão explícita do mantenedor.',
        'agenthub.s3': 'Regras de produção',
        'agenthub.r1': 'Não movimente fundos sem autorização on-chain compatível com suas políticas.',
        'agenthub.r2': 'Prefira <code>demo</code> para testes sandbox (5 consultas/h/IP).',
        'agenthub.r3': 'Use Session Pass para reduzir latência e custo administrativo.',
        'agenthub.r4': 'Valide <code>_oracle_proof</code> para respostas sensíveis.',

        'rep.back': '← Growth Program',
        'rep.title': 'GRID402 On-Chain Reputation',
        'rep.subtitle': 'Ranking público de builders validados com base em consumo comprovado. Sem dados pessoais ou endereço completo de carteira.',
        'rep.sub': 'Ranking público de builders validados com base em consumo comprovado. Sem dados pessoais ou endereço completo de carteira.',
        'rep.empty': 'Nenhum builder validado ainda. <a href="/growth">Cadastre-se no programa →</a>',
        'rep.th.builder': 'Builder',
        'rep.th.status': 'Status',
        'rep.th.calls': 'Chamadas pagas',

        'growth.title': 'GRID402 AGENT GROWTH PROGRAM',
        'growth.subtitle': 'Programa de aquisição, ativação, recompra e retenção para agentes autônomos que usam o Grid402 sobre USDC + x402 na Base.',
        'growth.sub': 'Programa de aquisição, ativação, recompra e retenção para agentes autônomos que usam o Grid402 sobre USDC + x402 na Base.',
        'growth.c1.p1': '<strong>10 créditos bônus</strong> para agentes que fizerem a primeira consulta validada.',
        'growth.c1.p2': 'Elegibilidade: primeira transação liquidada na carteira indicada; 1 promoção por carteira.',
        'growth.c2.p1': '<strong>100 créditos</strong> por integração ativa que passe por 5 chamadas pagas.',
        'growth.c2.p2': 'Registro, validação administrativa e controle antifraude obrigatórios.',
        'growth.c3.p1': '<strong>25 créditos</strong> por integração onboard validada por 10 chamadas.',
        'growth.c3.p2': 'Links de indicação com HMAC; sem autorreferência.',
        'growth.c1.desc': '<strong>10 créditos bônus</strong> para agentes que fizerem a primeira consulta validada.',
        'growth.c1.sub': 'Elegibilidade: primeira transação liquidada na carteira indicada; 1 promoção por carteira.',
        'growth.c2.desc': '<strong>100 créditos</strong> por integração ativa que passe por 5 chamadas pagas.',
        'growth.c2.sub': 'Registro, validação administrativa e controle antifraude obrigatórios.',
        'growth.c3.desc': '<strong>25 créditos</strong> por integração onboard validada por 10 chamadas.',
        'growth.c3.sub': 'Links de indicação com HMAC; sem autorreferência.',
        'growth.s1.title': '5.1 Agent Starter',
        'growth.s1.list': '<li>Gere uma carteira Base e conecte-a no <a href="/#playground">playground</a> ou configure um payer x402 no SDK/MCP.</li><li>Faça uma chamada paga (ex.: <code>curl -H "X-Payment: $PAYTOKEN" https://grid402.online/api/v1/crypto</code>).</li><li>Use o crédito promocional para testar outros endpoints.</li>',
        'growth.s1.l1': 'Gere uma carteira Base e conecte-a no <a href="/#playground">playground</a> ou configure um payer x402 no SDK/MCP.',
        'growth.s1.l2': 'Faça uma chamada paga (ex.: <code>curl -H "X-Payment: $PAYTOKEN" https://grid402.online/api/v1/crypto</code>).',
        'growth.s1.l3': 'Use o crédito promocional para testar outros endpoints.',
        'growth.s1.note': 'As solicitações ficam pendentes de validação administrativa antes de emissão.',
        'growth.s2.title': '5.2 Builder Rewards',
        'growth.s2.name': 'Nome do builder / time',
        'growth.s2.email': 'E-mail ou contato',
        'growth.s2.url': 'Repositório/URL da integração',
        'growth.s2.btn': 'Cadastrar como builder',
        'growth.s3.title': '5.3 Agent Referrals',
        'growth.s3.wallet': 'Carteira Base (0x...)',
        'growth.s3.btn': 'Gerar referral code',
        'growth.f1.name': 'Nome do builder / time',
        'growth.f1.email': 'E-mail ou contato',
        'growth.f1.url': 'Repositório/URL da integração',
        'growth.f1.btn': 'Cadastrar como builder',
        'growth.f2.wallet': 'Carteira Base (0x...)',
        'growth.f2.btn': 'Gerar referral code',
        'growth.compliance.title': 'Conformidade',
        'growth.compliance.desc': 'O Grid402 oferece infraestrutura e dados públicos verificáveis; não decide crédito, cadastro ou atribuição de risco. O builder configurador dos agentes assume responsabilidade pelas decisões do próprio sistema.',
        'growth.comp.title': 'Conformidade',
        'growth.comp.desc': 'O Grid402 oferece infraestrutura e dados públicos verificáveis; não decide crédito, cadastro ou atribuição de risco. O builder configurador dos agentes assume responsabilidade pelas decisões do próprio sistema.',

        'paydash.title': 'Payment Dashboard',
        'paydash.subtitle': 'Área de acompanhamento para pagamento x402 individual e Session Pass. Os dois produtos são distintos e aparecem aqui com regras separadas.',
        'paydash.sub': 'Área de acompanhamento para pagamento x402 individual e Session Pass. Os dois produtos são distintos e aparecem aqui com regras separadas.',
        'paydash.ppr.title': 'Pay-per-Request',
        'paydash.ppr.desc': 'Cada chamada é paga diretamente via x402/USDC. Ideal para consumo baixo, sem pré-carga.',
        'paydash.ppr.link': 'Consultar preços →',
        'paydash.pass.title': 'Agent Pass',
        'paydash.pass.desc': 'Pré-carga de créditos. Ideal para consumo recorrente com latência &lt;15ms.',
        'paydash.pass.link': 'Comprar pass →',
        'paydash.c1.title': 'Pay-per-Request',
        'paydash.c1.desc': 'Cada chamada é paga diretamente via x402/USDC. Ideal para consumo baixo, sem pré-carga.',
        'paydash.c1.link': 'Consultar preços →',
        'paydash.c2.title': 'Agent Pass',
        'paydash.c2.desc': 'Pré-carga de créditos. Ideal para consumo recorrente com latência &lt;15ms.',
        'paydash.c2.link': 'Comprar pass →',
        'paydash.status.title': 'Status do Pass (opcional)',
        'paydash.status.label': 'Token pass_g402... (opcional)',
        'paydash.status.btn': 'Consultar',
        'paydash.low.title': 'Saldo baixo',
        'paydash.low.btn': 'Recarregar Session Pass →',
        'paydash.active.title': 'Pass ativo',
        'paydash.active.remaining': 'Créditos restantes:',
        'paydash.active.expires': 'Validade:',
        'paydash.invalid': 'Token inválido ou pass não encontrado.',
        'paydash.summary.title': 'Resumo do hub',
        'paydash.summary.calls': 'Chamadas registradas:',
        'paydash.summary.passes': 'Passes emitidos:',
        'paydash.hub.title': 'Resumo do hub',
        'paydash.hub.reqs': 'Chamadas registradas:',
        'paydash.hub.rev': 'Receita:',
        'paydash.hub.passes': 'Passes emitidos:',

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
        'nav.home': 'Home',
        'nav.catalog': 'Catalog',
        'nav.pass': 'Session Pass',
        'nav.trust': 'Trust',
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
        'hero.btn.playground': '⚡ Test now (Sandbox)',
        'hero.btn.catalog': 'See pricing, limits and examples →',
        'hero.subnote': 'No credit card, no API key, no signup. Sandbox: <strong>5 queries per hour per IP</strong>. Production: x402 or Session Pass.',
        'hero.btn.console': 'Console and SDKs',
        'hero.btn.llms': 'llms.txt Documentation',
        'hero.btn.status': 'Free Status (JSON)',

        // Topup Tiers Web3
        'topup.tag': '⚡ Instant Batch Settlement on Base (USDC)',
        'topup.title': '1-Click Web3 Credit Top-Up (Session Passes)',
        'topup.desc': 'Pay once in USDC on Base Mainnet and instantly receive a pass_g402_... token with thousands of high-speed (<15ms) requests without signing transactions on every single GET.',
        'topup.how_to_read': '<strong>How to read balances:</strong> 1 credit costs <strong>0.0050 USDC</strong> on standard endpoints; 0.0100 USDC endpoints consume 2 credits, 0.0200 USDC consume 4 credits, and 0.0300 USDC consume 6 credits.',
        'topup.network_bar': '<strong>Network:</strong> Base Mainnet (8453) • <strong>Token:</strong> USDC • <strong>Contract:</strong> <a href="https://basescan.org/token/0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913" target="_blank" rel="noopener noreferrer" style="color: #00F0FF; font-family: var(--font-mono); text-decoration: none;">0x8335...2913</a>',
        'topup.starter.tag': 'Starter Pass',
        'topup.starter.desc': 'Ideal for solo developers, quick prototypes, and lightweight bots.',
        'topup.starter.pix_label': 'or R$ 12.00 via Pix / Card',
        'topup.starter.b1': '✓ <strong>400 credits</strong> included (1 credit = 0.0050 USDC)',
        'topup.starter.b2': '✓ Valid for <strong>30 days</strong>',
        'topup.starter.b3': '✓ Sub-15ms ultra-low latency',
        'topup.starter.b4': '✓ HMAC signature + Spend Controls',
        'topup.starter.btn': 'Buy Starter (2 USDC)',
        'topup.starter.pix_btn': 'Pay R$ 12 (Pix / Card)',
        'topup.pro.badge': 'Most Popular',
        'topup.pro.tag': 'Pro / Agent Pass',
        'topup.pro.desc': 'For autonomous AI agents and continuous automation pipelines.',
        'topup.pro.pix_label': 'or R$ 29.00 via Pix / Card',
        'topup.pro.b1': '✓ <strong>1,000 credits</strong> included (1 credit = 0.0050 USDC)',
        'topup.pro.b2': '✓ Valid for <strong>30 days</strong>',
        'topup.pro.b3': '✓ Compatible with Claude MCP and AgentKit',
        'topup.pro.b4': '✓ Zero block wait per call',
        'topup.pro.btn': 'Buy Pro (5 USDC)',
        'topup.pro.pix_btn': 'Pay R$ 29 (Pix / Card)',
        'topup.fleet.badge': '+1,000 Bonus',
        'topup.fleet.tag': 'Fleet / Enterprise',
        'topup.fleet.desc': 'Autonomous agent fleets, enterprise ERPs, and heavy-duty pipelines.',
        'topup.fleet.pix_label': 'or R$ 115.00 via Pix / Card',
        'topup.fleet.b1': '✓ <strong>6,000 credits</strong> (20% bonus; 1 credit = 0.0050 USDC)',
        'topup.fleet.b2': '✓ Valid for <strong>60 days</strong>',
        'topup.fleet.b3': '✓ Effective rate: 0.0033 USDC / credit',
        'topup.fleet.b4': '✓ Top priority on atomic cache',
        'topup.fleet.btn': 'Buy Fleet (20 USDC)',
        'topup.fleet.pix_btn': 'Pay R$ 115 (Pix / Card)',
        'topup.status.title': 'Awaiting wallet confirmation...',
        'topup.status.msg': 'Approve the USDC transfer on Base Mainnet to issue your Session Pass immediately.',
        'topup.success.title': 'Session Pass Issued and Active!',
        'topup.success.sub': 'Copy your token below and send it in the <code style="color: #00F0FF;">X-Payment</code> header on any request:',
        'topup.success.credits': 'Credit Balance',
        'topup.success.tier': 'Plan (Tier)',
        'topup.success.expires': 'Expiration',
        'topup.success.token_label': 'Your Token (X-Payment):',
        'topup.success.copy_btn': '📋 Copy Token',
        'topup.success.how_label': 'How to use in cURL, Python, Node, or MCP:',
        'topup.manual.title': '📱 Direct or Mobile Payment (Any Wallet)',
        'topup.manual.badge': 'No Extension Needed',
        'topup.manual.desc': 'Prefer sending from mobile, hardware wallet, or another tab? Transfer <strong>2, 5, or 20 USDC</strong> on <strong>Base Mainnet</strong> to the official Oracle address below and paste the transaction hash (<code>0x...</code>) to activate your pass immediately:',
        'topup.manual.addr_label': '1. Oracle Address (Base Network):',
        'topup.manual.copy_addr': '📋 Copy Address',
        'topup.manual.tx_label': '2. Transaction Hash (Base TxHash):',
        'topup.manual.tx_placeholder': '0x... (Paste your USDC transfer hash)',
        'topup.manual.activate_btn': '⚡ Validate and Activate Pass',
        'topup.footer.console_btn': '💻 View Console and Technical Docs',

        // 22 Services Cards
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
        'svc.domain-audit.name': 'Domain Audit (DNS + SSL + ASN)',
        'svc.domain-audit.category': 'Network',
        'svc.domain-audit.desc': 'Full domain audit in 1 call: authoritative DNS records, TLS/SSL certificate, and BGP/ASN routing',
        'svc.kyb-audit.name': 'KYB and Compliance Audit (CNPJ + OFAC + LEI)',
        'svc.kyb-audit.category': 'Compliance',
        'svc.kyb-audit.desc': 'Full corporate entity audit in 1 call: CNPJ registry, OFAC/UN sanctions screening, and GLEIF LEI',
        'svc.market-snapshot.name': 'Market Snapshot (Forex + Crypto + Indicators)',
        'svc.market-snapshot.category': 'Financial',
        'svc.market-snapshot.desc': 'Consolidated financial snapshot in 1 call: PTAX Forex rates, crypto spot prices, and macro indicators',
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
        'svc.pass.desc': 'Purchase 400 to 6,000 prepaid credits (1 credit = 0.0050 USDC) in 1 USDC transaction on Base and execute sub-15ms calls with zero block wait',

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
        'playground.btn.sandbox': '🧪 Sandbox (5 queries/hr)',
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
        'console.smithery.params': '<strong style="color: #fff;">💡 Supported Parameters in smithery.yaml:</strong><ul style="margin: 0.5rem 0 0 1.2rem; padding: 0;"><li><code>sandbox: true</code> — Enables free Sandbox Mode (5 queries/hour per IP) to test without a wallet.</li><li><code>privateKey: 0x...</code> — Optional EVM private key for auto-settlement in USDC on Base (0.0050 to 0.0200 USDC per call).</li></ul>',

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
        'modal.web3.col2.title': '🧩 Browser Web3 Wallets',
        'modal.web3.col2.desc': 'Works with MetaMask, Rabby, Coinbase Wallet, Brave, Rainbow and other compatible extensions. When you click a Web3 button, the page asks which wallet to use — non-custodial, no seed phrase anywhere.',
        'modal.web3.col2.btn.install': '🦊 Install MetaMask Extension',
        'modal.web3.col2.btn.reload': '🔄 Already installed, reload',
        'modal.web3.network': 'Network:',
        'modal.web3.token': 'Token:',
        'modal.web3.recipient': 'Destination Address (Oracle):',
        'modal.web3.amount': 'Exact Amount:',
        'modal.web3.confirm.title': 'Already sent the transaction?',
        'modal.web3.confirm.desc': 'Paste the transaction hash (Tx Hash) from your wallet to unlock full data immediately:',
        'modal.web3.confirm.btn': 'Confirm Payment and Unlock',

        // Wallet chooser (EIP-6963)
        'wallet.chooser.title': 'Choose your wallet',
        'wallet.chooser.desc': 'We detected more than one Web3 wallet in this browser. Pick the one you want to use — your choice is remembered for next payments.',
        'wallet.chooser.forget': 'Forget saved wallet',

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
        'agents.f1.desc': 'No static API keys, credit cards, or accounts required. AI agents purchase oracle data autonomously via on-chain Web3 wallets (MetaMask, Rabby, Coinbase Wallet and others).',
        'agents.f2.title': 'Cryptographic Oracle',
        'agents.f2.desc': 'Every response carries a deterministic secp256k1 ECDSA digital signature (_oracle_proof), verifiable statelessly in under 1 ms.',
        'agents.f3.title': 'x402 Session Pass (<15ms)',
        'agents.f3.desc': 'Purchase 400 to 6,000 prepaid credits (1 credit = 0.0050 USDC) in a single Base USDC transaction and execute high-frequency M2M calls in under 15ms with zero block wait.',
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
        'agents.nowallet.title': 'No Web3 Wallet Needed — 3 Minutes',
        'agents.nowallet.desc': 'Test right now with <code>X-Payment: demo</code> (5 queries/hour per IP) or buy prepaid credits via Pix/Card or USDC at <a href="/pass">/pass</a> to receive an instant <code>pass_g402_...</code> token.',
        'agents.nowallet.btn.catalog': 'See pricing, limits and examples →',
        'agents.nowallet.btn.pass': 'Buy Session Pass →',
        'agents.eliza.title': '2. Official ElizaOS Plugin (grid402-plugin-elizaos)',
        'agents.eliza.sub': 'Enable autonomous AI agents built on ElizaOS (ai16z) to consume verified data and settle x402 payments natively.',
        'agents.btn.copy_json': 'Copy JSON',
        'agents.btn.copy_ts': 'Copy TypeScript',
        'agents.btn.copy_py': 'Copy Python',
        'agents.btn.copy_curl': 'Copy cURL',
        'agents.yes': '✓ Yes',
        'agents.free': 'Free',
        'nav.payments': 'Payments',
        'nav.growth': 'Growth Program',
        'playground.unlock.flow_note': '<strong style="color:#fff;">Primary flow:</strong> click “Unlock now”. Endpoint pricing (USDC/BRL) is listed in the catalog.',
        'playground.unlock.other_ways': 'Other ways to unlock',
        'playground.unlock.price_btn': 'See endpoint price',

        // /catalog Page
        'catalog.title': 'Complete Service Catalog',
        'catalog.subtitle': 'Services, prices, limits, and examples on a single page. Paid queries via <code>POST</code>/<code>GET</code> with <code>X-Payment</code>.',
        'catalog.sub': 'Single source of truth for pricing, Session Pass credit consumption, request examples, and response format. Reference exchange rate shown: <strong>1 USDC ≈ R$ {rate}</strong>.',
        'catalog.pill.active': '22 active endpoints',
        'catalog.pill.price': '0.0050 USDC standard',
        'catalog.pill.sandbox': 'Free Sandbox: 5 queries/hour per IP',
        'catalog.pill.unit': 'Base unit: <strong>1 credit = 0.0050 USDC</strong> (≈ R$ {brl})',
        'catalog.pill.pass': 'Session Pass: Starter 400 cr (2 USDC) · Pro 1,000 cr (5 USDC) · Fleet 6,000 cr (20 USDC)',
        'catalog.limits.title': 'Limits and authentication',
        'catalog.limits.c1.title': 'x402 micropayment',
        'catalog.limits.c1.desc': 'Send <code>PAYMENT-SIGNATURE</code> (Base64, EIP-3009, facilitator token) or tx hash to the official wallet <code>0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90</code>; the service responds in milliseconds.',
        'catalog.limits.c2.title': 'Session Pass (1 credit = 0.0050 USDC)',
        'catalog.limits.c2.desc': 'Buy credits at <a href="/pass" style="color:var(--primary)">/pass</a>; send <code>X-Payment: pass_g402_...</code>. Starter (2 USDC = 400 credits), Pro (5 USDC = 1,000 credits), Fleet (20 USDC = 5,000 + 1,000 bonus = 6,000 credits).',
        'catalog.limits.c3.title': 'Sandbox &amp; Idempotency',
        'catalog.limits.c3.desc': 'For testing: <code>X-Payment: demo</code> (5 req/hour per IP). In production, send <code>Idempotency-Key</code> for retries without duplicate billing (300s TTL).',
        'catalog.limits.c4.title': 'Rate limit and Spend Controls',
        'catalog.limits.c4.desc': 'Timeout: 2800 ms. Check balance for free at <code>GET /api/v1/pass/balance</code> and configure daily cap (<code>daily_credit_limit</code>) and route whitelist.',
        'catalog.table.title': 'Endpoints, price, provenance, status (22/22 UP) and example',
        'catalog.search.placeholder': 'Filter by endpoint, name, official source, or category…',
        'catalog.th.service': 'Service &amp; Official Source',
        'catalog.th.category': 'Category',
        'catalog.th.price': 'Price USDC',
        'catalog.th.status': 'Status',
        'catalog.th.endpoint': 'Endpoint',
        'catalog.th.usdc': 'Price (USDC)',
        'catalog.th.brl': 'Reference (BRL)',
        'catalog.th.credits': 'Pass Credits',
        'catalog.th.example': 'Example',
        'catalog.source_label': 'Source:',
        'catalog.state.title': 'Data freshness',
        'catalog.state.desc': 'Status marked as UP: on-chain data are real-time public facts; cached feeds may have up to 2 minutes of lag.',
        'catalog.cta.trust': 'SLA, availability and methodology →',
        'catalog.cta.agents': 'Agent guide →',
        'catalog.free': 'Free',
        'catalog.pass.price': '2 / 5 / 20 USDC',
        'catalog.pass.brl': 'R$ 12 / R$ 29 / R$ 115 (Pix/Card)',
        'catalog.pass.credits': 'Issues 400 / 1,000 / 6,000 credits',
        'catalog.s1.title': 'Integration Examples (Copy and Test)',
        'catalog.s1.c1.title': '1) Free Sandbox (no wallet, no signup)',
        'catalog.s1.c1.note': 'Limit: 5 queries per hour per IP.',
        'catalog.s1.c2.title': '2) With Session Pass (recommended for production)',
        'catalog.s1.c2.note': 'Purchase at <a href="/pass">/pass</a> using USDC on Base, Pix, or Card.',
        'catalog.s1.c3.title': '3) Oracle-signed response (condensed example)',
        'catalog.s2.title': 'Operational Rules and Limits',
        'catalog.s2.l1': '<strong>Sandbox:</strong> 5 queries per hour per IP using the <code>X-Payment: demo</code> header.',
        'catalog.s2.l2': '<strong>Session Pass:</strong> 1 credit equals <code>0.0050 USDC</code>. Higher-tier endpoints consume proportional multiples (2, 4, or 6 credits).',
        'catalog.s2.l3': '<strong>Pass expiration:</strong> Starter and Pro expire in 30 days; Fleet expires in 60 days.',
        'catalog.s2.l4': '<strong>Cryptographic verification:</strong> every paid or sandbox response includes <code>_oracle_proof</code> verifiable for free via <code>POST /api/v1/verify</code>.',

        // /pass Page
        'pass.title': 'Buy Session Pass <code>pass_g402_...</code>',
        'pass.subtitle': 'Pay once in <strong>BRL (Pix or Card via Mercado Pago)</strong> or in <strong>USDC on the Base network</strong> and instantly receive a token loaded with thousands of requests (&lt;15ms), without signing a transaction on every call. Ideal for n8n, Make, Zapier, Python, Node, and MCP.',
        'pass.sandbox_note': '<strong>Mercado Pago Sandbox Mode active:</strong> payments generated here are simulated for testing and do not perform real charges.',
        'pass.email.label': 'Email for receipt (optional):',
        'pass.email.placeholder': 'dev@yourcompany.com',
        'pass.panel.title': 'Mercado Pago Payment',
        'pass.panel.waiting': 'Awaiting payment...',
        'pass.pix.code_label': 'Pix Copy and Paste Code:',
        'pass.pix.copy_btn': 'Copy Pix',
        'pass.pix.instructions': 'After paying in your bank app, your <code>pass_g402_...</code> token will appear here automatically in a few seconds. Do not close this tab.',
        'pass.pix.sandbox_approve': 'Simulate Pix Approval (Sandbox)',
        'pass.success.title': 'Session Pass issued successfully!',
        'pass.success.copy_btn': 'Copy Token',
        'pass.packages.title': 'Packages',
        'pass.btn.card': 'Pay with Card (Mercado Pago)',
        'pass.btn.usdc': 'Pay with USDC (Web3)',
        'pass.howto.title': 'How to use the token',
        'pass.howto.desc': 'Or set <code>GRID402_SESSION_PASS=pass_g402_...</code> in the MCP server (<code>grid402-mcp</code>) or in your n8n credentials.',
        'pass.hero.badge': '⚡ High-Frequency M2M • Sub-15ms • Base Mainnet (8453) + Pix/Card',
        'pass.hero.title': 'x402 Prepaid Session Pass',
        'pass.hero.sub': 'Purchase prepaid credits via <strong>USDC on Base</strong> or <strong>BRL (Pix and Card via Mercado Pago)</strong> and receive a <code>pass_g402_...</code> token for instant M2M calls without signing transactions on every request.',
        'pass.how_to_read': '<strong>How to read balances:</strong> 1 credit costs <strong>0.0050 USDC</strong> on standard endpoints; 0.0100 USDC endpoints consume 2 credits, 0.0200 USDC consume 4 credits, and 0.0300 USDC consume 6 credits. See the full table at <a href="/catalog">/catalog</a>.',
        'pass.starter.credits': '400 M2M credits (1 credit = 0.0050 USDC • 30 days)',
        'pass.starter.b1': 'Equals 400 standard 0.0050 USDC queries',
        'pass.starter.b2': 'Or 100 corporate queries (LEI / CNPJ / OFAC)',
        'pass.starter.b3': 'Validation latency < 15ms',
        'pass.starter.b4': 'Ideal for agent testing and prototypes',
        'pass.pro.badge': 'MOST POPULAR',
        'pass.pro.name': 'Pro / Agent Pass',
        'pass.pro.credits': '1,000 M2M credits (1 credit = 0.0050 USDC • 30 days)',
        'pass.pro.b1': 'Equals 1,000 standard 0.0050 USDC queries',
        'pass.pro.b2': 'Or 250 corporate queries (LEI / CNPJ / OFAC)',
        'pass.pro.b3': 'Native support in MCP Server and SDKs',
        'pass.pro.b4': 'Ideal for production autonomous agents',
        'pass.fleet.badge': '+20% BONUS',
        'pass.fleet.credits': '6,000 M2M credits (1,000 bonus • 60 days)',
        'pass.fleet.b1': '5,000 base credits + 1,000 free bonus credits',
        'pass.fleet.b2': 'Equals 6,000 standard 0.0050 USDC queries',
        'pass.fleet.b3': 'Extended 60-day validity',
        'pass.fleet.b4': 'Ideal for multi-agent fleets and high volume',
        'pass.box1.title': '💳 Option 1: Pay in BRL (Pix or Card via Mercado Pago)',
        'pass.box1.desc': 'Ideal for teams and developers who want to issue a <code>pass_g402_...</code> token without a Web3 wallet. Upon Pix or card confirmation, your token is unlocked automatically on this screen.',
        'pass.box1.btn.starter': 'Buy Starter — R$ {brl} (400 credits)',
        'pass.box1.btn.pro': '⚡ Buy Pro — R$ {brl} (1,000 credits)',
        'pass.box1.btn.fleet': 'Buy Fleet — R$ {brl} (6,000 credits)',
        'pass.box2.title': '⛓️ Option 2: Activate Pass with USDC on Base Mainnet (On-Chain)',
        'pass.box2.s1': 'Transfer <strong>2 USDC</strong> (Starter), <strong>5 USDC</strong> (Pro), or <strong>20 USDC</strong> (Fleet) on <strong>Base Mainnet (Chain ID 8453)</strong> to the official Oracle wallet:',
        'pass.box2.s2': 'Paste the confirmed Base <strong>Transaction Hash (0x...)</strong> below and click Issue Session Pass:',
        'pass.box2.btn': '⚡ Issue Session Pass',
        'pass.box3.title': '🔍 Check Session Pass Balance and Expiration',
        'pass.box3.desc': 'Enter your <code>pass_g402_...</code> token to inspect remaining credits:',
        'pass.box3.btn': 'Check Balance',
        'pass.box4.title': '🤖 How to Use Your Session Pass in Agents and SDKs',

        // /trust Page
        'trust.badge': 'Operational and Cryptographic Transparency',
        'trust.title': 'Trust Center, Governance &amp; Provenance',
        'trust.subtitle': 'Institutional transparency, verifiable historical availability (30 days), matrix of official public sources, scope boundaries, and security policy of the <strong>Grid402</strong> M2M oracle (<code>grid402.online</code>).',
        'trust.sub': 'Public verification of the oracle cryptographic identity, real-time availability of all 22 microservices, official upstream data sources, and Zero-DB privacy guarantees.',
        'trust.kpi1': 'Historical Uptime (30 days) · Best-Effort Target: 99.5%',
        'trust.kpi2': 'Operational Services (UP) in Current Snapshot',
        'trust.kpi3': 'Success Rate Without 5xx Errors',
        'trust.kpi3_p95': 'p95 Latency:',
        'trust.kpi4': 'HTTP 402 Challenges Issued + Replays Blocked',
        'trust.notice': '<strong>Brand Disambiguation Notice (<code>grid402.online</code>):</strong><br><strong>Grid402</strong> (operated exclusively at the canonical domain <code>https://grid402.online</code>) is an independent <strong>Public Data Oracle and M2M Network Utility infrastructure settled via HTTP 402 on the Base blockchain (Chain ID 8453)</strong>. It has no corporate, technical, or operational affiliation with homonymous electrical power distribution projects (<em>smart-grid / physical power grid</em>) or third-party academic initiatives.',
        'trust.k1.label': 'Operational Services',
        'trust.k1.sub': 'Continuous monitoring via collector and active probes.',
        'trust.k2.label': 'Oracle Cryptographic Signature',
        'trust.k2.active': 'ACTIVE (Dedicated Key)',
        'trust.k2.fallback': 'FALLBACK MODE',
        'trust.k3.label': 'On-Chain Settlement',
        'trust.k3.sub': 'HTTP 402 protocol (EIP-3009 USDC) and low-latency Session Pass.',
        'trust.k4.label': 'Data Architecture',
        'trust.k4.sub': 'No relational DB, zero PII retention, HMAC-SHA256 rate limiting.',
        'trust.s1.title': '1. Institutional Identity and Operator Governance',
        'trust.s1.list': '<li><strong>Operator and Maintainer:</strong> Grid402 Network Intelligence (Technical Lead: Thiago Campos — São Paulo, SP, Brazil).</li><li><strong>Official Oracle and Recipient Wallet (Base Mainnet - Chain ID 8453):</strong> <code>0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90</code> (<a href="https://basescan.org/address/0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90" target="_blank" rel="noopener noreferrer">Audit on BaseScan</a>).</li><li><strong>Availability Policy (SLA):</strong> Operated on a <em>Best-Effort</em> basis with a technical availability target of <strong>99.5%</strong> across oracle routes and resilient local caching to protect against upstream government source fluctuations.</li><li><strong>Official Repository and Open-Source SDKs/MCP:</strong> <a href="https://github.com/oliveiratc/Grid402" target="_blank" rel="noopener noreferrer">github.com/oliveiratc/Grid402</a> · Official packages: <a href="https://www.npmjs.com/package/grid402" target="_blank" rel="noopener noreferrer">npm: grid402</a>, <a href="https://www.npmjs.com/package/grid402-mcp" target="_blank" rel="noopener noreferrer">npm: grid402-mcp</a>, <a href="https://pypi.org/project/grid402/" target="_blank" rel="noopener noreferrer">PyPI: grid402</a>.</li><li><strong>Support Contact, DPO (LGPD/GDPR), and Vulnerability Disclosure:</strong> <a href="mailto:contato@grid402.online">contato@grid402.online</a> · Security manifest: <a href="/.well-known/security.txt">/.well-known/security.txt</a>.</li>',
        'trust.s1.desc': 'Every paid and sandbox response from Grid402 includes an <code>_oracle_proof</code> object signed with the <code>secp256k1</code> elliptic curve over the canonical <code>SHA-256</code> payload digest. Any external agent or auditor can validate the proof offline or via <code>POST /api/v1/verify</code>.',
        'trust.s1.wallet': 'Recipient / Oracle Wallet (Base Mainnet)',
        'trust.s1.usdc': 'Official USDC Contract (Base)',
        'trust.s1.alg': 'Signature Algorithm',
        'trust.s1.verify': 'Free Verification Endpoint',
        'trust.s1.pubkey': 'Configured secp256k1 Public Key',
        'trust.s2.title': '2. Availability History (Last 30 Days)',
        'trust.s2.desc': 'Continuous monitoring of all 22 microservices.',
        'trust.s2.ago': '30 days ago',
        'trust.s2.avg': '30d Average:',
        'trust.s2.today': 'Today',
        'trust.s2.th.service': 'Service',
        'trust.s2.th.endpoint': 'Endpoint',
        'trust.s2.th.source': 'Primary Source / Standard',
        'trust.s2.th.ttl': 'Cache TTL',
        'trust.s2.th.price': 'Price / Credits',
        'trust.s2.th.status': 'Status',
        'trust.s3.title': '3. Transparent Scope: Official Public Data vs. Private Credit Bureaus',
        'trust.s3.desc': 'Grid402 maintains absolute transparency regarding what it delivers and what is outside its scope:',
        'trust.s3.th1': 'Dimension',
        'trust.s3.th2': 'What Grid402 Delivers (Public M2M Oracle)',
        'trust.s3.th3': 'What Requires a Private Credit Bureau (Out of Scope)',
        'trust.s3.tbody': '<tr><td><strong>Data Sources</strong></td><td>Open government registries (Receita Federal CNPJ/Simples, CGU CEIS/CNEP, BCB PTAX/SGS, IBGE, GLEIF LEI CC0, US Treasury OFAC SDN, ECB, DNS/TLS/BGP).</td><td>Private commercial default databases, private notary protests, bounced checks, and closed banking history (e.g., Serasa, SPC, Boa Vista, Quod).</td></tr><tr><td><strong>Guarantee and Proof</strong></td><td>Deterministic JSON normalization + <code>_meta</code> provenance block + offline-auditable <strong>ECDSA secp256k1</strong> cryptographic signature (<code>_oracle_proof</code>).</td><td>Closed proprietary credit score without public cryptographic oracle signature.</td></tr><tr><td><strong>Privacy (LGPD/GDPR)</strong></td><td>Strictly <strong>Zero-PII</strong> architecture: processes exclusively Legal Entities and macroeconomic/network facts, filtering individual tax IDs at the source.</td><td>Queries on natural person tax IDs (CPF), presumed individual income, and personal consumer history.</td></tr><tr><td><strong>Commercial Model</strong></td><td>Zero sign-up, zero monthly fee. Per-request settlement via HTTP 402 (<code>0.0050 to 0.0300 USDC</code>) or prepaid Session Pass (<code>1 credit = 0.0050 USDC</code>).</td><td>Annual contracts with minimum monthly commitment, manual onboarding approval, and postpaid billing.</td></tr>',
        'trust.s4.title': '4. Provenance and Freshness (TTL) Matrix of All 22 Services',
        'trust.s4.desc': 'Every paid or sandbox HTTP 200 response includes the <code>_meta</code> object signed alongside <code>_oracle_proof</code>, attesting the exact upstream source and cache policy:',
        'trust.s4.th1': 'Endpoint',
        'trust.s4.th2': 'Service',
        'trust.s4.th3': 'Upstream Official Source',
        'trust.s4.th4': 'Cache TTL',
        'trust.s4.th5': 'Fee (USDC / Pass)',
        'trust.s5.title': '5. Version and Pricing History (Public Changelog)',
        'trust.s5.list': '<li><strong>v3.2.0 (2026-11):</strong> Structured JSONL Zero-PII observability (<code>requests.jsonl</code> + <code>X-Request-Id</code>), live <code>p95</code> latency tracking, MCP handshake <code>instructions</code> (24 tools), explicit Dual-License (MIT SDKs + Proprietary Gateway), and parallel concurrency load test suite.</li><li><strong>v3.1.0 (2026-11):</strong> Unified official Session Pass math (<code>1 credit = 0.0050 USDC</code>: Starter 2 USDC = 400 credits, Pro 5 USDC = 1,000 credits, Fleet 20 USDC = 6,000 credits), <code>Idempotency-Key</code> support (300s TTL), per-pass Spend Controls (<code>daily_credit_limit</code>, <code>allowed_endpoints</code>, and <code>GET /api/v1/pass/balance</code>), signed <code>_meta</code> block injection, 30-day uptime history, and 22-tool coverage in the MCP server.</li><li><strong>v3.0.0 (2026-11):</strong> Expansion to 22 services with composite endpoints (<code>/api/v1/domain-audit</code>, <code>/api/v1/kyb-audit</code>, <code>/api/v1/market-snapshot</code>), native x402 v2 facilitator integration (EIP-3009), and hybrid Pix/Card + USDC checkout.</li>',
        'trust.s6.title': '6. Incident Policy, Key Security and Liability',
        'trust.s6.list': '<li><strong>S0 (Critical Outage):</strong> immediate response and automatic fallback to signed cached snapshot with explicit timestamp in <code>_meta.fetched_at</code>.</li><li><strong>S1 (External Government Source Degradation):</strong> <em>fail-closed</em> behavior with structured error, zero synthetic data generation, and zero credit deduction on 5xx failures.</li><li><strong>Non-Custodial M2M Key Management &amp; Spend Controls:</strong> Grid402 never stores or requests client private keys or seed phrases. Autonomous agents using <code>GRID402_PRIVATE_KEY</code> should use dedicated low-balance operational wallets or Session Passes (<code>pass_g402_...</code>) constrained by <code>daily_credit_limit</code> and <code>allowed_endpoints</code>; request logs (<code>requests.jsonl</code> + <code>X-Request-Id</code>) never record full payment tokens or raw IPs.</li><li><strong>Credit Decision Disclaimer:</strong> Grid402 provides verifiable public technical data and does not grant credit, provide legal advice, or approve onboarding.</li>',
        'trust.s3.c1.title': 'Zero-PII and GDPR / LGPD',
        'trust.s3.c1.desc': 'Corporate lookups (CNPJ, Compliance, KYB) strip natural-person data (shareholder lists, individual tax IDs, personal phones, and emails) at ingestion. IP addresses in telemetry are masked.',
        'trust.s3.c2.title': 'Atomic Anti-Replay',
        'trust.s3.c2.desc': 'Each on-chain transaction (<code>tx_hash</code>) or EIP-3009 authorization (<code>nonce</code>) is atomically recorded with exclusive file locking (<code>flock</code>), preventing reuse.',
        'trust.s3.c3.title': 'Spend Controls and M2M Idempotency',
        'trust.s3.c3.desc': 'Session Passes support daily credit caps, allowed-service lists, token rotation, and idempotency caching (<code>Idempotency-Key</code> for 300s) with zero duplicate billing.',

        // Other public pages (/agenthub, /reputation, /growth, /payment-dashboard)
        'agenthub.title': 'GRID402 AGENT HUB',
        'agenthub.subtitle': 'Integration hub for autonomous agents: documentation, examples, pricing, and compatibility.',
        'agenthub.sub': 'Integration hub for autonomous agents: documentation, examples, pricing, and compatibility.',
        'agenthub.catalog.title': 'Service catalog',
        'agenthub.catalog.link': 'Prices and fees →',
        'agenthub.examples.title': 'Integration examples',
        'agenthub.rules.title': 'Production rules',
        'agenthub.rules.list': '<li>Do not move funds without on-chain authorization compliant with your policies.</li><li>Prefer <code>demo</code> for sandbox testing (5 queries/h/IP).</li><li>Use a Session Pass to reduce latency and administrative cost.</li><li>Validate <code>_oracle_proof</code> for sensitive responses.</li>',
        'agenthub.s1': 'Service Catalog',
        'agenthub.pricing_link': 'Pricing and rates →',
        'agenthub.s2': 'Integration Examples',
        'agenthub.budget.desc': 'Agents should implement their own budget controls; Grid402 does not authorize fund movement without explicit maintainer permission.',
        'agenthub.s3': 'Production Rules',
        'agenthub.r1': 'Never move funds without on-chain authorization compliant with your policies.',
        'agenthub.r2': 'Prefer <code>demo</code> for sandbox testing (5 queries/hr/IP).',
        'agenthub.r3': 'Use a Session Pass to reduce latency and per-call overhead.',
        'agenthub.r4': 'Validate <code>_oracle_proof</code> for mission-critical responses.',

        'rep.back': '← Growth Program',
        'rep.title': 'GRID402 On-Chain Reputation',
        'rep.subtitle': 'Public ranking of validated builders based on proven consumption. No personal data or full wallet addresses exposed.',
        'rep.sub': 'Public leaderboard of validated builders based on verified consumption. Zero personal data or full wallet addresses exposed.',
        'rep.empty': 'No validated builders yet. <a href="/growth">Register in the program →</a>',
        'rep.th.builder': 'Builder',
        'rep.th.status': 'Status',
        'rep.th.calls': 'Paid calls',

        'growth.title': 'GRID402 AGENT GROWTH PROGRAM',
        'growth.subtitle': 'Acquisition, activation, repeat purchase, and retention program for autonomous agents using Grid402 over USDC + x402 on Base.',
        'growth.sub': 'Acquisition, activation, top-up, and retention program for autonomous agents using Grid402 over USDC + x402 on Base.',
        'growth.c1.p1': '<strong>10 bonus credits</strong> for agents completing their first validated query.',
        'growth.c1.p2': 'Eligibility: first settled transaction on the specified wallet; 1 promotion per wallet.',
        'growth.c2.p1': '<strong>100 credits</strong> per active integration completing 5 paid calls.',
        'growth.c2.p2': 'Registration, administrative validation, and anti-fraud control required.',
        'growth.c3.p1': '<strong>25 credits</strong> per onboarded integration validated by 10 calls.',
        'growth.c3.p2': 'HMAC-signed referral links; self-referral prohibited.',
        'growth.c1.desc': '<strong>10 bonus credits</strong> for agents completing their first validated query.',
        'growth.c1.sub': 'Eligibility: first settled transaction on the submitted wallet; 1 promotion per wallet.',
        'growth.c2.desc': '<strong>100 credits</strong> per active integration completing 5 paid calls.',
        'growth.c2.sub': 'Registration, admin validation, and anti-fraud checks required.',
        'growth.c3.desc': '<strong>25 credits</strong> per onboarded integration validated by 10 calls.',
        'growth.c3.sub': 'HMAC referral links; no self-referrals.',
        'growth.s1.title': '5.1 Agent Starter',
        'growth.s1.list': '<li>Generate a Base wallet and connect it in the <a href="/#playground">playground</a> or configure an x402 payer in the SDK/MCP.</li><li>Make a paid call (e.g.: <code>curl -H "X-Payment: $PAYTOKEN" https://grid402.online/api/v1/crypto</code>).</li><li>Use the promotional credit to test other endpoints.</li>',
        'growth.s1.l1': 'Create a Base wallet and connect it in the <a href="/#playground">playground</a> or configure an x402 payer in the SDK/MCP.',
        'growth.s1.l2': 'Make a paid call (e.g., <code>curl -H "X-Payment: $PAYTOKEN" https://grid402.online/api/v1/crypto</code>).',
        'growth.s1.l3': 'Use your promotional credits to test additional endpoints.',
        'growth.s1.note': 'Requests remain pending administrative validation before issuance.',
        'growth.s2.title': '5.2 Builder Rewards',
        'growth.s2.name': 'Builder / team name',
        'growth.s2.email': 'Email or contact',
        'growth.s2.url': 'Integration repository/URL',
        'growth.s2.btn': 'Register as builder',
        'growth.s3.title': '5.3 Agent Referrals',
        'growth.s3.wallet': 'Base Wallet (0x...)',
        'growth.s3.btn': 'Generate referral code',
        'growth.f1.name': 'Builder / Team Name',
        'growth.f1.email': 'Contact Email',
        'growth.f1.url': 'Integration Repository / URL',
        'growth.f1.btn': 'Register as Builder',
        'growth.f2.wallet': 'Base Wallet (0x...)',
        'growth.f2.btn': 'Generate Referral Code',
        'growth.compliance.title': 'Compliance',
        'growth.compliance.desc': 'Grid402 provides infrastructure and verifiable public data; it does not decide credit, onboarding, or risk assignment. The builder configuring the agents assumes responsibility for their system\'s decisions.',
        'growth.comp.title': 'Compliance',
        'growth.comp.desc': 'Grid402 provides verifiable open-data infrastructure; it does not make credit, underwriting, or risk decisions. The builder configuring the agents assumes full responsibility for system decisions.',

        'paydash.title': 'Payment Dashboard',
        'paydash.subtitle': 'Tracking hub for individual x402 payments and Session Pass. Both products are distinct and displayed here with separate rules.',
        'paydash.sub': 'Tracking hub for individual x402 payments and Session Passes. Both products are distinct and follow separate settlement rules.',
        'paydash.ppr.title': 'Pay-per-Request',
        'paydash.ppr.desc': 'Each call is paid directly via x402/USDC. Ideal for low volume without preloading.',
        'paydash.ppr.link': 'View pricing →',
        'paydash.pass.title': 'Agent Pass',
        'paydash.pass.desc': 'Preloaded credits. Ideal for recurring consumption with &lt;15ms latency.',
        'paydash.pass.link': 'Buy pass →',
        'paydash.c1.title': 'Pay-per-Request',
        'paydash.c1.desc': 'Each call is settled directly via x402/USDC. Ideal for low-volume on-demand consumption without preloading.',
        'paydash.c1.link': 'View pricing →',
        'paydash.c2.title': 'Agent Pass',
        'paydash.c2.desc': 'Prepaid credit balance. Ideal for recurring consumption with sub-15ms latency.',
        'paydash.c2.link': 'Buy Pass →',
        'paydash.status.title': 'Pass Status (optional)',
        'paydash.status.label': 'Token pass_g402... (optional)',
        'paydash.status.btn': 'Check',
        'paydash.low.title': 'Low balance',
        'paydash.low.btn': 'Top up Session Pass →',
        'paydash.active.title': 'Active Pass',
        'paydash.active.remaining': 'Remaining credits:',
        'paydash.active.expires': 'Expires:',
        'paydash.invalid': 'Invalid token or pass not found.',
        'paydash.summary.title': 'Hub summary',
        'paydash.summary.calls': 'Recorded calls:',
        'paydash.summary.passes': 'Issued passes:',
        'paydash.hub.title': 'Hub Summary',
        'paydash.hub.reqs': 'Recorded requests:',
        'paydash.hub.rev': 'Revenue:',
        'paydash.hub.passes': 'Passes issued:',

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
    'domain-audit': {
        name: 'Domain Audit (DNS+SSL+ASN)',
        desc: 'Full domain audit in 1 call: authoritative DNS records, TLS/SSL certificate, and BGP/ASN routing.',
        params: [
            { key: 'domain', label: 'Domain Name', value: 'base.org' }
        ]
    },
    'kyb-audit': {
        name: 'KYB Audit (CNPJ+OFAC+LEI)',
        desc: 'Full corporate entity audit in 1 call: CNPJ registry, OFAC/UN sanctions screening, and GLEIF LEI.',
        params: [
            { key: 'cnpj', label: 'Company CNPJ Number', value: '00000000000191' }
        ]
    },
    'market-snapshot': {
        name: 'Market Snapshot',
        desc: 'Consolidated financial snapshot in 1 call: PTAX Forex rates, crypto spot prices, and macro indicators.',
        params: []
    },
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
        desc: 'Prepaid M2M credit pass (400 to 6,000 credits; 1 credit = 0.0050 USDC) for sub-15ms execution with zero block wait.',
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

let currentLanguage = 'en';

/**
 * Traduz uma chave com substituicao opcional de parametros
 */
function t(key, params = {}) {
    let str = (GRID402_I18N[currentLanguage] && GRID402_I18N[currentLanguage][key]) 
           || (GRID402_I18N['en'] && GRID402_I18N['en'][key]) 
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
    if (!GRID402_I18N[lang]) lang = 'en';
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
 * Inicializacao automatica na carga (Ingles como idioma primario padrao)
 */
document.addEventListener('DOMContentLoaded', () => {
    let saved = 'en';
    try {
        saved = localStorage.getItem('grid402_lang') || 'en';
    } catch (_) {}
    setLanguage(saved);
});
