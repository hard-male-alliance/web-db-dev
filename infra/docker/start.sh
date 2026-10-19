#!/bin/sh
# Bootstrap missing schema/seed rows without replacing retained application data.
set -eu
cd /app
php scripts/install.php
exec php -S 0.0.0.0:8080 -t frontend/web frontend/web/router.php
