FROM node:22-alpine AS backend-assets
WORKDIR /workspace/backend
COPY backend/package.json backend/package-lock.json* ./
RUN if [ -f package-lock.json ]; then npm ci; else npm install; fi
COPY backend/ ./
RUN npm run build

FROM composer:2 AS backend-vendor
WORKDIR /workspace/backend
COPY backend/composer.json backend/composer.lock* ./
RUN mkdir -p vendor && if [ -f composer.json ]; then composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader; fi

FROM php:8.3-fpm-alpine AS runtime
RUN apk add --no-cache \
    nginx \
    bash \
    curl \
    icu-dev \
    libzip-dev \
    oniguruma-dev \
    unzip \
    zip \
    gettext \
    autoconf \
    g++ \
    make \
    linux-headers \
    && docker-php-ext-install pdo pdo_mysql intl mbstring zip opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/cache/apk/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY backend/ /var/www/html/
COPY --from=backend-vendor /workspace/backend/vendor /var/www/html/vendor
COPY --from=backend-assets /workspace/backend/public/build /var/www/html/public/build

COPY infra/cloudrun/nginx.conf.template /etc/nginx/templates/default.conf.template
COPY infra/cloudrun/start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh \
    && mkdir -p /run/nginx /var/log/nginx

ENV PORT=8080
EXPOSE 8080

CMD ["/usr/local/bin/start.sh"]
