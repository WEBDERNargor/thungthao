# syntax=docker/dockerfile:1

ARG PHP_VERSION=8.2

FROM composer:2 AS composer_bin

# Nginx + PHP-FPM base image for Dokploy
FROM php:${PHP_VERSION}-fpm

ENV NGINX_DOCUMENT_ROOT=/var/www/html

# System deps + nginx
RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx \
    libzip-dev \
    zlib1g-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libwebp-dev \
    libxml2-dev \
    libonig-dev \
    pkg-config \
    sqlite3 \
    libsqlite3-dev \
  && rm -rf /var/lib/apt/lists/*

# PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
 && docker-php-ext-install -j"$(nproc)" \
      pdo_mysql \
      pdo_sqlite \
      gd \
      zip \
      exif \
      mbstring \
      xml \
      dom \
      simplexml

# Nginx config: adapt template for container
RUN rm -f /etc/nginx/conf.d/default.conf
COPY docker/nginx.conf /etc/nginx/conf.d/app.conf
RUN sed -i "s#/var/www/html#${NGINX_DOCUMENT_ROOT}#g" /etc/nginx/conf.d/app.conf \
 && sed -i 's/listen 80;/listen 8000;/' /etc/nginx/conf.d/app.conf \
 && sed -i 's/# fastcgi_pass 127.0.0.1:9000;/fastcgi_pass 127.0.0.1:9000;/' /etc/nginx/conf.d/app.conf

WORKDIR /var/www/html

# Copy composer binary
COPY --from=composer_bin /usr/bin/composer /usr/bin/composer

# Install PHP deps first for better layer caching
COPY composer.json /var/www/html/
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --no-progress

# Copy application source
COPY . /var/www/html

# Ensure runtime dirs
RUN mkdir -p /var/www/html/storage /var/www/html/temp /var/www/html/protect/uploads \
 && chown -R www-data:www-data /var/www/html
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 8000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["sh", "-c", "php-fpm & nginx -g 'daemon off;'"]
