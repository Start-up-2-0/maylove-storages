FROM php:8.2-cli-alpine

RUN apk add --no-cache \
        icu-dev \
        oniguruma-dev \
        freetype-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" intl opcache gd pdo pdo_mysql \
    && apk del $PHPIZE_DEPS

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

ENV COMPOSER_ALLOW_SUPERUSER=1

COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist

COPY . .

RUN mkdir -p var/cache var/log \
    && composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts \
    && composer dump-autoload --optimize --classmap-authoritative --no-dev

COPY docker/entrypoint.sh docker/start-web.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/entrypoint.sh /usr/local/bin/start-web.sh

ENV PORT=8081
EXPOSE 8081

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/local/bin/start-web.sh"]
