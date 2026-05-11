<script lang="ts">
    import { Form } from '@inertiajs/svelte';
    import type { Site } from '@/types';
    import SiteController from '@/actions/App/Http/Controllers/SiteController';
    import InputError from '@/components/InputError.svelte';
    import { Button } from '@/components/ui/button';
    import {
        Dialog,
        DialogContent,
        DialogDescription,
        DialogFooter,
        DialogTitle,
    } from '@/components/ui/dialog';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Spinner } from '@/components/ui/spinner';

    let {
        site,
        open = $bindable(false),
    }: {
        site: Site | null;
        open?: boolean;
    } = $props();

    const mode = $derived<'create' | 'edit'>(site === null ? 'create' : 'edit');
    const formAction = $derived(
        site === null
            ? SiteController.store.form()
            : SiteController.update.form(site.id),
    );
    const formKey = $derived(site?.id ?? 'create');
    const initialName = $derived(site?.name ?? '');
    const initialDomain = $derived(site?.domain ?? '');
</script>

<Dialog bind:open>
    <DialogContent class="sm:max-w-md">
        <div class="flex flex-col gap-2">
            <DialogTitle>
                {mode === 'create' ? 'Новый сайт' : 'Редактирование сайта'}
            </DialogTitle>
            <DialogDescription>
                {mode === 'create'
                    ? 'Зарегистрируйте сайт, чтобы получить сниппет для встраивания.'
                    : 'Изменить название и домен.'}
            </DialogDescription>
        </div>

        {#key formKey}
            <Form
                {...formAction}
                class="flex flex-col gap-4"
                onSuccess={() => (open = false)}
            >
                {#snippet children({ errors, processing })}
                    <div class="grid gap-2">
                        <Label for="site-form-name">Название</Label>
                        <Input
                            id="site-form-name"
                            name="name"
                            value={initialName}
                            placeholder="Мой блог"
                            autocomplete="off"
                            required
                        />
                        <InputError message={errors.name} />
                    </div>

                    <div class="grid gap-2">
                        <Label for="site-form-domain">
                            Домен (необязательно)
                        </Label>
                        <Input
                            id="site-form-domain"
                            name="domain"
                            value={initialDomain}
                            placeholder="example.com"
                            autocomplete="off"
                        />
                        <InputError message={errors.domain} />
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onclick={() => (open = false)}
                            disabled={processing}
                        >
                            Отмена
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {#if processing}<Spinner />{/if}
                            {mode === 'create' ? 'Создать' : 'Сохранить'}
                        </Button>
                    </DialogFooter>
                {/snippet}
            </Form>
        {/key}
    </DialogContent>
</Dialog>
