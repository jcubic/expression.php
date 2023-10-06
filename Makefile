
all: vendor

vendor:
	composer install

test:
	vendor/bin/phpunit

