<template>
  <AdminLayout title="Analytics" subtitle="Insights across users, revenue, and usage.">
    <div class="space-y-6">
      <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <Card v-for="stat in statCards" :key="stat.label"
          class="bg-card text-card-foreground border-border/50 shadow-sm">
          <CardHeader class="pb-2">
            <CardTitle class="text-sm font-medium text-muted-foreground">{{ stat.label }}</CardTitle>
            <CardDescription class="text-xs text-muted-foreground/80">{{ stat.caption }}</CardDescription>
          </CardHeader>
          <CardContent class="pt-0">
            <div class="text-2xl font-bold text-foreground">{{ stat.value }}</div>
            <div class="text-xs mt-1 flex items-center gap-2">
              <span :class="stat.trend >= 0 ? 'text-emerald-500' : 'text-rose-500'" class="font-medium">
                {{ stat.trend >= 0 ? '+' : '' }}{{ stat.trend }}%
              </span>
              <span class="text-muted-foreground/60">vs last period</span>
            </div>
          </CardContent>
        </Card>
      </div>

      <div class="grid gap-6 lg:grid-cols-2">
        <Card class="bg-card text-card-foreground border-border/50 shadow-sm">
          <CardHeader>
            <div class="flex items-start justify-between">
              <div>
                <CardTitle class="text-base font-semibold text-foreground">Revenue Trend</CardTitle>
                <CardDescription>Last 30 days</CardDescription>
              </div>
              <div class="text-right">
                <p class="text-xs text-muted-foreground">Total</p>
                <p class="text-lg font-bold text-foreground">{{ formatCurrency(props.stats.revenue.last30) }}</p>
              </div>
            </div>
          </CardHeader>
          <CardContent class="pl-0">
            <template v-if="revenueData.length > 0">
              <ChartContainer :config="revenueChartConfig" class="h-[280px] w-full">
                <VisXYContainer :data="revenueData" :height="280" :margin="{ top: 5, right: 10, bottom: 0, left: 10 }">
                  <VisLine :x="(d: RevenuePoint) => d.date" :y="(d: RevenuePoint) => d.amount"
                    :color="revenueChartConfig.revenue.color" :curve-type="'monotone'" :stroke-width="2" />
                  <VisAxis type="x" :x="(d: RevenuePoint) => d.date" :grid-line="false" :domain-line="false"
                    :tick-line="false" :tick-format="formatShortDate" :tick-values="revenueData.map((d) => d.date)"
                    color="#888888" />
                  <VisAxis type="y" :tick-line="false" :domain-line="false" :grid-line="true"
                    :tick-format="(v: number) => formatCurrencyShort(v)" color="#888888" />
                  <ChartTooltip />
                  <ChartCrosshair />
                </VisXYContainer>
              </ChartContainer>
            </template>
            <template v-else>
              <div class="h-[280px] flex items-center justify-center text-muted-foreground">
                No revenue data available
              </div>
            </template>
          </CardContent>
        </Card>

        <Card class="bg-card text-card-foreground border-border/50 shadow-sm">
          <CardHeader>
            <div class="flex items-start justify-between">
              <div>
                <CardTitle class="text-base font-semibold text-foreground">User Signups</CardTitle>
                <CardDescription>Last 7 days</CardDescription>
              </div>
              <div class="text-right">
                <p class="text-xs text-muted-foreground">New</p>
                <p class="text-lg font-bold text-foreground">{{ weeklyNew }}</p>
              </div>
            </div>
          </CardHeader>
          <CardContent class="pl-0">
            <template v-if="signupData.length > 0">
              <ChartContainer :config="signupChartConfig" class="h-[280px] w-full">
                <VisXYContainer :data="signupData" :height="280" :margin="{ top: 5, right: 10, bottom: 0, left: 10 }">
                  <VisGroupedBar :x="(d: SignupPoint) => d.label"
                    :y="[(d: SignupPoint) => d.newUsers]"
                    :color="[signupChartConfig.new.color]" :rounded-corners="4"
                    :bar-padding="0.2" />
                  <VisAxis type="x" :x="(d: SignupPoint) => d.label" :grid-line="false" :domain-line="false"
                    :tick-line="false" color="#888888" />
                  <VisAxis type="y" :tick-line="false" :domain-line="false" :grid-line="true"
                    :tick-format="(v: number) => Math.round(v)" color="#888888" />
                  <ChartTooltip />
                  <ChartCrosshair />
                </VisXYContainer>
                <ChartLegendContent :config="signupChartConfig" class="mt-4 justify-center" />
              </ChartContainer>
            </template>
            <template v-else>
              <div class="h-[280px] flex items-center justify-center text-muted-foreground">
                No signup data available
              </div>
            </template>
          </CardContent>
        </Card>
      </div>

      <Card class="bg-card text-card-foreground border-border/50 shadow-sm">
        <CardHeader class="pb-3">
          <CardTitle class="text-base font-semibold">System Snapshot</CardTitle>
          <CardDescription class="text-sm text-muted-foreground">Quick view of platform health</CardDescription>
        </CardHeader>
        <CardContent>
          <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-lg border border-border/50 bg-muted/30 p-4 hover:bg-muted/50 transition-colors">
              <div class="flex items-center justify-between mb-2">
                <p class="text-sm font-medium text-muted-foreground">Queue</p>
                <div class="h-2 w-2 rounded-full animate-pulse" :class="queueStatusColor"></div>
              </div>
              <p class="text-2xl font-bold text-foreground capitalize">{{ props.systemHealth.queue.status }}</p>
              <p class="text-xs text-muted-foreground mt-1">
                {{ props.systemHealth.queue.pending }} pending &bull; {{ props.systemHealth.queue.failed }} failed
              </p>
            </div>
            <div class="rounded-lg border border-border/50 bg-muted/30 p-4 hover:bg-muted/50 transition-colors">
              <div class="flex items-center justify-between mb-2">
                <p class="text-sm font-medium text-muted-foreground">AI Tokens Today</p>
                <div class="h-2 w-2 rounded-full bg-emerald-500"></div>
              </div>
              <p class="text-2xl font-bold text-foreground">{{ formatNumber(props.systemHealth.aiTokensToday) }}</p>
              <p class="text-xs text-muted-foreground mt-1">Total tokens used today</p>
            </div>
            <div class="rounded-lg border border-border/50 bg-muted/30 p-4 hover:bg-muted/50 transition-colors">
              <div class="flex items-center justify-between mb-2">
                <p class="text-sm font-medium text-muted-foreground">Words Used</p>
                <div class="h-2 w-2 rounded-full bg-emerald-500"></div>
              </div>
              <p class="text-2xl font-bold text-foreground">{{ formatNumber(props.stats.wordsUsed.total) }}</p>
              <p class="text-xs text-muted-foreground mt-1">All time word usage</p>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { VisAxis, VisGroupedBar, VisLine, VisXYContainer } from '@unovis/vue'
import { ChartContainer, ChartCrosshair, ChartLegendContent, ChartTooltip } from '@/components/ui/chart'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import AdminLayout from '@/layouts/AdminLayout.vue'

interface Props {
  stats: {
    users: { total: number; trend: number }
    revenue: { total: number; last30: number; trend: number }
    projects: { total: number; trend: number }
    wordsUsed: { total: number }
  }
  revenueChart: Array<{ date: string; amount: number }>
  signupsChart: Array<{ label: string; date: string; newUsers: number }>
  systemHealth: {
    queue: { pending: number; failed: number; status: string }
    aiTokensToday: number
  }
}

const props = defineProps<Props>()

type RevenuePoint = { date: Date; amount: number }
type SignupPoint = { label: string; newUsers: number }

const revenueData = computed<RevenuePoint[]>(() => {
  return props.revenueChart.map(item => ({
    date: new Date(item.date),
    amount: item.amount,
  }))
})

const signupData = computed<SignupPoint[]>(() => {
  return props.signupsChart.map(item => ({
    label: item.label,
    newUsers: item.newUsers,
  }))
})

const revenueChartConfig = {
  revenue: {
    label: 'Revenue',
    color: 'hsl(var(--primary))',
  },
}

const signupChartConfig = {
  new: {
    label: 'New Users',
    color: 'hsl(var(--primary))',
  },
}

const weeklyNew = computed(() => signupData.value.reduce((sum, item) => sum + item.newUsers, 0))

const statCards = computed(() => [
  { label: 'Users', caption: 'Total registered', value: formatNumber(props.stats.users.total), trend: props.stats.users.trend },
  { label: 'Revenue', caption: 'Last 30 days', value: formatCurrency(props.stats.revenue.last30), trend: props.stats.revenue.trend },
  { label: 'Projects', caption: 'Total projects', value: formatNumber(props.stats.projects.total), trend: props.stats.projects.trend },
  { label: 'Total Revenue', caption: 'All time', value: formatCurrency(props.stats.revenue.total), trend: 0 },
])

const queueStatusColor = computed(() => {
  if (props.systemHealth.queue.status === 'healthy') return 'bg-emerald-500'
  if (props.systemHealth.queue.status === 'warning') return 'bg-amber-500'
  return 'bg-rose-500'
})

const formatShortDate = (value: number) => {
  const date = new Date(value)
  return date.toLocaleDateString('en', { month: 'short', day: 'numeric' })
}

const formatCurrency = (value: number) => {
  return new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', maximumFractionDigits: 0 }).format(value)
}

const formatCurrencyShort = (value: number) => {
  if (value >= 1_000_000) return `${(value / 1_000_000).toFixed(1)}M`
  if (value >= 1_000) return `${(value / 1_000).toFixed(1)}k`
  return value.toString()
}

const formatNumber = (value: number) => {
  if (value >= 1_000_000) return `${(value / 1_000_000).toFixed(1)}M`
  if (value >= 1_000) return `${(value / 1_000).toFixed(1)}K`
  return new Intl.NumberFormat('en').format(value)
}
</script>
