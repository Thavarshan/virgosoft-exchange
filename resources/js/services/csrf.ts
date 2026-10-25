export function csrfTokenFromMeta(): string | undefined {
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') || undefined
    );
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
