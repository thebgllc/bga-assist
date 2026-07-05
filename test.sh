docker run --rm -v $(pwd):/app -w /app php:8.2-cli \
  bash -c "apt-get update -q && apt-get install -qy libxml2-dev unzip curl && \
           docker-php-ext-install dom && \
           curl -sS https://getcomposer.org/installer | php && \
           php composer.phar install && \
           ./vendor/bin/phpunit"