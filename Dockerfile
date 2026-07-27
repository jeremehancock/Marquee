# syntax=docker/dockerfile:1

# ---- Stage 1: install PHP dependencies ----
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
# The runtime image installs the PHP extensions (gd, pdo, curl, …) via apk; the
# build image only resolves and downloads packages, so skip its platform checks.
RUN composer install \
    --no-dev --no-scripts --no-interaction \
    --prefer-dist --optimize-autoloader \
    --ignore-platform-reqs

# ---- Stage 2: runtime ----
# The base image dictates the PHP version: :3.22 bundles PHP 8.4 (and php84-fpm),
# where :3.21 shipped 8.3. The php84-* extensions below must match the base.
FROM ghcr.io/linuxserver/baseimage-alpine-nginx:3.22

# PHP runtime extensions (and curl for the healthcheck)
RUN apk add --no-cache \
    curl \
    php84-curl \
    php84-gd \
    php84-pdo \
    php84-pdo_sqlite \
    php84-sqlite3 \
    php84-intl \
    php84-mbstring \
    php84-session \
    php84-openssl \
    php84-dom \
    php84-xml \
    php84-xmlwriter \
    php84-simplexml \
    php84-tokenizer \
    php84-fileinfo \
    php84-ctype \
    php84-phar

# php-fpm: pass environment variables through, and listen on TCP for nginx
RUN sed -E -i 's/^;?clear_env ?=.*$/clear_env = no/' /etc/php84/php-fpm.d/www.conf \
 && sed -E -i 's#^;?listen = .*#listen = 127.0.0.1:9000#' /etc/php84/php-fpm.d/www.conf

# PHP tuning (uploads / memory / long-running imports)
RUN printf 'upload_max_filesize = 20M\npost_max_size = 21M\nmemory_limit = 256M\nmax_execution_time = 600\n' \
    > /etc/php84/conf.d/zz-marquee.ini

# Application code + installed dependencies
COPY --chown=abc:abc . /app/www/
COPY --from=vendor --chown=abc:abc /app/vendor /app/www/vendor

# Container service definitions & nginx site config
COPY docker/root/ /

EXPOSE 80
VOLUME /config
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD curl -fsS http://127.0.0.1:80/health || exit 1
