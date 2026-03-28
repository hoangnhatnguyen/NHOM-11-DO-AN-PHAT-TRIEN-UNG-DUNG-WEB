FROM php:8.2-apache

# Cài dependency cần thiết
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    zip \
    libzip-dev

# Cài extension PHP
RUN docker-php-ext-install mysqli pdo pdo_mysql zip

# Enable rewrite (nếu cần)
RUN a2enmod rewrite

# Copy Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy code
COPY . /var/www/html/

# Set quyền
RUN mkdir -p /var/www/html/storage/logs \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

EXPOSE 80