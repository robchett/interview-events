FROM php:8.4

RUN apt-get update && apt-get install -y wget git libicu-dev zip
RUN docker-php-ext-install pdo_mysql intl opcache

WORKDIR /var/www/html


RUN wget https://raw.githubusercontent.com/composer/getcomposer.org/f3108f64b4e1c1ce6eb462b159956461592b3e3e/web/installer -O - -q | php -- --quiet

RUN wget https://get.symfony.com/cli/installer -O - | bash
RUN mv /root/.symfony5/bin/symfony /usr/local/bin/symfony

COPY --chown=www-data:www-data app .
RUN ./composer.phar install
