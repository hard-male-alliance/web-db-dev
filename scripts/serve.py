"""Initialize the configured MySQL database and serve the local team website."""

import argparse
from pathlib import Path
import subprocess
import sys

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from infra.phpenv import ROOT, environment, php, run


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--port", type=int, default=8080)
    args = parser.parse_args()
    if not 1 <= args.port <= 65535:
        parser.error("Port must be between 1 and 65535.")
    try:
        env = environment()
        run([php(), "scripts/install.php"], env=env)
        print(f"Team website: http://127.0.0.1:{args.port}/", flush=True)
        run([php(), "-S", f"127.0.0.1:{args.port}", "-t", "frontend/web",
             "frontend/web/router.php"], env=env)
        return 0
    except KeyboardInterrupt:
        return 0
    except (OSError, RuntimeError, subprocess.CalledProcessError) as error:
        print(f"Website startup failed: {error}", file=sys.stderr)
        return 1


if __name__ == "__main__":
    sys.exit(main())
