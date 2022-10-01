FROM php:8.1-fpm-alpine

RUN apk update && \
    apk add --no-cache bash \
        zip \
        libzip-dev \
        curl \
        curl-dev \
        gettext-dev \
        libmcrypt-dev \
        libxml2-dev \
        zlib-dev \
        libmcrypt && \
    apk add --no-cache  --virtual  build-dependencies \
        autoconf \
        build-base && \
    pecl install redis &&\
    docker-php-ext-enable redis && \
    # docker-php-ext-configure zip --with-libzip && \
    docker-php-ext-install \
    #     curl \
    #     opcache \
    #     mbstring \
    #     pdo \
        pdo_mysql &&\
    #     xml \
    #     json \
    #     zip && \
    apk del build-dependencies && \
    rm -rf /tmp/*


# Добавим свой php.ini, можем в нем определять свои значения конфига
COPY php.ini /usr/local/etc/php/conf.d/40-custom.ini
COPY php-fpm-custom.conf /usr/local/etc/php-fpm.d/custom.conf
COPY www.conf /usr/local/etc/php-fpm.d/www.conf

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

WORKDIR /var/www

USER www-data

CMD ["php-fpm"]