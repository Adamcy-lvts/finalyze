<template>
  <AdminLayout title="AI Monitoring" subtitle="Real-time insights into AI generation performance.">

    <div class="space-y-6">
      <div class="flex justify-end">
        <Button size="sm" :disabled="isRefreshing" @click="refresh">
          <span v-if="isRefreshing">Refreshing...</span>
          <span v-else>Refresh now</span>
        </Button>
      </div>
      <div v-if="status !== 'safe'" class="rounded-lg border p-4"
        :class="status === 'critical' ? 'border-rose-500/50 bg-rose-500/10' : 'border-amber-500/50 bg-amber-500/10'">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-semibold" :class="status === 'critical' ? 'text-rose-600' : 'text-amber-600'">
              {{ status === 'critical' ? 'Critical OpenAI Balance' : 'Low OpenAI Balance' }}
            </p>
            <p class="text-sm text-muted-foreground">
              Available ${{ snapshot?.available_usd?.toFixed(2) ?? '0.00' }} · Liability {{ formatNumber(liabilityTokens) }} tokens · Runway {{ runwayLabel }}
            </p>
          </div>
        </div>
      </div>

      <!-- Stats Grid -->
      <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <Card class="bg-card text-card-foreground border-border/50 shadow-sm">
          <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle class="text-sm font-medium text-muted-foreground">OpenAI Available</CardTitle>
            <DollarSign class="h-4 w-4 text-primary" />
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold">${{ snapshot?.available_usd?.toFixed(2) ?? '0.00' }}</div>
            <p class="text-xs text-muted-foreground mt-1">Granted ${{ snapshot?.granted_usd?.toFixed(2) ?? '0.00' }}</p>
          </CardContent>
        </Card>
        <Card class="bg-card text-card-foreground border-border/50 shadow-sm">
          <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle class="text-sm font-medium text-muted-foreground">Liability (tokens)</CardTitle>
            <Cpu class="h-4 w-4 text-emerald-500" />
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold">{{ formatNumber(liabilityTokens) }}</div>
            <p class="text-xs text-muted-foreground mt-1">Wallet {{ formatNumber(walletTokens) }} tokens</p>
          </CardContent>
        </Card>
        <Card class="bg-card text-card-foreground border-border/50 shadow-sm">
          <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle class="text-sm font-medium text-muted-foreground">Runway</CardTitle>
            <AlertTriangle class="h-4 w-4 text-amber-500" />
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold">{{ runwayLabel }}</div>
            <p class="text-xs text-muted-foreground mt-1">Avg burn (7d): {{ formatNumber(avgDaily7) }} tokens/day</p>
          </CardContent>
        </Card>
        <Card class="bg-card text-card-foreground border-border/50 shadow-sm">
          <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle class="text-sm font-medium text-muted-foreground">Billing Period</CardTitle>
            <Activity class="h-4 w-4 text-rose-500" />
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold">
              ${{ snapshot?.used_usd?.toFixed(2) ?? '0.00' }} used
            </div>
            <p class="text-xs text-muted-foreground mt-1">
              {{ snapshot?.period_start ?? '—' }} → {{ snapshot?.period_end ?? '—' }}
            </p>
          </CardContent>
        </Card>
      </div>

      <!-- Charts Section -->
      <div class="grid gap-6 lg:grid-cols-2">
        <Card class="bg-card text-card-foreground border-border/50 shadow-sm">
          <CardHeader>
            <CardTitle class="text-base font-semibold">Token Usage</CardTitle>
            <CardDescription>Total tokens consumed per model</CardDescription>
          </CardHeader>
          <CardContent class="pl-0">
            <ChartContainer :config="tokenChartConfig" class="h-[300px] w-full">
              <VisXYContainer :data="tokenData" :height="300" :margin="{ top: 10, right: 10, bottom: 0, left: 10 }">
                <VisGroupedBar :x="(d: TokenPoint) => d.model"
                  :y="[(d: TokenPoint) => d.input, (d: TokenPoint) => d.output]"
                  :color="[tokenChartConfig.input.color, tokenChartConfig.output.color]" :rounded-corners="4"
                  :bar-padding="0.2" />
                <VisAxis type="x" :x="(d: TokenPoint) => d.model" :grid-line="false" :domain-line="false"
                  color="#888888" />
                <VisAxis type="y" :grid-line="true" :domain-line="false" :tick-line="false"
                  :tick-format="(v: number) => (v / 1000) + 'k'" color="#888888" />
                <ChartTooltip />
                <ChartLegendContent :config="tokenChartConfig" class="mt-4 justify-center" />
              </VisXYContainer>
            </ChartContainer>
          </CardContent>
        </Card>
      </div>

    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, onBeforeUnmount, ref } from 'vue'
import AdminLayout from '@/layouts/AdminLayout.vue'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Cpu, Activity, AlertTriangle, DollarSign } from 'lucide-vue-next'
import { VisAxis, VisXYContainer, VisGroupedBar } from '@unovis/vue'
import { ChartContainer, ChartTooltip, ChartLegendContent } from '@/components/ui/chart'
import { route } from 'ziggy-js'

type TokenPoint = { model: string; input: number; output: number }

type Metrics = {
  liability_tokens: number
  wallet_tokens: number
  avg_daily_tokens_7: number
  avg_daily_tokens_30: number
  runway_days: number | null
  status: 'safe' | 'warning' | 'critical'
}

type Snapshot = {
  available_usd: number
  used_usd: number
  granted_usd: number
  period_start?: string
  period_end?: string
} | null

const props = defineProps<{
  metrics?: Metrics
  usageByModel?: TokenPoint[]
  billingSnapshot?: Snapshot
}>()

const defaultMetrics: Metrics = {
  liability_tokens: 0,
  wallet_tokens: 0,
  avg_daily_tokens_7: 0,
  avg_daily_tokens_30: 0,
  runway_days: null,
  status: 'safe',
}

const metrics = ref<Metrics>(props.metrics ?? defaultMetrics)
const snapshot = ref<Snapshot>(props.billingSnapshot ?? null)
const usageByModel = ref<TokenPoint[]>(props.usageByModel ?? [])
const status = computed(() => metrics.value?.status ?? 'safe')
const isRefreshing = ref(false)
const liabilityTokens = computed(() => metrics.value?.liability_tokens ?? 0)
const walletTokens = computed(() => metrics.value?.wallet_tokens ?? 0)
const avgDaily7 = computed(() => metrics.value?.avg_daily_tokens_7 ?? 0)

const runwayLabel = computed(() => {
  if (!metrics.value || metrics.value.runway_days === null) return '∞';
  return `${metrics.value.runway_days} days`;
})

const tokenData = computed(() => {
  if (!usageByModel.value?.length) return []
  return usageByModel.value.map((m) => ({
    model: m.model,
    input: m.input,
    output: m.output,
  }))
})

const tokenChartConfig = {
  input: {
    label: 'Input Tokens',
    color: 'hsl(var(--primary))',
  },
  output: {
    label: 'Output Tokens',
    color: 'hsl(var(--chart-2))',
  },
}

const formatNumber = (n?: number) => {
  if (n === undefined || n === null) return '0'
  return Number(n).toLocaleString()
}

let channel: any = null

onMounted(() => {
  channel = (window as any).Echo?.private('admin.ai')
  channel?.listen('.ai.provisioning.updated', (payload: any) => {
    if (payload.metrics) {
      metrics.value = payload.metrics
    }
    if (payload.snapshot) {
      snapshot.value = payload.snapshot
    }
    if (payload.usageByModel) {
      usageByModel.value = payload.usageByModel
    }
  })
})

onBeforeUnmount(() => {
  channel?.stopListening('.ai.provisioning.updated')
})

const refresh = async () => {
  try {
    isRefreshing.value = true
    const res = await fetch(route('admin.ai.refresh'), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
    })
    const data = await res.json()
    if (data?.metrics) metrics.value = data.metrics
    if (data?.snapshot) snapshot.value = data.snapshot
    if (data?.usageByModel) usageByModel.value = data.usageByModel
  } catch (e) {
    console.error('Failed to refresh AI metrics', e)
  } finally {
    isRefreshing.value = false
  }
}
</script>
