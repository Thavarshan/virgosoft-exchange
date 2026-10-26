#!/usr/bin/env bash
#
# Shared commit rules for the local git hooks and the CI identity guard.
#
#   scripts/guard.sh identity            author and committer for the next commit
#   scripts/guard.sh message <file>      commit message file
#   scripts/guard.sh range <base> <head> every commit in base..head
#                                        (base NEW: commits no remote has,
#                                         base ALL: the whole history)
#   scripts/guard.sh text                free text on stdin (PR title or body)

set -euo pipefail

ALLOWED_NAME='Thavarshan'
ALLOWED_EMAIL='tjthavarshan@gmail.com'

# Bracketed letters keep this file from matching its own pattern.
BANNED_PATTERN='clau[d]e|anthrop[i]c|jeromesinola[n]ka|sinolank[a]'

fail() {
    printf 'guard: %s\n' "$1" >&2
    exit 1
}

check_identity() {
    local expected="${ALLOWED_NAME} <${ALLOWED_EMAIL}>"
    local role ident

    for role in GIT_AUTHOR_IDENT GIT_COMMITTER_IDENT; do
        # "Name <email> <timestamp> <timezone>" -> "Name <email>"
        ident="$(git var "$role")"
        ident="${ident% * *}"

        if [ "$ident" != "$expected" ]; then
            fail "${role#GIT_} is '${ident}'; commits must be made as '${expected}'. Fix it with: git config --local user.name '${ALLOWED_NAME}' && git config --local user.email '${ALLOWED_EMAIL}'"
        fi
    done
}

check_banned() {
    if printf '%s' "$1" | grep -Eiq "$BANNED_PATTERN"; then
        fail "$2 contains a term that must not appear in this repository's history."
    fi
}

check_message() {
    local file="$1"
    local message subject second

    # Mirror git's default cleanup: drop comment lines before judging.
    message="$(grep -v '^#' "$file" || true)"
    subject="$(printf '%s\n' "$message" | sed -n '1p')"
    second="$(printf '%s\n' "$message" | sed -n '2p')"

    check_banned "$message" 'The commit message'

    case "$subject" in
        'Merge '* | 'Revert "'*) return 0 ;;
    esac

    [ -n "$subject" ] || fail 'The commit message is empty.'
    [ "${#subject}" -le 72 ] || fail "The subject is ${#subject} characters; keep it to 72 or fewer."
    printf '%s' "$subject" | grep -Eq '^[A-Z]' || fail 'The subject must start with a capital letter (imperative mood, e.g. "Add order filters").'
    printf '%s' "$subject" | grep -Eq '\.$' && fail 'The subject must not end with a period.'
    [ -z "$second" ] || fail 'Leave a blank line between the subject and the body.'

    return 0
}

check_range() {
    local base="$1" head="$2"
    local sha author email
    local -a revs

    # NEW means a branch the remote has not seen: check what no remote has.
    if [ "$base" = 'NEW' ]; then
        mapfile -t revs < <(git rev-list "$head" --not --remotes)
    elif [ "$base" = 'ALL' ]; then
        mapfile -t revs < <(git rev-list "$head")
    else
        mapfile -t revs < <(git rev-list "${base}..${head}")
    fi

    for sha in "${revs[@]}"; do
        [ -n "$sha" ] || continue

        author="$(git log -1 --format='%an' "$sha")"
        email="$(git log -1 --format='%ae' "$sha")"

        if [ "$author" != "$ALLOWED_NAME" ] || [ "$email" != "$ALLOWED_EMAIL" ]; then
            fail "Commit ${sha:0:12} is authored by '${author} <${email}>'; only '${ALLOWED_NAME} <${ALLOWED_EMAIL}>' is allowed."
        fi

        check_banned "$(git log -1 --format='%B' "$sha")" "Commit ${sha:0:12}"
    done
}

case "${1:-}" in
    identity) check_identity ;;
    message) check_message "${2:?message file required}" ;;
    range) check_range "${2:?base required}" "${3:?head required}" ;;
    text) check_banned "$(cat)" 'The text' ;;
    *) fail 'usage: guard.sh identity | message <file> | range <base> <head> | text' ;;
esac
