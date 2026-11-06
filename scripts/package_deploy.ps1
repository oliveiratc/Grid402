<#
.SYNOPSIS
    Script de Empacotamento para Deploy na Hostinger (Grid402)
#>

$ErrorActionPreference = "Stop"

$projectRoot = Split-Path -Parent $PSScriptRoot
$publicHtml = Join-Path $projectRoot "public_html"
$distDir = Join-Path $projectRoot "dist"
$zipPath = Join-Path $distDir "grid402_hostinger_deploy.zip"

Write-Host "Iniciando empacotamento para Hostinger..." -ForegroundColor Cyan

if (-not (Test-Path $distDir)) {
    New-Item -ItemType Directory -Path $distDir | Out-Null
}

if (Test-Path $zipPath) {
    Remove-Item $zipPath -Force
}

$tempStage = Join-Path $distDir "temp_stage"
if (Test-Path $tempStage) {
    Remove-Item $tempStage -Recurse -Force
}
New-Item -ItemType Directory -Path $tempStage | Out-Null

Copy-Item -Path "$publicHtml\*" -Destination $tempStage -Recurse -Force

if (Test-Path "$publicHtml\.htaccess") {
    Copy-Item -Path "$publicHtml\.htaccess" -Destination "$tempStage\.htaccess" -Force
}
if (Test-Path "$publicHtml\private_data\.htaccess") {
    $privateTarget = Join-Path $tempStage "private_data"
    if (-not (Test-Path $privateTarget)) {
        New-Item -ItemType Directory -Path $privateTarget | Out-Null
    }
    Copy-Item -Path "$publicHtml\private_data\.htaccess" -Destination "$privateTarget\.htaccess" -Force
}

Remove-Item -Path "$tempStage\private_data\*.json" -Force -ErrorAction SilentlyContinue

Compress-Archive -Path "$tempStage\*" -DestinationPath $zipPath -Force

Remove-Item $tempStage -Recurse -Force

$zipSize = (Get-Item $zipPath).Length / 1KB

Write-Host ""
Write-Host "Pacote gerado com sucesso!" -ForegroundColor Green
Write-Host "Arquivo: $zipPath" -ForegroundColor Yellow
Write-Host "Tamanho: $([math]::Round($zipSize, 2)) KB"
Write-Host ""
