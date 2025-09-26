<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import type { BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
    class?: string;
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
    class: '',
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs" :class="$props.class">
        <slot />
    </AppLayout>
</template>
