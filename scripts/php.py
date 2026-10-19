"""Run the project PHP without activating a shell."""
from pathlib import Path
import subprocess
import sys

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from infra.phpenv import environment, php, run

if __name__ == "__main__":
    try:
        run([php(), *sys.argv[1:]], cwd=Path.cwd(), env=environment())
    except subprocess.CalledProcessError as error:
        sys.exit(error.returncode)
    except RuntimeError as error:
        sys.exit(str(error))
