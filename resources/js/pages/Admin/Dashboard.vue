<template>
  <AdminLayout>
    <template #title>
      <div class="flex flex-col">
        <h2 class="text-2xl font-bold tracking-tight text-foreground">Dashboard</h2>
        <p class="text-muted-foreground text-sm">Overview of your system's performance.</p>
      </div>
    </template>

    <template #actions>
      <Button variant="outline" size="icon" class="relative" @click="openNotifications">
        <Bell class="h-4 w-4" />
        <span v-if="unreadCount > 0"
          class="absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full bg-destructive text-[10px] text-destructive-foreground ring-2 ring-background animate-pulse">
          {{ unreadCount }}
        </span>
        <span class="sr-only">Notifications</span>
      </Button>
    </template>

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
    <!-- Notification Drawer -->
    <Sheet v-model:open="isNotificationsOpen">
      <SheetContent
        class="w-full sm:max-w-md p-0 flex flex-col h-full bg-background/95 backdrop-blur-sm shadow-2xl border-l-primary/10">
        <SheetHeader class="px-6 py-4 border-b bg-muted/30 sticky top-0 z-10">
          <div class="flex items-center justify-between">
            <div class="space-y-1">
              <SheetTitle
                class="text-xl font-bold bg-gradient-to-r from-primary to-primary/60 bg-clip-text text-transparent">
                Notifications</SheetTitle>
              <SheetDescription>
                You have {{ unreadCount }} unread messages
              </SheetDescription>
            </div>
            <div v-if="unreadCount > 0">
              <Button variant="ghost" size="xs"
                class="h-8 text-xs hover:bg-primary/10 hover:text-primary transition-colors" @click="markAllAsRead">
                <Check class="mr-1.5 h-3.5 w-3.5" />
                Mark all read
              </Button>
            </div>
          </div>
        </SheetHeader>

        <div class="flex-1 overflow-y-auto custom-scrollbar">
          <div v-if="isLoadingNotifications" class="flex flex-col items-center justify-center h-40 space-y-3">
            <Loader2 class="h-8 w-8 animate-spin text-primary/50" />
            <p class="text-sm text-muted-foreground animate-pulse">Loading updates...</p>
          </div>

          <div v-else-if="notifications.length === 0"
            class="flex flex-col items-center justify-center h-full text-center p-8 space-y-4">
            <div class="rounded-full bg-muted/50 p-6 ring-1 ring-border shadow-sm">
              <Inbox class="h-10 w-10 text-muted-foreground/50" />
            </div>
            <h3 class="text-lg font-medium text-foreground">All caught up!</h3>
            <p class="text-sm text-muted-foreground max-w-[15rem]">You don't have any new notifications at the moment.
            </p>
          </div>

          <div v-else class="divide-y divide-border/40">
            <div v-for="notification in notifications" :key="notification.id"
              class="group relative flex flex-col p-4 transition-all duration-200 hover:bg-muted/40 gap-3"
              :class="{ 'bg-primary/5': !notification.is_read && !notification.read_at }">
              <!-- Header -->
              <div class="flex items-start justify-between gap-3">
                <div class="flex items-center gap-2">
                  <div class="h-2 w-2 rounded-full shrink-0" :class="{
                    'bg-rose-500': notification.severity === 'critical',
                    'bg-amber-500': notification.severity === 'warning',
                    'bg-blue-500': notification.severity === 'info' || !notification.severity,
                    'bg-emerald-500': notification.severity === 'success',
                    'animate-pulse': !notification.is_read && !notification.read_at
                  }"></div>
                  <h4 class="text-sm font-semibold text-foreground leading-none">
                    {{ notification.title || notification.data?.title || humanizeType(notification.type) }}
                  </h4>
                </div>
                <span class="text-[10px] text-muted-foreground whitespace-nowrap">
                  {{ formatTime(notification.created_at) }}
                </span>
              </div>

              <!-- Body -->
              <div class="pl-4">
                <p class="text-xs text-muted-foreground leading-relaxed"
                  v-if="notification.message || notification.data?.message">
                  <template v-if="(notification.message || notification.data?.message).includes('|')">
                    <span class="flex flex-wrap gap-2 mt-1">
                      <span v-for="(part, idx) in (notification.message || notification.data?.message).split('|')"
                        :key="idx"
                        class="inline-flex items-center px-2 py-1 rounded-md bg-background border border-border text-[10px] font-medium">
                        {{ part.trim() }}
                      </span>
                    </span>
                  </template>
                  <template v-else>
                    {{ notification.message || notification.data?.message }}
                  </template>
                </p>
                <p v-else class="text-xs text-muted-foreground italic">No details provided.</p>
              </div>

              <!-- Footer / Actions -->
              <div class="pl-4 flex items-center justify-between mt-1">
                <!-- Custom Actions from Data (if any) -->
                <div v-if="notification.data?.action_url" class="flex gap-2">
                  <Button variant="outline" size="xs" class="h-6 text-[10px]" as="a"
                    :href="notification.data.action_url">
                    {{ notification.data.action_text || 'View Details' }}
                  </Button>
                </div>
                <div v-else></div> <!-- Spacer -->

                <Button v-if="!notification.is_read && !notification.read_at" variant="ghost" size="xs"
                  class="h-6 text-[10px] gap-1 hover:text-primary transition-opacity opacity-0 group-hover:opacity-100"
                  @click.stop="markAsRead(notification.id)">
                  <Check class="h-3 w-3" /> Mark as read
                </Button>
              </div>
            </div>
          </div>
        </div>
      </SheetContent>
    </Sheet>
  </AdminLayout>
</template>

<script setup lang="ts">
import { computed, h, ref, onMounted } from 'vue'
import AdminLayout from '@/layouts/AdminLayout.vue'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Button } from '@/components/ui/button'
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
  Bell,
  X,
  Check,
  Loader2,
  Inbox
} from 'lucide-vue-next'
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
  SheetClose,
  SheetFooter
} from '@/components/ui/sheet'
import axios from 'axios'
import {
  useVueTable,
  getCoreRowModel,
  FlexRender,
  createColumnHelper,
} from '@tanstack/vue-table'

const props = defineProps<{
  stats: {
    users: { total: number; today: number }
    revenue: { total: number; today: number }
    projects: { total: number; today: number }
    words: { total: number; today: number }
  }
  recentActivity: { message: string; time: string }[]
}>()

const formatNumber = (val: number) => Number(val ?? 0).toLocaleString()

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

// Notification Logic
const isNotificationsOpen = ref(false)
const notifications = ref<any[]>([])
const isLoadingNotifications = ref(false)

const unreadCount = computed(() => notifications.value.filter(n => !n.is_read && !n.read_at).length)

const fetchNotifications = async () => {
  isLoadingNotifications.value = true
  try {
    const { data } = await axios.get('/admin/notifications?format=json')
    notifications.value = data.notifications || []
  } catch (error) {
    console.error('Failed to fetch notifications:', error)
  } finally {
    isLoadingNotifications.value = false
  }
}

const openNotifications = () => {
  isNotificationsOpen.value = true
  fetchNotifications()
}

const markAsRead = async (id: string) => {
  try {
    await axios.post(`/admin/notifications/${id}/read`)
    // Optimistic update
    const index = notifications.value.findIndex(n => n.id === id)
    if (index !== -1) {
      notifications.value[index].is_read = true
      notifications.value[index].read_at = new Date().toISOString()
    }
  } catch (error) {
    console.error('Failed to mark as read:', error)
  }
}

const markAllAsRead = async () => {
  try {
    await axios.post('/admin/notifications/read-all')
    // Optimistic update
    notifications.value.forEach(n => {
      n.is_read = true
      n.read_at = new Date().toISOString()
    })
  } catch (error) {
    console.error('Failed to mark all as read:', error)
  }
}

const formatTime = (dateString: string) => {
  const date = new Date(dateString)
  const now = new Date()
  const diffInSeconds = Math.floor((now.getTime() - date.getTime()) / 1000)

  if (diffInSeconds < 60) return 'Just now'
  if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`
  if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`
  return date.toLocaleDateString()
}

const humanizeType = (type: string) => {
  return type.split('\\').pop()?.replace(/([A-Z])/g, ' $1').trim() || 'Notification'
}

onMounted(() => {
  fetchNotifications()
})
</script>
