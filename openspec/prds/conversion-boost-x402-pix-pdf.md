# PRD: Aceleração de Conversão Multicanal, Recuperação x402 e Dossiê KYB em PDF (Grid402)

**Status:** Aprovado  
**Data:** 2026-11-28  
**Autor:** Thiago Campos + Agente

---

## 1. Contexto e problema

A análise operacional executada entre **27/11 e 28/11/2026** sobre a operação em produção do Grid402 (`https://grid402.online`) mostrou que a infraestrutura técnica de dados e liquidação está estável (`100% UP` em 30 dias), porém a taxa de conversão global sobre desafios `402` permanece em **0,4%**, gerando receita inicial baixa diante de **~230–260 page views humanos/dia** e **7.477 requisições API/dia**. Foram identificados três gargalos principais:

1. **Vazamento e ruído nos erros x402 (`473` pagamentos rejeitados, 49% das falhas):**
   - Falhas recuperáveis (`402` por valor insuficiente ou assinatura inválida e `400`/`429`) nem sempre expõem de forma explícita e padronizada no topo do payload JSON `required_raw_units`, `required_usdc` e o bloco completo `accepts[]` x402 v2 para autocorreção imediata de agentes pagadores.
   - Além disso, rajadas de testes automatizados (probes de conformidade x402 disparando múltiplos cenários negativos no mesmo segundo) misturam-se no painel `Diagnóstico Real de Falhas` com tentativas legítimas de pagadores reais que erraram valor ou expiração, dificultando priorizar correções comerciais.
2. **Abandono no teto do Sandbox `HTTP 429` da Home (`456` bloqueios, 47% das falhas):**
   - Humanos testando o Playground da Home que atingem o limite de `3 consultas/dia` veem apenas um card com redirecionamento para `/pass`. A saída da página no momento de maior intenção de uso derruba a conversão Pix (Micro R$ 4,90 e Starter R$ 12,00).
   - Ao mesmo tempo, expor um endpoint programático aberto ou tool MCP de resgate gratuito para bots criaria vetor de abuso via IPs rotativos; portanto, promoções gratuitas (`MCPTRY50`) devem ficar restritas à interface humana enquanto a campanha estiver ativa (até `08/12/2026`), mantendo as respostas M2M (`402`/`429` JSON e `/mcp`) 100% focadas em conversão paga (`x402`, Session Pass e RapidAPI).
3. **Ausência de download `.pdf` binário real em 1 clique no `/dossie-kyb`:**
   - O gerador `/dossie-kyb` já produz relatório completo com folha `@media print` via `window.print()`, mas usuários em dispositivos móveis, WhatsApp e fluxos corporativos B2B esperam baixar diretamente um arquivo `.pdf` anexo pronto para compartilhar e arquivar, sem depender de caixa de impressão do navegador.

---

## 2. Objetivos

- **O1 — Recuperação e auditoria x402 sem ruído:** garantir que 100% das respostas recuperáveis (`402`, `400` de prova e `429`) entreguem `required_raw_units`, `required_usdc` e `accepts[]` completos + `PAYMENT-REQUIRED`, auditar consistência EIP-712 em Base, Polygon e Arbitrum, e separar no `/admin` probes de conformidade vs. falhas reais de pagadores.
- **O2 — Proteção anti-abuso no canal M2M (`402`/`429` e `/mcp`):** eliminar qualquer anúncio ou incentivo de resgate automático gratuito nas respostas JSON M2M e manter `/mcp` sem ferramenta de trial grátis, direcionando agentes apenas para canais pagos (`x402`, Session Pass e RapidAPI USD).
- **O3 — Conversão inline no Playground da Home (`HTTP 429`):** converter usuários humanos bloqueados pelo limite de `3/dia` diretamente no card `429` via checkout Pix inline (Micro R$ 4,90 e Starter R$ 12,00) com salvamento e reexecução automática da consulta, exibindo resgate de `MCPTRY50` inline somente enquanto a campanha estiver vigente.
- **O4 — Entrega de laudo `.pdf` binário em PHP puro no `/dossie-kyb`:** gerar download imediato de `.pdf` (`%PDF-1.4`) sem dependências externas nem gravação em disco (Zero-PII), autenticado por `_oracle_proof` (ou amostra ilustrativa oficial), elevando o valor percebido para compradores B2B.

---

## 3. Não objetivos (fora do escopo)

Explicitamente **NÃO** fazem parte desta entrega:
- Integração de novo gateway de Cartão Internacional em USD (Stripe, Lemon Squeezy ou Paddle).
- Criação de trial gratuito perene via API ou nova tool no servidor MCP.
- Criação de novos micro-serviços de dados ou alteração dos pacotes/preços vigentes (`Micro R$ 4,90 / 50 cr`, `Starter R$ 12 / 400 cr`, `Pro R$ 29 / 1.200 cr`, `Fleet R$ 115 / 6.000 cr`).
- Uso de Composer, bibliotecas externas pesadas de PDF ou banco de dados SQL.

---

## 4. Público-alvo e personas

1. **Comprador B2B Brasil (PT-BR):** contador, analista de compliance, advogado ou comprador que testa consultas na Home ou acessa `/dossie-kyb`; quer pagar rápido via Pix (`R$ 4,90` ou `R$ 12,00`) sem sair da tela e baixar um laudo `.pdf` pronto no celular ou desktop.
2. **Desenvolvedor / Agente IA Integrador x402 (EN):** consome `/api/v1/*` ou `/mcp`; precisa de mensagens de erro x402 v1/v2 inequívocas (`required_raw_units`, `required_usdc`, `accepts[]`) para corrigir chamadas subpagas automaticamente.
3. **Operador do Grid402 (Admin):** analisa `/admin` para distinguir bugs/dificuldades de pagadores reais de rajadas automatizadas de scanners x402.

---

## 5. Histórias de usuário

- **HU01:** Como agente pagador x402 que enviou valor insuficiente ou assinatura inválida, quero receber `required_raw_units`, `required_usdc` e `accepts[]` na própria resposta de erro `402`/`400`/`429` para recalcular o pagamento e tentar novamente sem consultar documentação externa.
- **HU02:** Como operador do Grid402, quero que o `/admin` separe rajadas de probes sintéticos de conformidade das falhas reais de pagadores no painel de falhas para focar correções onde há clientes reais tentando pagar.
- **HU03:** Como visitante humano na Home que atingiu o limite diário de `3` consultas no Sandbox (`HTTP 429`), quero gerar e pagar o QR Code Pix (Micro `R$ 4,90` ou Starter `R$ 12,00`) dentro do próprio card `429` e ver minha consulta ser executada automaticamente após a aprovação, sem precisar navegar para outra página.
- **HU04:** Como visitante humano na Home enquanto a campanha `MCPTRY50` estiver ativa, quero resgatar os `50` créditos diretamente no card `429`; após a campanha expirar, o botão deve desaparecer automaticamente.
- **HU05:** Como usuário no `/dossie-kyb` com um dossiê emitido ou visualizando a amostra ilustrativa, quero clicar em um botão e baixar diretamente um arquivo `.pdf` válido (`A4`, com cabeçalho, veredito, dados completos, hash SHA-256 e QR de verificação ECDSA) funcionando em desktop e celular, com `window.print()` preservado como fallback.

---

## 6. Requisitos funcionais

| ID | Requisito | Prioridade |
|----|-----------|------------|
| **RF01** | **Respostas de erro x402 recuperáveis estruturadas + auditoria EIP-712:** Em `public_html/index.php`, `public_html/x402.php` e `public_html/facilitator.php`, toda resposta de erro recuperável (`402`, `400` de prova, `429`) MUST retornar `required_raw_units` (int), `required_usdc` (string 4 casas), `accepts` completo (x402 v2) e cabeçalho `PAYMENT-REQUIRED` atualizado; garantir por auditoria e testes que os parâmetros EIP-712 (`name`/`version`) de Base, Polygon e Arbitrum e o parsing v1/v2 estão consistentes. | Must |
| **RF02** | **Política anti-abuso M2M (`402`/`429` JSON e `/mcp`):** NÃO criar tool MCP de resgate gratuito e NÃO anunciar nem instruir o endpoint `POST /api/v1/pass/promo-claim` nas respostas JSON M2M (`402`, `429` ou falhas de `/mcp`); respostas M2M MUST direcionar agentes exclusivamente para pagamento `x402` v2/on-chain, compra de Session Pass (`Pix/USDC`) em `/pass` ou RapidAPI (Cartão USD). | Must |
| **RF03** | **Separação de probes sintéticos vs. falhas reais de pagadores no `/admin`:** Em `public_html/logger.php`, `public_html/admin.php` e `public_html/includes/admin_cockpit_view.php`, classificar registros de `payment_failures.json` com `failure_origin` (`compliance_probe` vs. `payer_failure`), identificando rajadas sintéticas (`>= 3` falhas distintas do mesmo `ip_hmac` em janela `<= 15s` ou assinaturas/provas de teste sintético reconhecidas); expor contagens `payer_failure` vs. `compliance_probe` em `/admin?api=stats` e adicionar filtro/badge na aba *Saúde e Alertas* destacando falhas reais de pagadores. | Must |
| **RF04** | **Pix Inline no card `HTTP 429` do Playground da Home:** Atualizar `public_html/index.php` para que o card `#playground-rate-limit-card` inclua seletor de pacote (**Micro R$ 4,90 = 50 cr** e **Starter R$ 12,00 = 400 cr**), campo de e-mail obrigatório, geração inline de QR Code (`data.qr_mime` dinâmico) e Pix Copia e Cola via `POST /api/v1/pass/mp-pix`, polling automático em `/api/v1/pass/mp-status`, gravação na persistência unificada (`grid402_active_pass_token` + cookie `g402_pass_token`), ativação do modo Session Pass e **reexecução automática da consulta** bloqueada após confirmação. | Must |
| **RF05** | **Resgate inline condicional de `MCPTRY50` apenas na UI humana da Home:** Renderizar no card `#playground-rate-limit-card` (`public_html/index.php`) o botão de resgate em 1 clique do cupom `MCPTRY50` (`POST /api/v1/pass/promo-claim` no navegador) **exclusivamente** quando `grid402_promo_get_status('MCPTRY50')` retornar `is_active === true` (respeitando janela até `2026-12-08T08:00:00Z` e cota máxima); quando inativo/expirado/esgotado, ocultar o botão automaticamente mantendo apenas Pix inline, modo Session Pass e Cartão USD. | Must |
| **RF06** | **Gerador PDF 1.4 binário nativo em PHP puro (`public_html/services/dossier_pdf.php`):** Implementar gerador sem Composer nem dependências externas e sem `mbstring` obrigatório, produzindo arquivo PDF 1.4 válido (`%PDF-1.4`) A4 multipágina em memória (Zero-DB, zero gravação em disco / Zero-PII) com cabeçalho institucional Grid402, card de veredito (`APPROVED`/`FLAGGED`/`REJECTED`), score 0–100, seções Receita Federal, Simples/MEI, Sanções CGU (CEIS/CNEP) e QSA, carimbo de emissão, hash SHA-256 e **QR Code vetorial** nativo em comandos gráficos PDF (`re f`). | Must |
| **RF07** | **Endpoint `POST /api/v1/dossier/pdf` autenticado por `_oracle_proof`:** Expor rota em `public_html/index.php` que recebe o JSON do dossiê já consultado no navegador, valida matematicamente o `_oracle_proof` via `grid402_verify_oracle_proof()` (ou aceita exclusivamente a amostra oficial ilustrativa carimbada como `AMOSTRA ILUSTRATIVA — SEM VALOR LEGAL`), rejeita payloads sem assinatura válida ou adulterados com HTTP `400`/`403` sem gerar PDF, e envia download direto (`Content-Type: application/pdf`, `Content-Disposition: attachment; filename="dossie-kyb-<cnpj>.pdf"`) sem consumir créditos adicionais. | Must |
| **RF08** | **Download `.pdf` em 1 clique no `/dossie-kyb` com fallback `window.print()`:** Atualizar `public_html/dossie_kyb.php` com botão principal **"⬇️ Baixar PDF Oficial (A4)"** integrado a `POST /api/v1/dossier/pdf` (via `fetch` + `Blob` / download de arquivo em desktop e mobile) e preservar **"🖨️ Imprimir (Navegador)"** (`window.print()`) como alternativa secundária. | Should |

---

## 7. Requisitos não funcionais

- **Zero-DB & Zero-PII:** PDF gerado estritamente em memória e transmitido no corpo da resposta HTTP sem criar arquivos temporários com dados de CNPJ/sócios/e-mail em disco.
- **Compatibilidade PHP 8.1+:** Código 100% compatível com Hostinger LiteSpeed e PHP CLI local Windows/Linux sem dependência obrigatória de `mbstring` ou bibliotecas de terceiros; conversão UTF-8 → WinAnsiEncoding (`Windows-1252` / Latin-1 fallback puro em PHP) para acentuação correta nos textos do PDF.
- **Performance:** Geração de PDF `< 250 ms`; classificação de `failure_origin` em `logger.php` / `payment_failures.json` adicionando `< 1 ms` por erro registrado.
- **Segurança:** Validação estrita de `_oracle_proof` antes de emitir PDF oficial (impede falsificação de laudos com timbre Grid402); anti-spoofing de headers preservado no resgate promo e no checkout Pix; nenhuma credencial ou access token exposto em JSON ou logs.
- **Invariantes:** Manter paridade exata das **27 ferramentas MCP**, ausência absoluta do caractere `&` em `README.md`, `mcp/README.md`, `integrations/agentkit/README.md`, `smithery.yaml` e abaixo de `grid402_notify_telegram_purchase()` em `public_html/x402.php`.

---

## 8. Fluxos e UX

1. **Fluxo A — Usuário humano atinge `HTTP 429` no Playground da Home:**
   - Usuário executa a 4ª consulta sandbox no mesmo dia → Gateway retorna `429` estruturado → Front-end exibe `#playground-rate-limit-card` com seleção `Micro R$ 4,90` (padrão destacado) ou `Starter R$ 12,00`, input de e-mail e botão **Gerar QR Code Pix** (+ botão condicional `MCPTRY50` enquanto campanha estiver ativa) → QR aparece no mesmo card com polling automático → Pix aprovado salva o token na persistência unificada, ativa modo Session Pass e **reexecuta automaticamente** a consulta que havia dado `429`.
2. **Fluxo B — Download `.pdf` no `/dossie-kyb`:**
   - Usuário gera dossiê pago ou abre amostra ilustrativa → Clica em **Baixar PDF Oficial (A4)** → Front-end envia o payload JSON atual para `POST /api/v1/dossier/pdf` → Backend valida `_oracle_proof` (ou assinatura da amostra oficial) → Retorna `application/pdf` binário com download imediato `dossie-kyb-<cnpj>.pdf`. Em erro inesperado de rede/navegador, botão **Imprimir (Navegador)** continua funcional via `window.print()`.
3. **Fluxo C — Diagnóstico x402 e Filtro no `/admin`:**
   - Agente M2M envia valor menor que `required_raw_units` → Recebe `402` com `required_raw_units`, `required_usdc` e `accepts[]` completos sem anúncio de cupom gratuito → Tenta novamente com valor correto.
   - No `/admin` (aba *Saúde e Alertas*), operador vê badges `Falhas de Pagadores` vs. `Probes de Conformidade` e pode alternar o filtro da tabela.

---

## 9. Métricas de sucesso

- **100%** das respostas recuperáveis (`402`, `400` de prova, `429`) contendo `required_raw_units`, `required_usdc`, `accepts[]` e `PAYMENT-REQUIRED`.
- **0** anúncios de `promo-claim` nas respostas JSON M2M (`402`/`429`/`/mcp`) e **27** ferramentas MCP preservadas sem adição de tool de trial.
- **100%** dos probes sintéticos em rajada classificados como `compliance_probe` no `/admin`, mantendo falhas reais de pagadores isoladas em `payer_failure`.
- Download de PDF válido (`%PDF-1.4`) no `/dossie-kyb` validado via HTTP E2E com rejeição (`403`) de payloads adulterados e zero arquivos criados em `private_data/`.
- Meta comercial pós-deploy (30 dias): elevar conversão geral do funil humano + M2M acima de `1,5%` com aumento mensurável de compras `Micro`/`Starter` originadas da Home e uso recorrente do `.pdf` no `/dossie-kyb`.

---

## 10. Riscos, dependências e premissas

- **Risco — Falsificação de PDF:** mitigado exigindo verificação matemática de `_oracle_proof` ECDSA `secp256k1` pelo backend antes de gerar PDF oficial (exceto amostra pré-definida no servidor marcada com faixa *AMOSTRA ILUSTRATIVA*).
- **Risco — Abuso de `MCPTRY50` por bots:** mitigado mantendo o resgate programático fora dos payloads M2M e do `/mcp`, preservando trava `1 resgate por IP HMAC` + `daily_credit_limit = 25` e removendo o botão da UI automaticamente quando a campanha expirar em `08/12/2026`.
- **Risco — Acentuação UTF-8 em PDF 1.4 sem fontes TrueType embutidas:** mitigado implementando conversor UTF-8 → `WinAnsiEncoding` (`Windows-1252`) sem depender de `iconv`/`mbstring`, testado com caracteres PT-BR (`ç`, `ã`, `õ`, `á`, `é`, `í`, `ó`, `ú`, `º`).

---

## 11. Questões em aberto

Nenhuma questão em aberto — todos os trade-offs foram alinhados e confirmados pelo operador em 28/11/2026.
