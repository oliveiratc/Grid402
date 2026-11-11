/**
 * Grid402 // Modulo de Internacionalizacao (i18n) Client-Side
 * Suporte a Portugues do Brasil (PT-BR) e Ingles (EN)
 * Regra: Todos os '&' em textos foram substituidos por 'e' (ou 'and' em ingles)
 */

const GRID402_I18N = {
    pt: {
        // Navegacao e Header
        'nav.services': 'Serviços',
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
        'hero.tag': 'Hub de Micro-Serviços para Robôs e Agentes de IA',
        'hero.title': 'Grid402: Hub de Utilitários de Rede e Dados Públicos para Agentes de IA',
        'hero.desc': 'Acesse cotações de câmbio, cripto, suíte de compliance e antifraude (score 360, sanções da CGU e Simples Nacional), indicadores econômicos oficiais (Selic, CDI e IPCA), feriados bancários ANBIMA, DNS, certificados SSL, BGP/ASN, CEP e CNPJ da Receita Federal em milissegundos sem cadastro ou chaves de API. Micropagamentos por chamada em USDC na Base.',
        'hero.btn.agents': '🤖 Central de Agentes IA',
        'hero.btn.playground': '⚡ Testar no Playground',
        'hero.btn.console': 'Console e SDKs',
        'hero.btn.llms': 'Documentação llms.txt',
        'hero.btn.status': 'Resumo Gratuito (JSON)',

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
        'roi.table.cnpj.grid': 'R$ 0,01 (0.0020 USDC)',
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
        'playground.btn.sandbox': '🧪 Sandbox (3 testes/h)',
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
        'console.btn.send_payment': 'Enviar com X-Payment',
        'console.btn.web3_pay': '⚡ Pagar com Carteira Web3 (1 Clique)',
        'console.btn.smart_wallet': '🔑 Passkey / Biometria (Smart Wallet)',
        'console.btn.web3_qr': '📱 QR Code / Celular',
        'console.btn.sandbox_demo': '🧪 Simular Sandbox (demo)',
        'console.input.placeholder': "Cole o hash de pagamento na Base (0x...) ou use 'demo' para simulação",
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
        'console.smithery.params': '<strong style="color: #fff;">💡 Parâmetros Suportados no smithery.yaml:</strong><ul style="margin: 0.5rem 0 0 1.2rem; padding: 0;"><li><code>sandbox: true</code> — Ativa o Modo Sandbox gratuito (20 consultas por hora por IP) para testes sem necessidade de carteira.</li><li><code>privateKey: 0x...</code> — Chave privada EVM opcional para auto-liquidação em USDC na Base (0.0005 a 0.0020 USDC por chamada).</li></ul>',

        'console.agentkit.title': '🪙 Integração Nativa Coinbase AgentKit (CDP)',
        'console.agentkit.desc': 'Permita que agentes de IA com carteiras CDP na Base consultem dados públicos e utilitários de rede pagando tarifas x402 de forma 100% autônoma, sem interrupção humana. O provedor de ações intercepta o desafio 402, transfere o USDC e reenvia a requisição de forma transparente.',
        'console.agentkit.example_ts': 'Exemplo em TypeScript (@coinbase/agentkit):',
        'console.agentkit.example_py': 'Exemplo em Python (cdp-agentkit-core):',
        'console.agentkit.docs': '<strong style="color: #fff;">📂 Documentação Completa e Código-Fonte:</strong><p style="margin: 0.4rem 0 0 0;">Consulte o diretório <code>integrations/agentkit/</code> no repositório para implementações prontas com LangChain, CrewAI e guias de configuração.</p>',

        'console.badges.intro': 'Badges em SVG dinâmicos para embeddar no README do seu repositório no GitHub:',
        'console.badges.btn.copy': '📋 Copiar Código Markdown',

        // Secao Sobre
        'about.title': 'Sobre o Grid402',
        'about.p1': 'O <strong>Grid402</strong> é uma infraestrutura pública de oráculo de dados e utilitários técnicos de rede desenhada especificamente para a Internet dos Agentes (Agentic Web) e sistemas autônomos Machine-to-Machine (M2M).',
        'about.p2': 'Tradicionalmente, consultar dados oficiais de empresas, endereçamento postal ou status de infraestrutura exige cadastro prévio, cartões de crédito internacionais, aprovação de tokens e chaves estáticas de API com risco de vazamento. O Grid402 elimina toda a fricção burocrática adotando o padrão nativo da web <strong>HTTP 402 Payment Required</strong> com liquidação instantânea de micropagamentos em USDC na rede Base Mainnet.',
        'about.p3': 'Todo o ecossistema opera de maneira estritamente Zero-DB e Stateless, processando apenas informações de domínio público com transparência computacional, código aberto sob Licença MIT e total conformidade com a legislação brasileira (LGPD e Marco Civil da Internet).',

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
        'footer.copyright': '© {year} Grid402. Todos os direitos reservados. Licenciado sob Licença MIT.',
        'footer.fee': 'Taxa: US$ {price} / consulta',
        'footer.ip.title': 'Direitos Autorais e Propriedade Intelectual:',
        'footer.ip.desc': 'O software, arquitetura do servidor MCP e documentações são de titularidade do Grid402 e disponibilizados sob a Licença MIT. Os dados técnicos retornados provêm de registros públicos governamentais abertos, protocolos livres da internet (DNS, BGP/ASN, TLS/SSL) e fontes de domínio público, mantendo-se livres de qualquer exclusividade proprietária conforme a legislação vigente.',
        'footer.disclaimer.title': 'Isenção de Responsabilidade e Dados Públicos:',
        'footer.disclaimer.desc': 'O Grid402 é um hub utilitário de acesso a dados de domínio público e protocolos abertos de rede fornecido "NO ESTADO EM QUE SE ENCONTRA" (AS-IS). Não tratamos dados pessoais sensíveis (PII) e operamos em estrita observância à Lei Geral de Proteção de Dados (Lei nº 13.709/2018) e ao Marco Civil da Internet (Lei nº 12.965/2014).',
        'footer.contributors': 'Copyright (c) 2026 Grid402 Contributors. Distribuído sob os termos da Licença MIT.',

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
        'msg.op_canceled': 'Operação cancelada ou falhou'
    },
    en: {
        // Navigation and Header
        'nav.services': 'Services',
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
        'hero.tag': 'Microservices Hub for Autonomous Bots and AI Agents',
        'hero.title': 'Grid402: Open Data and Network Utilities Hub for AI Agents',
        'hero.desc': 'Access live FX rates, crypto spot, compliance and anti-fraud suite (Risk 360 score, sanctions, Simples Nacional), official economic indicators (Selic, CDI, IPCA), ANBIMA banking holidays, DNS records, SSL certificates, BGP/ASN lookup, Brazilian postal codes (CEP), and Federal Revenue CNPJ registry in milliseconds with zero signup or API keys. Pay-per-call micropayments in USDC on Base.',
        'hero.btn.agents': '🤖 AI Agents Hub',
        'hero.btn.playground': '⚡ Test in Playground',
        'hero.btn.console': 'Console and SDKs',
        'hero.btn.llms': 'llms.txt Documentation',
        'hero.btn.status': 'Free Status (JSON)',

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
        'roi.table.cnpj.grid': '$0.0020 USDC (~$0.002)',
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
        'console.btn.send_payment': 'Send with X-Payment',
        'console.btn.web3_pay': '⚡ Pay with Web3 Wallet (1-Click)',
        'console.btn.smart_wallet': '🔑 Passkey / Biometrics (Smart Wallet)',
        'console.btn.web3_qr': '📱 Mobile QR Code',
        'console.btn.sandbox_demo': '🧪 Simulate Sandbox (demo)',
        'console.input.placeholder': "Paste Base transaction hash (0x...) or use 'demo' for simulation",
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
        'console.smithery.params': '<strong style="color: #fff;">💡 Supported Parameters in smithery.yaml:</strong><ul style="margin: 0.5rem 0 0 1.2rem; padding: 0;"><li><code>sandbox: true</code> — Enables free Sandbox Mode (20 queries/hour per IP) to test without a wallet.</li><li><code>privateKey: 0x...</code> — Optional EVM private key for auto-settlement in USDC on Base (0.0005 to 0.0020 USDC per call).</li></ul>',

        'console.agentkit.title': '🪙 Native Coinbase AgentKit Integration (CDP)',
        'console.agentkit.desc': 'Allow autonomous AI agents equipped with CDP wallets on Base to fetch public data and network utilities paying x402 rates 100% autonomously, with zero human intervention. The action provider intercepts HTTP 402, transfers USDC, and retries the request seamlessly.',
        'console.agentkit.example_ts': 'TypeScript Example (@coinbase/agentkit):',
        'console.agentkit.example_py': 'Python Example (cdp-agentkit-core):',
        'console.agentkit.docs': '<strong style="color: #fff;">📂 Complete Documentation and Source Code:</strong><p style="margin: 0.4rem 0 0 0;">Inspect the <code>integrations/agentkit/</code> directory in the repository for drop-in LangChain, CrewAI, and setup guides.</p>',

        'console.badges.intro': 'Dynamic SVG badges ready to embed into your GitHub repository README:',
        'console.badges.btn.copy': '📋 Copy Markdown Code',

        // About Section
        'about.title': 'About Grid402',
        'about.p1': '<strong>Grid402</strong> is an open-data oracle infrastructure and technical network utility gateway designed specifically for the Agentic Web and Machine-to-Machine (M2M) autonomous systems.',
        'about.p2': 'Traditionally, fetching official entity records, postal geocoding, or infrastructure status requires advance account signup, international credit cards, token approval, and static API keys prone to leaks. Grid402 removes all bureaucratic friction by adopting the native web standard <strong>HTTP 402 Payment Required</strong> with instant micropayment settlement in USDC on Base Mainnet.',
        'about.p3': 'The entire ecosystem operates strictly Zero-DB and Stateless, processing only public domain information with computational transparency, open source code under the MIT License, and full regulatory compliance (LGPD and Brazilian Civil Rights Framework for the Internet).',

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
        'footer.copyright': '© {year} Grid402. All rights reserved. Released under the MIT License.',
        'footer.fee': 'Rate: US$ {price} / query',
        'footer.ip.title': 'Copyright and Intellectual Property:',
        'footer.ip.desc': 'The Grid402 software, MCP server architecture, and documentation are proprietary to Grid402 and licensed under the MIT License. Returned technical data originates from open public records, internet standards (DNS, BGP/ASN, TLS/SSL), and public domain sources, free of proprietary exclusivity in accordance with applicable laws.',
        'footer.disclaimer.title': 'Disclaimer and Open Public Data:',
        'footer.disclaimer.desc': 'Grid402 is an open utility gateway providing access to public domain data and standard network protocols provided "AS IS". We do not process sensitive personally identifiable information (PII) and operate in compliance with data protection laws.',
        'footer.contributors': 'Copyright (c) 2026 Grid402 Contributors. Distributed under the terms of the MIT License.',

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
        'msg.op_canceled': 'Operation cancelled or failed'
    }
};

// Dicionario dos Presets do Playground em Ingles
const playgroundPresets_en = {
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
        const count = badgeText.getAttribute('data-i18n-count') || '14';
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
