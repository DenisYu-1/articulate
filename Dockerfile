FROM composer AS builder
FROM php:8.4-cli

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    bash \
    libzip-dev \
    zip \
    unzip \
    libpq-dev \
    git \
    graphviz \
    && docker-php-ext-install pdo_mysql pdo_pgsql zip

# Install Xdebug for testing coverage
RUN pecl install xdebug && docker-php-ext-enable xdebug

# Create non-root user for security
RUN groupadd -r appuser -g 1001 && \
    useradd -r -g appuser -u 1001 -m -d /home/appuser -s /bin/bash appuser

# Install Composer
COPY --from=builder /usr/bin/composer /usr/local/bin/composer

WORKDIR /app

# Copy composer files and install dependencies
COPY --chown=appuser:appuser composer.* ./
RUN composer install --no-scripts --no-interaction --prefer-dist --optimize-autoloader

# Copy source code
COPY --chown=appuser:appuser . .

USER appuser

CMD ["php", "-v"]
