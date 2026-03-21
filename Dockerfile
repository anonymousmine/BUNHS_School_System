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
    && echo "extension=mysqli" > /usr/local/etc/php/conf.d/mysqli.ini \
    && apt-get clean && rm -rf /var/lib/apt/lists/*