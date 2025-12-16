FROM composer AS builder
FROM php:8.4-cli

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    bash \
    libzip-dev \
    zip \
    unzip \
    libpq-dev \
    libsqlite3-dev \
    git \
    && docker-php-ext-install pdo_mysql pdo_pgsql pdo_sqlite zip

# Install Xdebug for testing coverage
RUN pecl install xdebug && docker-php-ext-enable xdebug

# Install Composer
COPY --from=builder /usr/bin/composer /usr/local/bin/composer

WORKDIR /app

# Copy composer files and install dependencies
COPY composer.* ./
RUN composer install --no-scripts --no-interaction --prefer-dist --optimize-autoloader

# Copy source code
COPY . .

CMD ["php", "-v"]
