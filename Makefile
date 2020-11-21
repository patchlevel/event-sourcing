help:                                                                           ## shows this help
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_\-\.]+:.*?## / {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

.PHONY: php-cs-check
php-cs-check:                                                                   ## run cs fixer (dry-run)
	vendor/bin/php-cs-fixer fix --diff --dry-run

.PHONY: php-cs-fix
php-cs-fix:                                                                     ## run cs fixer
	vendor/bin/php-cs-fixer fix

.PHONY: phpstan
phpstan:                                                                        ## run phpstan static code analyser
	vendor/bin/phpstan analyse

.PHONY: psalm
psalm:                                                                          ## run psalm static code analyser
	vendor/bin/psalm

.PHONY: phpunit
phpunit:                                                                        ## run phpunit tests
	vendor/bin/phpunit --testdox --colors=always -v $(OPTIONS)

.PHONY: static
static: php-cs-fix phpstan psalm                                                ## run static analyser

.PHONY: test
test: phpunit                                                                   ## run tests

.PHONY: dev
dev: static test                                                                ## run dev tools
