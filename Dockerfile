FROM php:7.0-apache

COPY . /var/www/html
WORKDIR /var/www/html

# Create a user
RUN useradd -ms /bin/bash slackemon

# Assign it to www-data
RUN usermod -a -G www-data slackemon

# Assign public_html to www-data
RUN chown -R www-data:www-data /var/www/html

# Change permission to only user and group www-data
RUN chmod -R 774 /var/www/html

# Install git and zip, used by Composer
RUN apt-get update && apt-get install git zlib1g-dev -y && \ 
    docker-php-ext-install zip

# Install package manager Composer
RUN curl -s http://getcomposer.org/installer | php

USER slackemon

# Install dependencies as non-root
RUN php composer.phar install

# Separate container vendor from local vendor
VOLUME /var/www/html/vendor

# Use root to start the server
USER root