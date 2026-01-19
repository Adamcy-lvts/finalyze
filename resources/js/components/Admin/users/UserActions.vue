<script setup lang="ts">
import { inject } from 'vue'
import { MoreHorizontal, KeyRound, Coins, Ban, Trash2, CheckCircle, CircleMinus } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import type { UserRow } from './columns'

const props = defineProps<{
    user: UserRow
}>()

const onResetPassword = inject<(user: UserRow) => void>('onResetPassword')
const onAddWords = inject<(user: UserRow) => void>('onAddWords')
const onDeductWords = inject<(user: UserRow) => void>('onDeductWords')
const onSuspend = inject<(user: UserRow) => void>('onSuspend')
const onDelete = inject<(user: UserRow) => void>('onDelete')


const unsuspendUser = () => {
    if (!confirm(`Unsuspend ${props.user.name}?`)) return
    router.post(route('admin.users.unban', { user: props.user.id }), {}, {
        preserveScroll: true,
    })
}
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button variant="ghost" class="h-8 w-8 p-0">
                <span class="sr-only">Open menu</span>
                <MoreHorizontal class="h-4 w-4" />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
            <DropdownMenuLabel>Actions</DropdownMenuLabel>

            <DropdownMenuItem @click="onResetPassword?.(user)">
                <KeyRound class="mr-2 h-4 w-4" />
                Reset Password
            </DropdownMenuItem>

            <DropdownMenuItem @click="onAddWords?.(user)">
                <Coins class="mr-2 h-4 w-4" />
                Add Words
            </DropdownMenuItem>

            <DropdownMenuItem @click="onDeductWords?.(user)">
                <CircleMinus class="mr-2 h-4 w-4" />
                Deduct Words
            </DropdownMenuItem>

            <DropdownMenuSeparator />

            <DropdownMenuItem v-if="!user.is_banned" @click="onSuspend?.(user)"
                class="text-amber-600 focus:text-amber-600 focus:bg-amber-50">
                <Ban class="mr-2 h-4 w-4" />
                Suspend
            </DropdownMenuItem>

            <DropdownMenuItem v-else @click="unsuspendUser"
                class="text-emerald-600 focus:text-emerald-600 focus:bg-emerald-50">
                <CheckCircle class="mr-2 h-4 w-4" />
                Unsuspend
            </DropdownMenuItem>

            <DropdownMenuItem @click="onDelete?.(user)" class="text-rose-600 focus:text-rose-600 focus:bg-rose-50">
                <Trash2 class="mr-2 h-4 w-4" />
                Delete
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
