FROM php:8.3-apache

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN a2enmod rewrite headers \
 && sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
 && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
 && sed -ri -e 's!AllowOverride None!AllowOverride All!' /etc/apache2/apache2.conf \
 && printf 'ServerTokens Prod\nServerSignature Off\n' > /etc/apache2/conf-available/hardening.conf \
 && a2enconf hardening \
 && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
 && printf 'expose_php=Off\ndisplay_errors=Off\nlog_errors=On\nerror_log=/dev/stderr\n' > "$PHP_INI_DIR/conf.d/zz-pixelite.ini"

# Experimental visual editor (EDITOR_ENABLED): GD re-encodes uploaded images. The editor refuses uploads when GD is missing.
RUN apt-get update && apt-get install -y --no-install-recommends libjpeg-dev libpng-dev libwebp-dev \
 && docker-php-ext-configure gd --with-jpeg --with-webp \
 && docker-php-ext-install -j2 gd \
 && rm -rf /var/lib/apt/lists/*

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

WORKDIR /var/www/html
COPY . /var/www/html

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
