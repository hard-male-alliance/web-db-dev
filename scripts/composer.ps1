# Compatibility entry point; implementation lives in Python.
& python (Join-Path $PSScriptRoot 'composer.py') @args
exit $LASTEXITCODE
