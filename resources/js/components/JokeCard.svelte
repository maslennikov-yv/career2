<script lang="ts">
    import JokeScratch from '@/components/JokeScratch.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Card, CardContent, CardHeader } from '@/components/ui/card';
    import type { Joke } from '@/types/joke';

    let { joke }: { joke: Joke } = $props();
</script>

<article aria-label={`Шутка типа ${joke.type}`}>
    <Card class="gap-3 py-4">
        <CardHeader
            class="flex flex-row items-center justify-between gap-2 px-4"
        >
            <Badge variant="secondary">{joke.type}</Badge>
        </CardHeader>
        <CardContent class="flex flex-col gap-3 px-4">
            <p class="text-base leading-relaxed">{joke.setup}</p>
            <JokeScratch text={joke.punchline} />
        </CardContent>
    </Card>
</article>
