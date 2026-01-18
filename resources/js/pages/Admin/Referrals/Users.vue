<template>
    <AdminLayout title="Referral Partners" subtitle="Manage referrers and their commission rates.">
        <Card class="border-border/50">
            <CardHeader class="flex flex-row items-center justify-between pb-4">
                <div>
                    <CardTitle class="text-base">All Referrers</CardTitle>
                    <CardDescription>
                        Default commission rate: {{ defaultRate }}%. Users with custom rates are highlighted.
                    </CardDescription>
                </div>
            </CardHeader>
            <CardContent>
                <!-- Search -->
                <div class="mb-6">
                    <Input
                        v-model="searchQuery"
                        placeholder="Search by name, email, or referral code..."
                        class="max-w-md"
                    />
                </div>

                <div v-if="referrers.data.length === 0" class="text-center py-12 text-muted-foreground">
                    No referrers found
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-border/50">
                                <th class="text-left py-3 px-2 text-sm font-medium text-muted-foreground">User</th>
                                <th class="text-left py-3 px-2 text-sm font-medium text-muted-foreground">Referral Code</th>
                                <th class="text-center py-3 px-2 text-sm font-medium text-muted-foreground">Referrals</th>
                                <th class="text-right py-3 px-2 text-sm font-medium text-muted-foreground">Total Earned</th>
                                <th class="text-center py-3 px-2 text-sm font-medium text-muted-foreground">Commission Rate</th>
                                <th class="text-right py-3 px-2 text-sm font-medium text-muted-foreground">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="referrer in referrers.data"
                                :key="referrer.id"
                                class="border-b border-border/30"
                                :class="{ 'bg-primary/5': referrer.has_custom_rate }"
                            >
                                <td class="py-4 px-2">
                                    <div>
                                        <p class="font-medium">{{ referrer.name }}</p>
                                        <p class="text-sm text-muted-foreground">{{ referrer.email }}</p>
                                    </div>
                                </td>
                                <td class="py-4 px-2">
                                    <code class="px-2 py-1 bg-muted rounded text-sm">{{ referrer.referral_code }}</code>
                                </td>
                                <td class="py-4 px-2 text-center">
                                    {{ referrer.total_referrals }}
                                </td>
                                <td class="py-4 px-2 text-right font-medium text-green-600">
                                    {{ referrer.total_earned_formatted }}
                                </td>
                                <td class="py-4 px-2 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <span class="font-medium">{{ referrer.effective_rate }}%</span>
                                        <Badge v-if="referrer.has_custom_rate" variant="secondary" class="text-xs">
                                            Custom
                                        </Badge>
                                    </div>
                                </td>
                                <td class="py-4 px-2 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            @click="openEditDialog(referrer)"
                                        >
                                            <Pencil class="h-4 w-4" />
                                        </Button>
                                        <Button
                                            v-if="referrer.has_custom_rate"
                                            variant="ghost"
                                            size="sm"
                                            @click="resetRate(referrer)"
                                            title="Reset to default"
                                        >
                                            <RotateCcw class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="referrers.last_page > 1" class="flex items-center justify-between mt-6">
                    <p class="text-sm text-muted-foreground">
                        Page {{ referrers.current_page }} of {{ referrers.last_page }}
                    </p>
                    <div class="flex gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="referrers.current_page === 1"
                            @click="goToPage(referrers.current_page - 1)"
                        >
                            Previous
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="referrers.current_page === referrers.last_page"
                            @click="goToPage(referrers.current_page + 1)"
                        >
                            Next
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Edit Dialog -->
        <Dialog v-model:open="editDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Edit Commission Rate</DialogTitle>
                    <DialogDescription>
                        Set a custom commission rate for {{ editingUser?.name }}
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-4 py-4">
                    <div class="space-y-2">
                        <Label>Commission Rate (%)</Label>
                        <Input
                            v-model.number="editForm.commission_rate"
                            type="number"
                            min="0"
                            max="100"
                            step="0.5"
                        />
                        <p class="text-sm text-muted-foreground">
                            Default rate is {{ defaultRate }}%. Leave empty or set to default to use the global rate.
                        </p>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="editDialogOpen = false">
                        Cancel
                    </Button>
                    <Button @click="saveRate" :disabled="savingRate">
                        <Loader2 v-if="savingRate" class="h-4 w-4 animate-spin mr-2" />
                        Save Rate
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AdminLayout>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import AdminLayout from '@/layouts/AdminLayout.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog'
import { Pencil, RotateCcw, Loader2 } from 'lucide-vue-next'
import { toast } from 'vue-sonner'
import { useDebounceFn } from '@vueuse/core'

interface Referrer {
    id: number
    name: string
    email: string
    referral_code: string
    commission_rate: number | null
    has_custom_rate: boolean
    effective_rate: number
    total_referrals: number
    total_earned: number
    total_earned_formatted: string
    bank_name: string | null
    account_name: string | null
    created_at: string
}

interface Props {
    referrers: {
        data: Referrer[]
        current_page: number
        last_page: number
        total: number
    }
    defaultRate: number
}

const props = defineProps<Props>()

const searchQuery = ref('')
const editDialogOpen = ref(false)
const editingUser = ref<Referrer | null>(null)
const savingRate = ref(false)

const editForm = ref({
    commission_rate: 0,
})

const debouncedSearch = useDebounceFn(() => {
    router.get(route('admin.referrals.users'), { search: searchQuery.value }, {
        preserveState: true,
        replace: true,
    })
}, 300)

watch(searchQuery, () => {
    debouncedSearch()
})

const goToPage = (page: number) => {
    router.get(route('admin.referrals.users'), { page, search: searchQuery.value }, {
        preserveState: true,
    })
}

const openEditDialog = (user: Referrer) => {
    editingUser.value = user
    editForm.value.commission_rate = user.commission_rate ?? props.defaultRate
    editDialogOpen.value = true
}

const saveRate = async () => {
    if (!editingUser.value) return

    savingRate.value = true

    try {
        const response = await fetch(route('admin.referrals.update-user-rate', editingUser.value.id), {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({ commission_rate: editForm.value.commission_rate }),
        })

        const data = await response.json()

        if (data.success) {
            toast.success(data.message || 'Commission rate updated')
            editDialogOpen.value = false
            router.reload({ only: ['referrers'] })
        } else {
            toast.error(data.message || 'Failed to update rate')
        }
    } catch (error) {
        toast.error('Failed to update rate')
    } finally {
        savingRate.value = false
    }
}

const resetRate = async (user: Referrer) => {
    try {
        const response = await fetch(route('admin.referrals.reset-user-rate', user.id), {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
        })

        const data = await response.json()

        if (data.success) {
            toast.success(data.message || 'Rate reset to default')
            router.reload({ only: ['referrers'] })
        } else {
            toast.error(data.message || 'Failed to reset rate')
        }
    } catch (error) {
        toast.error('Failed to reset rate')
    }
}
</script>
