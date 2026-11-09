# PRD: Grid402 Compliance and Antifraud Suite (Sanções CEIS/CNEP, Simples Nacional/MEI e Score 360)

**Status:** Em revisão
**Data:** 2026-11-09
**Autor:** oliveiratc + Antigravity

## 1. Contexto e problema
Agentes autônomos de inteligência artificial, contratos inteligentes e sistemas de pagamento automatizado operando no mercado brasileiro necessitam verificar a idoneidade jurídica e o enquadramento fiscal de fornecedores e parceiros antes de liberar pagamentos em USDC ou reais. Atualmente, bureaus de crédito tradicionais impõem mensalidades elevadas, contratos burocráticos e APIs que cobram entre R$ 2,00 e R$ 15,00 por consulta com alta latência.
Essa barreira inviabiliza micropagamentos e operações autônomas por agentes. O Grid402 já consolidou as consultas de CNPJ e utilitários de rede; a adição de checagem oficial de sanções administrativas (CEIS e CNEP da CGU), enquadramento no Simples Nacional / MEI e um Compliance Score consolidado com prova criptográfica secp256k1 entrega o pacote de maior conversão comercial para agentes de compra, tesouraria e contratação onchain.

## 2. Objetivos
- Disponibilizar o endpoint `/api/v1/sanctions` para consulta em tempo real no Cadastro de Empresas Inidôneas e Suspensas (CEIS) e Cadastro Nacional de Empresas Punidas (CNEP da CGU).
- Disponibilizar o endpoint `/api/v1/simples` para consulta de opção pelo Simples Nacional e enquadramento como Microempreendedor Individual (MEI).
- Disponibilizar o endpoint `/api/v1/compliance` (Score 360) que cruza CNPJ, Sanções, Simples Nacional e Dívida Ativa, retornando um status objetivo (`APROVADO`, `ALERTA` ou `REJEITADO`) com pontuação de 0 a 100.
- Implementar mecanismo de cache local indexado (24 horas para sanções e 7 dias para Simples Nacional) garantindo tempo de resposta médio inferior a 80 milissegundos.
- Integrar auto-liquidação x402 na rede Base (0.001 USDC para consultas pontuais e 0.003 USDC para o score consolidado), preservando modo sandbox gratuito via cabeçalho `X-Payment: demo`.

## 3. Não objetivos (fora do escopo)
- Não abrange dados pessoais protegidos por sigilo bancário ou fiscal (score de crédito pessoa física Serasa/SPC).
- Não inclui consultas a processos judiciais em andamento fora dos cadastros públicos consolidados da CGU e Receita Federal.
- Não envolve emissão direta de notas fiscais eletrônicas nesta etapa.

## 4. Público-alvo e personas
- **Desenvolvedores de Agentes Autônomos (Coinbase AgentKit, LangChain, CrewAI):** Agentes de tesouraria que precisam checar idoneidade antes de autorizar repasses.
- **Fintechs e Plataformas de Pagamento Web3:** Validação de contas de destino e onboarding automatizado de empresas brasileiras (KYB).
- **Aplicações de E-commerce e B2B:** Identificação de alíquotas e retenções tributárias pelo Simples Nacional antes de fechar pedidos.

## 5. Histórias de usuário
- Como um agente autônomo de compras, quero checar se um fornecedor tem sanções ativas no CEIS ou CNEP antes de liberar o pagamento, para evitar bloqueios judiciais e riscos de corrupção.
- Como uma plataforma de pagamento, quero verificar se uma empresa é optante pelo Simples Nacional ou MEI, para reter os impostos devidos na fonte de forma correta.
- Como um contrato inteligente de empréstimo ou tesouraria, quero consultar um veredito consolidado de compliance com prova criptográfica secp256k1, para registrar a auditoria onchain da operação.

## 6. Requisitos funcionais
| ID | Requisito | Prioridade |
|----|-----------|------------|
| RF01 | O endpoint `/api/v1/sanctions` deve receber o parâmetro `cnpj` e retornar a lista de sanções ativas nos cadastros CEIS e CNEP da CGU, indicando órgão sancionador, motivo e datas de vigência. | Must |
| RF02 | O endpoint `/api/v1/simples` deve receber o parâmetro `cnpj` e retornar se a empresa é optante pelo Simples Nacional, data de opção, e se está enquadrada como Microempreendedor Individual (MEI). | Must |
| RF03 | O endpoint `/api/v1/compliance` deve cruzar dados cadastrais, sanções e enquadramento fiscal, gerando um resultado consolidado contendo `score` (0 a 100), `status` (`APROVADO`, `ALERTA`, `REJEITADO`) e array de apontamentos objetivos. | Must |
| RF04 | Todas as respostas de sucesso dos três endpoints devem conter o objeto `_oracle_proof` assinado com a chave privada oficial secp256k1 da carteira do Grid402. | Must |
| RF05 | O protocolo x402 deve ser acionado para clientes sem autorização: 0.001 USDC para `/api/v1/sanctions` e `/api/v1/simples`, e 0.003 USDC para `/api/v1/compliance`, retornando HTTP 402 com os parâmetros de liquidação na rede Base. | Must |
| RF06 | Quando o cabeçalho `X-Payment: demo` for fornecido, a API deve retornar os dados completos em modo sandbox gratuito. | Must |
| RF07 | As novas ferramentas devem ser refletidas nos adaptadores TypeScript do Coinbase AgentKit, SDK Node.js e no servidor MCP oficial do Grid402. | Should |
| RF08 | A documentação pública do portal (`index.php`) e a telemetria do painel de administração (`admin.php`) devem registrar e monitorar os novos endpoints. | Should |

## 7. Requisitos não funcionais
- **Performance:** Latência de resposta com cache local inferior a 80ms para 95% das requisições.
- **Disponibilidade:** Tolerância a falhas do upstream governamental servindo a última versão sincronizada em cache com aviso de frescor de dados.
- **Segurança:** Respeito absoluto às diretrizes da LGPD (dados manifestamente públicos sob a Lei de Acesso à Informação) sem exposição de dados sigilosos.
- **Conformidade de Código:** Ausência absoluta do caractere e comercial em todo o código, documentação e commits.

## 8. Fluxos e UX
- **Fluxo Principal (Consulta Paga x402):**
  1. O agente chama `/api/v1/compliance?cnpj=00000000000191`.
  2. A API retorna HTTP 402 Payment Required com `price_usdc: 0.003`, endereço de destino do oráculo e identificador do desafio.
  3. A carteira do agente efetua a transferência de USDC na Base e reenvia a chamada com `X-Payment: <txHash>`.
  4. O Grid402 valida a transação, busca os dados em cache local, assina com secp256k1 e entrega o veredito estruturado.
- **Fluxo Sandbox (Desenvolvimento Gratuito):**
  1. O desenvolvedor envia `X-Payment: demo`.
  2. A API responde imediatamente com HTTP 200 contendo o payload completo e a marcação de modo sandbox.
- **Fluxo de Erro Cadastral:**
  1. CNPJ inválido ou com dígitos incorretos retorna HTTP 400 Bad Request com mensagem detalhada em formato JSON.

## 9. Métricas de sucesso
- Crescimento mínimo de 40% no volume diário de requisições pagas no portal Grid402.
- Tempo médio de resposta consolidado mantido abaixo de 100 milissegundos.
- 100% dos testes unitários e de integração aprovados no backend e adaptadores.

## 10. Riscos, dependências e premissas
- **Risco de instabilidade no portal de dados da CGU:** Mitigado por rotina de sincronização periódica e cache persistido em banco de dados local.
- **Dependência da base oficial de optantes do Simples Nacional:** A Receita Federal atualiza mensalmente os arquivos públicos; a base local deve ser atualizada em lote.

## 11. Questões em aberto
- Nenhuma questão em aberto identificada. Decisões alinhadas integralmente na etapa de Grill-me.
