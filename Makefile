run:
	php src/bootstrap.php

install:
	composer install

test:
	@if [ -f vendor/bin/phpunit ]; then vendor/bin/phpunit; else echo "No tests found"; fi

clean:
	rm -rf vendor

.PHONY: all run install test clean
all: install
