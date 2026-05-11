<script lang="ts">
    import { cn } from '@/lib/utils';

    let { text }: { text: string } = $props();

    let revealed = $state(false);

    function toggle() {
        revealed = !revealed;
    }
</script>

<button
    type="button"
    aria-expanded={revealed}
    onclick={toggle}
    class={cn(
        'group relative block w-full cursor-pointer touch-manipulation rounded-md p-3 text-left',
        'min-h-11 outline-none transition-colors',
        'focus-visible:ring-2 focus-visible:ring-ring',
        revealed
            ? 'bg-muted/30'
            : 'bg-muted/60 hover:bg-muted/70 dark:bg-muted/40 dark:hover:bg-muted/50',
    )}
>
    <span class="sr-only">
        {revealed
            ? 'Панчлайн раскрыт'
            : 'Панчлайн скрыт, нажмите чтобы показать'}
    </span>
    <span
        aria-hidden={!revealed}
        class={cn(
            'block text-base leading-relaxed',
            'motion-safe:transition-[filter,opacity] motion-safe:duration-200',
            !revealed && 'select-none opacity-85 blur-md',
        )}
    >
        {text}
    </span>
</button>
