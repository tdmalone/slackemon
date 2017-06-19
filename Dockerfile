FROM php:7.0-fpm-alpine

LABEL maintainer "tdmalone@gmail.com"

COPY . /slackemon
WORKDIR /slackemon

# Install git and zip, used by Composer; cron, nano, vim and finally postgres functions for PHP7
RUN apk update && apk add git zlib-dev nano vim postgresql-dev && \
    docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql && \
    docker-php-ext-install zip pdo pdo_pgsql pgsql

# Install Composer package manager
RUN curl -s http://getcomposer.org/installer | php

# Install dependencies
RUN php composer.phar install
