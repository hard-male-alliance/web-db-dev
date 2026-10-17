[CmdletBinding()]
param(
    [ValidateSet('up', 'check', 'persistence', 'export', 'restore', 'down', 'logs')]
    [string] $Task = 'up',
    [string] $Path = '.cache/exports/install.sql',
    [switch] $ConfirmRestore
)

$ErrorActionPreference = 'Stop'
$root = Split-Path $PSScriptRoot -Parent
if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    throw 'Install/start Docker Desktop (Linux containers) or Docker Engine with Compose v2.24+ first. Native PHP scripts remain available.'
}

function Invoke-Compose {
    & docker compose @args
    if ($LASTEXITCODE -ne 0) { throw "docker compose failed: $($args -join ' ')" }
}

function Test-Stack {
    Invoke-Compose exec -T app composer validate --strict --no-check-all
    Invoke-Compose exec -T app composer check-platform-reqs
    Invoke-Compose exec -T app php tests/toolchain.php
    Invoke-Compose exec -T app php tests/database.php
    # Read Compose's resolved port, including .env settings, instead of reimplementing dotenv.
    $binding = & docker compose port app 8080
    if ($LASTEXITCODE -ne 0 -or -not $binding) { throw 'Cannot resolve the application port.' }
    $url = "http://$($binding.Trim())/"
    $response = Invoke-WebRequest -Uri $url -UseBasicParsing -TimeoutSec 15
    if ($response.StatusCode -ne 200 -or -not $response.Content.Contains('Local infrastructure is ready')) {
        throw 'Browser-facing infrastructure response failed.'
    }
    Write-Host "PASS HTTP -> PHP -> Yii2 -> MySQL: $url"
}

Push-Location $root
try {
    $versionText = & docker compose version --short
    if ($LASTEXITCODE -ne 0) { throw 'Docker Compose is unavailable.' }
    $match = [regex]::Match(($versionText -join ''), '\d+\.\d+\.\d+')
    if (-not $match.Success -or [version] $match.Value -lt [version] '2.24.0') {
        throw 'Docker Compose v2.24.0 or newer is required.'
    }
    switch ($Task) {
        'up' {
            Invoke-Compose up --build --wait --wait-timeout 240
            Test-Stack
        }
        'check' { Test-Stack }
        'persistence' {
            $token = [guid]::NewGuid().ToString('N')
            Invoke-Compose exec -T app php scripts/database.php mark $token
            Invoke-Compose up -d --force-recreate --wait --wait-timeout 240 db app
            Invoke-Compose exec -T app php scripts/database.php verify $token
            Test-Stack
        }
        'export' {
            $candidate = if ([IO.Path]::IsPathRooted($Path)) { $Path } else { Join-Path $root $Path }
            $target = [IO.Path]::GetFullPath($candidate)
            $rootPrefix = $root.TrimEnd([char[]] '\/') + [IO.Path]::DirectorySeparatorChar
            if (-not $target.StartsWith($rootPrefix, [StringComparison]::OrdinalIgnoreCase)) {
                throw 'Exports must stay inside this project.'
            }
            New-Item -ItemType Directory -Force -Path (Split-Path $target -Parent) | Out-Null
            Invoke-Compose exec -T db sh /opt/project-tools/export.sh
            Invoke-Compose cp db:/tmp/team-export.sql $target
            Write-Host "SQL exported to $target"
        }
        'restore' {
            if (-not $ConfirmRestore) { throw 'SQL import can overwrite data. Pass -ConfirmRestore to proceed.' }
            $source = (Resolve-Path -LiteralPath $Path).Path
            Invoke-Compose cp $source db:/tmp/team-import.sql
            Invoke-Compose exec -T db sh /opt/project-tools/restore.sh
            Write-Host 'SQL imported. Run check and inspect the application data.'
        }
        'down' { Invoke-Compose down }
        'logs' { Invoke-Compose logs --tail 100 app db }
    }
} finally {
    Pop-Location
}
