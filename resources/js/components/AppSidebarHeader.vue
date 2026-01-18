<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import DashboardSwitcher from '@/components/DashboardSwitcher.vue';
import WordBalanceDisplay from '@/components/WordBalanceDisplay.vue';
import ThemeToggle from '@/components/ThemeToggle.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItemType } from '@/types';
import { useWordBalance } from '@/composables/useWordBalance';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItemType[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

// Use composable for real-time balance updates
const { wordBalance } = useWordBalance();
</script>

<template>
    <header id="app-header"
        class="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/70 px-3 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4">
        <div class="flex min-w-0 items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <div class="hidden md:block">
                    <Breadcrumbs :breadcrumbs="breadcrumbs" />
                </div>
            </template>
        </div>

        <!-- Right Side Actions -->
        <div class="ml-auto flex items-center gap-1 sm:gap-2">
            <DashboardSwitcher />
            <ThemeToggle />
            <div class="flex">
                <WordBalanceDisplay v-if="wordBalance" :balance="wordBalance" compact />
            </div>
        </div>
    </header>
</template>
