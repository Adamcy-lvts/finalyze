<script setup lang="ts">
import { SidebarProvider } from '@/components/ui/sidebar';
import { usePage } from '@inertiajs/vue3';

interface Props {
    variant?: 'header' | 'sidebar';
    class?: string;
}

const props = defineProps<Props>();

const isOpen = usePage().props.sidebarOpen;
</script>

<template>
    <div v-if="variant === 'header'" :class="['flex min-h-screen w-full flex-col', props.class]">
        <slot />
    </div>
    <SidebarProvider v-else :default-open="isOpen" :class="props.class">
        <slot />
    </SidebarProvider>
</template>
