FROM node:22-alpine AS assets

WORKDIR /app/frontend
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY frontend ./
RUN npm run tw:build


FROM php:8.2-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
        ca-certificates \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libwebp-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" mysqli pdo_mysql gd intl \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

RUN cp /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini \
    && printf '%s\n' \
        'variables_order = "EGPCS"' \
        'expose_php = Off' \
        'upload_max_filesize = 12M' \
        'post_max_size = 16M' \
        'max_execution_time = 60' \
        'memory_limit = 256M' \
        'date.timezone = "Asia/Manila"' \
        > /usr/local/etc/php/conf.d/santafe.ini

RUN printf '%s\n' \
        'ServerName localhost' \
        'ServerTokens Prod' \
        'ServerSignature Off' \
        '<Directory /var/www/html>' \
        '    Options -Indexes +FollowSymLinks' \
        '    AllowOverride All' \
        '    Require all granted' \
        '</Directory>' \
        > /etc/apache2/conf-available/santafe-security.conf \
    && a2enconf santafe-security

WORKDIR /var/www/html
COPY . /var/www/html
COPY --from=assets /app/frontend/assets/css/tailwind.css /var/www/html/frontend/assets/css/tailwind.css

RUN cp /var/www/html/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh \
    && sed -i 's/\r$//' /usr/local/bin/docker-entrypoint.sh \
    && chmod +x /usr/local/bin/docker-entrypoint.sh \
    && rm -f /var/www/html/Dockerfile /var/www/html/docker-entrypoint.sh /var/www/html/.dockerignore /var/www/html/.gitattributes \
    && mkdir -p /var/www/SantaBeachClub-private/receipts /var/www/SantaBeachClub-private/avatars /var/www/SantaBeachClub-private/discount_ids /var/www/html/backend/logs /var/www/html/frontend/uploads \
    && chown -R www-data:www-data /var/www/SantaBeachClub-private /var/www/html/backend/logs /var/www/html/frontend/uploads

EXPOSE 10000

ENTRYPOINT ["docker-entrypoint.sh"]
