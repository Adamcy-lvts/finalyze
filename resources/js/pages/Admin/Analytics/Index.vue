<template>
  <AdminLayout>
    <template #title>
      <div class="flex flex-col">
        <h2 class="text-2xl font-bold tracking-tight text-foreground">Analytics</h2>
        <p class="text-muted-foreground text-sm">Insights across users, revenue, and usage.</p>
      </div>
    </template>
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
                <p class="text-lg font-bold text-foreground">{{ formatCurrency(totalRevenue) }}</p>
              </div>
            </div>
          </CardHeader>
          <CardContent class="pl-0">
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
            <ChartContainer :config="signupChartConfig" class="h-[280px] w-full">
              <VisXYContainer :data="signupData" :height="280" :margin="{ top: 5, right: 10, bottom: 0, left: 10 }">
                <VisGroupedBar :x="(d: SignupPoint) => d.label"
                  :y="[(d: SignupPoint) => d.newUsers, (d: SignupPoint) => d.returning]"
                  :color="[signupChartConfig.new.color, signupChartConfig.returning.color]" :rounded-corners="4"
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
                <div class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></div>
              </div>
              <p class="text-2xl font-bold text-foreground">Healthy</p>
              <p class="text-xs text-muted-foreground mt-1">12 pending • 0 failed</p>
            </div>
            <div class="rounded-lg border border-border/50 bg-muted/30 p-4 hover:bg-muted/50 transition-colors">
              <div class="flex items-center justify-between mb-2">
                <p class="text-sm font-medium text-muted-foreground">OpenAI</p>
                <div class="h-2 w-2 rounded-full bg-emerald-500"></div>
              </div>
              <p class="text-2xl font-bold text-foreground">Stable</p>
              <p class="text-xs text-muted-foreground mt-1">Avg latency: 890ms</p>
            </div>
            <div class="rounded-lg border border-border/50 bg-muted/30 p-4 hover:bg-muted/50 transition-colors">
              <div class="flex items-center justify-between mb-2">
                <p class="text-sm font-medium text-muted-foreground">Cache</p>
                <div class="h-2 w-2 rounded-full bg-emerald-500"></div>
              </div>
              <p class="text-2xl font-bold text-foreground">OK</p>
              <p class="text-xs text-muted-foreground mt-1">Hit rate: 92%</p>
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

type RevenuePoint = { date: Date; amount: number }
type SignupPoint = { label: string; newUsers: number; returning: number }

const today = new Date()
const revenueData: RevenuePoint[] = [
  { date: new Date(today.getTime() - 30 * 24 * 60 * 60 * 1000), amount: 180000 },
  { date: new Date(today.getTime() - 24 * 24 * 60 * 60 * 1000), amount: 210000 },
  { date: new Date(today.getTime() - 18 * 24 * 60 * 60 * 1000), amount: 265000 },
  { date: new Date(today.getTime() - 12 * 24 * 60 * 60 * 1000), amount: 240000 },
  { date: new Date(today.getTime() - 6 * 24 * 60 * 60 * 1000), amount: 300000 },
  { date: new Date(today.getTime() - 3 * 24 * 60 * 60 * 1000), amount: 320000 },
  { date: today, amount: 340000 },
]

const signupData: SignupPoint[] = [
  { label: 'Mon', newUsers: 18, returning: 7 },
  { label: 'Tue', newUsers: 24, returning: 10 },
  { label: 'Wed', newUsers: 20, returning: 8 },
  { label: 'Thu', newUsers: 28, returning: 12 },
  { label: 'Fri', newUsers: 26, returning: 11 },
  { label: 'Sat', newUsers: 16, returning: 6 },
  { label: 'Sun', newUsers: 14, returning: 5 },
]

const revenueChartConfig = {
  revenue: {
    label: 'Revenue',
    color: 'hsl(var(--primary))',
  },
}

const signupChartConfig = {
  new: {
    label: 'New',
    color: 'hsl(var(--primary))',
  },
  returning: {
    label: 'Returning',
    color: 'hsl(var(--muted-foreground))',
  },
}

const totalRevenue = computed(() => revenueData.reduce((sum, item) => sum + item.amount, 0))
const weeklyNew = computed(() => signupData.reduce((sum, item) => sum + item.newUsers, 0))

const statCards = computed(() => [
  { label: 'Users', caption: 'Active users', value: '2,148', trend: 12 },
  { label: 'Revenue', caption: 'Last 30 days', value: formatCurrency(totalRevenue.value), trend: 8 },
  { label: 'Projects', caption: 'Total projects', value: '512', trend: 3 },
  { label: 'Words Generated', caption: 'All time', value: '2.8M', trend: 15 },
])

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
</script>
