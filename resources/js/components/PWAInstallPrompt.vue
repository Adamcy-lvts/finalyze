<script setup lang="ts">
import { usePWA } from '@/composables/usePWA';
import { Button } from '@/components/ui/button';
import { Download, X } from 'lucide-vue-next';
import AppLogo from '@/components/AppLogo.vue';

const { shouldShowInstallPrompt, installApp, dismissInstallPrompt } = usePWA();

const handleInstall = async () => {
    await installApp();
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
            v-if="shouldShowInstallPrompt"
            class="fixed bottom-4 left-4 right-4 z-50 mx-auto max-w-md rounded-lg border bg-card p-4 shadow-lg md:left-auto md:right-4"
            role="dialog"
            aria-label="Install Finalyze app"
        >
            <div class="flex items-start gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                    <AppLogo class="h-6 w-auto" />
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium text-foreground">Install Finalyze</p>
                    <p class="mt-1 text-xs text-muted-foreground">Install the app for a better experience with offline access.</p>
                </div>
                <Button variant="ghost" size="icon" class="h-6 w-6 shrink-0" @click="dismissInstallPrompt">
                    <X class="h-4 w-4" />
                </Button>
            </div>
            <div class="mt-3 flex justify-end gap-2">
                <Button variant="outline" size="sm" @click="dismissInstallPrompt"> Not now </Button>
                <Button size="sm" @click="handleInstall">
                    <Download class="mr-1 h-3 w-3" />
                    Install
                </Button>
            </div>
        </div>
    </Transition>
</template>
