[CmdletBinding()]
param(
    [ValidateSet('up', 'check', 'persistence', 'export', 'restore', 'down', 'logs')]
    [string] $Task = 'up',
    [string] $Path = '.cache/exports/install.sql',
    [switch] $ConfirmRestore
)
$arguments = @($Task, '--path', $Path)
if ($ConfirmRestore) { $arguments += '--confirm-restore' }
& python (Join-Path $PSScriptRoot 'dev.py') @arguments
exit $LASTEXITCODE
