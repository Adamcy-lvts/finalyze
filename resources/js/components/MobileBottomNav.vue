<script setup lang="ts">
import { bottomNavItems } from '@/config/nav';
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();
</script>

<template>
    <nav class="fixed bottom-0 left-0 right-0 z-50 border-t border-border/40 bg-background/80 backdrop-blur-xl md:hidden pb-safe">
        <div class="flex h-16 items-center justify-around px-2">
            <template v-for="item in bottomNavItems" :key="item.title">
                <!-- Special styling for 'New Project' (Create) button -->
                <Link 
                    v-if="item.title === 'New Project'"
                    :href="item.href"
                    class="group relative flex flex-col items-center justify-center gap-0.5 rounded-xl bg-zinc-900 px-4 py-1.5 text-white shadow-lg transition-all active:scale-95 hover:bg-zinc-800 dark:bg-zinc-800 dark:hover:bg-zinc-700 min-w-[75px] -mt-1"
                >
                    <component :is="item.icon" class="h-5 w-5 stroke-[2.5px]" />
                    <span class="font-bold text-[9px] leading-tight text-center">{{ item.title }}</span>
                    <!-- Subtle glow effect -->
                    <div class="absolute inset-0 -z-10 bg-white/5 opacity-0 blur-lg transition-opacity group-hover:opacity-100" />
                </Link>

                <!-- Standard Nav Items -->
                <Link 
                    v-else
                    :href="item.href"
                    class="group relative flex flex-col items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-xs transition-all active:scale-95 min-w-[60px]"
                    :class="[
                        item.href === page.url || (item.href !== '/dashboard' && page.url.startsWith(item.href))
                            ? 'text-primary' 
                            : 'text-muted-foreground hover:text-foreground'
                    ]"
                >
                    <div class="relative flex items-center justify-center">
                        <component 
                            :is="item.icon" 
                            class="h-5 w-5 transition-transform duration-300 ease-out group-active:scale-110"
                            :class="{ 
                                'stroke-[2.5px]': item.href === page.url || (item.href !== '/dashboard' && page.url.startsWith(item.href)) 
                            }"
                        />
                        <!-- Active indicator dot -->
                        <div 
                            v-if="item.href === page.url || (item.href !== '/dashboard' && page.url.startsWith(item.href))"
                            class="absolute -top-1 -right-1 h-1 w-1 rounded-full bg-primary animate-in fade-in zoom-in duration-500"
                        />
                    </div>
                    <span class="font-medium text-[10px] transition-colors duration-300">{{ item.title }}</span>
                </Link>
            </template>
        </div>
    </nav>
</template>

<style scoped>
/* Safe area padding for iPhones with home indicator */
.pb-safe {
    padding-bottom: env(safe-area-inset-bottom);
}
</style>
