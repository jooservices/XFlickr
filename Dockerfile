FROM php:8.5-cli-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    bash \
    ca-certificates \
    curl \
    default-mysql-client \
    git \
    gnupg \
    libicu-dev \
    libzip-dev \
    unzip \
    $PHPIZE_DEPS \
    && curl -fsSL https://deb.nodesource.com/setup_24.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && docker-php-ext-install bcmath intl pcntl pdo_mysql zip \
    && pecl install mongodb redis \
    && docker-php-ext-enable mongodb redis \
    && apt-get purge -y --auto-remove $PHPIZE_DEPS \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
