<script setup lang="ts">
import { usePWA } from '@/composables/usePWA';
import { Button } from '@/components/ui/button';
import { RefreshCw, X } from 'lucide-vue-next';

const { needRefresh, offlineReady, updateServiceWorker } = usePWA();

const close = () => {
    offlineReady.value = false;
    needRefresh.value = false;
};
</script>

<template>
    <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="translate-y-full opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="translate-y-0 opacity-100"
        leave-to-class="translate-y-full opacity-0"
    >
        <div
            v-if="needRefresh || offlineReady"
            class="fixed bottom-4 left-4 right-4 z-50 mx-auto max-w-md rounded-lg border bg-card p-4 shadow-lg md:left-auto md:right-4"
            role="alert"
        >
            <div class="flex items-start gap-3">
                <div class="flex-1">
                    <p v-if="offlineReady" class="text-sm font-medium text-foreground">App ready to work offline</p>
                    <p v-else class="text-sm font-medium text-foreground">New version available</p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        {{ offlineReady ? 'Content has been cached for offline use.' : 'Click update to get the latest features.' }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <Button v-if="needRefresh" size="sm" @click="updateServiceWorker(true)">
                        <RefreshCw class="mr-1 h-3 w-3" />
                        Update
                    </Button>
                    <Button variant="ghost" size="sm" @click="close">
                        <X class="h-4 w-4" />
                    </Button>
                </div>
            </div>
        </div>
    </Transition>
</template>
