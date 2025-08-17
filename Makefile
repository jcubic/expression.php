CURL=curl
GREP=grep
README_TMP=readme.html
USER=jcubic
PHP=$(shell command -v php82 >/dev/null 2>&1 && echo php82 || (php -v 2>&1 | grep -q "PHP 8.2" && echo php || echo php))
REPO=expression.php

.PHONY: purge

all: vendor src/jcubic/Parser.php

vendor:
	composer install

test:
	XDEBUG_MODE=coverage $(PHP) vendor/bin/phpunit --coverage-text --display-deprecations

src/jcubic/Parser.php: src/jcubic/Parser.peg.php
	$(PHP) compile.php ./src/jcubic/Parser.peg.php > src/jcubic/Parser.php

purge:
	$(CURL) -s https://github.com/$(USER)/$(REPO)/blob/master/README.md > $(README_TMP)
	$(GREP) -Eo '<img src="[^"]+"' $(README_TMP) | $(GREP) camo | $(GREP) -Eo 'https[^"]+' | xargs -I {} $(CURL) -w "\n" -s -X PURGE {}
