# PRD: SDK Python Oficial e Publicação no PyPI (grid402)

**Status:** Em revisão  
**Data:** 2026-11-08  
**Autor:** oliveiratco1 + Antigravity  

---

## 1. Contexto e Problema

O ecossistema de Inteligência Artificial e agentes autônomos (LangChain, CrewAI, AutoGen, LlamaIndex) é predominantemente construído em Python. Atualmente, os desenvolvedores Python que desejam consumir as 8 ferramentas do Grid402 precisam:
1. Copiar manualmente arquivos de integração do repositório (`integrations/agentkit/grid402_action_provider.py`).
2. Implementar sua própria lógica de tratamento de desafios HTTP 402, auto-liquidação on-chain na Base e suporte a Modo Sandbox.

O nome de pacote **`grid402`** está 100% vago e disponível no índice público oficial do **PyPI (Python Package Index)**. 

Criar e publicar a biblioteca oficial `grid402` permite que qualquer robô ou desenvolvedor instale a suíte com um único comando (`pip install grid402`) e utilize as ferramentas imediatamente em seus fluxos de IA.

---

## 2. Objetivos

- **O1:** Criar e estruturar o pacote Python oficial sob o nome `grid402` em conformidade com o padrão moderno PEP 517 / PEP 621 (`pyproject.toml`).
- **O2:** Fornecer um cliente HTTP robusto (`Grid402Client`) com suporte nativo a chamadas síncronas, Modo Sandbox gratuito (`sandbox=True`) e auto-liquidação na Base.
- **O3:** Disponibilizar o provedor de ações para IA (`Grid402ActionProvider`) compatível com Coinbase Developer Platform (CDP) AgentKit, LangChain e CrewAI.
- **O4:** Fornecer modelos tipados Pydantic (`CnpjInput`, `CepInput`, `DnsInput`, etc.) garantindo type safety e auto-descoberta de parâmetros por LLMs.
- **O5:** Gerar os artefatos de distribuição (`.whl` e `.tar.gz`) e disponibilizar o fluxo de publicação via Twine no PyPI.

---

## 3. Não Objetivos (Fora do Escopo)

- **Intermediação de Carteiras:** O SDK não armazena chaves privadas em nuvem nem realiza custódia de fundos; a assinatura ocorre localmente via provedor do usuário.
- **Dependências Pesadas Obrigatórias:** A biblioteca core deve depender apenas de recursos padrão do Python e Pydantic, mantendo dependências de web3 e frameworks de IA como opcionais/plugáveis.
- **Alterações no Backend Hostinger:** A API em produção permanece inalterada e atende a biblioteca diretamente.

---

## 4. Público-Alvo e Personas

1. **Desenvolvedores de Agentes de IA em Python:** Usuários de frameworks como LangChain, CrewAI e AutoGen que necessitam de fatos públicos (CNPJ, Câmbio, CEP, DNS) com auto-liquidação.
2. **Engenheiros de Dados e Scraping:** Desenvolvedores que precisam consultar dados da Receita Federal e utilitários de rede em pipelines automatizados.
3. **Makers e Desenvolvedores Web3:** Criadores de robôs autônomos na rede Base utilizando o Coinbase AgentKit.

---

## 5. Histórias de Usuário

- **US01:** Como desenvolvedor Python, quero executar `pip install grid402` e importar `from grid402 import Grid402Client` sem configurações complexas.
- **US02:** Como desenvolvedor sem carteira cripto, quero instanciar `Grid402Client(sandbox=True)` e realizar consultas gratuitas de teste no Modo Sandbox.
- **US03:** Como criador de robôs com carteiras CDP na Base, quero instanciar `Grid402ActionProvider` e conectar diretamente às ferramentas do AgentKit para auto-liquidação M2M.
- **US04:** Como engenheiro de software, quero suporte completo a tipagem estática (PEP 561 `py.typed`) para autocompletar e validação no VS Code ou Cursor.

---

## 6. Requisitos Funcionais

| ID | Requisito | Prioridade |
|---|---|---|
| **RF01** | **Estrutura Moderna PEP 621** | Configurar `python/pyproject.toml` com metadados completos, dependências limpas e marcação de licença MIT. | Must |
| **RF02** | **Cliente HTTP Central (`Grid402Client`)** | Implementar cliente com métodos para os 7 serviços técnicos e o resumo operacional (`get_summary`, `get_forex`, `get_crypto`, `get_dns`, `get_ssl`, `get_asn`, `get_cep`, `get_cnpj`). | Must |
| **RF03** | **Modo Sandbox Declarativo** | Suporte ao parâmetro `sandbox=True` no cliente e provedor, enviando automaticamente o cabeçalho `X-Payment: demo`. | Must |
| **RF04** | **Provedor de Ação Coinbase AgentKit** | Integrar `Grid402ActionProvider` para interceptar desafios HTTP 402 e acionar auto-transferência de USDC via `wallet_provider`. | Must |
| **RF05** | **Modelos Pydantic Tipados** | Exportar schemas de entrada para CNPJ, CEP, DNS, SSL e ASN, permitindo introspecção automática por orquestradores de IA. | Must |
| **RF06** | **Suíte de Testes Automatizados** | Desenvolver testes unitários cobrindo o cliente, modo sandbox, deserialização de dados e tratamento de erros. | Must |
| **RF07** | **Compilação e Pacote Wheel** | Gerar os arquivos binários de distribuição (`dist/grid402-*.whl` e `.tar.gz`) prontos para publicação via Twine. | Must |

---

## 7. Requisitos Não Funcionais

- **RNF01 (Compatibilidade Python):** Compatível com Python 3.9, 3.10, 3.11, 3.12, 3.13 e 3.14.
- **RNF02 (Tamanho do Pacote):** Pacote compilado no formato wheel com tamanho inferior a 40 KB.
- **RNF03 (Zero Dependências Forçadas):** Não exigir instalação forçada de pacotes pesados como `torch` ou `web3` no pacote core (Pydantic como única dependência essencial).
- **RNF04 (Linguagem e Redação):** Ausência total do caractere 'e comercial' em toda a documentação, docstrings e saídas de console.

---

## 8. Fluxos e UX

### 8.1. Fluxo de Uso Básico do Cliente
```python
from grid402 import Grid402Client

# 1. Modo Sandbox gratuito para testes
client = Grid402Client(sandbox=True)

# 2. Consulta de CNPJ da Receita Federal
empresa = client.get_cnpj("00000000000191")
print(empresa["razao_social"])  # BANCO DO BRASIL SA
```

### 8.2. Fluxo com Coinbase AgentKit M2M
```python
from cdp_agentkit_core.actions import CdpActionProvider
from grid402.agentkit import Grid402ActionProvider

# Conecta o provedor ao agente CDP para auto-liquidacao de micropagamentos na Base
action_provider = Grid402ActionProvider(sandbox=False)
```

---

## 9. Métricas de Sucesso

- **M01:** 100% dos testes unitários do SDK Python aprovados.
- **M02:** Arquivos `.whl` e `.tar.gz` gerados e validados com `twine check`.
- **M03:** Pacote publicado com sucesso no PyPI sob o nome `grid402`.

---

## 10. Riscos, Dependências e Premissas

- **P01:** O nome `grid402` foi verificado no PyPI e está livre para registro.
- **R01 (Autenticação PyPI):** A publicação no PyPI exige um token de API válido (`pypi-...`).  
  *Mitigação:* Fornecer script/comando claro para o mantenedor publicar via Twine utilizando seu token pessoal de forma segura.
- **R02 (Conflito de Nomes de Módulos):** Garantir que o diretório `grid402/` contenha `py.typed` para suporte a type checkers (mypy, pyright).

---

## 11. Questões em Aberto

*Nenhuma no momento. O escopo, estrutura de classes e modelo de publicação foram alinhados e aprovados na etapa de Grill-me.*

