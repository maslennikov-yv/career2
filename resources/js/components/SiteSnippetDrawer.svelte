<script lang="ts">
    import { toast } from 'svelte-sonner';
    import { Button } from '@/components/ui/button';
    import {
        Sheet,
        SheetContent,
        SheetDescription,
        SheetHeader,
        SheetTitle,
    } from '@/components/ui/sheet';
    import type { Site } from '@/types';

    let {
        site,
        open = $bindable(false),
    }: {
        site: Site | null;
        open?: boolean;
    } = $props();

    function buildSnippet(currentSite: Site): string {
        const origin =
            typeof window !== 'undefined' ? window.location.origin : '';
        const close = '</' + 'script>';

        return `<script async src="${origin}/tracker.js" data-site-id="${currentSite.public_id}" data-endpoint="${origin}/api/track">${close}`;
    }

    function copySnippet(text: string) {
        navigator.clipboard.writeText(text).then(
            () => toast.success('Скопировано'),
            () => toast.error('Не удалось скопировать'),
        );
    }
</script>

<Sheet bind:open>
    <SheetContent
        side="right"
        class="fixed w-full !max-w-3xl gap-6 sm:w-3/4 sm:!max-w-3xl"
    >
        {#if site}
            <SheetHeader class="gap-1 p-0">
                <SheetTitle>Код — {site.name}</SheetTitle>
                <SheetDescription>
                    {site.domain ?? 'Домен не указан'}
                </SheetDescription>
            </SheetHeader>

            <section class="flex flex-col gap-2">
                <header class="flex items-baseline justify-between">
                    <h3 class="text-sm font-semibold">
                        Сниппет для встраивания
                    </h3>
                    <span class="text-xs text-muted-foreground">
                        public_id:
                        <code class="font-mono">{site.public_id}</code>
                    </span>
                </header>
                <p class="text-xs text-muted-foreground">
                    Вставьте этот код перед закрывающим
                    <code>&lt;/body&gt;</code> на каждой странице, посещения которой
                    нужно считать.
                </p>
                <pre
                    class="overflow-x-auto rounded-md border bg-muted p-3 text-xs"><code
                        >{buildSnippet(site)}</code
                    ></pre>
                <div>
                    <Button
                        variant="outline"
                        size="sm"
                        onclick={() => copySnippet(buildSnippet(site))}
                    >
                        Скопировать
                    </Button>
                </div>
            </section>
        {/if}
    </SheetContent>
</Sheet>
