FROM php:8.3-apache

# System deps
RUN apt-get update && apt-get install -y \
    libmariadb-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    unzip \
    git \
    curl \
    netcat-openbsd \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
    mysqli \
    pdo_mysql \
    mbstring \
    zip \
    gd \
    exif \
    && docker-php-ext-enable mysqli pdo_mysql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# App FIRST (composer needs it)
WORKDIR /app
COPY composer.* ./
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Full app copy
COPY . .

# Apache
RUN a2enmod rewrite headers \
    && sed -i 's!/var/www/html!/app!g' /etc/apache2/sites-available/*.conf \
    && echo '<Directory /app>\n    AllowOverride All\n    Require all granted\n</Directory>' >> /etc/apache2/sites-available/000-default.conf \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# VERIFY mysqli
RUN echo "PHP Modules:" && php -m | grep -E "(mysqli|pdo_mysql)" || (echo "❌ mysqli MISSING" && exit 1)

# Railway PORT/health
EXPOSE 8080
ENV PORT 8080
HEALTHCHECK --interval=30s --timeout=3s CMD curl -f http://localhost:$PORT/ || exit 1

CMD ["./start-container.sh"]
