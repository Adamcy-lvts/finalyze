<template>
  <AdminLayout>
    <template #title>
      <div class="flex flex-col">
        <h2 class="text-2xl font-bold tracking-tight text-foreground">Dashboard</h2>
        <p class="text-muted-foreground text-sm">Overview of your system's performance.</p>
      </div>
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
  </AdminLayout>
</template>

<script setup lang="ts">
import { computed, h } from 'vue'
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
  Filter
} from 'lucide-vue-next'
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
</script>
