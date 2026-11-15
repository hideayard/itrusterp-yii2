# PHP 8.2 + Apache untuk API iTrustERP (Yii2)
FROM php:8.2-apache

# Ekstensi yang dibutuhkan Yii2 + modul (intl/zip untuk resource ERP)
RUN apt-get update \
 && apt-get install -y --no-install-recommends \
      git unzip curl libicu-dev libzip-dev libpng-dev libonig-dev \
 && docker-php-ext-install -j"$(nproc)" pdo_mysql intl zip mbstring gd opcache \
 && a2enmod rewrite headers \
 && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Document root HARUS folder web/ (bukan root proyek)
ENV APACHE_DOCUMENT_ROOT=/var/www/html/web
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
 && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf \
 && sed -ri -e 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

WORKDIR /var/www/html

# Layer composer dipisah agar cache build efisien
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --no-scripts

COPY . .

RUN composer dump-autoload --optimize --no-dev \
 && mkdir -p runtime/logs runtime/cache web/assets \
 && chown -R www-data:www-data runtime web/assets

EXPOSE 80
