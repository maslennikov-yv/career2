<script lang="ts">
    import SiteCitiesChart from '@/components/SiteCitiesChart.svelte';
    import SiteHourlyChart from '@/components/SiteHourlyChart.svelte';
    import SiteStatsHeader from '@/components/SiteStatsHeader.svelte';
    import { createSiteStats } from '@/lib/useSiteStats.svelte';
    import type { HourlyPoint, Site } from '@/types';

    let {
        site,
        enabled = true,
    }: {
        site: Site;
        enabled?: boolean;
    } = $props();

    const stats = createSiteStats();

    let hours = $state(24);

    const dataHours = $derived(stats.state.hours ?? hours);
    const granularity = $derived<'hour' | 'day'>(
        dataHours >= 168 ? 'day' : 'hour',
    );

    const bucketed = $derived<HourlyPoint[]>(
        granularity === 'hour'
            ? stats.state.hourly
            : aggregateByDay(stats.state.hourly),
    );

    function aggregateByDay(points: HourlyPoint[]): HourlyPoint[] {
        const byDay: Record<string, number> = {};

        for (const point of points) {
            const day = point.hour.slice(0, 10);
            byDay[day] = (byDay[day] ?? 0) + point.uniques;
        }

        return Object.entries(byDay).map(([day, uniques]) => ({
            hour: `${day}T00:00:00`,
            uniques,
        }));
    }

    function retry() {
        stats.request(site.id, hours);
    }

    $effect(() => {
        if (enabled && site) {
            stats.request(site.id, hours);
        } else if (!enabled) {
            stats.cancel();
        }
    });

    $effect(() => {
        return () => stats.cancel();
    });
</script>

<div class="flex flex-col gap-6">
    <SiteStatsHeader bind:hours />

    <div class="flex flex-col gap-4">
        <SiteHourlyChart
            data={bucketed}
            {granularity}
            loading={stats.state.loading}
            error={stats.state.error}
            onRetry={retry}
        />
        <SiteCitiesChart
            data={stats.state.cities}
            loading={stats.state.loading}
            error={stats.state.error}
            onRetry={retry}
        />
    </div>
</div>
