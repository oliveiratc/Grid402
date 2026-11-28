# Spec Delta

## ADDED Requirements

### Requirement: Python SDK and examples shall enforce TLS certificate validation
O cliente Python oficial e os exemplos de integração SHALL manter a validação estrita de certificados TLS e hostname em todas as requisições HTTPS.

#### Scenario: Conexao HTTPS originada do SDK Python
- **WHEN** o cliente SDK Python faz requisição HTTPS para o endpoint configurado
- **THEN** a verificação de certificados TLS e de hostname permanece ativa sem exceções de domínio
