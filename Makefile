.PHONY: *

default: unit cs static-analysis ## all the things

help:
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'

unit: ## run unit tests
	vendor/bin/phpunit

cs: ## verify code style rules
	php7.1 vendor/bin/phpcs

static-analysis: ## verify that no new static analysis issues were introduced
	vendor/bin/psalm

coverage: ## generate code coverage reports
	vendor/bin/phpunit --testsuite unit --coverage-html build/coverage-html --coverage-text

unit-php71: ## run unit tests with php 7.1
	php7.1 vendor/bin/phpunit

deps-php71-lowest: ## Update deps to lowest
	php7.1 /usr/local/bin/composer update --prefer-lowest --prefer-dist --no-interaction

deps-php8-lowest: ## Update deps to lowest
	php8.0 /usr/local/bin/composer update --prefer-lowest --prefer-dist --no-interaction

deps-php71-highest: ## Update deps to highest
	php7.1 /usr/local/bin/composer update --prefer-dist --no-interaction

deps-php8-highest: ## Update deps to highest
	php8.0 /usr/local/bin/composer update --prefer-dist --no-interaction
