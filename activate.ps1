# Dot-source this file: . ./activate.ps1
if ($MyInvocation.InvocationName -ne '.') {
    throw 'Use dot-sourcing to activate the current shell: . ./activate.ps1'
}
if (-not (Test-Path -LiteralPath (Join-Path $PSScriptRoot '.phpenv/ready.json'))) {
    throw 'Run python setup.py first.'
}
if (Test-Path Function:\deactivate) { deactivate }
$global:_PHPENV_Saved = @{}
foreach ($name in @('PATH', 'PHPENV', 'PHPRC', 'PHP_INI_SCAN_DIR', 'COMPOSER_HOME', 'COMPOSER_CACHE_DIR')) {
    $global:_PHPENV_Saved[$name] = @{
        Exists = Test-Path "Env:\$name"
        Value = [Environment]::GetEnvironmentVariable($name, 'Process')
    }
}
function global:deactivate {
    foreach ($entry in $global:_PHPENV_Saved.GetEnumerator()) {
        if ($entry.Value.Exists) {
            [Environment]::SetEnvironmentVariable($entry.Key, $entry.Value.Value, 'Process')
        } else {
            Remove-Item "Env:\$($entry.Key)" -ErrorAction SilentlyContinue
        }
    }
    Remove-Variable _PHPENV_Saved -Scope Global
    Remove-Item Function:\deactivate
}
$env:PHPENV = Join-Path $PSScriptRoot '.phpenv'
$env:PATH = (Join-Path $env:PHPENV 'bin') + [IO.Path]::PathSeparator + $env:PATH
$env:PHPRC = Join-Path $env:PHPENV 'php.ini'
$env:PHP_INI_SCAN_DIR = Join-Path $env:PHPENV 'conf.d'
$env:COMPOSER_HOME = Join-Path $PSScriptRoot '.cache/composer'
$env:COMPOSER_CACHE_DIR = Join-Path $PSScriptRoot '.cache/composer-cache'
