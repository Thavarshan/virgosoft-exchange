.PHONY: setup hooks db-up db-down dev migrate test test-file test-filter frontend-test lint analyse build fresh

hooks:
	git config --local core.hooksPath .githooks
	chmod +x .githooks/* scripts/*.sh scripts/ci/*.sh
	git config --local user.name Thavarshan
	git config --local user.email tjthavarshan@gmail.com

setup: hooks
	@if [ ! -f .env ]; then cp .env.example .env; fi
	docker run --rm -u "$$(id -u):$$(id -g)" -v "$$(pwd):/app" -w /app composer:2 composer install --no-interaction
	WWWUSER="$$(id -u)" WWWGROUP="$$(id -g)" ./vendor/bin/sail build
	WWWUSER="$$(id -u)" WWWGROUP="$$(id -g)" ./vendor/bin/sail up -d
	WWWUSER="$$(id -u)" WWWGROUP="$$(id -g)" ./vendor/bin/sail root-shell -c 'chown sail:sail /var/www/html/.env && mkdir -p /var/www/html/resources/js/actions /var/www/html/resources/js/routes /var/www/html/resources/js/wayfinder /var/www/html/public/build && chown -R sail:sail /var/www/html/vendor /var/www/html/resources/js/actions /var/www/html/resources/js/routes /var/www/html/resources/js/wayfinder /var/www/html/public/build /var/www/html/storage /var/www/html/bootstrap/cache'
	@if ! grep -Eq '^APP_KEY=.+$$' .env; then WWWUSER="$$(id -u)" WWWGROUP="$$(id -g)" ./vendor/bin/sail artisan key:generate --force; fi
	WWWUSER="$$(id -u)" WWWGROUP="$$(id -g)" ./vendor/bin/sail composer install
	WWWUSER="$$(id -u)" WWWGROUP="$$(id -g)" ./vendor/bin/sail root-shell -c 'chown -R sail:sail /var/www/html/node_modules'
	WWWUSER="$$(id -u)" WWWGROUP="$$(id -g)" ./vendor/bin/sail npm ci

db-up:
	./vendor/bin/sail up -d pgsql

db-down:
	./vendor/bin/sail stop pgsql

dev:
	./vendor/bin/sail composer run dev

migrate:
	./vendor/bin/sail artisan migrate

test:
	./vendor/bin/sail artisan test --compact

test-file:
	./vendor/bin/sail artisan test --compact $(file)

test-filter:
	./vendor/bin/sail artisan test --compact --filter=$(filter)

frontend-test:
	./vendor/bin/sail npm run test

lint:
	./vendor/bin/sail pint --dirty --format agent
	./vendor/bin/sail npm run lint

analyse:
	./vendor/bin/sail bin phpstan analyse
	./vendor/bin/sail npm run types:check

build:
	./vendor/bin/sail npm run build

fresh:
	./vendor/bin/sail artisan migrate:fresh --seed
