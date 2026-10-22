# syntax=docker/dockerfile:1

# ---- Stage 1: install PHP dependencies ----
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
# The runtime image compiles the PHP extensions Marquee needs (gd, intl); the
# build image only resolves and downloads packages, so skip its platform checks.
RUN composer install \
    --no-dev --no-scripts --no-interaction \
    --prefer-dist --optimize-autoloader \
    --ignore-platform-reqs

# ---- Stage 2: runtime ----
# The official PHP image is rebuilt for every PHP patch release, so this moving
# tag picks up PHP security fixes on any rebuild. PHP is compiled from source
# there, not taken from Alpine's packages, which stop being updated a few months
# after each Alpine release (that freeze is how 2.15.0 shipped PHP 8.4.16 and
# missed the fix for CVE-2026-109618/109619). Only a new PHP version line (8.6)
# needs this tag edited; see docs/docker.md.
FROM php:8.5-fpm-alpine

# s6-overlay supervises nginx, PHP-FPM and cron and restarts any that die.
# Wait as long as startup takes (chown -R on a large /config can be slow), and
# stop the container if a startup step fails rather than serve half-configured.
ENV S6_CMD_WAIT_FOR_SERVICES_MAXTIME=0 \
    S6_BEHAVIOUR_IF_STAGE2_FAILS=2 \
    S6_VERBOSITY=1

# nginx and the process supervisor, plus what startup needs: tzdata for TZ,
# shadow for mapping abc to PUID/PGID, bash for the service scripts, and curl
# for the healthcheck. None of these names carries a PHP or Alpine version.
RUN apk add --no-cache \
    bash \
    curl \
    nginx \
    s6-overlay \
    shadow \
    tzdata

# The official image already has every extension Marquee uses except gd and
# intl. Compile those, keep only the libraries they link against, and drop the
# build packages in the same layer so they never reach the image.
RUN apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        freetype-dev \
        icu-dev \
        libavif-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        libwebp-dev \
 && docker-php-ext-configure gd --with-avif --with-freetype --with-jpeg --with-webp \
 && docker-php-ext-install -j"$(nproc)" gd intl \
 && runDeps="$(scanelf --needed --nobanner --format '%n#p' --recursive /usr/local/lib/php/extensions \
        | tr ',' '\n' | sort -u \
        | awk 'system("[ -e /usr/local/lib/" $1 " ]") == 0 { next } { print "so:" $1 }')" \
 && apk add --no-cache --virtual .marquee-phpext-rundeps $runDeps \
 && apk del --no-network .build-deps

# Production PHP defaults. Marquee's own limits and its FPM pool arrive with
# docker/root below; the official pool files are removed so ours is the only
# one (the official one listens on every interface, ours on 127.0.0.1 only).
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
 && rm -f /usr/local/etc/php-fpm.d/docker.conf \
          /usr/local/etc/php-fpm.d/www.conf \
          /usr/local/etc/php-fpm.d/www.conf.default \
          /usr/local/etc/php-fpm.d/zz-docker.conf \
          /etc/nginx/http.d/default.conf

# The user everything runs as. 911 is the documented PUID/PGID default; the
# init-marquee-user service remaps it to PUID/PGID on every start.
RUN addgroup -g 911 abc \
 && adduser -D -H -h /config -s /bin/false -u 911 -G abc abc \
 && mkdir -p /var/lib/nginx/tmp /run/nginx \
 && chown -R abc:abc /var/lib/nginx

# Application code + installed dependencies
COPY --chown=abc:abc . /app/www/
COPY --from=vendor --chown=abc:abc /app/vendor /app/www/vendor

# Service definitions, nginx and PHP configuration
COPY docker/root/ /

EXPOSE 80
VOLUME /config
ENTRYPOINT ["/init"]
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD curl -fsS http://127.0.0.1:80/health || exit 1
