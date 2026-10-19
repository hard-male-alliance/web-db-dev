"""Standard-library unit tests and real activation/CLI journeys."""

from contextlib import contextmanager
import hashlib
import io
import json
import os
from pathlib import Path
import shutil
import subprocess
import sys
import tarfile
import tempfile
import unittest
from unittest.mock import patch
import zipfile

from infra import phpenv
from scripts.dev import project_path


@contextmanager
def temporary_project():
    root = phpenv.ROOT / ".temp"
    root.mkdir(exist_ok=True)
    with tempfile.TemporaryDirectory(dir=root, prefix="infra-test-") as directory:
        yield Path(directory)


class InfrastructureTests(unittest.TestCase):
    def test_platforms(self):
        for system, machine, expected in [("Windows", "AMD64", "windows-x64"),
                                          ("Linux", "x86_64", "linux-x64"),
                                          ("Linux", "aarch64", "linux-arm64"),
                                          ("Darwin", "arm64", "darwin-arm64")]:
            with self.subTest(system=system, machine=machine), patch("platform.system", return_value=system), patch("platform.machine", return_value=machine):
                self.assertEqual(phpenv.platform_key(), expected)
        with patch("platform.system", return_value="Windows"), patch("platform.machine", return_value="ARM64"):
            with self.assertRaises(RuntimeError):
                phpenv.platform_key()

    def test_archive_traversal_property(self):
        with temporary_project() as root:
            dest = root / "destination"
            dest.mkdir()
            for separator in ("/", "\\"):
                for prefix in ("..", "a/../..", "C:", "/absolute"):
                    name = prefix.replace("/", separator) + separator + "escape"
                    for kind in ("zip", "tar"):
                        with self.subTest(name=name, kind=kind):
                            archive = root / "archive"
                            if kind == "zip":
                                with zipfile.ZipFile(archive, "w") as bundle:
                                    bundle.writestr(name, "bad")
                            else:
                                with tarfile.open(archive, "w") as bundle:
                                    entry = tarfile.TarInfo(name)
                                    entry.size = 3
                                    bundle.addfile(entry, io.BytesIO(b"bad"))
                            with self.assertRaises(ValueError):
                                phpenv.extract(archive, dest, kind)
                            self.assertEqual(list(dest.iterdir()), [])

    def test_valid_archives_and_links(self):
        with temporary_project() as root:
            archive = root / "runtime.zip"
            with zipfile.ZipFile(archive, "w") as bundle:
                bundle.writestr("bin/php", "runtime")
            phpenv.extract(archive, root / "output", "zip")
            self.assertEqual((root / "output/bin/php").read_text(), "runtime")
            with tarfile.open(archive, "w") as bundle:
                entry = tarfile.TarInfo("link")
                entry.type = tarfile.SYMTYPE
                entry.linkname = "outside"
                bundle.addfile(entry)
            with self.assertRaises(ValueError):
                phpenv.extract(archive, root / "output", "tar")

    def test_download_cache_and_partial_cleanup(self):
        data = b"runtime"
        asset = {"url": "https://example.invalid/php", "sha256": hashlib.sha256(data).hexdigest()}
        with temporary_project() as root:
            response = io.BytesIO(data)
            response.url = asset["url"]
            with patch("urllib.request.urlopen", return_value=response):
                target = phpenv.download(asset, root)
            with patch("urllib.request.urlopen", side_effect=AssertionError("Unexpected network")):
                self.assertEqual(phpenv.download(asset, root), target)
            target.write_bytes(b"corrupt")
            with self.assertRaises(RuntimeError):
                phpenv.download(asset, root)
            target.unlink()
            response = io.BytesIO(b"wrong")
            response.url = asset["url"]
            with patch("urllib.request.urlopen", return_value=response), self.assertRaises(RuntimeError):
                phpenv.download(asset, root)
            self.assertEqual(list(root.iterdir()), [])

    def test_setup_lock(self):
        with temporary_project() as root, patch.object(phpenv, "ROOT", root):
            with phpenv.setup_lock():
                with self.assertRaises(RuntimeError):
                    with phpenv.setup_lock():
                        self.fail("Concurrent setup permitted")
            self.assertFalse((root / ".cache/setup.lock").exists())

    def test_failed_replacement_restores_previous_runtime(self):
        with temporary_project() as root:
            previous = root / ".phpenv"
            previous.mkdir()
            (previous / "keep").write_text("previous working environment")
            archive = root / "php.zip"
            with zipfile.ZipFile(archive, "w") as bundle:
                bundle.writestr("php.exe", "replacement")
            asset = {"version": "8.4.26", "url": "https://example.invalid/php", "sha256": "unused"}
            (root / "toolchain.lock.json").write_text(json.dumps({"php": asset, "composer": asset, "caBundle": asset}))
            with patch.object(phpenv, "ROOT", root), patch.object(phpenv, "ENV", previous), patch.object(phpenv, "platform_key", return_value="windows-x64"), patch.object(phpenv, "download", return_value=archive), patch.object(phpenv, "validate", side_effect=RuntimeError("Runtime cannot start")):
                with self.assertRaisesRegex(RuntimeError, "Runtime cannot start"):
                    phpenv.provision()
            self.assertEqual((previous / "keep").read_text(), "previous working environment")
            self.assertFalse((previous / "ready.json").exists())
            self.assertEqual(list((root / ".temp").iterdir()), [])

    def test_export_boundary(self):
        self.assertTrue(project_path(".cache/exports/test.sql").is_relative_to(phpenv.ROOT))
        for path in ("..", "../outside.sql", str(phpenv.ROOT.parent / "outside.sql")):
            with self.subTest(path=path), self.assertRaises(ValueError):
                project_path(path)


@unittest.skipUnless((phpenv.ENV / "ready.json").is_file(), "Run setup.py for real runtime journeys")
class RuntimeJourneys(unittest.TestCase):
    def test_runtime_reuse_needs_no_network(self):
        with patch("urllib.request.urlopen", side_effect=AssertionError("Unexpected network")):
            phpenv.provision()

    def test_php_and_composer_outside_project(self):
        with temporary_project() as other:
            result = subprocess.run([str(phpenv.php()), "-r", "echo getenv('PHPENV');"], cwd=other,
                                    env=phpenv.environment(), text=True, capture_output=True, check=True)
            self.assertEqual(result.stdout, str(phpenv.ENV))
            result = subprocess.run([sys.executable, str(phpenv.ROOT / "scripts/php.py"),
                                     "-r", "echo getcwd();"], cwd=other, text=True,
                                    capture_output=True, check=True)
            self.assertEqual(Path(result.stdout).resolve(), other.resolve())
            subprocess.run([str(phpenv.php()), str(phpenv.ENV / "composer.phar"), "--version"],
                           cwd=other, env=phpenv.environment(), check=True)
            subprocess.run([sys.executable, str(phpenv.ROOT / "scripts/composer.py"), "--version"],
                           cwd=other, check=True)

    def test_powershell_activation_roundtrip(self):
        powershell = shutil.which("pwsh") or shutil.which("powershell")
        if not powershell:
            self.skipTest("PowerShell not installed")
        script = r'''
$ErrorActionPreference = 'Stop'
$oldPath = $env:PATH
$env:PHPRC = 'original configuration'
Remove-Item Env:\PHP_INI_SCAN_DIR -ErrorAction SilentlyContinue
. ./activate.ps1
. ./activate.ps1
if ((Get-Command php).Source -notlike "*$([IO.Path]::DirectorySeparatorChar).phpenv*" ) { throw 'Wrong PHP' }
php -r 'echo PHP_VERSION;'
if ($LASTEXITCODE) { throw 'PHP failed' }
composer --version
if ($LASTEXITCODE) { throw 'Composer failed' }
deactivate
if ($env:PATH -cne $oldPath -or $env:PHPRC -cne 'original configuration') { throw 'Environment not restored' }
if (Test-Path Env:\PHP_INI_SCAN_DIR) { throw 'Unset state not restored' }
'''
        subprocess.run([powershell, "-NoProfile", "-NonInteractive", "-Command", script], cwd=phpenv.ROOT, check=True)

    @unittest.skipIf(os.name == "nt", "Unix Bash activation tested on Linux/macOS")
    def test_bash_activation_roundtrip(self):
        script = '''set -eu
old_path=$PATH
export PHPRC='original configuration'
unset PHP_INI_SCAN_DIR
source ./activate.sh
source ./activate.sh
test "$(command -v php)" = "$PHPENV/bin/php"
php -r 'echo PHP_VERSION;'
composer --version
deactivate
test "$PATH" = "$old_path"
test "$PHPRC" = 'original configuration'
test "${PHP_INI_SCAN_DIR+x}" != x
'''
        for shell in ("bash", "zsh"):
            if shutil.which(shell):
                with self.subTest(shell=shell):
                    subprocess.run([shell, "-c", script], cwd=phpenv.ROOT, check=True)


if __name__ == "__main__":
    unittest.main()
