"""Cross-platform Docker lifecycle, checks, persistence and SQL transfer."""

import argparse
import json
from pathlib import Path
import re
import subprocess
import sys
import urllib.request
import uuid

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from infra.phpenv import ROOT, run


def compose(*args, capture=False):
    result = run(["docker", "compose", *args], capture_output=capture, text=capture)
    return result.stdout.strip() if capture else None


def check():
    compose("exec", "-T", "app", "composer", "validate", "--strict", "--no-check-all")
    compose("exec", "-T", "app", "composer", "check-platform-reqs")
    for test in ("toolchain", "database"):
        compose("exec", "-T", "app", "php", f"tests/{test}.php")
    binding = compose("port", "app", "8080", capture=True)
    with urllib.request.urlopen(f"http://{binding}/health", timeout=15) as response:
        if response.status != 200 or json.load(response).get("status") != "ok":
            raise RuntimeError("Application database readiness failed.")
    with urllib.request.urlopen(f"http://{binding}/", timeout=15) as response:
        body = response.read()
        if (response.status != 200 or "星河工坊".encode() not in body
                or not body.lstrip().lower().startswith(b"<!doctype html>")):
            raise RuntimeError("Browser-facing application response failed.")
    print(f"PASS HTTP -> PHP -> Yii2 -> MySQL team website: http://{binding}/")


def project_path(value):
    path = (ROOT / value).resolve()
    if path == ROOT or not path.is_relative_to(ROOT):
        raise ValueError("SQL paths must stay inside this project.")
    return path


def main(argv=None):
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("task", nargs="?", default="up",
                        choices=("up", "check", "persistence", "export", "restore", "down", "logs"))
    parser.add_argument("--path", default=".cache/exports/install.sql")
    parser.add_argument("--confirm-restore", action="store_true")
    args = parser.parse_args(argv)
    if args.task == "restore" and not args.confirm_restore:
        parser.error("SQL import can overwrite data; pass --confirm-restore after reviewing the dump.")
    try:
        version = compose("version", "--short", capture=True)
        match = re.search(r"(\d+)\.(\d+)\.(\d+)", version)
        if not match or tuple(map(int, match.groups())) < (2, 24, 0):
            raise RuntimeError("Docker Compose 2.24+ is required.")
        if args.task == "up":
            compose("up", "--build", "--wait", "--wait-timeout", "240")
            check()
        elif args.task == "check":
            check()
        elif args.task == "persistence":
            token = uuid.uuid4().hex
            before = compose("exec", "-T", "app", "php", "scripts/domain_snapshot.php", capture=True)
            compose("exec", "-T", "app", "php", "scripts/database.php", "mark", token)
            compose("up", "-d", "--force-recreate", "--wait", "--wait-timeout", "240", "db", "app")
            compose("exec", "-T", "app", "php", "scripts/database.php", "verify", token)
            after = compose("exec", "-T", "app", "php", "scripts/domain_snapshot.php", capture=True)
            if json.loads(before) != json.loads(after):
                raise RuntimeError("Application data changed during container replacement.")
            print("PASS all domain rows survived container replacement.")
            check()
        elif args.task == "export":
            target = project_path(args.path)
            target.parent.mkdir(parents=True, exist_ok=True)
            compose("exec", "-T", "db", "sh", "/opt/project-tools/export.sh")
            compose("cp", "db:/tmp/team-export.sql", str(target))
            print(f"SQL exported to {target}")
        elif args.task == "restore":
            source = project_path(args.path)
            if not source.is_file():
                raise ValueError(f"SQL dump not found: {source}")
            compose("cp", str(source), "db:/tmp/team-import.sql")
            compose("exec", "-T", "db", "sh", "/opt/project-tools/restore.sh")
        elif args.task == "down":
            compose("down")
        else:
            compose("logs", "--tail", "100", "app", "db")
        return 0
    except (OSError, ValueError, RuntimeError, subprocess.CalledProcessError) as error:
        print(f"Infrastructure command failed: {error}", file=sys.stderr)
        return 1


if __name__ == "__main__":
    sys.exit(main())
