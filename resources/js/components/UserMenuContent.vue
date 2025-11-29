<script setup lang="ts">
import UserInfo from '@/components/UserInfo.vue';
import { DropdownMenuGroup, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator } from '@/components/ui/dropdown-menu';
import type { User } from '@/types';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Coins, CreditCard, LogOut, Settings } from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    user: User;
}

const handleLogout = () => {
    router.flushAll();
};

defineProps<Props>();

const page = usePage();
const auth = computed(() => page.props.auth as any);
const wordBalance = computed(() => auth.value?.user?.word_balance_data);
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
            <UserInfo :user="user" :show-email="true" />
        </div>
    </DropdownMenuLabel>

    <!-- Credit Balance Display -->
    <DropdownMenuSeparator v-if="wordBalance" />
    <DropdownMenuLabel v-if="wordBalance" class="px-2 py-1.5">
        <div class="flex items-center justify-between text-xs">
            <div class="flex items-center gap-1.5 text-muted-foreground">
                <Coins class="h-3.5 w-3.5" />
                <span>Credit Balance</span>
            </div>
            <span class="font-semibold text-foreground">
                {{ wordBalance.formatted_balance }}
            </span>
        </div>
        <div class="mt-1.5 h-1 w-full overflow-hidden rounded-full bg-muted">
            <div
                class="h-full bg-primary transition-all duration-300"
                :style="{ width: `${wordBalance.percentage_remaining}%` }"
            />
        </div>
    </DropdownMenuLabel>

    <DropdownMenuSeparator />
    <DropdownMenuGroup>
        <DropdownMenuItem :as-child="true">
            <Link class="block w-full" :href="route('pricing')" prefetch as="button">
                <CreditCard class="mr-2 h-4 w-4" />
                Buy Credits
            </Link>
        </DropdownMenuItem>
        <DropdownMenuItem :as-child="true">
            <Link class="block w-full" :href="route('profile.edit')" prefetch as="button">
                <Settings class="mr-2 h-4 w-4" />
                Settings
            </Link>
        </DropdownMenuItem>
    </DropdownMenuGroup>
    <DropdownMenuSeparator />
    <DropdownMenuItem :as-child="true">
        <Link class="block w-full" method="post" :href="route('logout')" @click="handleLogout" as="button">
            <LogOut class="mr-2 h-4 w-4" />
            Log out
        </Link>
    </DropdownMenuItem>
</template>
