FROM php:8.3-cli


RUN apt-get update && apt-get install -y libzip-dev libpq-dev
RUN docker-php-ext-install zip pdo pdo_pgsql

RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && php -r "unlink('composer-setup.php');"

WORKDIR /app

COPY . .

RUN composer install

CMD ["bash", "-c", "export DATABASE_URL=postgresql://user:W2iAXYlW7n3DeO7oV10MwOQ91odmuhES@dpg-cqk8bpiju9rs738i8t4g-a.oregon-postgres.render.com/php_project_9"]
CMD ["bash", "-c", "psql -a -d $DATABASE_URL -f database.sql"]
CMD ["bash", "-c", "make create", "make start"]
