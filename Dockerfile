FROM php:8.2-cli-alpine

RUN apk add --no-cache icu-dev oniguruma-dev $PHPIZE_DEPS \
    && docker-php-ext-install -j$(nproc) intl opcache \
    && apk del $PHPIZE_DEPS

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 8081

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php", "-S", "0.0.0.0:8081", "-t", "public", "public/index.php"]
