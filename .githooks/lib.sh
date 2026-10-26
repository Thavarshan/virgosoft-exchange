#!/usr/bin/env bash
# Shared helpers for the git hooks. Sourced, never executed directly.

ROOT="$(git rev-parse --show-toplevel)"
cd "$ROOT"

step() {
    printf '\n==> %s\n' "$*"
}

die() {
    printf '\nhook: %s\n' "$*" >&2
    exit 1
}

sail_running() {
    [ -x vendor/bin/sail ] \
        && command -v docker >/dev/null 2>&1 \
        && docker compose ps --status running --services 2>/dev/null | grep -qx 'laravel.test'
}

host_ready() {
    command -v php >/dev/null 2>&1 \
        && command -v npm >/dev/null 2>&1 \
        && [ -f vendor/autoload.php ] \
        && [ -d node_modules ]
}

# Pick where PHP and Node run: Sail when it is up, host tools otherwise.
select_runner() {
    if sail_running; then
        RUNNER=sail
    elif host_ready; then
        RUNNER=host
    else
        die "No way to run the checks. Start Sail (make setup && ./vendor/bin/sail up -d) or install PHP, Composer and Node dependencies on the host."
    fi
}

artisan() {
    if [ "$RUNNER" = sail ]; then
        ./vendor/bin/sail artisan "$@"
    else
        php artisan "$@"
    fi
}

# Run a Composer-installed binary such as pint or phpstan.
vbin() {
    local tool="$1"
    shift

    if [ "$RUNNER" = sail ]; then
        ./vendor/bin/sail bin "$tool" "$@"
    else
        "./vendor/bin/${tool}" "$@"
    fi
}

npm_cmd() {
    if [ "$RUNNER" = sail ]; then
        ./vendor/bin/sail npm "$@"
    else
        npm "$@"
    fi
}

# Route helpers are generated and gitignored; type checks need them.
generate_wayfinder() {
    artisan wayfinder:generate --with-form
}
