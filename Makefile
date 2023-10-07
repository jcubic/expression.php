CURL=curl
GREP=grep
README_TMP=readme.html
USER=jcubic
REPO=expression.php

.PHONY: purge

all: vendor

vendor:
	composer install

test:
	vendor/bin/phpunit

purge:
	$(CURL) -s https://github.com/$(USER)/$(REPO)/blob/master/README.md > $(README_TMP)
	$(GREP) -Eo '<img src="[^"]+"' $(README_TMP) | $(GREP) camo | $(GREP) -Eo 'https[^"]+' | xargs -I {} $(CURL) -w "\n" -s -X PURGE {}
