$ErrorActionPreference = 'Stop'
$root = Split-Path $PSScriptRoot -Parent
$lock = Get-Content -LiteralPath (Join-Path $root 'toolchain.lock.json') -Raw | ConvertFrom-Json
$phpDir = Join-Path $root ".cache/tools/php-$($lock.php.version)"
$php = Join-Path $phpDir 'php.exe'
$composer = Join-Path $root '.cache/downloads/composer.phar'
if (-not (Test-Path -LiteralPath $php) -or -not (Test-Path -LiteralPath $composer)) {
    throw 'Run scripts/bootstrap.ps1 first.'
}
$env:COMPOSER_HOME = Join-Path $root '.cache/composer'
$env:COMPOSER_CACHE_DIR = Join-Path $root '.cache/composer-cache'
# Composer subprocesses must resolve the same PHP executable as the parent.
$previousPath = $env:PATH
Push-Location $root
try {
    $env:PATH = "$phpDir;$previousPath"
    & $php $composer @args
    $code = $LASTEXITCODE
} finally {
    $env:PATH = $previousPath
    Pop-Location
}
exit $code
