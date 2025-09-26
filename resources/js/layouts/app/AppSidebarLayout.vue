<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
    class?: string;
    title?: string;
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
    class: '',
    title: '',
});
</script>

<template>
    <AppShell variant="sidebar" :class="$props.class">
        <AppSidebar />
        <AppContent variant="sidebar" class="overflow-x-hidden">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <slot />
        </AppContent>
    </AppShell>
    <!-- Sonner Toast Container -->
    <Toaster
        position="top-right"
        :expand="false"
        :rich-colors="true"
        :close-button="true"
    />
</template>
