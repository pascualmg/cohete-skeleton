FROM php:8.2-cli AS build

RUN apt-get update && apt-get install -y \
    libicu-dev \
    libzip-dev \
    unzip \
    && docker-php-ext-install \
    pcntl \
    sockets \
    mbstring \
    intl \
    bcmath \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --prefer-dist

COPY . .
RUN composer dump-autoload --optimize

FROM php:8.2-cli

RUN apt-get update && apt-get install -y \
    libicu-dev \
    && docker-php-ext-install \
    pcntl \
    sockets \
    mbstring \
    intl \
    bcmath \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY --from=build /app /app

EXPOSE 8080

CMD ["php", "src/bootstrap.php"]
