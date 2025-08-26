FROM php:8.2-cli

WORKDIR /var/www/html

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libpq-dev \
    zip \
    unzip

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_pgsql mbstring exif pcntl bcmath gd

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy application code first (this is the key change)
COPY . .

# Create necessary directories and set permissions
RUN mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache bootstrap/cache
RUN chmod -R 775 storage bootstrap/cache

# Install dependencies normally (since laravel/pail is already removed locally)
RUN composer install

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0"]