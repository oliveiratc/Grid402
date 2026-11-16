## ADDED Requirements

### Requirement: Extrato Detalhado e Exportação CSV/JSON no /payment-dashboard (REQ-I18N-004)
A página pública `/payment-dashboard` SHALL exibir, ao consultar um token `pass_g402_...` válido, uma tabela acessível de extrato recente com filtros por `endpoint` e `outcome`, cartões de resumo, aviso sobre a retenção de `200` eventos com link de suporte (`contato@grid402.online`) e botões diretos de exportação CSV e JSON totalmente traduzidos em Inglês (padrão SSR) e Português (`PT`) no `i18n.js`.

#### Scenario: Consulta e troca de idioma no extrato do /payment-dashboard
- **GIVEN** um cliente acessando `GET /payment-dashboard?token=pass_g402_...` com eventos registrados
- **WHEN** a página é renderizada em Inglês padrão e alternada para Português via seletor `PT | EN`
- **THEN** o HTML SHALL apresentar os rótulos em Inglês por padrão, links válidos para `/api/v1/pass/statement?...&format=csv` e `format=json`, estado vazio amigável quando sem resultados e tradução integral via chaves no `public_html/assets/js/i18n.js`.
