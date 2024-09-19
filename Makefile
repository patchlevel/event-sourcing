help:                                                                           ## shows this help
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_\-\.]+:.*?## / {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

vendor: composer.lock
	composer install

vendor-tools: tools/composer.lock
	cd tools && composer install

.PHONY: cs-check
cs-check: vendor                                                                ## run phpcs
	vendor/bin/phpcs

.PHONY: cs
cs: vendor                                                                      ## run phpcs fixer
	vendor/bin/phpcbf || true
	vendor/bin/phpcs

.PHONY: phpstan
phpstan: vendor                                                                 ## run phpstan static code analyser
	vendor/bin/phpstan analyse

.PHONY: phpstan-baseline
phpstan-baseline: vendor                                                        ## run phpstan static code analyser
	vendor/bin/phpstan analyse --generate-baseline

.PHONY: psalm
psalm: vendor                                                                   ## run psalm static code analyser
	vendor/bin/psalm

.PHONY: psalm-baseline
psalm-baseline: vendor                                                          ## run psalm static code analyser
	vendor/bin/psalm --update-baseline --set-baseline=baseline.xml

.PHONY: phpunit
phpunit: vendor phpunit-unit phpunit-integration                              	## run phpunit tests

.PHONY: phpunit-integration
phpunit-integration: vendor                                                    	## run phpunit integration tests
	vendor/bin/phpunit --testsuite=integration

.PHONY: phpunit-integration-postgres
phpunit-integration-postgres: vendor                                            ## run phpunit integration tests on postgres
	DB_URL="pdo-pgsql://postgres:postgres@localhost:5432/eventstore?charset=utf8" vendor/bin/phpunit --testsuite=integration

.PHONY: phpunit-unit
phpunit-unit: vendor                                             				## run phpunit unit tests
	XDEBUG_MODE=coverage vendor/bin/phpunit --testsuite=unit

.PHONY: infection
infection: vendor                                                               ## run infection
	php -d memory_limit=312M vendor/bin/roave-infection-static-analysis-plugin --threads=max

.PHONY: deptrac
deptrac: vendor-tools                                                           ## run deptrac
	cd tools && ./vendor/bin/deptrac -c ../deptrac.yaml

.PHONY: deptrac-baseline
deptrac-baseline: vendor-tools                                                 ## run deptrac and update baseline
	cd tools && ./vendor/bin/deptrac -c ../deptrac.yaml --formatter=baseline --output=../deptrac-baseline.yaml

.PHONY: static
static: psalm phpstan cs                                              			 ## run static analyser

test: phpunit                                                                   ## run tests

.PHONY: benchmark
benchmark: vendor                                                               ## run benchmarks
	DB_URL=sqlite3:///:memory: php -d memory_limit=512M vendor/bin/phpbench run tests/Benchmark --report=default

.PHONY: benchmark-base
benchmark-base: vendor                                                   	## run benchmarks
	DB_URL=sqlite3:///:memory: vendor/bin/phpbench run tests/Benchmark --revs=1 --report=default --progress=none --tag=base

.PHONY: benchmark-diff
benchmark-diff: vendor                                                   	## run benchmarks
	DB_URL=sqlite3:///:memory: vendor/bin/phpbench run tests/Benchmark --revs=1 --report=diff --progress=none --ref=base

.PHONY: benchmark-diff-test
benchmark-diff-test: benchmark-base benchmark-diff                                                	## run benchmarks

.PHONY: dev
dev: static test                                                                ## run dev tools

.PHONY: docs
docs: mkdocs                                                                          ## run mkdocs
	cd docs && python3 -m mkdocs serve

.PHONY: mkdocs
mkdocs:                                                                         ## run mkdocs
	cd docs && pip3 install -r requirements.txt

.PHONY: docs-extract-php
docs-extract-php:
	bin/docs-extract-php-code

.PHONY: docs-inject-php
docs-inject-php:
	bin/docs-inject-php-code

.PHONY: docs-format
docs-format: docs-phpcs docs-inject-php

.PHONY: docs-php-lint
docs-php-lint: docs-extract-php
	php -l docs_php/*.php | grep 'Parse error: '

.PHONY: docs-phpcs
docs-phpcs: docs-extract-php
	vendor/bin/phpcbf docs_php --exclude=SlevomatCodingStandard.TypeHints.DeclareStrictTypes || true

.PHONY: docs-psalm
docs-psalm: docs-extract-php
	vendor/bin/psalm --config=psalm_docs.xml
