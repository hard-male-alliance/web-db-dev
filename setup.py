"""Initialize the project toolchain, not a setuptools package."""

from infra.phpenv import main


if __name__ == "__main__":
    raise SystemExit(main())
