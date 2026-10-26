# Virgosoft Exchange

Virgosoft Exchange is a Laravel 13 cryptocurrency limit-order trading
assessment. The backend implements wallet balances, buy and sell reservations,
cancellation, an open orderbook, full-match settlement, order history,
commission calculation, PostgreSQL concurrency protection, and optional
persisted trade records. The Inertia/Vue screens provide the live trading demo
with limit-order placement, wallet/order history, orderbook polling, and
private-channel updates when Pusher is configured.

## Stack

- Laravel 13.38, PHP 8.5 Sail runtime (Composer requires PHP ^8.3), Fortify,
  Sanctum, PHPUnit, Pint, and Larastan
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
make dev
```

`make setup` creates `.env` when needed, installs Composer dependencies,
builds and starts Sail, generates an application key when one is not already
configured, and installs frontend dependencies. Docker keeps frontend
dependencies in a Linux-specific `sail-node-modules` volume, so host macOS or
Windows native bindings cannot interfere with Sail’s frontend commands.

The pull-request CI also runs this exact `make setup` path from a clean Docker
checkout before running the backend tests, frontend tests, and production
build. This keeps the documented setup path separate from the faster host-tool
CI checks.

Keep `make dev` running in a terminal; it starts Laravel, Vite, and the queue
listener before you open the application. A separate queue worker is not
needed while this command is running.

The application is available at <http://localhost:8080>. The Vite development
server uses port `5173`. PostgreSQL runs in the `pgsql` service and is exposed
to host tools on `127.0.0.1:5432` by default.

Useful commands:

```shell
make dev                         # Laravel, Vite, and queue listener
./vendor/bin/sail artisan queue:work # Only when running without make dev
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
  20 per page. Optional `symbol=BTC|ETH`, `side=buy|sell`, and
  `status=1|2|3` filters combine with AND; `page` selects a page and all
  active filters are preserved in pagination links.
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
- USD trade value is calculated with exact decimal arithmetic and rounded up to
  cents before commission is calculated.
- The buyer pays a `1.5%` commission on that rounded trade value. The fee is
  also rounded up to the nearest cent, with a one-cent minimum for positive
  trades.
- Buy reservations cover the limit-price cost plus the commission. Any price
  improvement is refunded during settlement.
- Sell reservations move the asset quantity from available to locked balance.
- Matching excludes self-orders and rejects insufficient available funds or
  assets without changing the order or wallet state.

Placement, matching, settlement, cancellation, trade persistence, and the
`OrderMatched` event run within the relevant database transaction. PostgreSQL
transaction-scoped advisory locks serialize operations for the same market
before participant row locks, preventing empty-book races. Placement and
cancellation transactions retry detected deadlocks up to three times; the
market lock does not eliminate every possible cross-market deadlock.

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
QUEUE_CONNECTION=database
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

Never expose `PUSHER_APP_SECRET` to the browser or commit `.env`. Rotate any
secret shared in chat or other public output and replace its local value.
After changing credentials, stop any existing development process, then run:

```shell
./vendor/bin/sail artisan config:clear
make dev
```

`make dev` includes Laravel's queue listener. Only start a separate worker
when running the application without `make dev`:

```shell
./vendor/bin/sail artisan queue:work
```

Without public Pusher settings, the wallet shows `Polling only` and refreshes
the visible orderbook every 15 seconds. A dropped configured connection shows
`Offline`; polling continues while the page is visible.

The automated suite verifies private-channel ownership, event payloads,
after-commit dispatch, rollback behavior, and concurrency. A real Pusher
credential/browser check is still required to verify external delivery.

To verify delivery with the initial funded demo accounts, use separate browser
profiles or a normal and private window; ordinary tabs share the login session.
As `counterparty@example.com`, sell `0.01000000 BTC` at `95000.00`. Then, as
`demo@example.com`, buy the same quantity at the same price. Verify that both
private-channel authorization requests succeed and both sessions receive
`order.matched`, refresh balances, and show filled orders without reloading.
The buyer should finish with `35.75 USD` and `1.01000000 BTC`; the seller
should finish with `1950.00 USD` and `0.99000000 BTC`. Both reservations should
be cleared. These totals assume neither account has traded previously.

## Tests and quality

```shell
make test
make test-file file=tests/Feature/Trading/OrderMatchingTest.php
make test-filter filter="users_can_authenticate"
make frontend-test
make lint
make analyse
make build
npm run types:e2e
npx playwright install chromium webkit
npm run e2e:prepare
npm run test:e2e
```

## Browser tests

Playwright runs against a separate `virgosoft_e2e` PostgreSQL database. The
guarded `e2e:prepare` command refuses to reset any other database. Run it once
to migrate and seed the database; the browser fixture then uses
`e2e:prepare --reset` before each test to truncate application tables without
dropping the schema needed by the queue worker. The browser server uses
database sessions and queues, built frontend assets, and one worker so
retrying a test starts from a fresh scenario.

For a local run, create the database once, configure the E2E environment, and
start a local Pusher-compatible server such as Soketi before running the
browser suite. For example, with PostgreSQL available through Sail:

```shell
docker compose exec pgsql createdb -U virgosoft virgosoft_e2e
docker run --detach --name soketi --publish 6001:6001 \
  --env SOKETI_DEFAULT_APP_ID=e2e-app \
  --env SOKETI_DEFAULT_APP_KEY=e2e-key \
  --env SOKETI_DEFAULT_APP_SECRET=e2e-secret \
  quay.io/soketi/soketi:1.4-16-debian
```

Then run the E2E commands from a PHP/Node environment that can reach the
database, with `DB_HOST=127.0.0.1`, `DB_DATABASE=virgosoft_e2e`,
`BROADCAST_CONNECTION=pusher`, `QUEUE_CONNECTION=database`,
`SESSION_DRIVER=database`, and the matching local Pusher values. Build the
frontend after those values are configured:

```shell
export APP_ENV=testing DB_HOST=127.0.0.1 DB_DATABASE=virgosoft_e2e
export BROADCAST_CONNECTION=pusher QUEUE_CONNECTION=database SESSION_DRIVER=database
export PUSHER_APP_ID=e2e-app PUSHER_APP_KEY=e2e-key PUSHER_APP_SECRET=e2e-secret
export PUSHER_APP_CLUSTER=mt1 PUSHER_HOST=127.0.0.1 PUSHER_PORT=6001 PUSHER_SCHEME=http
export VITE_PUSHER_APP_KEY=e2e-key VITE_PUSHER_APP_CLUSTER=mt1
export VITE_PUSHER_HOST=127.0.0.1 VITE_PUSHER_PORT=6001 VITE_PUSHER_SCHEME=http
export VITE_E2E=true
php artisan e2e:prepare
npm run build
npx playwright install chromium webkit
npm run types:e2e
npm run test:e2e
```

Keep `php artisan queue:work` running in a separate terminal while the browser
suite executes. It is required for queued broadcast delivery:

```shell
php artisan queue:work
```

The required pull-request E2E job uses the same flow with Soketi and uploads
Playwright reports, screenshots, traces, and server logs. The manually
triggered `Hosted Pusher E2E` workflow uses repository secrets and the hosted
Pusher service instead. The match tests include a reconnect case: the buyer
places an open order, loses connectivity while the seller fills it, and
verifies the buyer catches up after its private channel resubscribes without a
page reload. The former proves the application integration without external
credentials; only the latter proves delivery through Pusher itself.
Neither workflow resets the development database or exposes a reset route.

Backend tests use PostgreSQL. Run `make db-up` first when the database is not
running. The Compose init script creates `virgosoft_testing` alongside the
development and E2E databases.

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
  and that `make dev`'s queue listener or a standalone worker is running.
