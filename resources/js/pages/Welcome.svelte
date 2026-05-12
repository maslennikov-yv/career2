<script lang="ts">
    import { InfiniteScroll, Link, page, router } from '@inertiajs/svelte';
    import { untrack } from 'svelte';
    import AppHead from '@/components/AppHead.svelte';
    import JokeCard from '@/components/JokeCard.svelte';
    import { Skeleton } from '@/components/ui/skeleton';
    import { pluralizeRu } from '@/lib/pluralize';
    import { toUrl } from '@/lib/utils';
    import type { Joke, JokesPagination } from '@/types/joke';
    import { login, register } from '@/routes';
    import sitesRoutes from '@/routes/sites';

    let {
        canRegister = true,
        jokes,
        latest,
    }: {
        canRegister: boolean;
        jokes: JokesPagination;
        latest?: Joke[];
    } = $props();

    let counter = $state<{ visits: number; uniques: number } | null>(null);

    async function fetchCounter() {
        const res = await fetch('/api/counter');
        if (res.ok) counter = await res.json();
    }

    const auth = $derived(page.props.auth);

    const numberFmt = new Intl.NumberFormat('ru-RU');

    let prepended = $state<Joke[]>([]);
    let announcement = $state('');

    const fromPage = $derived<Joke[]>(jokes.data ?? []);
    const allJokes = $derived<Joke[]>([...prepended, ...fromPage]);
    const newestId = $derived(allJokes[0]?.id ?? 0);

    function pluralizeNewJokes(n: number): string {
        return pluralizeRu(n, [
            `Появилась ${n} новая шутка`,
            `Появилось ${n} новые шутки`,
            `Появилось ${n} новых шуток`,
        ]);
    }

    $effect(() =>
        untrack(() => {
            fetchCounter();

            function reloadIfVisible() {
                if (document.visibilityState !== 'visible') {
                    return;
                }

                fetchCounter();
                router.reload({
                    only: ['latest'],
                    data: { after: newestId },
                });
            }

            const interval = setInterval(reloadIfVisible, 45_000);
            document.addEventListener('visibilitychange', reloadIfVisible);

            return () => {
                clearInterval(interval);
                document.removeEventListener(
                    'visibilitychange',
                    reloadIfVisible,
                );
            };
        }),
    );

    $effect(() => {
        if (!latest?.length) {
            return;
        }

        untrack(() => {
            const seen = new Set([...prepended, ...fromPage].map((j) => j.id));
            const novel = latest!.filter((j) => !seen.has(j.id));

            if (novel.length === 0) {
                return;
            }

            prepended = [...novel, ...prepended];
            announcement = pluralizeNewJokes(novel.length);
        });
    });
</script>

<AppHead title="Шутки" />

<div class="min-h-screen bg-background text-foreground">
    <header class="border-b border-border/60">
        <nav
            class="mx-auto flex max-w-3xl items-center justify-between gap-4 px-4 py-4 sm:px-6"
        >
            <div class="flex items-baseline gap-3">
                <h1 class="text-base font-semibold">Шутки</h1>
                {#if counter}
                    <span
                        class="text-xs text-muted-foreground tabular-nums"
                        aria-label="Счётчик посещений сайта"
                    >
                        {numberFmt.format(counter.visits)}
                        {pluralizeRu(counter.visits, [
                            'визит',
                            'визита',
                            'визитов',
                        ])} ·
                        {numberFmt.format(counter.uniques)}
                        {pluralizeRu(counter.uniques, [
                            'посетитель',
                            'посетителя',
                            'посетителей',
                        ])}
                    </span>
                {/if}
            </div>
            <div class="flex items-center gap-2 text-sm">
                {#if auth.user}
                    <Link
                        href={toUrl(sitesRoutes.index())}
                        class="rounded-sm border border-border px-3 py-1.5 hover:border-foreground/40"
                    >
                        Сайты
                    </Link>
                {:else}
                    <Link
                        href={toUrl(login())}
                        class="px-3 py-1.5 hover:underline"
                    >
                        Log in
                    </Link>
                    {#if canRegister}
                        <Link
                            href={toUrl(register())}
                            class="rounded-sm border border-border px-3 py-1.5 hover:border-foreground/40"
                        >
                            Register
                        </Link>
                    {/if}
                {/if}
            </div>
        </nav>
    </header>

    <main class="mx-auto max-w-3xl px-4 py-6 sm:px-6 sm:py-10">
        <div role="status" aria-live="polite" class="sr-only">
            {announcement}
        </div>

        <aside
            class="mb-6 flex flex-wrap items-center gap-2 rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-xs text-muted-foreground"
        >
            <span>Все записи в JSON:</span>
            <a
                href="/api/jokes"
                target="_blank"
                rel="noopener"
                class="rounded-sm border border-border bg-background px-2 py-0.5 font-mono text-[11px] text-foreground hover:border-foreground/40"
            >
                GET /api/jokes
            </a>
        </aside>

        {#if allJokes.length === 0}
            <div
                role="status"
                aria-live="polite"
                class="rounded-lg border border-dashed border-border p-8 text-center text-sm text-muted-foreground"
            >
                Пока пусто, скоро прилетит.
            </div>
        {:else}
            <InfiniteScroll
                data="jokes"
                buffer={400}
                class="flex flex-col gap-4"
                role="feed"
            >
                {#each allJokes as joke (joke.id)}
                    <JokeCard {joke} />
                {/each}
                {#snippet next({ loading, hasMore })}
                    {#if loading}
                        <Skeleton class="h-24 w-full" />
                    {:else if !hasMore}
                        <p
                            class="py-4 text-center text-xs text-muted-foreground"
                        >
                            Это всё, что у нас есть.
                        </p>
                    {/if}
                {/snippet}
            </InfiniteScroll>
        {/if}
    </main>
</div>
