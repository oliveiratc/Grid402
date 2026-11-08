# Script de Publicacao Oficial do Pacote grid402 v2.2.0 no npm
# Requer login ativo no npm (npm whoami) ou token de automacao configurado.

$ErrorActionPreference = "Stop"

Write-Host "=== Publicador Oficial grid402 v2.2.0 (npm) ===" -ForegroundColor Cyan

# 1. Execucao de todos os testes antes de publicar
Write-Host "`n1. Executando suite completa de testes automatizados..." -ForegroundColor Yellow

Push-Location "sdk/node"

try {
    Write-Host " -> Testes unitarios ESM..." -ForegroundColor Gray
    node test_sdk.mjs
    if ($LASTEXITCODE -ne 0) {
        Write-Error "Falha nos testes unitarios ESM."
        exit 1
    }

    Write-Host " -> Testes de compatibilidade CommonJS..." -ForegroundColor Gray
    node test_cjs.cjs
    if ($LASTEXITCODE -ne 0) {
        Write-Error "Falha nos testes CommonJS."
        exit 1
    }

    Write-Host " -> Testes de integracao com a API remota..." -ForegroundColor Gray
    node test_live_sandbox.mjs
    if ($LASTEXITCODE -ne 0) {
        Write-Error "Falha nos testes live sandbox."
        exit 1
    }

    Write-Host "`nTodos os testes foram concluidos com sucesso!" -ForegroundColor Green

    # 2. Verificacao de autenticacao no npm
    Write-Host "`n2. Verificando sessao ativa no registro npm..." -ForegroundColor Yellow
    $currentUser = npm whoami 2>$null
    if ($LASTEXITCODE -ne 0 -or -not $currentUser) {
        Write-Host "Nenhuma sessao npm detectada. Por favor, execute 'npm login' no terminal antes de publicar." -ForegroundColor Magenta
        exit 1
    }

    Write-Host "Sessao ativa como usuario: $currentUser" -ForegroundColor Green

    # 3. Publicacao oficial com tag latest e acesso publico
    Write-Host "`n3. Publicando grid402@2.2.0 no npm..." -ForegroundColor Cyan
    npm publish --access public

    Write-Host "`nPacote grid402 v2.2.0 publicado com sucesso no npm!" -ForegroundColor Green
    Write-Host "Verifique em: https://www.npmjs.com/package/grid402" -ForegroundColor Cyan
    Write-Host "Instale a qualquer momento com: npm install grid402@2.2.0" -ForegroundColor Yellow
} finally {
    Pop-Location
}

