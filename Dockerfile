FROM php:8.3.11RC1-zts-alpine3.20
WORKDIR /var/www/html

RUN docker-php-ext-install mysqli pdo pdo_mysql
RUN apk update
RUN curl -sS https://getcomposer.org/installer | php -- --version=2.4.3 --install-dir=/usr/local/bin --filename=composer

COPY . .
RUN composer install
RUN php artisan key:generate
EXPOSE 8000

CMD ["php","artisan","serve","--host=0.0.0.0"]