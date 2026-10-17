#!/bin/sh
set -eu
export MYSQL_PWD="$MYSQL_PASSWORD"
mysqldump --user="$MYSQL_USER" --default-character-set=utf8mb4 \
    --single-transaction --no-tablespaces --set-gtid-purged=OFF \
    "$MYSQL_DATABASE" > /tmp/team-export.sql
