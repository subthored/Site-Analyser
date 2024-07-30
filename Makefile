PORT ?= 8000
start:
	PHP_CLI_SERVER_WORKERS=5 php -S 0.0.0.0:$(PORT) -t public public/index.php

install:
	composer install

lint:
	composer exec --verbose phpcs -- --standard=PSR12 app public src

create:
	./bin/create_tables