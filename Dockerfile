# syntax=docker/dockerfile:1

# ---- Stage 1: install PHP dependencies ----
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev --no-scripts --no-interaction \
    --prefer-dist --optimize-autoloader

# ---- Stage 2: runtime ----
FROM ghcr.io/linuxserver/baseimage-alpine-nginx:3.21

# PHP runtime extensions
RUN apk add --no-cache \
    php83-curl \
    php83-gd \
    php83-pdo \
    php83-pdo_sqlite \
    php83-sqlite3 \
    php83-intl \
    php83-mbstring \
    php83-session \
    php83-openssl \
    php83-dom \
    php83-xml \
    php83-xmlwriter \
    php83-simplexml \
    php83-tokenizer \
    php83-fileinfo \
    php83-ctype \
    php83-phar

# php-fpm: pass environment variables through, and listen on TCP for nginx
RUN sed -E -i 's/^;?clear_env ?=.*$/clear_env = no/' /etc/php83/php-fpm.d/www.conf \
 && sed -E -i 's#^;?listen = .*#listen = 127.0.0.1:9000#' /etc/php83/php-fpm.d/www.conf

# PHP tuning (uploads / memory)
RUN printf 'upload_max_filesize = 20M\npost_max_size = 21M\nmemory_limit = 256M\n' \
    > /etc/php83/conf.d/zz-marquee.ini

# Application code + installed dependencies
COPY --chown=abc:abc . /app/www/
COPY --from=vendor --chown=abc:abc /app/vendor /app/www/vendor

# Container service definitions & nginx site config
COPY docker/root/ /

EXPOSE 80
VOLUME /config
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD curl -fsS http://127.0.0.1:80/health || exit 1
