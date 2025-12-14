<template>
  <AdminLayout title="Users" subtitle="Manage your platform's user base.">

    <div class="grid gap-4 md:grid-cols-3 mb-8">
      <Card class="bg-card text-card-foreground border-border/50 shadow-sm">
        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle class="text-sm font-medium text-muted-foreground">Total Users</CardTitle>
          <Users class="h-4 w-4 text-muted-foreground" />
        </CardHeader>
        <CardContent>
          <div class="text-2xl font-bold">{{ users.data.length }}</div>
        </CardContent>
      </Card>
      <Card class="bg-card text-card-foreground border-border/50 shadow-sm">
        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle class="text-sm font-medium text-muted-foreground">Active Users</CardTitle>
          <UserCheck class="h-4 w-4 text-emerald-500" />
        </CardHeader>
        <CardContent>
          <div class="text-2xl font-bold">{{ activeUsersCount }}</div>
        </CardContent>
      </Card>
      <Card class="bg-card text-card-foreground border-border/50 shadow-sm">
        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle class="text-sm font-medium text-muted-foreground">Banned Users</CardTitle>
          <UserX class="h-4 w-4 text-rose-500" />
        </CardHeader>
        <CardContent>
          <div class="text-2xl font-bold">{{ bannedUsersCount }}</div>
        </CardContent>
      </Card>
    </div>

    <Card class="border border-border/50 bg-card shadow-sm">
      <CardHeader class="flex flex-row items-center justify-between pb-3">
        <div>
          <CardTitle class="text-base font-semibold text-foreground">All Users</CardTitle>
          <CardDescription>A list of all registered users including their name, email, and status.</CardDescription>
        </div>
      </CardHeader>
      <CardContent>
        <DataTable :columns="userColumns" :data="users.data" search-key="email"
          search-placeholder="Filter by email..." />
      </CardContent>
    </Card>

    <!-- Reset Password Dialog -->
    <Dialog :open="resetPasswordOpen" @update:open="resetPasswordOpen = $event">
      <DialogContent class="sm:max-w-[425px]">
        <DialogHeader>
          <DialogTitle>Reset Password</DialogTitle>
          <DialogDescription>
            Set a new password for {{ selectedUser?.name }}.
          </DialogDescription>
        </DialogHeader>
        <div class="grid gap-4 py-4">
          <div class="space-y-2">
            <Label for="password">New Password</Label>
            <Input id="password" type="password" v-model="resetPasswordForm.password" />
            <p v-if="resetPasswordForm.errors.password" class="text-sm text-destructive">
              {{ resetPasswordForm.errors.password }}
            </p>
          </div>
          <div class="space-y-2">
            <Label for="password_confirmation">Confirm Password</Label>
            <Input id="password_confirmation" type="password" v-model="resetPasswordForm.password_confirmation" />
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" @click="resetPasswordOpen = false">Cancel</Button>
          <Button @click="submitResetPassword" :disabled="resetPasswordForm.processing">
            <Loader2 v-if="resetPasswordForm.processing" class="mr-2 h-4 w-4 animate-spin" />
            Reset Password
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- Add/Deduct Words Dialog -->
    <Dialog :open="addWordsOpen" @update:open="addWordsOpen = $event">
      <DialogContent class="sm:max-w-[425px]">
        <DialogHeader>
          <DialogTitle>{{ isDeduction ? 'Deduct Words' : 'Add Words' }}</DialogTitle>
          <DialogDescription>
            {{ isDeduction ? 'Remove words from' : 'Add words to' }} {{ selectedUser?.name }}'s balance.
          </DialogDescription>
        </DialogHeader>
        <div class="grid gap-4 py-4">
          <div class="space-y-2">
            <Label for="words">Amount of Words</Label>
            <Input id="words" type="number" min="0" v-model="addWordsForm.words" placeholder="e.g. 5000" />
            <p v-if="addWordsForm.errors.words" class="text-sm text-destructive">
              {{ addWordsForm.errors.words }}
            </p>
          </div>
          <div class="space-y-2">
            <Label for="reason">Reason</Label>
            <Input id="reason" v-model="addWordsForm.reason" placeholder="e.g. Bonus, Refund, Adjustment" />
            <p v-if="addWordsForm.errors.reason" class="text-sm text-destructive">
              {{ addWordsForm.errors.reason }}
            </p>
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" @click="addWordsOpen = false">Cancel</Button>
          <Button @click="submitAddWords" :disabled="addWordsForm.processing"
            :variant="isDeduction ? 'destructive' : 'default'">
            <Loader2 v-if="addWordsForm.processing" class="mr-2 h-4 w-4 animate-spin" />
            {{ isDeduction ? 'Deduct Words' : 'Add Words' }}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- Suspend User Dialog -->
    <Dialog :open="suspendOpen" @update:open="suspendOpen = $event">
      <DialogContent class="sm:max-w-[425px]">
        <DialogHeader>
          <DialogTitle>Suspend User</DialogTitle>
          <DialogDescription>
            Ban {{ selectedUser?.name }} from accessing the platform.
          </DialogDescription>
        </DialogHeader>
        <div class="grid gap-4 py-4">
          <div class="space-y-2">
            <Label for="ban_reason">Reason for Suspension</Label>
            <Textarea id="ban_reason" v-model="suspendForm.reason" placeholder="e.g. Violation of terms..." />
            <p v-if="suspendForm.errors.reason" class="text-sm text-destructive">
              {{ suspendForm.errors.reason }}
            </p>
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" @click="suspendOpen = false">Cancel</Button>
          <Button variant="destructive" @click="submitSuspend" :disabled="suspendForm.processing">
            <Loader2 v-if="suspendForm.processing" class="mr-2 h-4 w-4 animate-spin" />
            Suspend User
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

  </AdminLayout>
</template>

<script setup lang="ts">
import { computed, ref, provide } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AdminLayout.vue'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import DataTable from '@/components/Admin/DataTable.vue'
import { userColumns, type UserRow } from '@/components/Admin/users/columns'
import { Users, UserCheck, UserX, Loader2 } from 'lucide-vue-next'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { route } from 'ziggy-js'

const props = defineProps<{
  users: {
    data: UserRow[]
  }
}>()

const activeUsersCount = computed(() => props.users.data.filter(u => !u.is_banned).length)
const bannedUsersCount = computed(() => props.users.data.filter(u => u.is_banned).length)

// State
const selectedUser = ref<UserRow | null>(null)
const resetPasswordOpen = ref(false)
const addWordsOpen = ref(false)
const suspendOpen = ref(false)
const isDeduction = ref(false)

// Forms
const resetPasswordForm = useForm({
  password: '',
  password_confirmation: '',
})

const addWordsForm = useForm({
  words: undefined as number | undefined,
  reason: '',
})

const suspendForm = useForm({
  reason: '',
})

// Actions provided to columns
const onResetPassword = (user: UserRow) => {
  selectedUser.value = user
  resetPasswordForm.reset()
  resetPasswordForm.clearErrors()
  resetPasswordOpen.value = true
}

const onAddWords = (user: UserRow) => {
  selectedUser.value = user
  isDeduction.value = false
  addWordsForm.reset()
  addWordsForm.clearErrors()
  addWordsOpen.value = true
}

const onDeductWords = (user: UserRow) => {
  selectedUser.value = user
  isDeduction.value = true
  addWordsForm.reset()
  addWordsForm.clearErrors()
  addWordsOpen.value = true
}

const onSuspend = (user: UserRow) => {
  selectedUser.value = user
  suspendForm.reset()
  suspendForm.clearErrors()
  suspendOpen.value = true
}

provide('onResetPassword', onResetPassword)
provide('onAddWords', onAddWords)
provide('onDeductWords', onDeductWords)
provide('onSuspend', onSuspend)

// Submit Handlers
const submitResetPassword = () => {
  if (!selectedUser.value) return
  resetPasswordForm.post(route('admin.users.reset-password', selectedUser.value.id), {
    onSuccess: () => {
      resetPasswordOpen.value = false
      resetPasswordForm.reset()
    },
  })
}

const submitAddWords = () => {
  if (!selectedUser.value) return

  // Ensure correct sign based on operation
  const amount = Math.abs(Number(addWordsForm.words))
  addWordsForm.words = isDeduction.value ? -amount : amount

  addWordsForm.post(route('admin.users.adjust-balance', selectedUser.value.id), {
    onSuccess: () => {
      addWordsOpen.value = false
      addWordsForm.reset()
    },
  })
}

const submitSuspend = () => {
  if (!selectedUser.value) return
  suspendForm.post(route('admin.users.ban', selectedUser.value.id), {
    onSuccess: () => {
      suspendOpen.value = false
      suspendForm.reset()
    },
  })
}
</script>
