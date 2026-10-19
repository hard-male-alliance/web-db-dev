FROM composer:2.10.3@sha256:af98f42dfff7c68ba8d53c2164fd9fde1087b7d449514baa38c418b1f6bc4bac AS composer
FROM php:8.4.26-cli-bookworm@sha256:836ac6c672d1372a47c8fd61b625015bd07760f493fb2eaab2087636498a2b4b

# A fixed package index prevents apt dependencies drifting between rebuilds.
RUN rm -f /etc/apt/sources.list.d/debian.sources \
    && printf '%s\n' \
       'deb [check-valid-until=no] https://snapshot.debian.org/archive/debian/20261001T000000Z/ bookworm main' \
       'deb [check-valid-until=no] https://snapshot.debian.org/archive/debian-security/20261001T000000Z/ bookworm-security main' \
       > /etc/apt/sources.list \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
       libicu-dev libonig-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j2 gd intl mbstring pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY infra/docker/php.ini /usr/local/etc/php/conf.d/project.ini
WORKDIR /app
COPY composer.json composer.lock ./
RUN COMPOSER_ALLOW_SUPERUSER=1 composer install --no-interaction --prefer-dist --no-progress \
    && composer clear-cache

COPY toolchain.lock.json containers.lock.json ./
COPY infra/probe ./infra/probe
COPY tests ./tests
COPY scripts/database.php ./scripts/database.php
COPY scripts/domain_snapshot.php ./scripts/domain_snapshot.php
COPY scripts/install.php ./scripts/install.php
COPY common ./common
COPY frontend ./frontend
COPY backend ./backend
COPY data ./data
COPY infra/docker/start.sh ./infra/docker/start.sh
RUN useradd --uid 1000 --create-home app
USER app
EXPOSE 8080
# Development/demo only; replace this server when defining production requirements.
CMD ["sh", "/app/infra/docker/start.sh"]
