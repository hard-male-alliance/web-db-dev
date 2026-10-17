[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$root = Split-Path $PSScriptRoot -Parent
# Exact requirements are intentional for this application toolchain, not a reusable library.
& (Join-Path $PSScriptRoot 'composer.ps1') validate --strict --no-check-all
if ($LASTEXITCODE -ne 0) { throw 'Composer validation failed.' }
& (Join-Path $PSScriptRoot 'composer.ps1') check-platform-reqs
if ($LASTEXITCODE -ne 0) { throw 'Platform requirements failed.' }
& (Join-Path $PSScriptRoot 'composer.ps1') smoke
if ($LASTEXITCODE -ne 0) { throw 'Runtime smoke test failed.' }

$lock = Get-Content -LiteralPath (Join-Path $root 'toolchain.lock.json') -Raw | ConvertFrom-Json
$php = Join-Path $root ".cache/tools/php-$($lock.php.version)/php.exe"
$listener = [Net.Sockets.TcpListener]::new([Net.IPAddress]::Loopback, 0)
$listener.Start()
$port = $listener.LocalEndpoint.Port
$listener.Stop()
$logs = Join-Path $root '.cache/check'
New-Item -ItemType Directory -Force -Path $logs | Out-Null
$server = Start-Process -FilePath $php -ArgumentList @('-S', "127.0.0.1:$port", '-t', 'tests') `
    -WorkingDirectory $root -WindowStyle Hidden -PassThru `
    -RedirectStandardOutput (Join-Path $logs 'http.out.log') `
    -RedirectStandardError (Join-Path $logs 'http.err.log')
try {
    $response = $null
    for ($attempt = 0; $attempt -lt 30; $attempt++) {
        try {
            $response = Invoke-WebRequest -Uri "http://127.0.0.1:$port/http.php?name=%3Cscript%3E" -UseBasicParsing -TimeoutSec 2
            break
        } catch {
            if ($server.HasExited) { throw 'PHP HTTP server exited. See .cache/check logs.' }
            Start-Sleep -Milliseconds 200
        }
    }
    if ($null -eq $response -or $response.StatusCode -ne 200) { throw 'HTTP startup/request failed.' }
    if (-not $response.Content.Contains("PHP $($lock.php.version) / Yii2 2.0.55")) {
        throw 'HTTP response has unexpected runtime/framework versions.'
    }
    if (-not $response.Content.Contains('&lt;script&gt;') -or $response.Content.Contains('<script>')) {
        throw 'View output escaping failed.'
    }
    Write-Host 'PASS loopback HTTP -> PHP -> Yii2 view -> escaped HTML.'
} finally {
    if (-not $server.HasExited) { Stop-Process -Id $server.Id }
}
