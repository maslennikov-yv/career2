<script module lang="ts">
    import sitesRoutes from '@/routes/sites';

    export const layout = {
        breadcrumbs: [{ title: 'Сайты', href: sitesRoutes.index() }],
    };
</script>

<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import Heading from '@/components/Heading.svelte';
    import SiteChartsPanel from '@/components/SiteChartsPanel.svelte';
    import type { Site } from '@/types';

    let { site }: { site: Site } = $props();
</script>

<AppHead title={`Статистика — ${site.name}`} />

<div class="flex h-full flex-1 flex-col gap-6 p-4">
    <Heading
        title="Статистика"
        description={`${site.name}${site.domain ? ' · ' + site.domain : ''}`}
    />
    <SiteChartsPanel {site} />
</div>
