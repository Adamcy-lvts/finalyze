<script setup lang="ts">
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { LayoutDashboard, Wallet } from 'lucide-vue-next'

const page = usePage()
const affiliate = computed(() => page.props.auth?.user?.affiliate)
const hasDualAccess = computed(() => Boolean(affiliate.value?.has_dual_access))
const isAffiliateRoute = computed(() => page.url.startsWith('/affiliate'))
</script>

<template>
    <DropdownMenu v-if="hasDualAccess">
        <DropdownMenuTrigger as-child>
            <Button variant="ghost" size="sm" class="gap-1 sm:gap-2">
                <LayoutDashboard v-if="!isAffiliateRoute" class="h-4 w-4" />
                <Wallet v-else class="h-4 w-4" />
                <span class="hidden text-xs font-medium sm:inline">
                    {{ isAffiliateRoute ? 'Affiliate Dashboard' : 'User Dashboard' }}
                </span>
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-52">
            <DropdownMenuItem as-child>
                <Link href="/dashboard" class="flex items-center gap-2">
                    <LayoutDashboard class="h-4 w-4" />
                    User Dashboard
                </Link>
            </DropdownMenuItem>
            <DropdownMenuItem as-child>
                <Link href="/affiliate" class="flex items-center gap-2">
                    <Wallet class="h-4 w-4" />
                    Affiliate Dashboard
                </Link>
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
