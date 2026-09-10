FROM php:8.2-apache

# Enable Apache mod_rewrite & mod_headers
RUN a2enmod rewrite headers

# Install required PHP extensions for MySQL & image processing
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Configure Apache DocumentRoot and Directory permissions
ENV APACHE_DOCUMENT_ROOT=/var/www/html
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Allow .htaccess overrides
RUN echo '<Directory /var/www/html>\n\
    Options -Indexes +FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' > /etc/apache2/conf-available/override.conf \
    && a2enconf override

# Copy application source code
COPY . /var/www/html/

# Set appropriate permissions for uploads
RUN mkdir -p /var/www/html/frontend/uploads \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Use the PORT environment variable provided by Render (defaults to 80)
EXPOSE 80

CMD ["apache2-foreground"]
