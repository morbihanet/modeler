.PHONY: test
test: vendor/autoload.php
	vendor/bin/phpunit tests/Modeler.php