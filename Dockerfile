FROM php:8.2-apache
RUN apt-get update && apt-get install -y \
    git \
    curl \
    && docker-php-ext-install pdo pdo_mysql

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

COPY . /var/www/html

RUN composer install

RUN php artisan key:generate

CMD ["php", "artisan", "serve"]