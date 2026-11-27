FROM node:22-bookworm-slim AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js tsconfig.json ./
COPY resources ./resources
RUN npm run build

FROM php:8.3-apache-bookworm
ENV PORT=8080 APP_ENV=production APP_DEBUG=false LOG_CHANNEL=stderr COMPOSER_ALLOW_SUPERUSER=1
WORKDIR /app
RUN apt-get update && apt-get install -y --no-install-recommends git unzip libjpeg62-turbo-dev libpng-dev libwebp-dev libzip-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" gd pdo_mysql pcntl zip opcache \
    && a2enmod rewrite headers expires \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --prefer-dist --no-interaction --no-progress --no-autoloader
COPY . .
COPY --from=frontend /app/public/build ./public/build
COPY deploy/apache.conf /etc/apache2/sites-available/000-default.conf
RUN printf '%s\n' 'Listen ${PORT}' > /etc/apache2/ports.conf \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && cp deploy/php.ini "$PHP_INI_DIR/conf.d/portfolio.ini" \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && composer dump-autoload --no-dev --optimize --no-interaction \
    && chown -R www-data:www-data /app/storage /app/bootstrap/cache \
    && a2dismod mpm_event mpm_worker \
    && a2enmod mpm_prefork \
    && apache2ctl configtest
EXPOSE 8080
# Normalize the MPM modules again in the running container before Apache loads them.
CMD ["/bin/sh", "-ec", "\
    rm -f /etc/apache2/mods-enabled/mpm_event.* /etc/apache2/mods-enabled/mpm_worker.*; \
    a2enmod mpm_prefork; \
    apache2ctl configtest; \
    exec apache2-foreground"]
