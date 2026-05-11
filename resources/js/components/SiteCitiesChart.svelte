<script lang="ts">
    import { ArcElement, Chart, DoughnutController, Tooltip } from 'chart.js';
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
    import type { CityPoint } from '@/types';

    Chart.register(DoughnutController, ArcElement, Tooltip);

    let {
        data,
        loading,
        error,
        onRetry,
    }: {
        data: CityPoint[];
        loading: boolean;
        error: string | null;
        onRetry: () => void;
    } = $props();

    const { resolvedAppearance } = themeState();

    const baseColorVars = [
        '--chart-1',
        '--chart-2',
        '--chart-3',
        '--chart-4',
        '--chart-5',
    ];
    const fallbackColorVar = '--muted-foreground';

    const numberFormatter = new Intl.NumberFormat('ru-RU');
    const percentFormatter = new Intl.NumberFormat('ru-RU', {
        style: 'percent',
        maximumFractionDigits: 1,
    });

    const total = $derived(data.reduce((acc, p) => acc + p.visits, 0));

    function readColor(name: string): string {
        return getComputedStyle(document.documentElement)
            .getPropertyValue(name)
            .trim();
    }

    function colorForCity(city: string, index: number, asVar: boolean): string {
        const cssVar =
            city === 'Прочее'
                ? fallbackColorVar
                : baseColorVars[index % baseColorVars.length];

        return asVar ? `var(${cssVar})` : readColor(cssVar);
    }

    let canvas = $state<HTMLCanvasElement | null>(null);
    let chart: Chart<'doughnut', number[], string> | null = null;
    let pendingInit = false;

    function buildConfig(): ChartConfiguration<'doughnut', number[], string> {
        const resolvedColors = data.map((p, i) =>
            colorForCity(p.city, i, false),
        );
        const cardColor = readColor('--card');

        return {
            type: 'doughnut',
            data: {
                labels: data.map((p) => p.city),
                datasets: [
                    {
                        data: data.map((p) => p.visits),
                        backgroundColor: resolvedColors,
                        borderColor: cardColor,
                        borderWidth: 2,
                        hoverOffset: 6,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 300, easing: 'easeOutQuart' },
                cutout: '62%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (item) =>
                                ` ${item.label}: ${numberFormatter.format(item.parsed)}`,
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
        const labels = data.map((p) => p.city);
        const values = data.map((p) => p.visits);
        const resolvedColors = data.map((p, i) =>
            colorForCity(p.city, i, false),
        );

        if (!chart) {
            return;
        }

        if (pendingInit) {
            pendingInit = false;

            return;
        }

        chart.data.labels = labels;
        chart.data.datasets[0].data = values;
        chart.data.datasets[0].backgroundColor = resolvedColors;
        chart.update();
    });
</script>

<Card>
    <CardHeader>
        <CardTitle>Города</CardTitle>
        <CardDescription>Топ городов по числу посещений</CardDescription>
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
            <div class="mx-auto w-full max-w-[480px]">
                <Skeleton class="aspect-square w-full rounded-full" />
            </div>
        {:else if data.length === 0}
            <p
                class="flex aspect-video items-center justify-center text-sm text-muted-foreground"
            >
                Пока данных нет.
            </p>
        {:else}
            <div
                class="grid items-center gap-4 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]"
            >
                <div class="relative mx-auto w-full max-w-[480px]">
                    {#key resolvedAppearance()}
                        <div class="aspect-square w-full">
                            <canvas
                                bind:this={canvas}
                                aria-label="Распределение посещений по городам"
                            ></canvas>
                        </div>
                    {/key}
                    <div
                        class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center"
                    >
                        <span class="text-xs text-muted-foreground">Всего</span>
                        <span class="text-2xl font-semibold tabular-nums">
                            {numberFormatter.format(total)}
                        </span>
                    </div>
                </div>

                <ul class="flex flex-col gap-2 text-sm">
                    {#each data as point, i (i)}
                        {@const share = total > 0 ? point.visits / total : 0}
                        <li class="flex items-center gap-2">
                            <span
                                class="size-2.5 shrink-0 rounded-[2px]"
                                style="background-color: {colorForCity(
                                    point.city,
                                    i,
                                    true,
                                )};"
                                aria-hidden="true"
                            ></span>
                            <span class="flex-1 truncate" title={point.city}>
                                {point.city}
                            </span>
                            <span class="text-muted-foreground tabular-nums">
                                {numberFormatter.format(point.visits)}
                            </span>
                            <span
                                class="w-12 text-right text-xs text-muted-foreground tabular-nums"
                            >
                                {percentFormatter.format(share)}
                            </span>
                        </li>
                    {/each}
                </ul>
            </div>
        {/if}
    </CardContent>
</Card>
