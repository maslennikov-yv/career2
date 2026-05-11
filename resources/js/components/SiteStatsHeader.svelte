<script lang="ts">
    import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';

    let {
        hours = $bindable(),
    }: {
        hours: number;
    } = $props();

    const tabs = [
        { label: '24 часа', value: 24 },
        { label: '7 дней', value: 24 * 7 },
        { label: '30 дней', value: 24 * 30 },
    ];
</script>

<Tabs
    value={String(hours)}
    onValueChange={(v) => {
        const next = Number(v);

        if (!Number.isNaN(next)) {
            hours = next;
        }
    }}
>
    <TabsList>
        {#each tabs as tab (tab.value)}
            <TabsTrigger value={String(tab.value)}>{tab.label}</TabsTrigger>
        {/each}
    </TabsList>
</Tabs>
