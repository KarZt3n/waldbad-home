FROM composer:2.8 AS vendor

WORKDIR /app

COPY composer.json composer.lock symfony.lock ./
RUN composer install \
    --no-dev \
    --no-autoloader \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --prefer-dist

COPY . ./
RUN composer dump-autoload --no-dev --classmap-authoritative --no-interaction

FROM php:8.4-apache-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends gosu libicu-dev \
    && docker-php-ext-install -j"$(nproc)" intl pdo_mysql \
    && a2enmod expires headers rewrite \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY --from=vendor /app ./
COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /usr/local/bin/waldbad-entrypoint

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    APP_RUNTIME_OPTIONS='{"disable_dotenv":true}'

RUN chmod 0755 /usr/local/bin/waldbad-entrypoint \
    && mkdir -p var/cache var/log public/assets public/uploads/media \
    && chown -R www-data:www-data var public/assets public/uploads/media \
    && APP_SECRET=build-placeholder \
       DATABASE_URL='mysql://build:build@database:3306/build?serverVersion=11.4.0-MariaDB&charset=utf8mb4' \
       DEFAULT_URI='http://localhost' \
       MAILER_DSN='null://null' \
       MESSENGER_TRANSPORT_DSN='sync://' \
       php bin/console importmap:install --no-interaction \
    && APP_SECRET=build-placeholder \
       DATABASE_URL='mysql://build:build@database:3306/build?serverVersion=11.4.0-MariaDB&charset=utf8mb4' \
       DEFAULT_URI='http://localhost' \
       MAILER_DSN='null://null' \
       MESSENGER_TRANSPORT_DSN='sync://' \
       php bin/console asset-map:compile --no-interaction

ENTRYPOINT ["waldbad-entrypoint"]
CMD ["apache2-foreground"]
