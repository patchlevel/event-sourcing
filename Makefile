help:                                                                           ## shows this help
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_\-\.]+:.*?## / {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

vendor: composer.lock
	composer install

.PHONY: phpcs-check
phpcs-check: vendor                                                             ## run phpcs
	vendor/bin/phpcs

.PHONY: phpcs-fix
phpcs-fix: vendor                                                               ## run phpcs fixer
	vendor/bin/phpcbf

.PHONY: phpstan
phpstan: vendor                                                                 ## run phpstan static code analyser
	vendor/bin/phpstan analyse

.PHONY: psalm
psalm: vendor                                                                   ## run psalm static code analyser
	vendor/bin/psalm

.PHONY: phpunit
phpunit: vendor                                                                 ## run phpunit tests
	vendor/bin/phpunit --testdox --colors=always -v $(OPTIONS)

.PHONY: static
static: phpstan psalm phpcs-check                                               ## run static analyser

test: phpunit                                                                   ## run tests

.PHONY: dev
dev: static test                                                                ## run dev tools
