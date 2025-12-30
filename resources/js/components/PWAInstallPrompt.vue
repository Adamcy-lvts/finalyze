<script setup lang="ts">
import { usePWA } from '@/composables/usePWA';
import { Button } from '@/components/ui/button';
import { Download, X, Smartphone, Zap, WifiOff } from 'lucide-vue-next';

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
            class="fixed bottom-4 left-4 right-4 z-50 mx-auto max-w-sm overflow-hidden rounded-xl border bg-card shadow-2xl md:left-auto md:right-4"
            role="dialog"
            aria-label="Install Finalyze app"
        >
            <!-- Header with gradient -->
            <div class="relative bg-gradient-to-r from-primary/20 to-primary/5 px-4 pb-4 pt-4">
                <Button
                    variant="ghost"
                    size="icon"
                    class="absolute right-2 top-2 h-7 w-7 rounded-full hover:bg-background/50"
                    @click="dismissInstallPrompt"
                >
                    <X class="h-4 w-4" />
                </Button>

                <div class="flex items-center gap-3">
                    <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl bg-[#0a0a0f] shadow-lg">
                        <img src="/img/finalyze_icon_logo.png" alt="Finalyze" class="h-10 w-10 object-contain" />
                    </div>
                    <div>
                        <h3 class="text-base font-semibold text-foreground">Install Finalyze</h3>
                        <p class="text-sm text-muted-foreground">Get the full app experience</p>
                    </div>
                </div>
            </div>

            <!-- Features list -->
            <div class="space-y-2 px-4 py-3">
                <div class="flex items-center gap-2 text-sm text-muted-foreground">
                    <Smartphone class="h-4 w-4 text-primary" />
                    <span>Launch from your home screen</span>
                </div>
                <div class="flex items-center gap-2 text-sm text-muted-foreground">
                    <Zap class="h-4 w-4 text-primary" />
                    <span>Faster loading &amp; performance</span>
                </div>
                <div class="flex items-center gap-2 text-sm text-muted-foreground">
                    <WifiOff class="h-4 w-4 text-primary" />
                    <span>Access your work offline</span>
                </div>
            </div>

            <!-- Action buttons -->
            <div class="flex gap-2 border-t bg-muted/30 px-4 py-3">
                <Button variant="outline" class="flex-1" @click="dismissInstallPrompt"> Maybe later </Button>
                <Button class="flex-1" @click="handleInstall">
                    <Download class="mr-2 h-4 w-4" />
                    Install App
                </Button>
            </div>
        </div>
    </Transition>
</template>
