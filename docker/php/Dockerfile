FROM php:8.5-fpm

# Install system dependencies
RUN apt-get update && apt-get upgrade -y && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libpq-dev \
    libzip-dev \
    libicu-dev \
    libfcgi-bin \
    zip \
    unzip \
    procps \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure intl \
    && docker-php-ext-install \
        pdo \
        pdo_pgsql \
        intl \
        zip \
        opcache

# Install Redis extension
RUN pecl install redis \
    && docker-php-ext-enable redis

# Configure OPcache
RUN { \
    echo 'opcache.memory_consumption=128'; \
    echo 'opcache.interned_strings_buffer=8'; \
    echo 'opcache.max_accelerated_files=4000'; \
    echo 'opcache.revalidate_freq=2'; \
    echo 'opcache.fast_shutdown=1'; \
    echo 'opcache.enable_cli=1'; \
    echo 'opcache.validate_timestamps=1'; \
} > /usr/local/etc/php/conf.d/opcache-recommended.ini

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Install Tailwind CSS standalone CLI
ARG TAILWIND_VERSION=v3.4.17
RUN curl -sLO https://github.com/tailwindlabs/tailwindcss/releases/download/${TAILWIND_VERSION}/tailwindcss-linux-x64 \
    && chmod +x tailwindcss-linux-x64 \
    && mv tailwindcss-linux-x64 /usr/local/bin/tailwindcss

# Set working directory
WORKDIR /var/www/html

# Create the non-root user PHP-FPM workers run as. The project is bind-mounted from the host, so
# its uid/gid must match the host user that owns the checkout (var/ must be writable by workers).
ARG WWW_UID=1000
ARG WWW_GID=1000
RUN groupadd -g ${WWW_GID} www \
    && useradd -u ${WWW_UID} -ms /bin/bash -g www www

# Run workers as www (default pool user is www-data, uid 33)
COPY www.conf /usr/local/etc/php-fpm.d/zz-www-user.conf

# Install health check script
COPY healthcheck.sh /usr/local/bin/healthcheck.sh
RUN chmod +x /usr/local/bin/healthcheck.sh

# Expose port 9000 for PHP-FPM
EXPOSE 9000

CMD ["php-fpm"]
