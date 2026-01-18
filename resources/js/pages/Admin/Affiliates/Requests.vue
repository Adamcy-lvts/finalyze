<script setup lang="ts">
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import AdminLayout from '@/layouts/AdminLayout.vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { toast } from 'vue-sonner'

interface RequestItem {
    id: number
    name: string
    email: string
    requested_at: string | null
    projects_count: number
    created_at: string
}

interface Props {
    requests: {
        data: RequestItem[]
        current_page: number
        last_page: number
        total: number
    }
}

const props = defineProps<Props>()
const rejectNotes = ref<Record<number, string>>({})

const approve = async (request: RequestItem) => {
    try {
        const response = await fetch(route('admin.affiliates.requests.approve', request.id), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
        })

        const data = await response.json()
        if (data.success) {
            toast.success('Request approved')
            router.reload({ only: ['requests'] })
        } else {
            toast.error(data.message || 'Failed to approve request')
        }
    } catch (error) {
        toast.error('Failed to approve request')
    }
}

const reject = async (request: RequestItem) => {
    try {
        const response = await fetch(route('admin.affiliates.requests.reject', request.id), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({ reason: rejectNotes.value[request.id] || null }),
        })

        const data = await response.json()
        if (data.success) {
            toast.success('Request rejected')
            router.reload({ only: ['requests'] })
        } else {
            toast.error(data.message || 'Failed to reject request')
        }
    } catch (error) {
        toast.error('Failed to reject request')
    }
}

const formatDate = (dateString: string | null) => {
    if (!dateString) return '—'
    return new Date(dateString).toLocaleDateString('en-NG', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    })
}
</script>

<template>
    <AdminLayout title="Affiliate Requests" subtitle="Review and approve affiliate applications.">
        <Card class="border-border/50">
            <CardHeader>
                <CardTitle>Pending Requests</CardTitle>
            </CardHeader>
            <CardContent>
                <div v-if="props.requests.data.length === 0" class="text-sm text-muted-foreground">No pending requests.</div>
                <div v-else class="space-y-4">
                    <div v-for="request in props.requests.data" :key="request.id" class="rounded-lg border border-border/50 p-4">
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                            <div>
                                <p class="font-medium">{{ request.name }}</p>
                                <p class="text-sm text-muted-foreground">{{ request.email }}</p>
                                <p class="text-xs text-muted-foreground mt-1">Requested: {{ formatDate(request.requested_at) }}</p>
                                <p class="text-xs text-muted-foreground">Projects: {{ request.projects_count }}</p>
                            </div>
                            <div class="flex flex-col sm:flex-row gap-2 sm:items-center">
                                <Input
                                    v-model="rejectNotes[request.id]"
                                    placeholder="Optional rejection note"
                                    class="min-w-[200px]"
                                />
                                <Button variant="outline" @click="reject(request)">Reject</Button>
                                <Button @click="approve(request)">Approve</Button>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="props.requests.last_page > 1" class="flex items-center justify-between mt-6">
                    <p class="text-sm text-muted-foreground">
                        Page {{ props.requests.current_page }} of {{ props.requests.last_page }}
                    </p>
                    <div class="flex gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="props.requests.current_page === 1"
                            @click="router.get(route('admin.affiliates.requests.index'), { page: props.requests.current_page - 1 }, { preserveState: true })"
                        >
                            Previous
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="props.requests.current_page === props.requests.last_page"
                            @click="router.get(route('admin.affiliates.requests.index'), { page: props.requests.current_page + 1 }, { preserveState: true })"
                        >
                            Next
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    </AdminLayout>
</template>
