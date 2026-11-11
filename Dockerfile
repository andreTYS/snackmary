FROM php:8.3-apache

# Extensiones: mysqli (BD) y gd (fotos de productos en JPG/PNG/WEBP)
RUN apt-get update \
    && apt-get install -y --no-install-recommends libjpeg62-turbo-dev libpng-dev libwebp-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" mysqli gd \
    && rm -rf /var/lib/apt/lists/* \
    && a2enmod rewrite headers \
    # Permitir .htaccess (bloqueo de archivos sensibles)
    && sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf \
    # No mostrar versión de Apache/PHP
    && printf 'ServerTokens Prod\nServerSignature Off\n' > /etc/apache2/conf-enabled/zz-seguridad.conf \
    # Evita el aviso AH00558 (Traefik es quien recibe el dominio real)
    && echo 'ServerName localhost' > /etc/apache2/conf-enabled/servername.conf \
    # En la carpeta de fotos nunca se ejecuta PHP (aunque alguien lograra subir un .php)
    && printf '<Directory /var/www/html/uploads>\n    php_admin_flag engine off\n    Options -Indexes -ExecCGI\n    <FilesMatch "\\.(?!(jpg|webp)$)[^.]+$">\n        Require all denied\n    </FilesMatch>\n</Directory>\n' > /etc/apache2/conf-enabled/zz-uploads.conf

COPY docker/php.ini /usr/local/etc/php/conf.d/snackmary.ini

WORKDIR /var/www/html
COPY --chown=www-data:www-data . /var/www/html/
# Archivos que no deben quedar dentro de la imagen
RUN rm -rf .git docker docker-compose.yml Dockerfile .env .env.example config.local.php \
    && mkdir -p uploads/productos && chown -R www-data:www-data uploads
