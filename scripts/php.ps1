$ErrorActionPreference = 'Stop'
$root = Split-Path $PSScriptRoot -Parent
$lock = Get-Content -LiteralPath (Join-Path $root 'toolchain.lock.json') -Raw | ConvertFrom-Json
$php = Join-Path $root ".cache/tools/php-$($lock.php.version)/php.exe"
if (-not (Test-Path -LiteralPath $php)) { throw 'Run scripts/bootstrap.ps1 first.' }
& $php @args
exit $LASTEXITCODE
