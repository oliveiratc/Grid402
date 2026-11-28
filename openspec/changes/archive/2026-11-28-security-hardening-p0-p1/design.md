# Design

## Context

Ver `proposal.md` para a motivação e achados da auditoria de segurança. O Grid402 opera sob arquitetura Zero-DB em PHP 8+, Node.js (MCP server), Python (SDK) e TypeScript (AgentKit/ElizaOS). O estado de pagamentos, nonces e sessões é mantido em arquivos JSON travados sob `flock(LOCK_EX)`. A blindagem requer ajustes pontuais em arquivos PHP de gateway/serviços e nos clientes de automação.

## Goals / Non-Goals

**Goals:**
- Estancar vazamentos de receita e fechar IDOR na entrega de Session Passes Web3.
- Assegurar paridade 1:1 entre fundos liquidados e créditos concedidos em Permit2 e Solana SVM.
- Proteger o webhook Mercado Pago em modo fail-closed e sem exposição de credenciais.
- Impor limites e validações financeiras estritas nos auto-payers do MCP e SDKs.
- Remover arquivos sensíveis do ambiente local e blindar o processo de deploy e git.
- Mitigar riscos de DoS e ReDoS em PDF, workdays e extract, mantendo a performance de < 15 ms.

**Non-Goals:**
- Não reescrever o motor de banco de dados nem migrar para SQL.
- Não alterar a precificação ou tiers vigentes de Session Pass.
- Não alterar interfaces visuais de páginas públicas além das proteções necessárias.

## Decisions

### 1. Separação de Primeira Emissão vs. Recuperação em `/api/v1/pass-emitir`
- **Decisão:** Na primeira submissão válida de um `txHash`, o `pass_token` é retornado imediatamente no payload para preservar a experiência do usuário comprador sem atrito. Em qualquer tentativa subsequente com o mesmo `txHash`, a resposta retorna `{"ok": false, "status": "already_issued"}` sem o segredo.
- **Alternativa rejeitada:** Exigir assinatura EIP-191 em todas as requisições, inclusive no checkout inicial. Isso aumentaria a fricção de compra e quebraria integrações legadas sem carteira conectada.

### 2. Validação Estrita de Liquidação em Permit2 e Solana
- **Decisão:** Calcular créditos exclusivamente a partir de `paymentRequirements.amount` validado contra a autorização. Em Permit2, rejeitar autorizações onde `permitted.amount != paymentRequirements.amount`. Em Solana SVM, validar o mint USDC oficial e a ATA recebedora na instrução SPL, ignorando campos de valor do JSON de envelope.
- **Alternativa rejeitada:** Aceitar `permitted.amount` como valor pago caso o contrato confirme a transação. O Permit2 permite autorizar valores superiores ao transferido, permitindo que o cliente autorize $100 mas transfira apenas $2.

### 3. Webhook Mercado Pago Fail-Closed com Validação Temporal
- **Decisão:** Em produção, se `GRID402_MP_WEBHOOK_SECRET` não estiver preenchido, retornar HTTP 503 imediatamente. Validar o HMAC do cabeçalho `x-signature` exigindo que a diferença entre o timestamp `ts` e o horário atual do servidor seja $\le 300$ segundos. O endpoint responde apenas `{"ok": true}`.
- **Alternativa rejeitada:** Permitir fallback sem validação de secret em ambiente de desenvolvimento/produção mesclado.

### 4. Allowlist Canônica e Tetos em Auto-Payers
- **Decisão:** Em `mcp/x402_payer.mjs` e SDKs, aplicar allowlist canônica de `payTo` e `asset` por rede (`chainId`), recusar redirects (`redirect: 'error'`), exigir `https://` e impor tetos padrão de $0.10 por transação e $1.00 por sessão.
- **Alternativa rejeitada:** Permitir que o servidor remoto 402 defina livremente o destinatário da transferência.

### 5. Sanitização de PDF e Prevenção de DoS Linear
- **Decisão:** Em `public_html/services/dossier_pdf.php`, no modo amostra (`is_sample_demo=true`), ignorar qualquer texto enviado no corpo e injetar uma fixture interna constante. Reescrever o algoritmo de wrap de texto para iterar por palavras e comprimentos sem reexecutar regex recursivas em substrings completas.
- **Alternativa rejeitada:** Limitar apenas o tamanho do payload HTTP. Strings curtas especialmente formatadas com múltiplos espaços ou caracteres multibyte ainda poderiam induzir complexidade quadrática.

## Risks / Trade-offs

- [Risk] Usuário que perde a conexão logo após o primeiro pagamento não consegue recuperar o token submetendo novamente o `txHash` → [Mitigation] Disponibilizar endpoint de recuperação `/api/v1/pass/recover` autenticado via assinatura da carteira pagadora.
- [Risk] Clientes em ambiente de teste ou sandbox local que utilizam HTTP plano podem falhar no auto-payer → [Mitigation] Permitir protocolo `http://` exclusivamente quando a flag de ambiente `GRID402_ALLOW_INSECURE_HTTP=true` estiver explicitamente ativada para localhost.
