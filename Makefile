user := $(shell id -u)
group := $(shell id -g)
dc := USER_ID=$(user) GROUP_ID=$(group) docker-compose
dr := $(dc) run --rm
de := docker-compose exec
sy := $(de) php bin/console
drtest := $(dc) -f docker-compose.yml run --rm

.PHONY: test
test: vendor/autoload.php ## Execute les tests
	$(drtest) php vendor/bin/phpunit tests/Modeler.php