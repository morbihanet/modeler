.PHONY: test
.PHONY: test_stop
test:
	php vendor/bin/phpunit
test_stop:
	php vendor/bin/phpunit --stop-on-failure
