# Virgosoft Exchange

Virgosoft Exchange is a Laravel 13 cryptocurrency limit-order trading
assessment. The backend implements wallet balances, buy and sell reservations,
cancellation, an open orderbook, full-match settlement, order history,
commission calculation, PostgreSQL concurrency protection, and optional
persisted trade records. The Inertia/Vue screens are the remaining integration
surface for the live trading demo.

## Stack

- Laravel 13.38, PHP 8.5+, Fortify, Sanctum, PHPUnit, Pint, and Larastan
- PostgreSQL 17 with database-backed sessions, cache, and queues
- Inertia 3, Vue 3, TypeScript, Vite, Tailwind CSS 4, and Wayfinder
- Axios for API requests, Laravel Echo and Pusher JS for private channels
- Laravel Sail and Docker Compose for the application and PostgreSQL services

## Prerequisites

Install Docker Desktop and Git. PHP, Composer, Node, npm, PostgreSQL, and the
application runtime are provided by Laravel Sail.

## Sail setup

From a fresh checkout:

```shell
make setup
./vendor/bin/sail artisan migrate --seed
```

`make setup` creates `.env` when needed, installs Composer dependencies,
builds and starts Sail, generates an application key when one is not already
configured, and installs frontend dependencies.

The application is available at <http://localhost:8080>. The Vite development
server uses port `5173`. PostgreSQL runs in the `pgsql` service and is exposed
to host tools on `127.0.0.1:5432` by default.

Useful commands:

```shell
make dev                         # Laravel and Vite development processes
./vendor/bin/sail artisan queue:work
make migrate
make fresh                       # destructive disposable-data reset
```

Inside the application container, use `DB_HOST=pgsql`. From TablePlus or
another host application, use `127.0.0.1`, port `5432`, and the credentials in
`.env`.

## Demo accounts

Local and testing seeds create two accounts. Both use the password
`password`, start with `$1000.00`, `1.00000000 BTC`, and `10.00000000 ETH`:

- `demo@example.com`
- `counterparty@example.com`

Run `./vendor/bin/sail artisan app:seed-default-user` to create only the
default demo account. Seeders are restricted to local and testing
environments. They initialize missing users and asset rows but do not overwrite
existing balances, reservations, or orders when rerun.

## Backend API

All API responses are JSON. Sanctum session authentication protects private
endpoints; guests receive `401` responses.

- `GET /api/session` — current authenticated user.
- `GET /api/profile` — current USD and asset balances.
- `GET /api/profile/orders` — the user’s complete order history,
  20 per page.
- `GET /api/orders?symbol=BTC` — shared open orderbook.
- `POST /api/orders` — place a buy or sell order and reserve funds.
- `POST /api/orders/{order}/cancel` — cancel an owned open order and release
  its reservation.

Order history includes open, filled, and cancelled orders. It is sorted by
`created_at` descending and then `id` descending. The shared orderbook returns
only open orders for the requested symbol.

## Trading policies

The assessment leaves several matching details open, so the implementation
uses these documented choices:

- Only full matches are supported. Buy and sell quantities must be equal;
  partial fills remain open and no partial-fill columns are required.
- The oldest compatible counter-order is selected first. Compatibility means
  opposite sides, the same symbol, equal quantity, and crossing prices.
- Execution uses the existing counter-order’s price, not the incoming
  order’s limit price.
- The buyer pays a `1.5%` commission on the executed USD trade value. The fee
  is rounded up to the nearest cent, with a one-cent minimum for positive
  trades.
- Buy reservations cover the limit-price cost plus the commission. Any price
  improvement is refunded during settlement.
- Sell reservations move the asset quantity from available to locked balance.
- Matching excludes self-orders and rejects insufficient available funds or
  assets without changing the order or wallet state.

Placement, matching, settlement, cancellation, trade persistence, and the
`OrderMatched` event run within the relevant database transaction. PostgreSQL
transaction-scoped advisory locks serialize each market before participant row
locks, preventing empty-book races and opposite lock-order deadlocks.

## Pusher and live updates

`OrderMatched` explicitly uses the `pusher` broadcaster. It is dispatched only
after the settlement transaction commits and is delivered to both
participants’ private channels:

```text
private-user.{user_id}
```

The authorization route is `/broadcasting/auth`. Laravel authenticates the
session and permits only the matching `user.{id}` channel. The frontend
authorizer reads the current `XSRF-TOKEN` cookie for each authorization request,
so login token rotation does not leave a stale CSRF token in the SPA.

To enable actual Pusher delivery, add server credentials and matching public
frontend settings to `.env`:

```dotenv
BROADCAST_CONNECTION=pusher
PUSHER_APP_ID=your-app-id
PUSHER_APP_KEY=your-app-key
PUSHER_APP_SECRET=your-app-secret
PUSHER_APP_CLUSTER=mt1
VITE_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
VITE_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"
VITE_PUSHER_HOST=
VITE_PUSHER_PORT=443
VITE_PUSHER_SCHEME=https
```

Never expose `PUSHER_APP_SECRET` to the browser. Start a queue worker while
testing delivery:

```shell
./vendor/bin/sail artisan queue:work
```

The automated suite verifies private-channel ownership, event payloads,
after-commit dispatch, rollback behavior, and concurrency. A real Pusher
credential/browser check is still required to verify external delivery.

## Tests and quality

```shell
make test
make test-file file=tests/Feature/Trading/OrderMatchingTest.php
make test-filter filter="users_can_authenticate"
make frontend-test
make lint
make analyse
make build
```

Backend tests use PostgreSQL. Run `make db-up` first when the database is not
running. The Compose init script creates `virgosoft_testing` alongside the
development database.

See [docs/development-workflow.md](docs/development-workflow.md) for the
test workflow, [docs/architecture.md](docs/architecture.md) for implementation
decisions, and [docs/assessment-roadmap.md](docs/assessment-roadmap.md) for
completed and remaining assessment scope.

## Troubleshooting

- Check services with `docker compose ps` and PostgreSQL with
  `docker compose logs pgsql`.
- If configuration is stale, run
  `./vendor/bin/sail artisan optimize:clear`.
- If frontend assets are missing, run
  `./vendor/bin/sail npm run build` or `make dev`.
- If auth cookies fail across hosts, keep the browser and Laravel URL on the
  same host and check `SANCTUM_STATEFUL_DOMAINS`.
- If Pusher jobs are not delivered, confirm credentials, `QUEUE_CONNECTION`,
  and that `queue:work` is running.
