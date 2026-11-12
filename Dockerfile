FROM php:8.3-apache

# Extensiones: mysqli (BD) y gd (fotos de productos en JPG/PNG/WEBP)
RUN apt-get update \
    && apt-get install -y --no-install-recommends libjpeg62-turbo-dev libpng-dev libwebp-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" mysqli gd \
    && rm -rf /var/lib/apt/lists/* \
    && a2enmod rewrite headers \
    # Permitir .htaccess (bloqueo de archivos sensibles)
    && sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

COPY docker/php.ini /usr/local/etc/php/conf.d/snackmary.ini
COPY docker/apache-seguridad.conf /etc/apache2/conf-enabled/zz-seguridad.conf

WORKDIR /var/www/html
# El código queda de solo lectura para Apache (www-data): aunque alguien lograra
# ejecutar algo, no podría modificar el sistema. Solo uploads/ es escribible.
COPY --chown=root:root . /var/www/html/
RUN rm -rf .git docker docker-compose.yml Dockerfile .env .env.* config.local.php \
    && find /var/www/html -type d -exec chmod 755 {} + \
    && find /var/www/html -type f -exec chmod 644 {} + \
    && mkdir -p uploads/productos && chown -R www-data:www-data uploads
