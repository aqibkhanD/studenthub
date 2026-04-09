FROM php:8.3-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    bash \
    git \
    curl \
    libpng-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    libzip-dev \
    zip \
    unzip \
    postgresql-dev \
    oniguruma-dev \
    freetype-dev \
    icu-dev \
    linux-headers

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install \
        pdo \
        pdo_pgsql \
        pgsql \
        mbstring \
        zip \
        exif \
        pcntl \
        bcmath \
        gd \
        intl \
        opcache

# Install Redis extension via PECL
RUN pecl install redis && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# ── Layer-cache optimisation: install dependencies before copying all code ──
# Copy only the manifest files first — this layer is cached as long as
# composer.json / composer.lock haven't changed.
# NOTE: build context must be the PROJECT ROOT (see docker-compose.yml),
#       so paths here are relative to the repo root.
COPY backend/composer.json backend/composer.lock ./
RUN composer install \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --no-dev \
    --no-interaction \
    --quiet

# Copy full application code
COPY backend/ .

# Generate the optimised autoloader now that all app classes are present
RUN composer dump-autoload --optimize --no-dev --no-interaction

# Set permissions — must be done as root before switching user
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

USER www-data
