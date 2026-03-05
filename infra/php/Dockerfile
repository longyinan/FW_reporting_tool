FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    bash \
    git \
    curl \
    icu-dev \
    libzip-dev \
    oniguruma-dev \
    unzip \
    zip \
    autoconf \
    g++ \
    make \
    linux-headers \
    postgresql-dev

RUN docker-php-ext-install pdo pdo_mysql pdo_pgsql pgsql intl mbstring zip

RUN pecl install redis xdebug \
    && docker-php-ext-enable redis xdebug

COPY infra/php/conf.d/xdebug.ini /usr/local/etc/php/conf.d/zz-xdebug.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

CMD ["php-fpm"]
