FROM php:8.2-apache

# Install PHP cURL extension for Supabase REST/API calls
RUN apt-get update \
    && apt-get install -y libcurl4-openssl-dev \
    && docker-php-ext-install curl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Copy project files into Apache web root
COPY . /var/www/html/

# Make your uploaded frontend file the homepage
RUN if [ -f /var/www/html/pawcircle_frontend.html ]; then \
    cp /var/www/html/pawcircle_frontend.html /var/www/html/index.html; \
    fi

EXPOSE 80