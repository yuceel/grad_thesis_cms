FROM php:8.1-apache

WORKDIR /var/www/html
COPY . /var/www/html/

# Enable mysqli and PDO extensions for MySQL
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Enable URL rewriting via .htaccess (optional, but helpful)
RUN a2enmod rewrite

# Expose port 10000 for Render
EXPOSE 10000

# Update Apache to listen on port 10000 instead of 80
RUN sed -i 's/80/10000/g' /etc/apache2/ports.conf \
 && sed -i 's/VirtualHost \*:80/VirtualHost \*:10000/g' /etc/apache2/sites-enabled/000-default.conf

CMD ["apache2-foreground"]
