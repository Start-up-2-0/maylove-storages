FROM php:8.2-cli-alpine

RUN apk add --no-cache \
        icu-dev \
        oniguruma-dev \
        freetype-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        ffmpeg \
        python3 \
        py3-pip \
        curl \
        unzip \
        ca-certificates \
        $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" intl opcache gd pdo pdo_mysql \
    && curl -fsSL https://deno.land/install.sh | DENO_INSTALL=/usr/local sh \
    && pip3 install --break-system-packages --no-cache-dir "yt-dlp[default]" \
    && yt-dlp --version \
    && deno --version \
    && apk del $PHPIZE_DEPS

COPY docker/php-railway.ini /usr/local/etc/php/conf.d/99-railway.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

ENV COMPOSER_ALLOW_SUPERUSER=1

COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist

COPY . .

RUN mkdir -p var/cache var/log \
    && composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts \
    && composer dump-autoload --optimize --classmap-authoritative --no-dev

COPY docker/entrypoint.sh docker/start-web.sh docker/pre-deploy.sh docker/migrate.sh docker/migrate-db.sh docker/storage-bootstrap.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/entrypoint.sh /usr/local/bin/start-web.sh /usr/local/bin/pre-deploy.sh /usr/local/bin/migrate.sh /usr/local/bin/migrate-db.sh /usr/local/bin/storage-bootstrap.sh

# Railway injeta PORT em runtime; não fixar porta diferente por serviço.
EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/local/bin/start-web.sh"]
