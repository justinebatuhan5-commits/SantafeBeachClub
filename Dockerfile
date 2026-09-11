FROM php:8.2-apache

# Enable Apache mod_rewrite & mod_headers
RUN a2enmod rewrite headers

# Install required PHP extensions for MySQL & image processing
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Configure Apache DocumentRoot and Directory permissions
ENV APACHE_DOCUMENT_ROOT=/var/www/html
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Allow .htaccess overrides and pass environment variables to PHP
RUN echo '<Directory /var/www/html>\n\
    Options -Indexes +FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>\n\
PassEnv DB_HOST DB_PORT DB_NAME DB_USER DB_PASS GMAIL_USER GMAIL_APP_PASSWORD MAIL_FROM_NAME CLOUDINARY_CLOUD_NAME CLOUDINARY_API_KEY CLOUDINARY_API_SECRET PORT\n' > /etc/apache2/conf-available/override.conf \
    && a2enconf override

# Copy application source code
COPY . /var/www/html/

# Set appropriate permissions for uploads
RUN mkdir -p /var/www/html/frontend/uploads \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Listen on both 80 and 10000 (standard Render ports)
RUN echo "Listen 10000" >> /etc/apache2/ports.conf \
    && sed -i 's/<VirtualHost \*:80>/<VirtualHost \*:80 \*:10000>/' /etc/apache2/sites-available/000-default.conf

EXPOSE 80 10000

CMD ["apache2-foreground"]




