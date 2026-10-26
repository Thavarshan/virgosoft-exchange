export function csrfTokenFromMeta(): string | undefined {
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') || undefined
    );
}

export function csrfTokenFromCookie(): string | undefined {
    const cookie = document.cookie
        .split('; ')
        .find((value) => value.startsWith('XSRF-TOKEN='));

    return cookie
        ? decodeURIComponent(cookie.slice('XSRF-TOKEN='.length))
        : undefined;
}

export function csrfHeadersFromCookie(): Record<string, string> {
    const token = csrfTokenFromCookie();

    return token
        ? {
              'X-XSRF-TOKEN': token,
              'X-Requested-With': 'XMLHttpRequest',
          }
        : {
              'X-Requested-With': 'XMLHttpRequest',
          };
}

export function csrfHeaders(): Record<string, string> {
    const token = csrfTokenFromMeta();

    return token
        ? {
              'X-CSRF-TOKEN': token,
              'X-Requested-With': 'XMLHttpRequest',
          }
        : {
              'X-Requested-With': 'XMLHttpRequest',
          };
}
