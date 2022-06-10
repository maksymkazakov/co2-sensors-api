FROM php:8.1.7-apache

COPY config/_apache/000-default.conf /etc/apache2/sites-available/000-default.conf
RUN a2enmod rewrite

RUN chown -R www-data:www-data /var/www

CMD ["apache2-foreground"]
