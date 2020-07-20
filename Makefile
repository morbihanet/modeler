.PHONY: test
.PHONY: test_stop
.PHONY: release
release:
	./release.sh --env=production -p
test:
	php vendor/bin/phpunit
test_stop:
	php vendor/bin/phpunit --stop-on-failure
