## 1. Estruturação do Pacote Python
- [x] 1.1 Criar diretório `python/` com `pyproject.toml`, `README.md` e `LICENSE` (MIT).
- [x] 1.2 Implementar `python/grid402/__init__.py` exportando os componentes principais e versão.
- [x] 1.3 Implementar `python/grid402/models.py` com os schemas Pydantic tipados.
- [x] 1.4 Implementar `python/grid402/client.py` com a classe `Grid402Client` e métodos para as 8 ferramentas.
- [x] 1.5 Implementar `python/grid402/agentkit.py` com o `Grid402ActionProvider` e ciclo de auto-liquidação.
- [x] 1.6 Adicionar `py.typed` para marcação de tipos PEP 561.

## 2. Testes Automatizados
- [x] 2.1 Criar suite de testes em `python/tests/test_sdk.py` cobrindo o cliente, modo sandbox, modelos e tratamento de 402.
- [x] 2.2 Executar os testes automatizados com Python local e validar 100% de aprovação.

## 3. Empacotamento e Preparação PyPI
- [x] 3.1 Instalar ou verificar ferramentas de build (`build` e `twine`).
- [x] 3.2 Compilar o pacote gerando os artefatos `.whl` e `.tar.gz` em `python/dist/`.
- [x] 3.3 Validar a integridade do pacote compilado com `twine check`.
- [x] 3.4 Disponibilizar instrução e script de publicação para o PyPI com token.
