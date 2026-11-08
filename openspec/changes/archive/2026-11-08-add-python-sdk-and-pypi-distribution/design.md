# Design: SDK Python Oficial e Distribuição no PyPI

## Arquitetura do Pacote

O pacote Python `grid402` adota a estrutura padrão de diretórios `src/` ou módulo raiz com build moderno via `hatchling` ou `setuptools`:

```text
python/
├── pyproject.toml
├── README.md
├── LICENSE
├── tests/
│   └── test_client.py
└── grid402/
    ├── __init__.py
    ├── client.py
    ├── agentkit.py
    ├── models.py
    └── py.typed
```

### Componentes Principais

1. **`Grid402Client` (`client.py`)**:
   - Conexão HTTP leve utilizando `urllib.request` nativo ou `httpx` (quando disponível), sem exigir dependências pesadas.
   - Suporte a `sandbox=True`, injetando automaticamente `X-Payment: demo`.
   - Tratamento estruturado do erro HTTP 402 com retorno da amostra de dados (`preview`) e campos protegidos (`locked_fields`).
   - Métodos explícitos: `get_summary()`, `get_forex()`, `get_crypto()`, `get_dns()`, `get_ssl()`, `get_asn()`, `get_cep()`, `get_cnpj()`.

2. **`Grid402ActionProvider` (`agentkit.py`)**:
   - Integração com o Coinbase Developer Platform (CDP) AgentKit.
   - Ciclo de auto-liquidação: intercepta o desafio 402, extrai o endereço e valor em USDC, aciona `wallet_provider.transfer()` na Base e repassa a requisição com `X-Payment: <tx_hash>`.

3. **Modelos Pydantic (`models.py`)**:
   - `CnpjInput`, `CepInput`, `DnsInput`, `SslInput`, `AsnInput`.
   - Compatíveis com anotações de funções em LangChain Tools (`@tool`) e CrewAI.

4. **Configuração de Build (`pyproject.toml`)**:
   - Especificação PEP 621.
   - Dependência essencial: `pydantic>=2.0.0`.
   - Dependências opcionais em extras: `[agentkit]` (`cdp-agentkit-core`).
