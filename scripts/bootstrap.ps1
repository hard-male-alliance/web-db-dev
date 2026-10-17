[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$root = Split-Path $PSScriptRoot -Parent
$lock = Get-Content -LiteralPath (Join-Path $root 'toolchain.lock.json') -Raw | ConvertFrom-Json
if ($env:OS -ne 'Windows_NT' -or -not [Environment]::Is64BitOperatingSystem) {
    throw 'This bootstrap supports Windows x64 only.'
}

$downloads = Join-Path $root '.cache/downloads'
$tools = Join-Path $root '.cache/tools'
New-Item -ItemType Directory -Force -Path $downloads, $tools | Out-Null
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12

function Get-VerifiedAsset($asset, [string] $name) {
    $path = Join-Path $downloads $name
    if (-not (Test-Path -LiteralPath $path)) {
        Invoke-WebRequest -Uri $asset.url -OutFile $path -UseBasicParsing
    }
    $actual = (Get-FileHash -LiteralPath $path -Algorithm SHA256).Hash.ToLowerInvariant()
    if ($actual -ne $asset.sha256) {
        throw "Checksum mismatch for $name. Remove the cached download and retry; do not bypass verification."
    }
    return $path
}

$zip = Get-VerifiedAsset $lock.php 'php.zip'
$composer = Get-VerifiedAsset $lock.composer 'composer.phar'
$ca = Get-VerifiedAsset $lock.caBundle 'cacert.pem'
$phpDir = Join-Path $tools "php-$($lock.php.version)"
$php = Join-Path $phpDir 'php.exe'
if (-not (Test-Path -LiteralPath $php)) {
    Expand-Archive -LiteralPath $zip -DestinationPath $phpDir -Force
}

# Absolute extension/CA paths make the runtime independent of the caller's working directory.
$ini = @"
[PHP]
extension_dir="$phpDir/ext"
extension=curl
extension=fileinfo
extension=gd
extension=intl
extension=mbstring
extension=openssl
extension=pdo_mysql
extension=pdo_sqlite
extension=sqlite3
extension=zip
date.timezone=Asia/Shanghai
default_charset=UTF-8
error_reporting=E_ALL
display_errors=On
log_errors=On
curl.cainfo="$ca"
openssl.cafile="$ca"
"@
[IO.File]::WriteAllText((Join-Path $phpDir 'php.ini'), $ini, [Text.UTF8Encoding]::new($false))
$actual = & $php -r 'echo PHP_VERSION;'
if ($LASTEXITCODE -ne 0 -or $actual -ne $lock.php.version) {
    throw 'PHP failed to start or has the wrong version. Check the Microsoft Visual C++ 2015-2022 x64 runtime prerequisite.'
}
& $php $composer --version
if ($LASTEXITCODE -ne 0) { throw 'Composer failed to start.' }
Write-Host "Ready: PHP $actual. Use scripts/php.ps1 and scripts/composer.ps1; system PATH was not changed."
