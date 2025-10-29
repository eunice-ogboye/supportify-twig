# Use official PHP image
FROM php:8.2-apache

# Set working directory
WORKDIR /var/www/html

# Install Composer
RUN apt-get update && apt-get install -y unzip git \
    && php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && rm composer-setup.php

# Copy all files to container
COPY . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Expose port
EXPOSE 80

# Start Apache server
CMD ["apache2-foreground"]
