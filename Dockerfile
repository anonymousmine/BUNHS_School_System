FROM php:8.3-apache

# Install mysqli + deps
RUN apt-get update && apt-get install -y \
    libmariadb-dev libzip-dev libpng-dev libjpeg-dev libfreetype6-dev libonig-dev \
    unzip git curl netcat-openbsd \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install mysqli pdo_mysql mbstring zip gd exif \
    && docker-php-ext-enable mysqli \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY composer.* ./
RUN composer install --no-dev --optimize-autoloader

COPY . .

RUN chmod +x start-container.sh \
    && a2enmod rewrite headers \
    && sed -i 's!/var/www/html!/app!g' /etc/apache2/sites-available/*.conf \
    && echo '<Directory /app>\n    AllowOverride All\n    Require all granted\n</Directory>' >> /etc/apache2/sites-available/000-default.conf

RUN php -m | grep mysqli

EXPOSE 8080
CMD ["./start-container.sh"]
