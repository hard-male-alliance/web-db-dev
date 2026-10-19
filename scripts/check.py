"""Check the native runtime and a real loopback HTTP -> Yii2 request."""

from pathlib import Path
import socket
import subprocess
import sys
import time
import urllib.error
import urllib.request

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from infra.phpenv import ROOT, composer, environment, php, run


def check():
    composer(["validate", "--strict", "--no-check-all"])
    composer(["check-platform-reqs"])
    composer(["smoke"])
    with socket.socket() as listener:
        listener.bind(("127.0.0.1", 0))
        port = listener.getsockname()[1]
    logs = ROOT / ".cache/check"
    logs.mkdir(parents=True, exist_ok=True)
    with (logs / "http.log").open("wb") as log:
        server = subprocess.Popen([str(php()), "-S", f"127.0.0.1:{port}", "-t", "tests"],
                                  cwd=ROOT, env=environment(), stdout=log, stderr=log)
        try:
            url = f"http://127.0.0.1:{port}/http.php?name=%3Cscript%3E"
            for _ in range(50):
                if server.poll() is not None:
                    raise RuntimeError(f"PHP HTTP server exited; see {logs / 'http.log'}")
                try:
                    with urllib.request.urlopen(url, timeout=2) as response:
                        body = response.read().decode("utf-8")
                    break
                except (urllib.error.URLError, TimeoutError):
                    time.sleep(0.1)
            else:
                raise RuntimeError("PHP HTTP server did not become ready.")
            version = run([php(), "-r", "echo PHP_VERSION;"], env=environment(), capture_output=True, text=True).stdout
            if f"PHP {version} / Yii2 2.0.55" not in body:
                raise RuntimeError("Unexpected runtime/framework in HTTP response.")
            if "&lt;script&gt;" not in body or "<script>" in body:
                raise RuntimeError("View escaping failed.")
            print("PASS loopback HTTP -> PHP -> Yii2 view -> escaped HTML.")
        finally:
            server.terminate()
            try:
                server.wait(timeout=5)
            except subprocess.TimeoutExpired:
                server.kill()
                server.wait()


if __name__ == "__main__":
    try:
        check()
    except (OSError, RuntimeError, subprocess.CalledProcessError) as error:
        sys.exit(str(error))
