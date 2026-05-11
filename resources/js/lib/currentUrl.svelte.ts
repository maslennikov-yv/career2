import type { LinkComponentBaseProps } from '@inertiajs/core';
import { page } from '@inertiajs/svelte';
import { toUrl } from '@/lib/utils';

type CurrentUrlState = {
    readonly currentUrl: string;
    isCurrentUrl: (
        urlToCheck: NonNullable<LinkComponentBaseProps['href']>,
        currentUrl: string,
    ) => boolean;
};

export function currentUrlState(): CurrentUrlState {
    const currentUrl = $derived.by(() => {
        const origin =
            typeof window === 'undefined'
                ? 'http://localhost'
                : window.location.origin;

        try {
            return new URL(page.url, origin).pathname;
        } catch {
            return page.url;
        }
    });

    function isCurrentUrl(
        urlToCheck: NonNullable<LinkComponentBaseProps['href']>,
        current: string,
    ): boolean {
        const resolved = toUrl(urlToCheck);

        if (typeof resolved !== 'string') {
            return false;
        }

        return current === resolved;
    }

    return {
        get currentUrl() {
            return currentUrl;
        },
        isCurrentUrl,
    };
}
