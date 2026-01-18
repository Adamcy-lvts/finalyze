<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { route } from 'ziggy-js'
import AppLayout from '@/layouts/AppLayout.vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'

interface Referral {
    id: number
    name: string
    email: string
    successful_payments_count: number
    created_at: string
}

const referrals = ref<Referral[]>([])
const loading = ref(false)
const pagination = ref({
    current_page: 1,
    last_page: 1,
    total: 0,
    per_page: 20,
})

const load = async (page = 1) => {
    loading.value = true
    try {
        const response = await fetch(`${route('affiliate.referrals.data')}?page=${page}`)
        const data = await response.json()
        referrals.value = data.referrals || []
        pagination.value = data.pagination || pagination.value
    } finally {
        loading.value = false
    }
}

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-NG', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    })
}

onMounted(() => load())
</script>

<template>
    <AppLayout title="Affiliate Referrals">
        <div class="container mx-auto px-4 py-8 max-w-6xl">
            <Card class="border-border/50">
                <CardHeader>
                    <CardTitle>Your Referrals</CardTitle>
                </CardHeader>
                <CardContent>
                    <div v-if="loading" class="text-sm text-muted-foreground">Loading referrals...</div>
                    <div v-else-if="referrals.length === 0" class="text-sm text-muted-foreground">No referrals yet.</div>
                    <div v-else class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b border-border/50">
                                    <th class="text-left py-3 px-2 text-sm font-medium text-muted-foreground">User</th>
                                    <th class="text-center py-3 px-2 text-sm font-medium text-muted-foreground">Purchases</th>
                                    <th class="text-right py-3 px-2 text-sm font-medium text-muted-foreground">Joined</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="referral in referrals" :key="referral.id" class="border-b border-border/30">
                                    <td class="py-3 px-2 text-sm">
                                        <div class="font-medium">{{ referral.name }}</div>
                                        <div class="text-xs text-muted-foreground">{{ referral.email }}</div>
                                    </td>
                                    <td class="py-3 px-2 text-center">
                                        <Badge v-if="referral.successful_payments_count > 0" class="bg-green-500/10 text-green-600 border-green-500/20">
                                            {{ referral.successful_payments_count }} purchase{{ referral.successful_payments_count > 1 ? 's' : '' }}
                                        </Badge>
                                        <span v-else class="text-xs text-muted-foreground">No purchases</span>
                                    </td>
                                    <td class="py-3 px-2 text-right text-sm text-muted-foreground">{{ formatDate(referral.created_at) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="pagination.last_page > 1" class="flex items-center justify-end gap-2 pt-4">
                        <Button variant="outline" size="sm" :disabled="pagination.current_page <= 1" @click="load(pagination.current_page - 1)">
                            Previous
                        </Button>
                        <Button variant="outline" size="sm" :disabled="pagination.current_page >= pagination.last_page" @click="load(pagination.current_page + 1)">
                            Next
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
