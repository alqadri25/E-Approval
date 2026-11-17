# ==========================================
# FrankenPHP Laravel Runtime
# ==========================================
FROM dunglas/frankenphp:1-php8.3-bookworm

# Install PHP extensions required by Laravel, SQLite, DomPDF, PhpSpreadsheet, QRCode
RUN install-php-extensions \
    pdo_sqlite \
    sqlite3 \
    bcmath \
    intl \
    opcache \
    zip \
    gd \
    mbstring

# Copy Composer from official image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Set production environment variables
ENV PORT=80 \
    APP_NAME="E-Approval" \
    APP_ENV=production \
    APP_DEBUG=false \
    APP_KEY="base64:iI/y07XaGJ1V1DZ4AEuy11Oa2HmBVQmMdcJSHvVMvcg=" \
    APP_URL="https://e-approvalmusashi.vercel.app" \
    LOG_CHANNEL=stderr \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/app/database/database.sqlite \
    SESSION_DRIVER=cookie \
    SESSION_LIFETIME=525600 \
    CACHE_STORE=file

# Copy repository files
COPY . .

# Configure optimal OPcache and PHP settings for production
RUN echo "opcache.enable=1\n\
opcache.enable_cli=1\n\
opcache.memory_consumption=256\n\
opcache.interned_strings_buffer=16\n\
opcache.max_accelerated_files=30000\n\
opcache.validate_timestamps=0\n\
opcache.save_comments=1\n\
opcache.fast_shutdown=1\n\
max_execution_time=60\n\
memory_limit=512M" > /usr/local/etc/php/conf.d/opcache-recommended.ini

# Install PHP dependencies without dev dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# Copy Caddy configuration
COPY Caddyfile /etc/frankenphp/Caddyfile

# Ensure storage, cache, and database directories exist and have proper permissions
RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache public/build \
    && chmod -R 777 storage bootstrap/cache database

# Precompile Laravel configuration, routes, and views for instantaneous execution
RUN php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache

EXPOSE 80

CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
