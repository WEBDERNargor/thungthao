# syntax=docker/dockerfile:1

ARG PHP_VERSION=8.2

FROM composer:2 AS composer_bin

FROM php:${PHP_VERSION}-apache

ENV APACHE_DOCUMENT_ROOT=/var/www/html

RUN apt-get update && apt-get install -y --no-install-recommends \
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

RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        pdo_sqlite \
        gd \
        zip \
        exif \
        mbstring \
        xml \
        dom \
        simplexml

RUN a2enmod rewrite headers deflate

COPY docker/apache.conf /etc/apache2/conf-available/app.conf
RUN a2enconf app
RUN sed -i 's/Listen 80/Listen 8000/' /etc/apache2/ports.conf \
    && sed -i 's/:80>/:8000>/' /etc/apache2/sites-available/000-default.conf

# PHP runtime config (increase upload/post limits for resumable uploads)
COPY docker/php/*.ini /usr/local/etc/php/conf.d/

WORKDIR /var/www/html

# Copy composer binary and install PHP dependencies inside final image
COPY --from=composer_bin /usr/bin/composer /usr/bin/composer

# Leverage Docker layer caching: install vendor based on composer.json
COPY composer.json /var/www/html/
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --no-progress

# Now copy the rest of the application source
COPY . /var/www/html

RUN mkdir -p /var/www/html/storage /var/www/html/temp /var/www/html/protect/uploads \
    && chown -R www-data:www-data /var/www/html

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 8000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["apache2-foreground"]
