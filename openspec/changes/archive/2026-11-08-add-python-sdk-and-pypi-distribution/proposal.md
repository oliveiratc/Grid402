# SDK Python Oficial e Distribuição no PyPI (grid402)

## Why
O ecossistema de orquestração de IA e robôs autônomos opera majoritariamente em Python (LangChain, CrewAI, AutoGen). Atualmente, os desenvolvedores precisam copiar scripts soltos de integração do repositório. O nome de pacote `grid402` encontra-se 100% vago e disponível para registro no PyPI oficial. A criação de um SDK Python empacotado sob as especificações modernas da comunidade (PEP 517 / PEP 621) viabiliza instalação em 1 comando (`pip install grid402`) e adoção global por agentes de IA.
Referência: `openspec/prds/grid402-python-sdk-pypi-distribution.md`.

## What Changes
- Criação da pasta `python/` contendo o pacote `grid402` configurado com `pyproject.toml`.
- Implementação de `Grid402Client` para chamadas HTTP síncronas às 8 ferramentas e suporte ao Modo Sandbox.
- Implementação de `Grid402ActionProvider` com auto-liquidação x402 para o ecossistema Coinbase AgentKit e LangChain.
- Definição de modelos Pydantic tipados para parâmetros e validação estática (`py.typed`).
- Suíte de testes unitários do SDK em Python (`python/tests/`).
- Geração dos artefatos de distribuição (`.whl` e `.tar.gz`) e documentação de publicação no PyPI.

## Capabilities
### New Capabilities
- `python-sdk`: Biblioteca Python oficial `grid402` disponível para instalação via pip contendo cliente HTTP e provedor de ações para agentes de IA.

## Impact
- **Specs afetadas:** `python-sdk` (novo domínio de especificação)
- **Código afetado:** `python/` (criação do módulo e configuração de build)
