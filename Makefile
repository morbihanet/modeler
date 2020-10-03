.PHONY: test
.PHONY: test_stop
.PHONY: release
release:
	do_release production
test:
	php vendor/bin/phpunit --testdox
test_stop:
	php vendor/bin/phpunit --stop-on-failure --testdox
