# Compatibility entry point; implementation lives in Python.
& python (Join-Path $PSScriptRoot 'check.py') @args
exit $LASTEXITCODE
