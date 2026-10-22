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
# The base image dictates the PHP version: :3.24 bundles PHP 8.5 (and php85-fpm),
# where :3.22 shipped 8.4. The php85-* extensions below must match the base.
# Alpine only updates PHP on its newest release, so an older tag freezes PHP
# (:3.22 sat on 8.4.16 and missed the fix for CVE-2026-109618/109619).
FROM ghcr.io/linuxserver/baseimage-alpine-nginx:3.24

# PHP runtime extensions (and curl for the healthcheck)
RUN apk add --no-cache \
    curl \
    php85-curl \
    php85-gd \
    php85-pdo \
    php85-pdo_sqlite \
    php85-sqlite3 \
    php85-intl \
    php85-mbstring \
    php85-session \
    php85-openssl \
    php85-dom \
    php85-xml \
    php85-xmlwriter \
    php85-simplexml \
    php85-tokenizer \
    php85-fileinfo \
    php85-ctype \
    php85-phar

# php-fpm: pass environment variables through, and listen on TCP for nginx
RUN sed -E -i 's/^;?clear_env ?=.*$/clear_env = no/' /etc/php85/php-fpm.d/www.conf \
 && sed -E -i 's#^;?listen = .*#listen = 127.0.0.1:9000#' /etc/php85/php-fpm.d/www.conf

# PHP tuning (uploads / memory / long-running imports)
RUN printf 'upload_max_filesize = 20M\npost_max_size = 21M\nmemory_limit = 256M\nmax_execution_time = 600\n' \
    > /etc/php85/conf.d/zz-marquee.ini

# Application code + installed dependencies
COPY --chown=abc:abc . /app/www/
COPY --from=vendor --chown=abc:abc /app/vendor /app/www/vendor

# Container service definitions & nginx site config
COPY docker/root/ /

EXPOSE 80
VOLUME /config
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD curl -fsS http://127.0.0.1:80/health || exit 1
