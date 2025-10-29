# Use PHP image
FROM php:8.2-apache

# Install system dependencies for Composer and PHP extensions
RUN apt-get update && apt-get install -y git unzip libzip-dev zip && docker-php-ext-install pdo pdo_mysql zip

# Set working directory
WORKDIR /var/www/html

# Copy composer files first
COPY composer.json composer.lock ./

# Install PHP dependencies inside the container
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && composer install --no-dev --optimize-autoloader

# Now copy the rest of your app
COPY . .

# Enable Apache mod_rewrite (for Laravel or routing)
RUN a2enmod rewrite

# Set permissions (important for Laravel)
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage

# Expose port
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
