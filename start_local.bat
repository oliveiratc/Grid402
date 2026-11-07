@echo off
title Grid402 Local Server
echo ========================================================
echo    Grid402 - Servidor Local de Desenvolvimento
echo    Acesse: http://localhost:8000
echo ========================================================
echo.
php -S 127.0.0.1:8000 -t public_html public_html/router.php
pause

