<script module lang="ts">
    import sitesRoutes from '@/routes/sites';

    export const layout = {
        breadcrumbs: [{ title: 'Сайты', href: sitesRoutes.index() }],
    };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import BarChart3 from 'lucide-svelte/icons/bar-chart-3';
    import Code2 from 'lucide-svelte/icons/code-2';
    import Pencil from 'lucide-svelte/icons/pencil';
    import Plus from 'lucide-svelte/icons/plus';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import AppHead from '@/components/AppHead.svelte';
    import Heading from '@/components/Heading.svelte';
    import SiteChartsDrawer from '@/components/SiteChartsDrawer.svelte';
    import SiteFormDialog from '@/components/SiteFormDialog.svelte';
    import SiteSnippetDrawer from '@/components/SiteSnippetDrawer.svelte';
    import { Button } from '@/components/ui/button';
    import {
        Card,
        CardContent,
        CardHeader,
        CardTitle,
    } from '@/components/ui/card';
    import type { Site } from '@/types';
    import { toUrl } from '@/lib/utils';
    import sites from '@/routes/sites';

    let { sites: list }: { sites: Site[] } = $props();

    let chartsOpen = $state(false);
    let snippetOpen = $state(false);
    let formOpen = $state(false);
    let chartsSite = $state<Site | null>(null);
    let snippetSite = $state<Site | null>(null);
    let formSite = $state<Site | null>(null);

    function openCharts(site: Site) {
        chartsSite = site;
        chartsOpen = true;
    }

    function openSnippet(site: Site) {
        snippetSite = site;
        snippetOpen = true;
    }

    function openForm(site: Site | null) {
        formSite = site;
        formOpen = true;
    }

    function deleteSite(site: Site) {
        if (
            !confirm(
                `Удалить сайт «${site.name}»? Все посещения будут утеряны.`,
            )
        ) {
            return;
        }

        router.delete(toUrl(sites.destroy(site.id)), { preserveScroll: true });
    }
</script>

<AppHead title="Сайты" />

<div class="flex h-full flex-1 flex-col gap-6 p-4">
    <div class="flex items-center justify-between">
        <Heading title="Сайты" description="Счётчики посещений" />
        <Button onclick={() => openForm(null)}>
            <Plus class="size-4" />
            Создать
        </Button>
    </div>

    {#if list.length === 0}
        <Card>
            <CardContent class="py-10 text-center text-muted-foreground">
                Пока ничего нет — создайте первый счётчик.
            </CardContent>
        </Card>
    {:else}
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            {#each list as site (site.id)}
                <Card>
                    <CardHeader
                        class="flex flex-row items-start justify-between gap-2"
                    >
                        <div class="flex flex-col gap-1">
                            <CardTitle class="text-base">{site.name}</CardTitle>
                            {#if site.domain}
                                <span class="text-xs text-muted-foreground"
                                    >{site.domain}</span
                                >
                            {/if}
                        </div>
                        <div class="flex items-center gap-1">
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                aria-label="Редактировать"
                                onclick={() => openForm(site)}
                            >
                                <Pencil class="size-4" />
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                aria-label="Удалить"
                                onclick={() => deleteSite(site)}
                            >
                                <Trash2 class="size-4 text-destructive" />
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-3">
                        <div class="text-sm text-muted-foreground">
                            Посещений:
                            <span class="font-medium text-foreground"
                                >{site.visits_count ?? 0}</span
                            >
                        </div>
                        <div class="flex gap-2">
                            <Button
                                type="button"
                                variant="default"
                                size="sm"
                                onclick={() => openCharts(site)}
                            >
                                <BarChart3 class="size-4" />
                                Графики
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onclick={() => openSnippet(site)}
                            >
                                <Code2 class="size-4" />
                                Код
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            {/each}
        </div>
    {/if}
</div>

<SiteChartsDrawer site={chartsSite} bind:open={chartsOpen} />
<SiteSnippetDrawer site={snippetSite} bind:open={snippetOpen} />
<SiteFormDialog site={formSite} bind:open={formOpen} />
