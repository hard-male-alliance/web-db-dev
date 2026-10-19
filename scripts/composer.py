"""Run the project Composer without activating a shell."""
from pathlib import Path
import subprocess
import sys

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from infra.phpenv import composer

if __name__ == "__main__":
    try:
        composer(sys.argv[1:])
    except subprocess.CalledProcessError as error:
        sys.exit(error.returncode)
    except RuntimeError as error:
        sys.exit(str(error))
