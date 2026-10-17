#!/bin/sh
set -eu
export MYSQL_PWD="$MYSQL_PASSWORD"
mysql --user="$MYSQL_USER" --default-character-set=utf8mb4 \
    "$MYSQL_DATABASE" < /tmp/team-import.sql
