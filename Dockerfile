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

# Install git and zip, used by Composer, cron and nano
RUN apt-get update && apt-get install git zlib1g-dev cron nano -y && \ 
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

# Set up the cron job every minute, with the correct environment
# then, start apache2 in foreground (https://github.com/docker-library/php/blob/76a1c5ca161f1ed6aafb2c2d26f83ec17360bc68/7.1/apache/Dockerfile#L205)
CMD printenv > /etc/environment && \
    echo "* * * * * /usr/local/bin/php /var/www/html/cron.php" | crontab - && \
    cron && \
    apache2-foreground
