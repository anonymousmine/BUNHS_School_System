FROM php:8.3-apache

# Install system dependencies and PHP extensions
RUN apt-get update &amp;&amp; apt-get install -y \
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
    &amp;&amp; docker-php-ext-configure gd --with-freetype --with-jpeg \
    &amp;&amp; docker-php-ext-install \
    mysqli \
    pdo_mysql \
    mbstring \
    zip \
    gd \
    exif \
    &amp;&amp; docker-php-ext-enable mysqli pdo_mysql \
    &amp;&amp; apt-get clean &amp;&amp; rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# App setup
WORKDIR /app
COPY composer.* ./
RUN composer install --no-dev --optimize-autoloader --no-interaction

COPY . .

# Apache configuration for /app
RUN a2enmod rewrite headers \
    &amp;&amp; sed -i 's!/var/www/html!/app!g' /etc/apache2/sites-available/*.conf \
    &amp;&amp; echo '<Directory /app>\n    AllowOverride All\n    Require all granted\n</Directory>' >> /etc/apache2/sites-available/000-default.conf \
    &amp;&amp; mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# CRITICAL: Verify mysqli extension is loaded
RUN echo "PHP Modules:" &amp;&amp; php -m | grep -E "(mysqli|pdo_mysql)" || (echo "❌ mysqli MISSING" &amp;&amp; exit 1)

# Railway PORT and healthcheck
EXPOSE 8080
ENV PORT 8080
HEALTHCHECK --interval=30s --timeout=3s CMD curl -f http://localhost:$PORT/ || exit 1

CMD ["./start-container.sh"]
