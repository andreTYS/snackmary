FROM php:8.3-apache

# Extensiones: mysqli (BD), gd no hace falta (FPDF usa PNG nativo)
RUN docker-php-ext-install mysqli \
    && a2enmod rewrite headers \
    # Permitir .htaccess (bloqueo de archivos sensibles)
    && sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf \
    # No mostrar versión de Apache/PHP
    && printf 'ServerTokens Prod\nServerSignature Off\n' > /etc/apache2/conf-enabled/zz-seguridad.conf

COPY docker/php.ini /usr/local/etc/php/conf.d/snackmary.ini

WORKDIR /var/www/html
COPY --chown=www-data:www-data . /var/www/html/
# Archivos que no deben quedar dentro de la imagen
RUN rm -rf .git docker docker-compose.yml Dockerfile .env .env.example config.local.php
