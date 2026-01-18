<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import AppLogo from './AppLogo.vue';

import { footerNavItems, getMainNavItems } from '@/config/nav';

const page = usePage();
const userAffiliate = page.props.auth?.user?.affiliate;

const mainNavItems = computed(() => getMainNavItems({
    isAffiliate: Boolean(userAffiliate?.is_affiliate),
    isPureAffiliate: Boolean(userAffiliate?.is_pure),
    isAffiliateRoute: page.url.startsWith('/affiliate'),
}));
</script>

<template>
    <Sidebar id="app-sidebar" collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="route('dashboard')">
                            <div class="flex items-center justify-center">
                                <AppLogo class="h-10 w-auto" />
                            </div>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
