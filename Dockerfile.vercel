# ==========================================
# Stage 1: Build frontend assets (Vite/Node)
# ==========================================
FROM node:20-alpine AS frontend
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY . .
RUN npm run build

# ==========================================
# Stage 2: FrankenPHP Laravel Runtime
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
    LOG_CHANNEL=stderr \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/app/database/database.sqlite \
    SESSION_DRIVER=cookie \
    CACHE_STORE=array

# Copy repository files
COPY . .

# Copy compiled frontend assets from frontend stage
COPY --from=frontend /app/public/build ./public/build

# Install PHP dependencies without dev dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# Copy Caddy configuration
COPY Caddyfile /etc/frankenphp/Caddyfile

# Ensure storage, cache, and database directories exist and have proper permissions
RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chmod -R 777 storage bootstrap/cache database

EXPOSE 80

CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
