Write-Host "Iniciando servidor local do Grid402 em http://localhost:8000..." -ForegroundColor Cyan
Write-Host "Pressione Ctrl+C para encerrar o servidor." -ForegroundColor Yellow
php -S 127.0.0.1:8000 -t public_html public_html/router.php

