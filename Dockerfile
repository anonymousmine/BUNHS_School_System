FROM dunglas/frankenphp:php8.4.19-bookworm

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
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
    mysqli \
    pdo_mysql \
    mbstring \
    zip \
    gd \
    && docker-php-ext-enable mysqli pdo_mysql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . /app

RUN composer install --optimize-autoloader --no-interaction

EXPOSE 8080

# Make start script executable
RUN chmod +x start-container.sh

CMD ["./start-container.sh"]
