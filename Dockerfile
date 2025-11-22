FROM php:8.3-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libzip-dev \
    unzip \
    git \
    libpng-dev

# ===> ADD THIS LINE HERE <===
# Allow git to operate on the /app directory owned by the host user
RUN git config --global --add safe.directory /app

# Install PHP extensions
RUN docker-php-ext-install zip gd

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set custom php.ini settings
RUN echo "memory_limit = 512M" > /usr/local/etc/php/conf.d/custom.ini

# Set the working directory
WORKDIR /app

