FROM php:7.0-apache

COPY . /var/www/html # Copies all file project in the container
WORKDIR /var/www/html

RUN useradd -ms /bin/bash slackemon && \ # Create a user
    usermod -a -G www-data slackemon && \ # Assign it to www-data
    chown -R www-data:www-data /var/www/html && \ # Assign public_html to www-data
    chmod -R 774 /var/www/html # Change permission to only user and group www-data

RUN apt-get update && apt-get install git zlib1g-dev -y && \ # Install git and zip, used by Composer
    docker-php-ext-install zip && \ # Install zip
    curl -s http://getcomposer.org/installer | php # Install package manager Composer

USER slackemon 

RUN php composer.phar install # Install dependencies as non-root

VOLUME /var/www/html/vendor # Separate container vendor from local vendor

USER root # Use root to start the server