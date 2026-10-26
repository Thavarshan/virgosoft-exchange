export { cn } from "cn"

export function toUrl(href: string | { url: string }): string {
    return typeof href === 'string' ? href : href.url;
}
