<script setup lang="ts">
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import AdminLayout from '@/layouts/AdminLayout.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Badge } from '@/components/ui/badge'
import { Switch } from '@/components/ui/switch'
import { toast } from 'vue-sonner'

interface Invite {
    id: number
    code: string
    type: string
    max_uses: number | null
    uses: number
    expires_at: string | null
    is_active: boolean
    note: string | null
    created_by: string | null
    created_at: string
}

interface Props {
    invites: {
        data: Invite[]
        current_page: number
        last_page: number
        total: number
    }
}

const props = defineProps<Props>()

const form = ref({
    type: 'single_use',
    max_uses: null as number | null,
    expires_at: '',
    note: '',
})

const creating = ref(false)

const createInvite = async () => {
    creating.value = true
    try {
        const response = await fetch(route('admin.affiliates.invites.store'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({
                type: form.value.type,
                max_uses: form.value.type === 'reusable' ? form.value.max_uses : null,
                expires_at: form.value.expires_at || null,
                note: form.value.note || null,
            }),
        })

        const data = await response.json()

        if (data.success) {
            toast.success('Invite created')
            router.reload({ only: ['invites'] })
            form.value = { type: 'single_use', max_uses: null, expires_at: '', note: '' }
        } else {
            toast.error(data.message || 'Failed to create invite')
        }
    } catch (error) {
        toast.error('Failed to create invite')
    } finally {
        creating.value = false
    }
}

const updateInvite = async (invite: Invite, updates: Partial<Invite>) => {
    try {
        const response = await fetch(route('admin.affiliates.invites.update', invite.id), {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify(updates),
        })

        const data = await response.json()

        if (data.success) {
            toast.success('Invite updated')
            router.reload({ only: ['invites'] })
        } else {
            toast.error(data.message || 'Failed to update invite')
        }
    } catch (error) {
        toast.error('Failed to update invite')
    }
}

const deleteInvite = async (invite: Invite) => {
    try {
        const response = await fetch(route('admin.affiliates.invites.destroy', invite.id), {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
        })

        const data = await response.json()

        if (data.success) {
            toast.success('Invite deleted')
            router.reload({ only: ['invites'] })
        } else {
            toast.error(data.message || 'Failed to delete invite')
        }
    } catch (error) {
        toast.error('Failed to delete invite')
    }
}

const copyLink = async (code: string) => {
    try {
        await navigator.clipboard.writeText(`${window.location.origin}/affiliate/invite/${code}`)
        toast.success('Invite link copied')
    } catch (error) {
        toast.error('Failed to copy invite link')
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
    <AdminLayout title="Affiliate Invites" subtitle="Generate and manage affiliate registration invites.">
        <div class="grid gap-6 lg:grid-cols-[1.1fr_1fr]">
            <Card class="border-border/50">
                <CardHeader>
                    <CardTitle>Create Invite</CardTitle>
                    <CardDescription>Generate single-use or reusable invite links.</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="space-y-2">
                        <Label>Invite Type</Label>
                        <Select v-model="form.type">
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="single_use">Single Use</SelectItem>
                                <SelectItem value="reusable">Reusable</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div v-if="form.type === 'reusable'" class="space-y-2">
                        <Label>Max Uses</Label>
                        <Input v-model.number="form.max_uses" type="number" min="1" />
                    </div>

                    <div class="space-y-2">
                        <Label>Expires At</Label>
                        <Input v-model="form.expires_at" type="date" />
                    </div>

                    <div class="space-y-2">
                        <Label>Note</Label>
                        <Input v-model="form.note" placeholder="For John Doe" />
                    </div>

                    <Button class="w-full" :disabled="creating" @click="createInvite">
                        Create Invite
                    </Button>
                </CardContent>
            </Card>

            <Card class="border-border/50">
                <CardHeader>
                    <CardTitle>Invite List</CardTitle>
                    <CardDescription>Track invite usage and status.</CardDescription>
                </CardHeader>
                <CardContent>
                    <div v-if="props.invites.data.length === 0" class="text-sm text-muted-foreground">No invites yet.</div>
                    <div v-else class="space-y-4">
                        <div v-for="invite in props.invites.data" :key="invite.id" class="rounded-lg border border-border/50 p-4">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <code class="text-sm bg-muted px-2 py-1 rounded">{{ invite.code }}</code>
                                        <Badge variant="secondary">{{ invite.type.replace('_', ' ') }}</Badge>
                                    </div>
                                    <p class="text-xs text-muted-foreground mt-2">
                                        Uses: {{ invite.uses }}{{ invite.max_uses ? ` / ${invite.max_uses}` : '' }} · Expires: {{ formatDate(invite.expires_at) }}
                                    </p>
                                    <p v-if="invite.note" class="text-xs text-muted-foreground">{{ invite.note }}</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <Switch
                                        :checked="invite.is_active"
                                        @update:checked="updateInvite(invite, { is_active: $event })"
                                    />
                                    <Button variant="outline" size="sm" @click="copyLink(invite.code)">Copy Link</Button>
                                    <Button variant="ghost" size="sm" @click="deleteInvite(invite)">Delete</Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AdminLayout>
</template>
