# Source this file from Bash or Zsh: source /path/to/project/activate.sh
if [ -n "${BASH_VERSION:-}" ]; then
    if [ "${BASH_SOURCE[0]}" = "$0" ]; then
        echo 'Use: source activate.sh' >&2
        exit 1
    fi
    _phpenv_script=${BASH_SOURCE[0]}
elif [ -n "${ZSH_VERSION:-}" ]; then
    _phpenv_script=${(%):-%x}
else
    echo 'activate.sh requires Bash or Zsh.' >&2
    return 1
fi
_phpenv_root=$(CDPATH= cd -- "$(dirname -- "$_phpenv_script")" && pwd)
if [ ! -f "$_phpenv_root/.phpenv/ready.json" ]; then
    echo 'Run python setup.py first.' >&2
    unset _phpenv_script _phpenv_root
    return 1
fi
if typeset -f deactivate >/dev/null 2>&1; then
    deactivate
fi

# Preserve set/unset state as well as values, including inherited PHP configuration.
_PHPENV_OLD_PATH=$PATH
_PHPENV_OLD_PHPRC=${PHPRC-}; _PHPENV_SET_PHPRC=${PHPRC+x}
_PHPENV_OLD_SCAN=${PHP_INI_SCAN_DIR-}; _PHPENV_SET_SCAN=${PHP_INI_SCAN_DIR+x}
_PHPENV_OLD_HOME=${COMPOSER_HOME-}; _PHPENV_SET_HOME=${COMPOSER_HOME+x}
_PHPENV_OLD_CACHE=${COMPOSER_CACHE_DIR-}; _PHPENV_SET_CACHE=${COMPOSER_CACHE_DIR+x}
_PHPENV_OLD_ENV=${PHPENV-}; _PHPENV_SET_ENV=${PHPENV+x}
deactivate() {
    export PATH=$_PHPENV_OLD_PATH
    if [ "$_PHPENV_SET_PHPRC" = x ]; then export PHPRC=$_PHPENV_OLD_PHPRC; else unset PHPRC; fi
    if [ "$_PHPENV_SET_SCAN" = x ]; then export PHP_INI_SCAN_DIR=$_PHPENV_OLD_SCAN; else unset PHP_INI_SCAN_DIR; fi
    if [ "$_PHPENV_SET_HOME" = x ]; then export COMPOSER_HOME=$_PHPENV_OLD_HOME; else unset COMPOSER_HOME; fi
    if [ "$_PHPENV_SET_CACHE" = x ]; then export COMPOSER_CACHE_DIR=$_PHPENV_OLD_CACHE; else unset COMPOSER_CACHE_DIR; fi
    if [ "$_PHPENV_SET_ENV" = x ]; then export PHPENV=$_PHPENV_OLD_ENV; else unset PHPENV; fi
    unset _PHPENV_OLD_PATH _PHPENV_OLD_PHPRC _PHPENV_SET_PHPRC _PHPENV_OLD_SCAN _PHPENV_SET_SCAN
    unset _PHPENV_OLD_HOME _PHPENV_SET_HOME _PHPENV_OLD_CACHE _PHPENV_SET_CACHE _PHPENV_OLD_ENV _PHPENV_SET_ENV
    hash -r 2>/dev/null || true
    unset -f deactivate
}
export PHPENV="$_phpenv_root/.phpenv"
export PATH="$PHPENV/bin:$PATH"
export PHPRC="$PHPENV/php.ini"
export PHP_INI_SCAN_DIR="$PHPENV/conf.d"
export COMPOSER_HOME="$_phpenv_root/.cache/composer"
export COMPOSER_CACHE_DIR="$_phpenv_root/.cache/composer-cache"
hash -r 2>/dev/null || true
unset _phpenv_script _phpenv_root
