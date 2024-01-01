CURL=curl
GREP=grep
README_TMP=readme.html
USER=jcubic
REPO=expression.php

.PHONY: purge

all: vendor src/jcubic/Parser.php

vendor:
	composer install

test:
	XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-text

src/jcubic/Parser.php: src/jcubic/Parser.peg.php
	php compile.php ./src/jcubic/Parser.peg.php > src/jcubic/Parser.php

purge:
	$(CURL) -s https://github.com/$(USER)/$(REPO)/blob/master/README.md > $(README_TMP)
	$(GREP) -Eo '<img src="[^"]+"' $(README_TMP) | $(GREP) camo | $(GREP) -Eo 'https[^"]+' | xargs -I {} $(CURL) -w "\n" -s -X PURGE {}
