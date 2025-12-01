<template>
  <AdminLayout>
    <template #title>
      <div class="flex flex-col">
        <h2 class="text-2xl font-bold tracking-tight text-foreground">AI Monitoring</h2>
        <p class="text-muted-foreground text-sm">Real-time insights into AI generation performance.</p>
      </div>
    </template>

    <div class="space-y-6">
      <!-- Stats Grid -->
      <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <Card class="bg-card text-card-foreground border-border/50 shadow-sm">
          <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle class="text-sm font-medium text-muted-foreground">Total Requests</CardTitle>
            <Cpu class="h-4 w-4 text-primary" />
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold">12,453</div>
            <p class="text-xs text-muted-foreground mt-1">+18% from last month</p>
          </CardContent>
        </Card>
        <Card class="bg-card text-card-foreground border-border/50 shadow-sm">
          <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle class="text-sm font-medium text-muted-foreground">Avg Latency</CardTitle>
            <Activity class="h-4 w-4 text-emerald-500" />
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold">845ms</div>
            <p class="text-xs text-muted-foreground mt-1">-12ms improvement</p>
          </CardContent>
        </Card>
        <Card class="bg-card text-card-foreground border-border/50 shadow-sm">
          <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle class="text-sm font-medium text-muted-foreground">Error Rate</CardTitle>
            <AlertTriangle class="h-4 w-4 text-amber-500" />
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold">0.4%</div>
            <p class="text-xs text-muted-foreground mt-1">Within healthy range</p>
          </CardContent>
        </Card>
        <Card class="bg-card text-card-foreground border-border/50 shadow-sm">
          <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle class="text-sm font-medium text-muted-foreground">Est. Cost</CardTitle>
            <DollarSign class="h-4 w-4 text-rose-500" />
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold">$432.50</div>
            <p class="text-xs text-muted-foreground mt-1">This billing cycle</p>
          </CardContent>
        </Card>
      </div>

      <!-- Charts Section -->
      <div class="grid gap-6 lg:grid-cols-2">
        <Card class="bg-card text-card-foreground border-border/50 shadow-sm">
          <CardHeader>
            <CardTitle class="text-base font-semibold">Request Volume</CardTitle>
            <CardDescription>Requests per hour over the last 24 hours</CardDescription>
          </CardHeader>
          <CardContent class="pl-0">
            <ChartContainer :config="volumeChartConfig" class="h-[300px] w-full">
              <VisXYContainer :data="volumeData" :height="300" :margin="{ top: 10, right: 10, bottom: 0, left: 10 }">
                <VisLine :x="(d: DataPoint) => d.x" :y="(d: DataPoint) => d.y" color="hsl(var(--primary))"
                  :curve-type="'monotone'" :stroke-width="2" />
                <VisArea :x="(d: DataPoint) => d.x" :y="(d: DataPoint) => d.y" color="hsl(var(--primary))"
                  :opacity="0.1" :curve-type="'monotone'" />
                <VisAxis type="x" :x="(d: DataPoint) => d.x" :tick-format="(v: number) => new Date(v).getHours() + 'h'"
                  :grid-line="false" :domain-line="false" color="#888888" />
                <VisAxis type="y" :grid-line="true" :domain-line="false" :tick-line="false" color="#888888" />
                <ChartTooltip />
              </VisXYContainer>
            </ChartContainer>
          </CardContent>
        </Card>

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

      <!-- Recent Failures -->
      <Card class="bg-card text-card-foreground border-border/50 shadow-sm">
        <CardHeader>
          <CardTitle class="text-base font-semibold">Recent Failures</CardTitle>
          <CardDescription>Latest failed generation attempts.</CardDescription>
        </CardHeader>
        <CardContent>
          <div class="rounded-md border border-border/50">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Time</TableHead>
                  <TableHead>Model</TableHead>
                  <TableHead>Error</TableHead>
                  <TableHead class="text-right">Action</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow v-for="failure in recentFailures" :key="failure.id">
                  <TableCell class="font-medium">{{ failure.time }}</TableCell>
                  <TableCell>
                    <Badge variant="outline">{{ failure.model }}</Badge>
                  </TableCell>
                  <TableCell class="text-rose-500">{{ failure.error }}</TableCell>
                  <TableCell class="text-right">
                    <Button variant="ghost" size="sm">View Logs</Button>
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
import { ref } from 'vue'
import AdminLayout from '@/layouts/AdminLayout.vue'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Cpu, Activity, AlertTriangle, DollarSign } from 'lucide-vue-next'
import { VisAxis, VisLine, VisArea, VisXYContainer, VisGroupedBar } from '@unovis/vue'
import { ChartContainer, ChartTooltip, ChartLegendContent } from '@/components/ui/chart'

type DataPoint = { x: number; y: number }
type TokenPoint = { model: string; input: number; output: number }

const volumeChartConfig = {
  volume: {
    label: 'Requests',
    color: 'hsl(var(--primary))',
  },
}

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

// Mock Data
const now = Date.now()
const volumeData = ref<DataPoint[]>(
  Array.from({ length: 24 }, (_, i) => ({
    x: now - (23 - i) * 3600000,
    y: Math.floor(Math.random() * 500) + 100,
  }))
)

const tokenData = ref<TokenPoint[]>([
  { model: 'GPT-4', input: 45000, output: 12000 },
  { model: 'GPT-3.5', input: 120000, output: 85000 },
  { model: 'Claude 3', input: 30000, output: 15000 },
])

const recentFailures = ref([
  { id: 1, time: '10 mins ago', model: 'GPT-4', error: 'Rate limit exceeded' },
  { id: 2, time: '45 mins ago', model: 'GPT-3.5', error: 'Context length exceeded' },
  { id: 3, time: '2 hours ago', model: 'Claude 3', error: 'API timeout' },
])
</script>
