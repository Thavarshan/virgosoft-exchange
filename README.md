# Virgosoft Exchange

Virgosoft Exchange is a Laravel 13 + Vue 3 foundation for a cryptocurrency limit-order trading assessment. The repository contains authentication, API, PostgreSQL, queues, sessions, broadcasting, CI, and a polished but intentionally non-functional trading shell. Trading domain logic is deliberately left for incremental TDD implementation.

## Stack

- Laravel 13.38, PHP 8.5+, Fortify, Sanctum, Pest, Pint, Larastan
- PostgreSQL 17, database-backed sessions/cache/queues
- Vue 3, TypeScript, Vite, Tailwind CSS 4, Vue Router, Axios
- Laravel Echo and Pusher JS, Lucide icons, Vitest, Vue Test Utils

## Prerequisites

Install PHP 8.3+, Composer, Node 22+, npm, Docker Desktop, and Git. Docker is used only for PostgreSQL during local development.

## First-time setup

```shell
make setup
make db-up
php artisan migrate --seed
```

The local-only seed creates `test@example.com` and `demo@example.com`, both using the password `password`. The seeder exits without creating users in production.

## Development

```shell
make dev
```

This starts Laravel and Vite through the project’s Composer development command. In another terminal, keep `make db-up` running. Start a queue worker with `php artisan queue:work` when testing queued work.

## Authentication and API

The Vue SPA uses Sanctum’s `/sanctum/csrf-cookie` endpoint, the session cookie, and the protected `GET /api/session` endpoint. Login and logout are Fortify endpoints. Unauthenticated API requests return JSON errors. No auth token is stored in localStorage.

## Pusher

Local development defaults to the `log` broadcast connection and tests use `null`, so credentials are not required. To enable Pusher, set `PUSHER_APP_ID`, `PUSHER_APP_KEY`, `PUSHER_APP_SECRET`, and `PUSHER_APP_CLUSTER` in `.env`, plus the matching `VITE_PUSHER_*` public settings. Never expose the server secret to Vue. Private channels follow `user.{id}` and authorize only the matching user.

## Tests and quality

```shell
make test
make test-file file=tests/Feature/Auth/AuthenticationTest.php
make test-filter filter="users can authenticate"
make frontend-test
make lint
make analyse
make build
```

Backend tests require PostgreSQL. Run `make db-up` first; the Compose init script creates `virgosoft_testing` alongside the development database. `php artisan migrate:fresh --seed` resets disposable development data.

See [docs/development-workflow.md](docs/development-workflow.md) for red-green-refactor TDD, [docs/architecture.md](docs/architecture.md) for design decisions, and [docs/assessment-roadmap.md](docs/assessment-roadmap.md) for the intentionally unimplemented trading work.

## Troubleshooting

- Check PostgreSQL with `docker compose ps` and `docker compose logs postgres`.
- If configuration is stale, run `php artisan optimize:clear`.
- If frontend assets are missing, run `npm run build` or `npm run dev`.
- If auth cookies fail across hosts, keep the browser and Laravel URL on the same host and check `SANCTUM_STATEFUL_DOMAINS`.
