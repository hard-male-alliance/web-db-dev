# Compatibility entry point; implementation lives in Python.
& python (Join-Path $PSScriptRoot '../setup.py') @args
exit $LASTEXITCODE
