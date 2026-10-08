FROM ghcr.io/grobolof/laravel:php-8.5-fpm-trixie

USER root

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libreoffice-writer \
        fonts-liberation \
        fonts-dejavu-core \
    && rm -rf /var/lib/apt/lists/*
