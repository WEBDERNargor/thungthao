# syntax=docker/dockerfile:1

ARG PHP_VERSION=8.2

FROM composer:2 AS composer_bin

FROM php:${PHP_VERSION}-apache

ENV APACHE_DOCUMENT_ROOT=/var/www/html

# System deps
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

# PHP extensions
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

# Apache modules
RUN a2enmod rewrite headers deflate

# PHP runtime config (uploads, memory)
RUN mkdir -p /usr/local/etc/php/conf.d \
 && cat > /usr/local/etc/php/conf.d/uploads.ini <<'INI'
; PHP upload and execution limits for large/resumable uploads
upload_max_filesize = 512M
post_max_size = 512M
max_file_uploads = 200
memory_limit = 512M
max_execution_time = 300
max_input_time = 300
INI

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
CMD ["apache2-foreground"]
