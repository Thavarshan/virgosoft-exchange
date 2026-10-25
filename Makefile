.PHONY: setup db-up db-down dev migrate test test-file test-filter frontend-test lint analyse build fresh

setup:
	composer install
	@if [ ! -f .env ]; then cp .env.example .env; php artisan key:generate; fi
	npm install

db-up:
	docker compose up -d postgres

db-down:
	docker compose stop postgres

dev:
	composer run dev

migrate:
	php artisan migrate

test:
	php artisan test --compact

test-file:
	php artisan test --compact $(file)

test-filter:
	php artisan test --compact --filter=$(filter)

frontend-test:
	npm run test

lint:
	vendor/bin/pint --dirty --format agent
	npm run lint

analyse:
	vendor/bin/phpstan analyse
	npm run types:check

build:
	npm run build

fresh:
	php artisan migrate:fresh --seed
