# Dockerfile (for php:8.4-fpm-alpine)
FROM php:8.4-fpm-alpine

# metadata
LABEL maintainer="you@example.com"

# install runtime deps + build deps (removed at the end)
RUN apk add --no-cache --virtual .build-deps \
        autoconf \
        g++ \
        make \
        bash \
        curl \
        libtool \
        pkgconfig \
        openssl-dev \
    && apk add --no-cache \
        icu-dev \
        libzip-dev \
        libxml2-dev \
        libpng-dev \
        libxslt-dev \
        oniguruma-dev \
        freetype-dev \
        libjpeg-turbo-dev \
        zlib-dev \
        tzdata \
    \
    # configure intl (icu)
    && docker-php-ext-configure intl \
    \
    # configure gd with jpeg + freetype support
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    \
    # install PHP extensions
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_mysql \
        opcache \
        intl \
        zip \
        calendar \
        dom \
        mbstring \
        gd \
        xsl \
    \
    # install pecl extensions (APCu)
    && pecl install apcu \
    && docker-php-ext-enable apcu \
    \
    # cleanup build deps to reduce image size
    && apk del .build-deps \
    && rm -rf /var/cache/apk/* /tmp/pear

# Install composer from official composer image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# timezone / locale envs
ENV LANG=fr_FR.UTF-8 \
    LANGUAGE=fr_FR:fr \
    LC_ALL=fr_FR.UTF-8 \
    TZ=Europe/Paris

WORKDIR /var/www/html

# copy entrypoint script (optional) and set permissions if needed
# COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
# RUN chmod +x /usr/local/bin/entrypoint.sh

# expose fpm port (nginx will proxy to this)
EXPOSE 9000

# default command: php-fpm (do not start nginx here)
CMD ["php-fpm"]
