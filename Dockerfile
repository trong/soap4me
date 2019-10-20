FROM php:7.3-cli

WORKDIR /app
COPY . /app

RUN apt-get update && \
    apt-get install -y aria2 unzip

RUN docker-php-ext-install -j$(nproc) pcntl && \
    mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

RUN rm -rf /var/lib/apt/lists/*

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer && \
    composer install --no-dev --prefer-dist --no-progress

ENTRYPOINT ["php", "soap4me.php"]
