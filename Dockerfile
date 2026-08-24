FROM php:8.2-apache

# Install PHP cURL extension for Supabase REST/API calls (also used for the
# optional SendPulse OAuth integration in api/routes/auth.php)
RUN apt-get update \
    && apt-get install -y libcurl4-openssl-dev \
    && docker-php-ext-install curl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Enable required Apache modules (headers for security policies, rewrite kept
# available even though this app's own routing needs none — public/index.php
# and public/api/index.php are hit by direct path, not a rewritten one)
RUN a2enmod headers rewrite

# Apply hardened production PHP settings (expose_php=Off, allow_url_fopen=Off,
# display_errors=Off, secure session cookies, upload limits matching the
# 25MB post/gallery media cap enforced in api/routes/core.php)
COPY php-production.ini "$PHP_INI_DIR/conf.d/zz-pawcircle-production.ini"

# Change Apache DocumentRoot to public/ — main.php/views/modals/api stay
# outside the web root, only public/ (index.php, api/index.php, css/js/assets)
# is ever served directly
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Copy project files into the Apache web root
COPY . /var/www/html/

EXPOSE 80
