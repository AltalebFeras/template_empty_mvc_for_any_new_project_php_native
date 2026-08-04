# =============================================================================
#  Multi-Stage Dockerfile — PHP-FPM 8.2 + Alpine
#
#  Build:   docker build -t app .
#  Run:     docker-compose up -d
# =============================================================================

# --- Stage 1: Composer Dependencies ---
FROM composer:2 AS vendor

WORKDIR /app
COPY composer.json composer.lock* ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader

# --- Stage 2: Application ---
FROM php:8.2-fpm-alpine AS app

# Install system dependencies & PHP extensions.
RUN apk add --no-cache \
        freetype-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        libwebp-dev \
        libxml2-dev \
        curl-dev \
        oniguruma-dev \
        linux-headers \
        $PHPIZE_DEPS \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        pdo_pgsql \
        gd \
        opcache \
        mbstring \
        curl \
        xml \
        bcmath \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS linux-headers \
    && rm -rf /var/cache/apk/* /tmp/pear

# OPcache configuration.
COPY opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# PHP production settings.
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" && \
    echo "expose_php = Off" >> "$PHP_INI_DIR/php.ini" && \
    echo "upload_max_filesize = 10M" >> "$PHP_INI_DIR/php.ini" && \
    echo "post_max_size = 12M" >> "$PHP_INI_DIR/php.ini" && \
    echo "memory_limit = 256M" >> "$PHP_INI_DIR/php.ini" && \
    echo "max_execution_time = 30" >> "$PHP_INI_DIR/php.ini"

# Create non-root user.
RUN addgroup -g 1000 appuser && adduser -u 1000 -G appuser -D appuser

# Set working directory.
WORKDIR /var/www

# Copy application code.
COPY --chown=appuser:appuser . .
COPY --from=vendor --chown=appuser:appuser /app/vendor ./vendor

# Create required directories.
RUN mkdir -p logs storage/uploads storage/cache && \
    chown -R appuser:appuser logs storage

# Switch to non-root user.
USER appuser

EXPOSE 9000

CMD ["php-fpm"]
