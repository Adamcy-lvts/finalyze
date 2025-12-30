<script setup lang="ts">
import { usePWA } from '@/composables/usePWA';
import { WifiOff } from 'lucide-vue-next';

const { isOnline } = usePWA();
</script>

<template>
    <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="-translate-y-full opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="translate-y-0 opacity-100"
        leave-to-class="-translate-y-full opacity-0"
    >
        <div
            v-if="!isOnline"
            class="fixed left-0 right-0 top-0 z-50 flex items-center justify-center gap-2 bg-amber-500 px-4 py-2 text-sm font-medium text-amber-950 dark:bg-amber-600 dark:text-amber-50"
            role="status"
            aria-live="polite"
        >
            <WifiOff class="h-4 w-4" />
            <span>You're offline. Some features may be limited.</span>
        </div>
    </Transition>
</template>
