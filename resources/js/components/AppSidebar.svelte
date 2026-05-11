<script lang="ts">
    import BarChart3 from 'lucide-svelte/icons/bar-chart-3';
    import House from 'lucide-svelte/icons/house';
    import type { Snippet } from 'svelte';
    import NavMain from '@/components/NavMain.svelte';
    import NavUser from '@/components/NavUser.svelte';
    import {
        Sidebar,
        SidebarContent,
        SidebarFooter,
    } from '@/components/ui/sidebar';
    import type { NavItem } from '@/types';
    import { home } from '@/routes';
    import sitesRoutes from '@/routes/sites';

    let {
        children,
    }: {
        children?: Snippet;
    } = $props();

    const mainNavItems: NavItem[] = [
        {
            title: 'На страницу',
            href: home(),
            icon: House,
        },
        {
            title: 'Сайты',
            href: sitesRoutes.index(),
            icon: BarChart3,
        },
    ];
</script>

<Sidebar collapsible="icon" variant="inset">
    <SidebarContent>
        <NavMain items={mainNavItems} />
    </SidebarContent>

    <SidebarFooter>
        <NavUser />
    </SidebarFooter>
</Sidebar>
{@render children?.()}
