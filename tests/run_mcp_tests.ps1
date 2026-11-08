param()

$php = "C:\Users\thiag\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe"
$proc = Start-Process -FilePath $php -ArgumentList "-S 127.0.0.1:8956 tests/router.php" -PassThru
Start-Sleep -Milliseconds 600

try {
    $env:GRID402_ENDPOINT = "http://127.0.0.1:8956"
    node mcp/test_mcp.mjs
}
finally {
    if ($proc -and -not $proc.HasExited) {
        Stop-Process -Id $proc.Id -Force
    }
}
