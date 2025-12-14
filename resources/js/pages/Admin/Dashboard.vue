<template>
  <AdminLayout title="Dashboard" subtitle="Overview of your system's performance.">

    <!-- Stats Grid -->
    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4 mb-8">
      <Card v-for="stat in statsData" :key="stat.title"
        class="bg-card text-card-foreground border-border/50 shadow-sm hover:shadow-md transition-all duration-200">
        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle class="text-sm font-medium text-muted-foreground">
            {{ stat.title }}
          </CardTitle>
          <component :is="stat.icon" class="h-4 w-4 text-muted-foreground" />
        </CardHeader>
        <CardContent>
          <div class="text-2xl font-bold">{{ stat.value }}</div>
          <p class="text-xs text-muted-foreground mt-1 flex items-center gap-1">
            <span :class="stat.trendUp ? 'text-emerald-500' : 'text-rose-500'" class="flex items-center">
              <ArrowUpRight v-if="stat.trendUp" class="h-3 w-3 mr-0.5" />
              <ArrowDownRight v-else class="h-3 w-3 mr-0.5" />
              {{ stat.trend }}
            </span>
            <span class="opacity-70">from yesterday</span>
          </p>
        </CardContent>
      </Card>
    </div>

    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-7">
      <!-- Main Chart Area (Placeholder) -->
      <Card class="col-span-4 border-border/50 shadow-sm">
        <CardHeader>
          <CardTitle>Revenue Overview</CardTitle>
          <CardDescription>Monthly revenue breakdown for the current year.</CardDescription>
        </CardHeader>
        <CardContent class="pl-2">
          <div
            class="h-[350px] w-full flex items-center justify-center rounded-md border border-dashed border-border bg-muted/20">
            <div class="flex flex-col items-center gap-2 text-muted-foreground">
              <BarChart3 class="h-10 w-10 opacity-50" />
              <span class="text-sm font-medium">Chart Visualization Placeholder</span>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Recent Activity / System Status -->
      <Card class="col-span-3 border-border/50 shadow-sm flex flex-col">
        <CardHeader>
          <CardTitle>System Health</CardTitle>
          <CardDescription>Real-time status of core services.</CardDescription>
        </CardHeader>
        <CardContent class="flex-1">
          <div class="space-y-6">
            <div v-for="(status, index) in systemStatus" :key="index" class="flex items-center justify-between">
              <div class="flex items-center gap-3">
                <div class="flex h-9 w-9 items-center justify-center rounded-full bg-muted/50 border border-border">
                  <component :is="status.icon" class="h-4 w-4 text-foreground" />
                </div>
                <div class="space-y-1">
                  <p class="text-sm font-medium leading-none">{{ status.name }}</p>
                  <p class="text-xs text-muted-foreground">{{ status.description }}</p>
                </div>
              </div>
              <div class="flex items-center gap-2">
                <span class="relative flex h-2.5 w-2.5">
                  <span
                    class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                  <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                </span>
                <span class="text-xs font-medium text-emerald-600 dark:text-emerald-400">Operational</span>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>

    <!-- Registration Invites -->
    <div class="mt-8">
      <Card class="border-border/50 shadow-sm">
        <CardHeader>
          <div class="flex items-start justify-between gap-4">
            <div>
              <CardTitle>Registration Invites</CardTitle>
              <CardDescription>
                Generate invite codes/links to control who can register while testing.
              </CardDescription>
            </div>
            <Badge :variant="inviteOnlyEnabled ? 'default' : 'secondary'">
              {{ inviteOnlyEnabled ? 'Invite-only enabled' : 'Invite-only disabled' }}
            </Badge>
          </div>
        </CardHeader>
        <CardContent class="space-y-6">
          <div class="grid gap-4 md:grid-cols-3">
            <div class="grid gap-2">
              <Label for="count">How many</Label>
              <Input id="count" type="number" min="1" max="25" v-model="inviteForm.count" />
            </div>
            <div class="grid gap-2">
              <Label for="max_uses">Max uses</Label>
              <Input id="max_uses" type="number" min="1" max="1000" v-model="inviteForm.max_uses" />
            </div>
            <div class="grid gap-2">
              <Label for="expires_in_days">Expires in (days)</Label>
              <Input
                id="expires_in_days"
                type="number"
                min="1"
                max="365"
                v-model="inviteForm.expires_in_days"
              />
            </div>
          </div>

          <div class="flex flex-wrap items-center gap-3">
            <Button
              size="sm"
              class="gap-2"
              :disabled="inviteForm.processing"
              @click="createInvites"
            >
              <Plus class="h-4 w-4" />
              Generate invite{{ Number(inviteForm.count) === 1 ? '' : 's' }}
            </Button>
            <div v-if="inviteForm.hasErrors" class="text-sm text-destructive">
              Please fix the invite form errors.
            </div>
          </div>

          <div class="rounded-md border border-border">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Code</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead>Uses</TableHead>
                  <TableHead>Expires</TableHead>
                  <TableHead class="text-right">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow v-for="invite in invites" :key="invite.id">
                  <TableCell class="font-mono text-sm">{{ invite.code }}</TableCell>
                  <TableCell>
                    <Badge :variant="statusVariant(invite.status)">
                      {{ statusLabel(invite.status) }}
                    </Badge>
                  </TableCell>
                  <TableCell class="text-sm text-muted-foreground">
                    {{ invite.uses }}<span v-if="invite.max_uses"> / {{ invite.max_uses }}</span>
                  </TableCell>
                  <TableCell class="text-sm text-muted-foreground">
                    {{ invite.expires_at ? formatDate(invite.expires_at) : '—' }}
                  </TableCell>
                  <TableCell class="text-right">
                    <div class="flex justify-end gap-2">
                      <Button
                        variant="outline"
                        size="sm"
                        class="h-8 gap-1"
                        @click="copy(invite.code, 'Invite code copied')"
                      >
                        <Copy class="h-3.5 w-3.5" />
                        <span class="hidden sm:inline">Copy</span>
                      </Button>
                      <Button
                        variant="outline"
                        size="sm"
                        class="h-8 gap-1"
                        @click="copy(invite.link, 'Invite link copied')"
                      >
                        <LinkIcon class="h-3.5 w-3.5" />
                        <span class="hidden sm:inline">Link</span>
                      </Button>
                      <Button
                        v-if="invite.status === 'active'"
                        variant="destructive"
                        size="sm"
                        class="h-8 gap-1"
                        @click="revokeInvite(invite.id)"
                      >
                        <Ban class="h-3.5 w-3.5" />
                        <span class="hidden sm:inline">Revoke</span>
                      </Button>
                    </div>
                  </TableCell>
                </TableRow>
                <TableRow v-if="!invites.length">
                  <TableCell colspan="5" class="h-24 text-center text-muted-foreground">
                    No invites yet.
                  </TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </div>
        </CardContent>
      </Card>
    </div>

    <!-- Recent Activity Table -->
    <div class="mt-8">
      <Card class="border-border/50 shadow-sm">
        <CardHeader>
          <div class="flex items-center justify-between">
            <div>
              <CardTitle>Recent Activity</CardTitle>
              <CardDescription>Latest actions performed across the platform.</CardDescription>
            </div>
            <Button variant="outline" size="sm" class="h-8 gap-1">
              <Filter class="h-3.5 w-3.5" />
              <span class="sr-only sm:not-sr-only sm:whitespace-nowrap">Filter</span>
            </Button>
          </div>
        </CardHeader>
        <CardContent>
          <div class="rounded-md border border-border">
            <Table>
              <TableHeader>
                <TableRow v-for="headerGroup in table.getHeaderGroups()" :key="headerGroup.id">
                  <TableHead v-for="header in headerGroup.headers" :key="header.id">
                    <FlexRender v-if="!header.isPlaceholder" :render="header.column.columnDef.header"
                      :props="header.getContext()" />
                  </TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <template v-if="table.getRowModel().rows?.length">
                  <TableRow v-for="row in table.getRowModel().rows" :key="row.id"
                    :data-state="row.getIsSelected() ? 'selected' : undefined">
                    <TableCell v-for="cell in row.getVisibleCells()" :key="cell.id">
                      <FlexRender :render="cell.column.columnDef.cell" :props="cell.getContext()" />
                    </TableCell>
                  </TableRow>
                </template>
                <TableRow v-else>
                  <TableCell :colspan="columns.length" class="h-24 text-center">
                    No results.
                  </TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </div>
        </CardContent>
      </Card>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import { computed, h } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import { route } from 'ziggy-js'
import AdminLayout from '@/layouts/AdminLayout.vue'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import {
  Users,
  DollarSign,
  FileText,
  Type,
  ArrowUpRight,
  ArrowDownRight,
  Activity,
  Server,
  Database,
  Cpu,
  BarChart3,

  Filter,
  Copy,
  Plus,
  Ban,
  Link as LinkIcon,
} from 'lucide-vue-next'
import {
  useVueTable,
  getCoreRowModel,
  FlexRender,
  createColumnHelper,
} from '@tanstack/vue-table'

const props = defineProps<{
  inviteOnlyEnabled: boolean
  stats: {
    users: { total: number; today: number }
    revenue: { total: number; today: number }
    projects: { total: number; today: number }
    words: { total: number; today: number }
  }
  recentActivity: { message: string; time: string }[]
  invites: {
    id: number
    code: string
    link: string
    uses: number
    max_uses: number | null
    status: 'active' | 'expired' | 'revoked' | 'used_up'
    expires_at: string | null
    created_at: string | null
  }[]
}>()

const formatNumber = (val: number) => Number(val ?? 0).toLocaleString()
const formatDate = (iso: string) => new Date(iso).toLocaleString()

const inviteForm = useForm({
  count: 1,
  max_uses: 1,
  expires_in_days: 7,
})

const createInvites = () => {
  inviteForm.post(route('admin.invites.store'), {
    preserveScroll: true,
    onSuccess: () => {
      toast.success('Invite(s) created')
    },
  })
}

const revokeInvite = (inviteId: number) => {
  router.post(route('admin.invites.revoke', { invite: inviteId }), undefined, {
    preserveScroll: true,
    onSuccess: () => {
      toast.success('Invite revoked')
    },
  })
}

const copy = async (text: string, successMessage: string) => {
  const value = String(text ?? '')
  if (!value) {
    toast.error('Nothing to copy')
    return
  }

  try {
    if (window.isSecureContext && navigator.clipboard?.writeText) {
      await navigator.clipboard.writeText(value)
      toast.success(successMessage)
      return
    }
  } catch {
    // Fall back below
  }

  try {
    const el = document.createElement('textarea')
    el.value = value
    el.setAttribute('readonly', 'true')
    el.style.position = 'fixed'
    el.style.top = '-9999px'
    el.style.left = '-9999px'
    document.body.appendChild(el)
    el.focus()
    el.select()
    const ok = document.execCommand('copy')
    document.body.removeChild(el)

    if (ok) {
      toast.success(successMessage)
    } else {
      toast.error('Copy failed (browser blocked)')
    }
  } catch {
    toast.error('Copy failed')
  }
}

const statusLabel = (status: string) => {
  if (status === 'active') return 'Active'
  if (status === 'expired') return 'Expired'
  if (status === 'revoked') return 'Revoked'
  if (status === 'used_up') return 'Used up'
  return status
}

const statusVariant = (status: string) => {
  if (status === 'active') return 'default'
  if (status === 'expired') return 'secondary'
  if (status === 'used_up') return 'secondary'
  if (status === 'revoked') return 'destructive'
  return 'secondary'
}

const statsData = computed(() => [
  {
    title: 'Total Users',
    value: formatNumber(props.stats.users.total),
    icon: Users,
    trend: `+${formatNumber(props.stats.users.today)}`,
    trendUp: true
  },
  {
    title: 'Total Revenue',
    value: `₦${formatNumber(props.stats.revenue.total)}`,
    icon: DollarSign,
    trend: `+₦${formatNumber(props.stats.revenue.today)}`,
    trendUp: true
  },
  {
    title: 'Active Projects',
    value: formatNumber(props.stats.projects.total),
    icon: FileText,
    trend: `+${formatNumber(props.stats.projects.today)}`,
    trendUp: true
  },
  {
    title: 'Words Generated',
    value: formatNumber(props.stats.words.total),
    icon: Type,
    trend: `+${formatNumber(props.stats.words.today)}`,
    trendUp: true
  },
])

const systemStatus = [
  { name: 'Job Queue', description: 'Processing background tasks', icon: Activity },
  { name: 'OpenAI API', description: 'Content generation service', icon: Cpu },
  { name: 'Database', description: 'Primary data storage', icon: Database },
  { name: 'Cache', description: 'Redis cache cluster', icon: Server },
]

// TanStack Table Setup for Recent Activity
const columnHelper = createColumnHelper<{ message: string; time: string }>()

const columns = [
  columnHelper.accessor('message', {
    header: 'Activity',
    cell: (info) => h('div', { class: 'font-medium text-foreground' }, info.getValue()),
  }),
  columnHelper.accessor('time', {
    header: 'Time',
    cell: (info) => h('div', { class: 'text-muted-foreground text-sm' }, info.getValue()),
  }),
]

const table = useVueTable({
  get data() {
    return props.recentActivity
  },
  columns,
  getCoreRowModel: getCoreRowModel(),
})
</script>
