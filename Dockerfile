FROM php:7.0-fpm-alpine

LABEL maintainer "tdmalone@gmail.com"

ENV PROJECT_ROOT /slackemon

COPY . $PROJECT_ROOT
WORKDIR $PROJECT_ROOT

RUN addgroup -g 1000 slackemon && \
    adduser -u 1000 -G slackemon -s /bin/sh -D slackemon && \
    chown -R slackemon:slackemon $PROJECT_ROOT && \
    chmod -R 774 $PROJECT_ROOT

# Install git and zip, used by Composer; cron, nano, vim and finally postgres functions for PHP7
RUN apk --no-cache add git zlib-dev nano vim postgresql-dev && \
    docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql && \
    docker-php-ext-install zip pdo pdo_pgsql pgsql

# Install Composer package manager
RUN curl -s http://getcomposer.org/installer | php

USER slackemon

# Install dependencies
RUN php composer.phar install
