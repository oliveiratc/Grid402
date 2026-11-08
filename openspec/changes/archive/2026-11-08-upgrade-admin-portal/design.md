# Design: Modernizacao e Monitoramento Avancado do Portal Administrativo

## Contexto

O Grid402 utiliza arquitetura Zero-DB com arquivos JSON persistidos de maneira atomica sob concorrencia via `flock` exclusiva. O painel administrativo (`public_html/admin.php`) exibe telemetria em tempo real e consolida receitas geradas pelo protocolo x402. O sistema agora opera com 11 micro-servicos e necessita de atualizacao no catalogo, monitoramento on-chain da carteira oficial na Base, gestao de credenciais e exportacao contabil de dados.

## Decisoes Tecnicas

### Decisao 1: Leitura On-Chain via JSON-RPC da Base com Cache de 60 Segundos
- **Arquitetura**: O modulo `public_html/x402.php` ja possui o metodo `grid402_rpc_call($method, $params)`.
  - Saldo USDC: `eth_call` com a assinatura `0x70a08231` (ERC-20 `balanceOf`) direcionado ao contrato oficial do USDC na Base (`0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913`). O resultado hexadecimal e convertido considerando 6 casas decimais.
  - Saldo ETH (gas): `eth_getBalance` para a carteira oficial `0xa31c31069c43ac1c0075a93c325b0dcd7744356c`. O valor e convertido considerando 18 casas decimais.
- **Cache**: Armazenado em `public_html/private_data/wallet_balance_cache.json`. Uma nova requisicao RPC so ocorre se o arquivo nao existir ou se o timestamp for superior a 60 segundos. Se o RPC publico falhar, o sistema retorna o ultimo saldo conhecido sem interromper o painel.
- **Racional**: Garante tempo de resposta do dashboard abaixo de 50 ms sem estourar limites de requisicao (rate-limits) do provedor RPC da Base.

### Decisao 2: Classificacao Estruturada de Tipos em `metrics.php`
- **Classificacao**: O evento de telemetria passa a armazenar o campo `type`:
  - `paid`: Pagamento x402 validado on-chain.
  - `sandbox`: Consulta em modo sandbox (`X-Payment: demo`).
  - `challenge_402`: Desafio de pagamento emitido (HTTP 402).
  - `blocked_403`: Bloqueio de Replay Attack (HTTP 403).
  - `free`: Consulta a rota gratuita (`/status`).
- **Buffer Circular**: A constante `GRID402_METRICS_RECENT_LIMIT` e aumentada de 50 para 100 eventos, oferecendo historico mais amplo sem inflar o payload do JSON.

### Decisao 3: Filtros Reativos em Vanilla JS no Cliente
- **Abordagem**: Os filtros de tipo (`Todos`, `Pagos`, `Sandbox`, `Desafios 402`, `Bloqueados 403`) e a busca textual operam exclusivamente no DOM atraves de `dataset.type` e comparacao de texto de cada linha da tabela.
- **Racional**: Resposta visual em 0 ms sem requisicoes adicionais ao servidor a cada clique de filtro ou tecla digitada.

### Decisao 4: Endpoint de Exportacao CSV com BOM UTF-8
- **Rota**: `GET /admin?api=export_csv`.
- **Formato**: Envio do caractere especial BOM (`\xEF\xBB\xBF`) no inicio do fluxo para que o Microsoft Excel e ferramentas de planilhas abram o arquivo automaticamente em UTF-8 com suporte correto a caracteres acentuados.
- **Colunas**: `Horario UTC`, `Servico`, `Status HTTP`, `Tipo`, `Valor USDC`, `Cotacao PTAX BRL`, `Valor BRL Estimado`, `Hash BaseScan`, `IP Mascarado`.

### Decisao 5: Modal de Alteracao de Senha no Proprio Dashboard
- **Seguranca**: O modal envia requisicao POST autenticada para `/admin?action=change_password`. O backend verifica a senha atual com `password_verify`, valida tamanho minimo de 8 caracteres e grava o novo hash Bcrypt (custo 11) de forma atomica em `admin_auth.json`.

### Decisao 6: Roteamento de Fallback no `index.php`
- **Interceptacao**: Inclusao no topo da classificacao de rotas de `public_html/index.php`:
  ```php
  if ($requestPath === '/admin' || $requestPath === '/dashboard' || $pageParam === 'admin' || $pageParam === 'dashboard') {
      require __DIR__ . '/admin.php';
      exit;
  }
  ```
- **Racional**: Assegura que `/admin` funcione mesmo em servidores locais sem mod_rewrite ou em cenarios onde o LiteSpeed encaminhe tudo para o index.php.

## Riscos e Mitigacoes

- **[Risco: Sobrecarga do RPC publico da Base]** -> Mitigado com trava de cache estrita de 60 segundos por arquivo.
- **[Risco: Bloqueio acidental de acesso por alteracao de senha]** -> Exigencia da senha atual no formulario e validacao previa antes da gravacao.

