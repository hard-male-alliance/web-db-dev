# Compatibility entry point; implementation lives in Python.
& python (Join-Path $PSScriptRoot 'php.py') @args
exit $LASTEXITCODE
