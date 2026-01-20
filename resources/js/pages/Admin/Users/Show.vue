<template>
  <AdminLayout title="User Detail">
    <div class="space-y-4">
      <div class="bg-white border border-slate-200 rounded-xl p-4 text-sm text-slate-700">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <div class="text-xs text-slate-500">Name</div>
            <div class="font-medium text-slate-900">{{ user.name }}</div>
          </div>
          <div>
            <div class="text-xs text-slate-500">Email</div>
            <div class="font-medium text-slate-900">{{ user.email }}</div>
          </div>
          <div>
            <div class="text-xs text-slate-500">Word Balance</div>
            <div class="font-medium text-slate-900">{{ user.word_balance ?? 0 }}</div>
          </div>
          <div>
            <div class="text-xs text-slate-500">Projects</div>
            <div class="font-medium text-slate-900">{{ user.projects_count }}</div>
          </div>
          <div>
            <div class="text-xs text-slate-500">Payments</div>
            <div class="font-medium text-slate-900">{{ user.payments_count }}</div>
          </div>
          <div>
            <div class="text-xs text-slate-500">Status</div>
            <div class="font-medium" :class="user.is_banned ? 'text-red-600' : 'text-green-600'">
              {{ user.is_banned ? 'Banned' : 'Active' }}
            </div>
          </div>
          <div>
            <div class="text-xs text-slate-500">Online</div>
            <div class="font-medium" :class="user.is_online ? 'text-green-600' : 'text-slate-600'">
              {{ user.is_online ? 'Online' : 'Offline' }}
            </div>
          </div>
          <div>
            <div class="text-xs text-slate-500">Last Active</div>
            <div class="font-medium text-slate-900">
              {{ user.last_active_at ? formatDate(user.last_active_at) : '—' }}
            </div>
          </div>
          <div>
            <div class="text-xs text-slate-500">Last Login</div>
            <div class="font-medium text-slate-900">
              {{ user.last_login_at ? formatDate(user.last_login_at) : '—' }}
            </div>
          </div>
        </div>
      </div>

      <div class="bg-white border border-slate-200 rounded-xl p-4 text-sm text-slate-700">
        <h3 class="text-sm font-semibold text-slate-900 mb-2">Recent Word Transactions</h3>
        <div v-if="transactions.length === 0" class="text-slate-500 text-sm">No transactions yet.</div>
        <div v-else class="overflow-x-auto">
          <table class="min-w-full text-left text-sm">
            <thead>
              <tr class="text-xs uppercase tracking-wide text-slate-500 border-b border-slate-200">
                <th class="py-2 pr-3">Type</th>
                <th class="py-2 pr-3">Words</th>
                <th class="py-2 pr-3">Description</th>
                <th class="py-2 pr-3">Date</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="tx in transactions" :key="tx.id" class="border-b border-slate-100">
                <td class="py-2 pr-3">{{ tx.type }}</td>
                <td class="py-2 pr-3">{{ tx.words }}</td>
                <td class="py-2 pr-3">{{ tx.description }}</td>
                <td class="py-2 pr-3 text-slate-500">{{ formatDate(tx.created_at) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="bg-white border border-slate-200 rounded-xl p-4 text-sm text-slate-700">
        <h3 class="text-sm font-semibold text-slate-900 mb-2">Recent Activity</h3>
        <div v-if="activities.length === 0" class="text-slate-500 text-sm">No activity logged yet.</div>
        <div v-else class="overflow-x-auto">
          <table class="min-w-full text-left text-sm">
            <thead>
              <tr class="text-xs uppercase tracking-wide text-slate-500 border-b border-slate-200">
                <th class="py-2 pr-3">Type</th>
                <th class="py-2 pr-3">Message</th>
                <th class="py-2 pr-3">Date</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="activity in activities" :key="activity.id" class="border-b border-slate-100">
                <td class="py-2 pr-3">{{ activity.type }}</td>
                <td class="py-2 pr-3">{{ activity.message }}</td>
                <td class="py-2 pr-3 text-slate-500">{{ formatDate(activity.created_at) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue'

const props = defineProps<{
  user: {
    id: number
    name: string
    email: string
    is_banned: boolean
    word_balance: number | null
    projects_count: number
    payments_count: number
    last_active_at: string | null
    last_login_at: string | null
    is_online: boolean
    created_at: string
  }
  transactions: {
    id: number
    type: string
    words: number
    description: string | null
    created_at: string
  }[]
  activities: {
    id: number
    type: string
    message: string
    created_at: string
  }[]
}>()

const formatDate = (dateStr: string) => new Date(dateStr).toLocaleString()
</script>
