.PHONY: setup db-up db-down dev migrate test test-file test-filter frontend-test lint analyse build fresh

setup:
	@if [ ! -f .env ]; then cp .env.example .env; fi
	docker run --rm -u "$$(id -u):$$(id -g)" -v "$$(pwd):/app" -w /app composer:2 composer install --no-interaction
	./vendor/bin/sail build
	./vendor/bin/sail up -d
	@if ! grep -Eq '^APP_KEY=.+$$' .env; then ./vendor/bin/sail artisan key:generate --force; fi
	./vendor/bin/sail composer install
	./vendor/bin/sail npm install

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
