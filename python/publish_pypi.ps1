# Script de Publicacao Oficial do Pacote Grid402 no PyPI
# Requer conta no PyPI (https://pypi.org) e geracao de API Token com escopo global ou para o pacote grid402.

param (
    [switch]$TestPyPI = $false
)

$ErrorActionPreference = "Stop"

Write-Host "=== Publicador Oficial Grid402 PyPI ===" -ForegroundColor Cyan

# 1. Validacao dos artefatos compilados
$distFiles = Get-ChildItem -Path "python/dist" -Filter "*.whl", "*.tar.gz"
if ($distFiles.Count -eq 0) {
    Write-Error "Nenhum arquivo encontrado em python/dist/. Execute o build antes de publicar."
    exit 1
}

Write-Host "Artefatos localizados para envio:" -ForegroundColor Green
$distFiles | ForEach-Object { Write-Host " - $($_.Name) ($([math]::Round($_.Length / 1KB, 2)) KB)" }

# 2. Verificacao de integridade com Twine
Write-Host "`nValidando integridade com twine check..." -ForegroundColor Yellow
.\python\.venv\Scripts\python.exe -m twine check python/dist/*
if ($LASTEXITCODE -ne 0) {
    Write-Error "A verificacao dos pacotes falhou."
    exit 1
}

# 3. Solicitacao segura do API Token do PyPI
$token = $env:TWINE_PASSWORD
if (-not $token) {
    $token = $env:PYPI_API_TOKEN
}

if (-not $token) {
    Write-Host "`nDigite ou cole seu API Token do PyPI (inicia com pypi-...):" -ForegroundColor Magenta
    $secureInput = Read-Host -AsSecureString
    $token = [System.Net.NetworkCredential]::new("", $secureInput).Password
}

if (-not $token) {
    Write-Error "API Token nao fornecido. Abortando publicacao."
    exit 1
}

# 4. Upload com Twine
$repositoryArgs = @()
if ($TestPyPI) {
    Write-Host "`nEnviando para o TestPyPI (https://test.pypi.org)..." -ForegroundColor Cyan
    $repositoryArgs = @("--repository", "testpypi")
} else {
    Write-Host "`nEnviando para o PyPI Oficial (https://pypi.org)..." -ForegroundColor Green
}

$env:TWINE_USERNAME = "__token__"
$env:TWINE_PASSWORD = $token

try {
    .\python\.venv\Scripts\python.exe -m twine upload @repositoryArgs python/dist/*
    Write-Host "`nPacote grid402 v1.0.0 publicado com sucesso no PyPI!" -ForegroundColor Green
    Write-Host "Instale a qualquer momento com: pip install grid402" -ForegroundColor Cyan
} finally {
    $env:TWINE_USERNAME = ""
    $env:TWINE_PASSWORD = ""
    $token = ""
}
