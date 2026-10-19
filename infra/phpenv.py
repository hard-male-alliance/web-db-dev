"""Download, verify and provision a project-local PHP runtime."""

import argparse
from contextlib import contextmanager
import hashlib
import json
import os
from pathlib import Path
import platform
import shutil
import stat
import subprocess
import tarfile
import tempfile
import urllib.parse
import urllib.request
import zipfile

ROOT = Path(__file__).resolve().parents[1]
ENV = ROOT / ".phpenv"
REQUIRED = ("curl", "fileinfo", "gd", "intl", "mbstring", "openssl", "pdo_mysql",
            "zip")


def run(args, *, cwd=ROOT, **kwargs):
    return subprocess.run([str(arg) for arg in args], cwd=cwd, check=True, **kwargs)


def platform_key():
    system = platform.system().lower()
    machine = platform.machine().lower()
    arch = {"amd64": "x64", "x86_64": "x64", "arm64": "arm64", "aarch64": "arm64"}.get(machine)
    if system not in ("windows", "linux", "darwin") or arch is None:
        raise RuntimeError(f"Unsupported platform: {system}/{machine}")
    if system == "windows" and arch != "x64":
        raise RuntimeError("Windows requires an x64 Python interpreter and x64 VC++ runtime.")
    return f"{system}-{arch}"


def runtime_asset(lock):
    key = platform_key()
    return lock["php"] if key == "windows-x64" else lock["php"]["platforms"][key]


def download(asset, cache):
    """Only complete, hash-verified files are published to the download cache."""
    if urllib.parse.urlparse(asset["url"]).scheme != "https":
        raise ValueError("Toolchain download URLs must use HTTPS.")
    cache.mkdir(parents=True, exist_ok=True)
    target = cache / asset["sha256"]
    if target.exists():
        if hashlib.sha256(target.read_bytes()).hexdigest() != asset["sha256"]:
            raise RuntimeError(f"Corrupt cached download: {target}; remove it and retry.")
        return target
    handle, name = tempfile.mkstemp(dir=cache, suffix=".part")
    try:
        with os.fdopen(handle, "wb") as output, urllib.request.urlopen(asset["url"], timeout=60) as response:
            if urllib.parse.urlparse(response.url).scheme != "https":
                raise RuntimeError("Download redirected away from HTTPS.")
            shutil.copyfileobj(response, output)
        partial = Path(name)
        if hashlib.sha256(partial.read_bytes()).hexdigest() != asset["sha256"]:
            raise RuntimeError(f"Checksum mismatch: {asset['url']}")
        partial.replace(target)
    finally:
        Path(name).unlink(missing_ok=True)
    return target


def extract(archive, destination, kind):
    """Reject traversal, links and device files before extracting anything."""
    destination = destination.resolve()
    if kind == "zip":
        with zipfile.ZipFile(archive) as bundle:
            for member in bundle.infolist():
                safe_member(destination, member.filename)
                if stat.S_ISLNK(member.external_attr >> 16):
                    raise ValueError("Archive links are not permitted.")
            bundle.extractall(destination)
    else:
        with tarfile.open(archive) as bundle:
            for member in bundle.getmembers():
                safe_member(destination, member.name)
                if not (member.isfile() or member.isdir()):
                    raise ValueError("Archive special files are not permitted.")
            bundle.extractall(destination, filter="data")


def safe_member(destination, name):
    # Backslashes and drive prefixes must also be rejected on Unix hosts.
    normalized = name.replace("\\", "/")
    if ":" in normalized or not (destination / normalized).resolve().is_relative_to(destination):
        raise ValueError(f"Unsafe archive member: {name}")


def environment():
    env = os.environ.copy()
    env.update(PHPENV=str(ENV), PHPRC=str(ENV / "php.ini"),
               PHP_INI_SCAN_DIR=str(ENV / "conf.d"),
               COMPOSER_HOME=str(ROOT / ".cache/composer"),
               COMPOSER_CACHE_DIR=str(ROOT / ".cache/composer-cache"))
    env["PATH"] = str(ENV / "bin") + os.pathsep + env.get("PATH", "")
    return env


def php():
    path = ENV / "bin" / ("php.exe" if os.name == "nt" else "php")
    if not path.is_file() or not (ENV / "ready.json").is_file():
        raise RuntimeError("PHP environment is not initialized. Run python setup.py first.")
    return path


def composer(args):
    return run([php(), ENV / "composer.phar", *args], env=environment())


def configure(stage, windows):
    ca = (ENV / "cacert.pem").as_posix()
    lines = ["[PHP]", "date.timezone=Asia/Shanghai", "default_charset=UTF-8",
             "error_reporting=E_ALL", "display_errors=On", "log_errors=On",
             f'curl.cainfo="{ca}"', f'openssl.cafile="{ca}"']
    if windows:
        lines += [f'extension_dir="{(ENV / "bin/ext").as_posix()}"']
        lines += [f"extension={extension}" for extension in (*REQUIRED, "pdo_sqlite", "sqlite3")]
    (stage / "php.ini").write_text("\n".join(lines) + "\n", encoding="utf-8")
    (stage / "conf.d").mkdir()
    # Composer's @php and child processes use this environment's PHP via PATH.
    shell = '#!/bin/sh\nbase=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)\nexec "$base/bin/php" -c "$base/php.ini" "$base/composer.phar" "$@"\n'
    launcher = stage / "bin/composer"
    launcher.write_text(shell, encoding="utf-8", newline="\n")
    launcher.chmod(0o755)
    (stage / "bin/composer.bat").write_text('@echo off\r\n"%~dp0php.exe" -c "%~dp0..\\php.ini" "%~dp0..\\composer.phar" %*\r\n', encoding="utf-8", newline="")


def validate(version):
    code = "echo json_encode([PHP_VERSION, get_loaded_extensions()]);"
    result = run([ENV / "bin" / ("php.exe" if os.name == "nt" else "php"), "-r", code],
                 env=environment(), capture_output=True, text=True)
    actual, extensions = json.loads(result.stdout)
    if actual != version:
        raise RuntimeError(f"PHP runtime mismatch: expected {version}, got {actual}")
    missing = set(REQUIRED) - {extension.lower() for extension in extensions}
    if missing:
        raise RuntimeError(f"PHP {actual} is missing required extensions: {missing}")
    run([ENV / "bin" / ("php.exe" if os.name == "nt" else "php"), ENV / "composer.phar", "--version"], env=environment())


@contextmanager
def setup_lock():
    path = ROOT / ".cache/setup.lock"
    path.parent.mkdir(exist_ok=True)
    try:
        path.mkdir()
    except FileExistsError:
        raise RuntimeError(f"Setup already running. If interrupted, remove {path} and retry.") from None
    try:
        yield
    finally:
        path.rmdir()


def provision():
    lock = json.loads((ROOT / "toolchain.lock.json").read_text(encoding="utf-8"))
    key = platform_key()
    fingerprint = {"schema": 1, "root": str(ROOT), "platform": key, "lock": lock}
    marker = ENV / "ready.json"
    if marker.is_file() and json.loads(marker.read_text(encoding="utf-8")) == fingerprint:
        validate(runtime_asset(lock)["version"])
        return
    windows = key == "windows-x64"
    asset = runtime_asset(lock)
    cache = ROOT / ".cache/downloads"
    runtime = download(asset, cache)
    composer_file = download(lock["composer"], cache)
    ca = download(lock["caBundle"], cache)
    temporary = ROOT / ".temp"
    temporary.mkdir(exist_ok=True)
    with tempfile.TemporaryDirectory(dir=temporary, prefix="phpenv-") as directory:
        stage = Path(directory) / "environment"
        (stage / "bin").mkdir(parents=True)
        if windows:
            extract(runtime, stage / "bin", "zip")
        else:
            extract(runtime, stage / "bin", "tar")
            (stage / "bin/php").chmod(0o755)
        shutil.copy2(composer_file, stage / "composer.phar")
        shutil.copy2(ca, stage / "cacert.pem")
        configure(stage, windows)
        backup = Path(directory) / "previous"
        if ENV.exists():
            ENV.rename(backup)
        try:
            stage.rename(ENV)
            validate(runtime_asset(lock)["version"])
            marker.write_text(json.dumps(fingerprint, indent=2) + "\n", encoding="utf-8")
        except BaseException:
            if ENV.exists():
                shutil.rmtree(ENV)
            if backup.exists():
                backup.rename(ENV)
            raise


def main(argv=None):
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--skip-composer", action="store_true", help="Provision runtime only; do not install project dependencies")
    args = parser.parse_args(argv)
    try:
        with setup_lock():
            provision()
            if not args.skip_composer:
                composer(["install", "--no-interaction", "--prefer-dist"])
                composer(["check-platform-reqs"])
        print("Ready. Activate with: source activate.sh (Bash/Zsh) or . ./activate.ps1 (PowerShell)")
        return 0
    except (OSError, ValueError, RuntimeError, subprocess.CalledProcessError) as error:
        print(f"Setup failed: {error}")
        return 1
