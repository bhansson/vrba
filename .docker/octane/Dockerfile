FROM php:8.3-cli

ARG PUID=1000
ARG PGID=1000

ENV APP_ENV=local \
    OCTANE_SERVER=swoole \
    OCTANE_PORT=8000 \
    WWWUSER=${PUID} \
    WWWGROUP=${PGID}

WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install --no-install-recommends --yes \
        git \
        unzip \
        curl \
        gnupg \
        libzip-dev \
        libpq-dev \
        libsqlite3-dev \
        libssl-dev \
        pkg-config \
        supervisor \
        libjpeg62-turbo-dev \
        libpng-dev \
        libfreetype6-dev \
        libonig-dev \
        libxml2-dev \
        ca-certificates \
        procps \
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install --no-install-recommends --yes nodejs \
    && docker-php-ext-configure zip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        bcmath \
        pcntl \
        pdo \
        pdo_mysql \
        pdo_pgsql \
        pdo_sqlite \
        gd \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && printf "\n" | pecl install swoole \
    && docker-php-ext-enable swoole \
    && groupmod -o -g ${PGID} www-data \
    && usermod -o -u ${PUID} -g www-data www-data \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY .docker/octane/php.ini /usr/local/etc/php/conf.d/octane.ini

EXPOSE 8000

ENTRYPOINT ["/bin/bash", "/var/www/html/.docker/octane/start-octane.sh"]
