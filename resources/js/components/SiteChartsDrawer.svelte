<script lang="ts">
    import SiteChartsPanel from '@/components/SiteChartsPanel.svelte';
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
</script>

<Sheet bind:open>
    <SheetContent
        side="right"
        class="fixed w-full !max-w-none gap-6 overflow-y-auto p-6 sm:w-[95vw] sm:!max-w-[1400px]"
    >
        {#if site}
            <SheetHeader class="gap-1 p-0">
                <SheetTitle>Графики — {site.name}</SheetTitle>
                <SheetDescription>
                    {site.domain ?? 'Домен не указан'}
                </SheetDescription>
            </SheetHeader>

            <SiteChartsPanel {site} enabled={open} />
        {/if}
    </SheetContent>
</Sheet>
