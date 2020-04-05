.PHONY: test
.PHONY: test_stop
test:
	php vendor/bin/phpunit tests/Modeler.php
test_stop:
	php vendor/bin/phpunit --stop-on-failure tests/Modeler.php