FROM php:8.2-fpm

# ARG & ENV
ARG BUILD_NUMBER=100
ENV BUILD_NUMBER $BUILD_NUMBER

# Install basic library
# RUN apt-get -y install zip gcc make build-essential g++
RUN apt-get update && \
  apt-get -y install wget curl sqlite3 \
  zlib1g-dev mariadb-client libzip-dev libonig-dev \
  libfreetype-dev libjpeg62-turbo-dev libpng-dev libsqlite3-dev
# RUN  docker-php-ext-configure gd --with-freetype --with-jpeg
RUN docker-php-ext-install zip gd pdo_sqlite

COPY --from=composer /usr/bin/composer /usr/bin/composer
COPY php.ini /usr/local/etc/php/

# COPY APP
COPY ./kona3engine /var/www/html/kona3engine
COPY ./skin /var/www/html/skin
COPY ./script /var/www/html/script
COPY ./*.php /var/www/html/

# mkdir
RUN mkdir -p /var/www/html/data \
  && mkdir -p /var/www/html/cache \
  && mkdir -p /var/www/html/private

WORKDIR /var/www/html
EXPOSE 8899

CMD ["php", "-S", "0.0.0.0:8899"]
