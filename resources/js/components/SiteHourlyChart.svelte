<script lang="ts">
    import {
        BarController,
        BarElement,
        CategoryScale,
        Chart,
        LinearScale,
        Tooltip,
    } from 'chart.js';
    import type { ChartConfiguration } from 'chart.js';
    import RotateCw from 'lucide-svelte/icons/rotate-cw';
    import { Alert, AlertDescription } from '@/components/ui/alert';
    import { Button } from '@/components/ui/button';
    import {
        Card,
        CardContent,
        CardDescription,
        CardHeader,
        CardTitle,
    } from '@/components/ui/card';
    import { Skeleton } from '@/components/ui/skeleton';
    import { themeState } from '@/lib/theme.svelte';
    import type { HourlyPoint } from '@/types';

    Chart.register(
        BarController,
        BarElement,
        CategoryScale,
        LinearScale,
        Tooltip,
    );

    let {
        data,
        granularity,
        loading,
        error,
        onRetry,
    }: {
        data: HourlyPoint[];
        granularity: 'hour' | 'day';
        loading: boolean;
        error: string | null;
        onRetry: () => void;
    } = $props();

    const { resolvedAppearance } = themeState();

    const numberFormatter = new Intl.NumberFormat('ru-RU');

    const hourTickFormatter = new Intl.DateTimeFormat('ru-RU', {
        hour: '2-digit',
        minute: '2-digit',
    });

    const hourTooltipFormatter = new Intl.DateTimeFormat('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });

    const dayFormatter = new Intl.DateTimeFormat('ru-RU', {
        day: '2-digit',
        month: 'short',
    });

    function formatTick(iso: string): string {
        const d = new Date(iso);

        return granularity === 'day'
            ? dayFormatter.format(d)
            : hourTickFormatter.format(d);
    }

    function formatTooltipTitle(iso: string): string {
        const d = new Date(iso);

        return granularity === 'day'
            ? dayFormatter.format(d)
            : hourTooltipFormatter.format(d);
    }

    function readColor(name: string): string {
        return getComputedStyle(document.documentElement)
            .getPropertyValue(name)
            .trim();
    }

    let canvas = $state<HTMLCanvasElement | null>(null);
    let chart: Chart<'bar', number[], string> | null = null;
    let pendingInit = false;

    function buildConfig(): ChartConfiguration<'bar', number[], string> {
        const barColor = readColor('--chart-1');
        const gridColor = readColor('--border');
        const textColor = readColor('--muted-foreground');

        return {
            type: 'bar',
            data: {
                labels: data.map((p) => p.hour),
                datasets: [
                    {
                        data: data.map((p) => p.uniques),
                        backgroundColor: barColor,
                        borderRadius: 4,
                        borderSkipped: false,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 300, easing: 'easeOutQuart' },
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            title: (items) =>
                                formatTooltipTitle(items[0]?.label ?? ''),
                            label: (item) =>
                                `${numberFormatter.format(item.parsed.y ?? 0)}`,
                        },
                    },
                },
                scales: {
                    x: {
                        grid: { display: false },
                        border: { color: gridColor },
                        ticks: {
                            color: textColor,
                            autoSkip: true,
                            maxRotation: 0,
                            callback(value) {
                                const label = this.getLabelForValue(
                                    value as number,
                                );

                                return formatTick(label);
                            },
                        },
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: gridColor },
                        border: { display: false },
                        ticks: {
                            color: textColor,
                            callback: (v) => numberFormatter.format(Number(v)),
                        },
                    },
                },
            },
        };
    }

    $effect(() => {
        if (!canvas) {
            return;
        }

        // Read theme reactively so chart rebuilds on light/dark switch.
        resolvedAppearance();

        chart = new Chart(canvas, buildConfig());
        pendingInit = true;

        return () => {
            chart?.destroy();
            chart = null;
            pendingInit = false;
        };
    });

    $effect(() => {
        // Track data deps.
        const labels = data.map((p) => p.hour);
        const values = data.map((p) => p.uniques);

        if (!chart) {
            return;
        }

        if (pendingInit) {
            pendingInit = false;

            return;
        }

        chart.data.labels = labels;
        chart.data.datasets[0].data = values;
        chart.update();
    });
</script>

<Card>
    <CardHeader>
        <CardTitle>Посещения</CardTitle>
        <CardDescription>
            {granularity === 'day'
                ? 'Уникальные посетители по дням'
                : 'Уникальные посетители по часам'}
        </CardDescription>
    </CardHeader>
    <CardContent>
        {#if error}
            <Alert variant="destructive" class="flex items-center gap-2">
                <AlertDescription>{error}</AlertDescription>
                <Button
                    variant="outline"
                    size="sm"
                    class="ml-auto"
                    onclick={onRetry}
                >
                    <RotateCw class="size-3.5" />
                    Повторить
                </Button>
            </Alert>
        {:else if loading && data.length === 0}
            <Skeleton class="aspect-video w-full" />
        {:else if data.length === 0}
            <p
                class="flex aspect-video items-center justify-center text-sm text-muted-foreground"
            >
                Пока данных нет.
            </p>
        {:else}
            {#key resolvedAppearance()}
                <div class="aspect-video w-full">
                    <canvas
                        bind:this={canvas}
                        aria-label={granularity === 'day'
                            ? 'Гистограмма уникальных посетителей по дням'
                            : 'Гистограмма уникальных посетителей по часам'}
                    ></canvas>
                </div>
            {/key}
        {/if}
    </CardContent>
</Card>
