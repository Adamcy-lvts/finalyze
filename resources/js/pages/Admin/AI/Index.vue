<template>
  <AdminLayout title="AI Analytics" subtitle="OpenAI credit monitoring, profitability analysis, and usage insights.">
    <div class="space-y-6">
      <!-- Header Actions -->
      <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div class="flex items-center gap-1.5 bg-muted/30 p-1 rounded-xl border border-border/50">
          <Button v-for="period in periods" :key="period.value" size="sm"
            :variant="selectedDays === period.value ? 'default' : 'ghost'" class="h-8 rounded-lg px-4"
            @click="changePeriod(period.value)">
            {{ period.label }}
          </Button>
        </div>
        <Button variant="outline" size="sm" :disabled="isRefreshing" @click="refresh"
          class="rounded-xl border-border/50">
          <RefreshCw v-if="isRefreshing" class="h-4 w-4 animate-spin text-primary" />
          <RefreshCw v-else class="h-4 w-4 text-primary" />
          <span class="ml-2 font-medium">{{ isRefreshing ? 'Refreshing...' : 'Sync Data' }}</span>
        </Button>
      </div>

      <!-- Alert Banner -->
      <div v-if="status !== 'safe'"
        class="relative overflow-hidden group rounded-2xl border p-5 transition-all duration-300 shadow-lg" :class="status === 'critical'
          ? 'border-rose-500/30 bg-rose-500/5'
          : 'border-amber-500/30 bg-amber-500/5'">
        <div
          class="absolute inset-0 bg-gradient-to-r from-transparent via-white/5 to-transparent -translate-x-full group-hover:translate-x-full transition-transform duration-1000">
        </div>
        <div class="flex items-start gap-4">
          <div :class="status === 'critical' ? 'bg-rose-500/20' : 'bg-amber-500/20'" class="p-2.5 rounded-xl">
            <AlertTriangle :class="status === 'critical' ? 'text-rose-500' : 'text-amber-500'" class="h-6 w-6" />
          </div>
          <div class="flex-1">
            <h3 class="text-base font-bold leading-none mb-1.5"
              :class="status === 'critical' ? 'text-rose-500' : 'text-amber-500'">
              {{ status === 'critical' ? 'Critical: OpenAI Credit Depleted' : 'Warning: Low Credit Reserve' }}
            </h3>
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-muted-foreground font-medium">
              <span class="flex items-center gap-1.5">
                <DollarSign class="h-3.5 w-3.5 opacity-70" />
                Available: <span class="font-bold text-foreground">${{ formatUsd(snapshot?.available_usd) }}</span>
              </span>
              <span class="w-1.5 h-1.5 rounded-full bg-border"></span>
              <span class="flex items-center gap-1.5">
                <Cpu class="h-3.5 w-3.5 opacity-70" />
                Liability: <span class="font-bold text-foreground">{{ formatNumber(liabilityTokens) }} tokens</span>
              </span>
              <span class="w-1.5 h-1.5 rounded-full bg-border"></span>
              <span class="flex items-center gap-1.5">
                <Clock class="h-3.5 w-3.5 opacity-70" />
                Projection: <span class="font-bold text-foreground">{{ runwayLabel }} remaining</span>
              </span>
            </div>
          </div>
          <Button size="sm" :variant="status === 'critical' ? 'destructive' : 'default'"
            class="hidden sm:flex rounded-xl font-bold px-5">
            Top Up Now
          </Button>
        </div>
      </div>

      <!-- OpenAI Credit Stats -->
      <div class="grid gap-4 grid-cols-1 sm:grid-cols-2 lg:grid-cols-4">
        <Card
          class="relative overflow-hidden border-border/40 shadow-sm transition-all hover:shadow-md hover:border-primary/20 h-full group cursor-pointer"
          @click="openBalanceModal">
          <CardHeader class="flex flex-row items-center justify-between pb-2 space-y-0">
            <span class="text-[10px] font-bold uppercase tracking-[0.1em] text-muted-foreground/60">OpenAI
              Balance</span>
            <div class="p-2 rounded-xl bg-emerald-500/10 text-emerald-500 group-hover:scale-110 transition-transform">
              <DollarSign class="h-4 w-4" />
            </div>
          </CardHeader>
          <CardContent>
            <div class="text-3xl font-black tracking-tighter">${{ formatUsd(balanceInfo?.available_usd ?? snapshot?.available_usd) }}</div>
            <p class="text-xs text-muted-foreground mt-3 flex items-center gap-1.5 font-medium">
              <span
                class="inline-block w-1.5 h-1.5 rounded-full bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.5)]"></span>
              Initial: ${{ formatUsd(balanceInfo?.initial_balance ?? creditSettings?.initial_balance) }}
            </p>
            <p class="text-[9px] text-muted-foreground/60 mt-1 flex items-center gap-1.5 font-medium">
              <Pencil class="h-3 w-3" />
              Click to update balance
            </p>
          </CardContent>
        </Card>

        <Card
          class="relative overflow-hidden border-border/40 shadow-sm transition-all hover:shadow-md hover:border-primary/20 h-full group">
          <CardHeader class="flex flex-row items-center justify-between pb-2 space-y-0">
            <span class="text-[10px] font-bold uppercase tracking-[0.1em] text-muted-foreground/60">User
              Liability</span>
            <div class="p-2 rounded-xl bg-blue-500/10 text-blue-500 group-hover:scale-110 transition-transform">
              <Cpu class="h-4 w-4" />
            </div>
          </CardHeader>
          <CardContent>
            <div class="text-3xl font-black tracking-tighter">{{ formatNumber(liabilityTokens) }}</div>
            <p class="text-xs text-muted-foreground mt-3 flex items-center gap-1.5 font-medium">
              <Users class="h-3.5 w-3.5 opacity-60" />
              {{ formatNumber(liabilityBreakdown?.users_with_balance ?? 0) }} active accounts
            </p>
          </CardContent>
        </Card>

        <Card
          class="relative overflow-hidden border-border/40 shadow-sm transition-all hover:shadow-md hover:border-primary/20 h-full group">
          <CardHeader class="flex flex-row items-center justify-between pb-2 space-y-0">
            <span class="text-[10px] font-bold uppercase tracking-[0.1em] text-muted-foreground/60">Internal
              Reserve</span>
            <div class="p-2 rounded-xl bg-violet-500/10 text-violet-500 group-hover:scale-110 transition-transform">
              <Wallet class="h-4 w-4" />
            </div>
          </CardHeader>
          <CardContent>
            <div class="text-3xl font-black tracking-tighter text-violet-500">{{ formatNumber(walletTokens) }}</div>
            <p class="text-xs mt-3 flex items-center gap-1.5 font-bold"
              :class="liabilityCoverage >= 100 ? 'text-emerald-500' : 'text-amber-500'">
              <span class="inline-block w-1.5 h-1.5 rounded-full"
                :class="liabilityCoverage >= 100 ? 'bg-emerald-500' : 'bg-amber-500'"></span>
              {{ liabilityCoverage.toFixed(1) }}% Coverage Ratio
            </p>
          </CardContent>
        </Card>

        <Card
          class="relative overflow-hidden border-border/40 shadow-sm transition-all hover:shadow-md hover:border-primary/20 h-full group">
          <CardHeader class="flex flex-row items-center justify-between pb-2 space-y-0">
            <span class="text-[10px] font-bold uppercase tracking-[0.1em] text-muted-foreground/60">Projected
              Runway</span>
            <div class="p-2 rounded-xl bg-amber-500/10 text-amber-500 group-hover:scale-110 transition-transform">
              <Clock class="h-4 w-4" />
            </div>
          </CardHeader>
          <CardContent>
            <div class="text-3xl font-black tracking-tighter" :class="status !== 'safe' ? 'text-rose-500' : ''">
              {{ runwayLabel }}
            </div>
            <p class="text-xs text-muted-foreground mt-3 flex items-center gap-1.5 font-medium">
              <Zap class="h-3.5 w-3.5 opacity-60 text-amber-500" />
              Burn: {{ formatNumber(avgDaily7) }} tokens / day
            </p>
          </CardContent>
        </Card>
      </div>

      <!-- Profitability Stats -->
      <div class="grid gap-4 grid-cols-1 sm:grid-cols-2 lg:grid-cols-4">
        <Card class="bg-card border-border/40 border-l-4 border-l-emerald-500 shadow-sm relative overflow-hidden group">
          <div class="absolute right-0 top-0 h-full w-24 bg-gradient-to-l from-emerald-500/5 to-transparent"></div>
          <CardHeader class="pb-2">
            <CardTitle class="text-[10px] font-bold uppercase tracking-widest text-muted-foreground/50">Gross Revenue
              ({{ selectedDays }}d)</CardTitle>
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-black flex items-baseline gap-2">
              {{ formatNaira(profitability?.revenue_ngn ?? 0) }}
            </div>
            <p class="text-[10px] text-muted-foreground mt-1 font-mono uppercase font-bold opacity-60">${{
              formatUsd(profitability?.revenue_usd) }} USD Eq.</p>
          </CardContent>
        </Card>

        <Card class="bg-card border-border/40 border-l-4 border-l-rose-500 shadow-sm relative overflow-hidden group">
          <div class="absolute right-0 top-0 h-full w-24 bg-gradient-to-l from-rose-500/5 to-transparent"></div>
          <CardHeader class="pb-2">
            <CardTitle class="text-[10px] font-bold uppercase tracking-widest text-muted-foreground/50">Incurred Cost
              ({{ selectedDays }}d)</CardTitle>
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-black text-rose-500 tracking-tight">${{ formatUsd(profitability?.cost_usd, 4) }}
            </div>
            <p class="text-[10px] text-muted-foreground mt-1 font-bold uppercase opacity-60">Aggregated API Usage</p>
          </CardContent>
        </Card>

        <Card class="bg-card border-border/40 border-l-4 border-l-primary shadow-sm overflow-hidden relative group">
          <div class="absolute right-0 top-0 h-full w-24 bg-gradient-to-l from-primary/5 to-transparent"></div>
          <CardHeader class="pb-2">
            <CardTitle class="text-[10px] font-bold uppercase tracking-widest text-muted-foreground/50">Net AI Profit
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-black tracking-tight"
              :class="(profitability?.profit_usd ?? 0) >= 0 ? 'text-emerald-500' : 'text-rose-500'">
              ${{ formatUsd(profitability?.profit_usd) }}
            </div>
            <p class="text-[10px] text-muted-foreground mt-1 font-bold uppercase opacity-60">Pre-Tax Operating Income
            </p>
          </CardContent>
        </Card>

        <Card class="bg-card border-border/40 border-l-4 border-l-blue-500 shadow-sm relative overflow-hidden group">
          <div class="absolute right-0 top-0 h-full w-24 bg-gradient-to-l from-blue-500/5 to-transparent"></div>
          <CardHeader class="pb-2">
            <CardTitle class="text-[10px] font-bold uppercase tracking-widest text-muted-foreground/50">Efficiency
              Margin</CardTitle>
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-black flex items-center gap-2"
              :class="(profitability?.margin_percent ?? 0) >= 0 ? 'text-emerald-500' : 'text-rose-500'">
              {{ profitability?.margin_percent ?? 0 }}%
              <TrendingUp v-if="(profitability?.margin_percent ?? 0) > 0" class="h-5 w-5 opacity-70" />
            </div>
            <p class="text-[10px] text-muted-foreground mt-1 font-bold uppercase opacity-60">Gross Margin Analysis</p>
          </CardContent>
        </Card>
      </div>

      <!-- Charts Section -->
      <div class="grid gap-6 lg:grid-cols-2">
        <!-- Usage Trends Chart -->
        <Card
          class="bg-card text-card-foreground border-border/40 shadow-sm overflow-hidden transition-all hover:border-border">
          <CardHeader class="border-b border-border/40 bg-muted/20 px-6 py-4">
            <div class="flex items-center justify-between">
              <div>
                <CardTitle class="text-lg font-black tracking-tight">Usage Velocity</CardTitle>
                <CardDescription class="text-xs font-medium">Temporal token consumption analysis</CardDescription>
              </div>
              <div class="text-right">
                <p class="text-[9px] uppercase font-black text-muted-foreground tracking-widest opacity-60 mb-1">Total
                  Volume</p>
                <Badge variant="secondary" class="font-black text-sm px-3 py-1">{{ formatTokensShort(totalTokens) }}
                </Badge>
              </div>
            </div>
          </CardHeader>
          <CardContent class="p-6">
            <div class="relative h-[320px] w-full">
              <canvas ref="trendsCanvas" />
              <div v-if="usageTrendsData.length === 0"
                class="absolute inset-0 flex items-center justify-center bg-card/50 backdrop-blur-xs rounded-xl">
                <p class="text-sm font-bold text-muted-foreground/60 uppercase tracking-widest">Awaiting Usage Data...
                </p>
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Model Usage Chart -->
        <Card
          class="bg-card text-card-foreground border-border/40 shadow-sm overflow-hidden transition-all hover:border-border">
          <CardHeader class="border-b border-border/40 bg-muted/20 px-6 py-4">
            <div class="flex items-center justify-between">
              <div>
                <CardTitle class="text-lg font-black tracking-tight">Token Allocation</CardTitle>
                <CardDescription class="text-xs font-medium">Distribution by model architecture</CardDescription>
              </div>
              <div class="text-right">
                <p class="text-[9px] uppercase font-black text-muted-foreground tracking-widest opacity-60 mb-1">Direct
                  Costs</p>
                <Badge variant="outline" class="font-black text-sm px-3 py-1 border-primary/30 text-primary">${{
                  totalModelCost.toFixed(3) }}</Badge>
              </div>
            </div>
          </CardHeader>
          <CardContent class="p-6">
            <div class="relative h-[320px] w-full">
              <canvas ref="modelsCanvas" />
              <div v-if="modelData.length === 0"
                class="absolute inset-0 flex items-center justify-center bg-card/50 backdrop-blur-xs rounded-xl">
                <p class="text-sm font-bold text-muted-foreground/60 uppercase tracking-widest">No Model Activity
                  Detected</p>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      <!-- Liability vs Wallet + Top Users -->
      <div class="grid gap-6 lg:grid-cols-2">
        <!-- Solvency Analysis -->
        <Card class="bg-card text-card-foreground border-border/40 shadow-sm relative overflow-hidden group">
          <CardHeader class="pb-4">
            <CardTitle class="text-base font-black flex items-center gap-2 tracking-tight">
              <div class="p-1.5 rounded-lg bg-primary/10 text-primary">
                <Wallet class="h-4 w-4" />
              </div>
              Liquidity & Solvency
            </CardTitle>
            <CardDescription class="text-xs font-medium">Capital reserve adequacy assessment</CardDescription>
          </CardHeader>
          <CardContent>
            <div class="space-y-6">
              <div class="relative">
                <div class="flex justify-between text-[10px] font-black uppercase tracking-widest mb-2.5">
                  <span class="text-muted-foreground/70">Unclaimed Liability</span>
                  <span class="text-foreground">{{ formatNumber(liabilityTokens) }} items</span>
                </div>
                <div class="h-4 bg-muted/50 rounded-full overflow-hidden border border-border/20 p-0.5">
                  <div
                    class="h-full bg-gradient-to-r from-blue-700 via-blue-500 to-blue-400 rounded-full transition-all duration-1000 ease-in-out shadow-[0_0_12px_rgba(59,130,246,0.3)]"
                    :style="{ width: liabilityBarWidth }"></div>
                </div>
              </div>
              <div class="relative">
                <div class="flex justify-between text-[10px] font-black uppercase tracking-widest mb-2.5">
                  <span class="text-muted-foreground/70">Liquid Asset Reserves</span>
                  <span class="text-emerald-500">{{ formatNumber(walletTokens) }} items</span>
                </div>
                <div class="h-4 bg-muted/50 rounded-full overflow-hidden border border-border/20 p-0.5">
                  <div
                    class="h-full bg-gradient-to-r from-emerald-700 via-emerald-500 to-emerald-400 rounded-full transition-all duration-1000 ease-in-out shadow-[0_0_12px_rgba(16,185,129,0.3)]"
                    :style="{ width: walletBarWidth }"></div>
                </div>
              </div>
              <div class="pt-5 border-t border-border/40 mt-2">
                <div class="flex items-center justify-between mb-3">
                  <span class="text-sm font-bold tracking-tight">Health Status</span>
                  <Badge :variant="liabilityCoverage >= 100 ? 'default' : 'destructive'"
                    class="rounded-lg px-3 py-1 font-black text-[10px] uppercase">
                    {{ liabilityCoverage >= 100 ? 'Fully Collateralized' : 'Under-Provisioned' }}
                    ({{ liabilityCoverage.toFixed(0) }}%)
                  </Badge>
                </div>
                <p class="text-xs text-muted-foreground leading-relaxed font-medium">
                  {{ liabilityCoverage >= 100
                    ? `Current OpenAI credits are sufficient to cover 100% of outstanding user liabilities. The platform
                  is currently in a safe liquidity state.`
                    : `Current reserves do not meet 1:1 coverage for user balances. Immediate top-up recommended to ensure
                  service continuity.`
                  }}
                </p>
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Highest Liabilities -->
        <Card class="bg-card text-card-foreground border-border/40 shadow-sm overflow-hidden group">
          <CardHeader class="pb-0">
            <CardTitle class="text-base font-black flex items-center gap-2 tracking-tight">
              <div class="p-1.5 rounded-lg bg-primary/10 text-primary">
                <Users class="h-4 w-4" />
              </div>
              Concentration Risk
            </CardTitle>
            <CardDescription class="text-xs font-medium">Exposure by top individual accounts</CardDescription>
          </CardHeader>
          <div class="p-4">
            <Table>
              <TableHeader class="hover:bg-transparent">
                <TableRow class="border-b border-border/40 hover:bg-transparent">
                  <TableHead class="text-[10px] font-black uppercase tracking-widest h-10">Entity Name</TableHead>
                  <TableHead class="text-right text-[10px] font-black uppercase tracking-widest h-10">Balance
                  </TableHead>
                  <TableHead class="text-right text-[10px] font-black uppercase tracking-widest h-10">Provision
                  </TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow v-for="user in topUsers" :key="user.id"
                  class="border-b border-border/20 hover:bg-muted/30 transition-all group/row">
                  <TableCell class="py-3">
                    <div class="font-bold text-sm tracking-tight group-hover/row:text-primary transition-colors">{{
                      user.name }}</div>
                    <div class="text-[9px] uppercase font-black text-muted-foreground/60 tracking-tighter">{{ user.email
                    }}</div>
                  </TableCell>
                  <TableCell class="text-right font-mono text-xs font-bold">{{ formatNumber(user.word_balance) }} <span
                      class="text-[9px] opacity-40">WD</span></TableCell>
                  <TableCell class="text-right font-mono text-xs text-muted-foreground font-medium">
                    {{ formatTokensShort(Math.round(user.word_balance * 1.5)) }} <span
                      class="text-[9px] opacity-40">TK</span>
                  </TableCell>
                </TableRow>
                <TableRow v-if="topUsers.length === 0">
                  <TableCell colspan="3" class="py-16 text-center">
                    <p class="text-xs font-bold text-muted-foreground/40 uppercase tracking-[0.2em]">Zero Liability
                      Detected</p>
                  </TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </div>
        </Card>
      </div>

      <!-- Model Pricing Reference -->
      <Card
        class="bg-card text-card-foreground border-border/40 shadow-sm overflow-hidden group border-t-2 border-t-primary/20">
        <CardHeader class="bg-muted/30 border-b border-border/40 px-6 py-4">
          <div class="flex items-center gap-3">
            <div class="p-2 rounded-xl bg-primary/5 text-primary border border-primary/10">
              <Zap class="h-4 w-4" />
            </div>
            <div>
              <CardTitle class="text-base font-black tracking-tight">Financial Unit Standards</CardTitle>
              <CardDescription class="text-[10px] font-bold uppercase tracking-widest opacity-60">Settlement Rates (USD
                per 1K Tokens)</CardDescription>
            </div>
          </div>
        </CardHeader>
        <CardContent class="p-0">
          <div
            class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 divide-y md:divide-y-0 md:divide-x divide-border/30">
            <div v-for="(pricing, model) in modelPricing" :key="model"
              class="group/item p-6 hover:bg-muted/20 transition-all">
              <div class="flex items-center justify-between mb-5">
                <p class="text-sm font-black tracking-tighter group-hover/item:text-primary transition-colors">{{ model
                }}</p>
                <div class="h-px flex-1 mx-4 bg-border/20"></div>
                <Badge variant="outline"
                  class="text-[9px] font-black uppercase tracking-tighter py-0 px-2 border-primary/20 bg-primary/5 text-primary">
                  External API</Badge>
              </div>
              <div class="space-y-3.5 font-mono">
                <div class="flex items-center justify-between text-xs">
                  <div class="flex items-center gap-2">
                    <div class="w-1 h-1 rounded-full bg-blue-500"></div>
                    <span class="text-muted-foreground font-bold text-[10px] uppercase">Input</span>
                  </div>
                  <span class="font-black tracking-tight text-foreground/80">${{ pricing.prompt.toFixed(5) }}</span>
                </div>
                <div class="flex items-center justify-between text-xs">
                  <div class="flex items-center gap-2">
                    <div class="w-1 h-1 rounded-full bg-emerald-500"></div>
                    <span class="text-muted-foreground font-bold text-[10px] uppercase">Output</span>
                  </div>
                  <span class="font-black tracking-tight text-foreground/80">${{ pricing.completion.toFixed(5) }}</span>
                </div>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>

    <!-- Balance Edit Modal -->
    <Dialog v-model:open="isBalanceModalOpen">
      <DialogContent class="sm:max-w-[425px]">
        <DialogHeader>
          <DialogTitle class="flex items-center gap-2">
            <DollarSign class="h-5 w-5 text-emerald-500" />
            Update OpenAI Credit Balance
          </DialogTitle>
          <DialogDescription>
            Set your current OpenAI credit balance. This will be used to calculate live depletion based on actual API costs.
          </DialogDescription>
        </DialogHeader>
        <div class="space-y-4 py-4">
          <div class="space-y-2">
            <Label for="initial_balance">Initial Balance (USD)</Label>
            <div class="relative">
              <span class="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground">$</span>
              <Input id="initial_balance" v-model="balanceForm.initial_balance" type="number" step="0.01" min="0"
                class="pl-7" placeholder="0.00" />
            </div>
            <p class="text-xs text-muted-foreground">
              Enter the amount you topped up in your OpenAI account.
            </p>
          </div>
          <div class="space-y-2">
            <Label for="notes">Notes (optional)</Label>
            <Input id="notes" v-model="balanceForm.notes" type="text" placeholder="e.g., Top-up on Dec 30, 2025" />
          </div>
          <div v-if="balanceInfo" class="p-3 rounded-lg bg-muted/50 border border-border/50 space-y-2">
            <p class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Live Calculation Preview</p>
            <div class="grid grid-cols-2 gap-2 text-sm">
              <div>
                <span class="text-muted-foreground">Initial:</span>
                <span class="ml-1 font-bold">${{ formatUsd(balanceForm.initial_balance || 0) }}</span>
              </div>
              <div>
                <span class="text-muted-foreground">API Costs:</span>
                <span class="ml-1 font-bold text-rose-500">${{ formatUsd(balanceInfo.costs_since_set) }}</span>
              </div>
              <div class="col-span-2">
                <span class="text-muted-foreground">Available:</span>
                <span class="ml-1 font-bold text-emerald-500">${{
                  formatUsd(Math.max(0, (parseFloat(balanceForm.initial_balance) || 0) - (balanceInfo.costs_since_set || 0)))
                }}</span>
              </div>
            </div>
            <p class="text-[10px] text-muted-foreground mt-1">
              <span class="font-medium">Source:</span> {{ balanceInfo.cost_source === 'openai_api' ? 'OpenAI Costs API' : 'Local Usage Logs' }}
            </p>
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" @click="isBalanceModalOpen = false">Cancel</Button>
          <Button @click="saveBalance" :disabled="isSavingBalance">
            <RefreshCw v-if="isSavingBalance" class="h-4 w-4 mr-2 animate-spin" />
            {{ isSavingBalance ? 'Saving...' : 'Save Balance' }}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </AdminLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, onBeforeUnmount, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { Chart, registerables, type ChartConfiguration } from 'chart.js'
import AdminLayout from '@/layouts/AdminLayout.vue'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Badge } from '@/components/ui/badge'
import {
  Cpu, DollarSign, AlertTriangle, Clock, RefreshCw, TrendingUp, Zap, PiggyBank, Wallet, Users, Pencil, X
} from 'lucide-vue-next'
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { route } from 'ziggy-js'

// Register Chart.js components
Chart.register(...registerables)

type TrendPoint = { date: string; tokens: number; cost: number }
type ModelPoint = {
  model: string
  prompt_tokens: number
  completion_tokens: number
  total_tokens: number
  cost_usd: number
}
type TopUser = { id: number; name: string; email: string; word_balance: number }

type Metrics = {
  liability_tokens: number
  wallet_tokens: number
  avg_daily_tokens_7: number
  avg_daily_tokens_30: number
  runway_days: number | null
  status: 'safe' | 'warning' | 'critical'
}

type Profitability = {
  revenue_ngn: number
  revenue_usd: number
  cost_usd: number
  profit_usd: number
  margin_percent: number
  period_days: number
}

type LiabilityBreakdown = {
  users_with_balance: number
  total_words: number
  total_tokens: number
  top_users: TopUser[]
}

type Snapshot = {
  available_usd: number
  used_usd: number
  granted_usd: number
  period_start?: string
  period_end?: string
} | null

type ModelPricing = Record<string, { prompt: number; completion: number }>

type CreditSettings = {
  initial_balance: number
  balance_set_at: string | null
  notes: string | null
}

type BalanceInfo = {
  initial_balance: number
  costs_since_set: number
  available_usd: number
  balance_set_at: string
  cost_source: 'openai_api' | 'local'
}

const props = defineProps<{
  metrics?: Metrics
  profitability?: Profitability
  usageTrends?: TrendPoint[]
  liabilityBreakdown?: LiabilityBreakdown
  modelBreakdown?: ModelPoint[]
  billingSnapshot?: Snapshot
  modelPricing?: ModelPricing
  selectedDays?: number
  creditSettings?: CreditSettings
  balanceInfo?: BalanceInfo
}>()

const periods = [
  { label: '7d', value: 7 },
  { label: '30d', value: 30 },
  { label: '90d', value: 90 },
]

const defaultMetrics: Metrics = {
  liability_tokens: 0,
  wallet_tokens: 0,
  avg_daily_tokens_7: 0,
  avg_daily_tokens_30: 0,
  runway_days: null,
  status: 'safe',
}

const metrics = ref<Metrics>(props.metrics ?? defaultMetrics)
const profitability = ref<Profitability | undefined>(props.profitability)
const usageTrends = ref<TrendPoint[]>(props.usageTrends ?? [])
const liabilityBreakdown = ref<LiabilityBreakdown | undefined>(props.liabilityBreakdown)
const modelBreakdown = ref<ModelPoint[]>(props.modelBreakdown ?? [])
const snapshot = ref<Snapshot>(props.billingSnapshot ?? null)
const modelPricing = ref<ModelPricing>(props.modelPricing ?? {})
const selectedDays = ref(props.selectedDays ?? 30)
const creditSettings = ref<CreditSettings | undefined>(props.creditSettings)
const balanceInfo = ref<BalanceInfo | undefined>(props.balanceInfo)

// Balance modal state
const isBalanceModalOpen = ref(false)
const isSavingBalance = ref(false)
const balanceForm = ref({
  initial_balance: props.creditSettings?.initial_balance?.toString() ?? '0',
  notes: props.creditSettings?.notes ?? '',
})

const status = computed(() => metrics.value?.status ?? 'safe')
const isRefreshing = ref(false)
const liabilityTokens = computed(() => metrics.value?.liability_tokens ?? 0)
const walletTokens = computed(() => metrics.value?.wallet_tokens ?? 0)
const avgDaily7 = computed(() => metrics.value?.avg_daily_tokens_7 ?? 0)

const runwayLabel = computed(() => {
  if (!metrics.value || metrics.value.runway_days === null) return '...'
  return `${metrics.value.runway_days} days`
})

const liabilityCoverage = computed(() => {
  if (liabilityTokens.value === 0) return 100
  return (walletTokens.value / liabilityTokens.value) * 100
})

const topUsers = computed(() => liabilityBreakdown.value?.top_users ?? [])
const totalTokens = computed(() => usageTrends.value.reduce((sum, d) => sum + d.tokens, 0))
const totalModelCost = computed(() => modelBreakdown.value.reduce((sum, d) => sum + d.cost_usd, 0))

// Bar widths for liability vs wallet
const maxTokens = computed(() => Math.max(liabilityTokens.value, walletTokens.value, 1))
const liabilityBarWidth = computed(() => `${(liabilityTokens.value / maxTokens.value) * 100}%`)
const walletBarWidth = computed(() => `${(walletTokens.value / maxTokens.value) * 100}%`)

// Chart Canvas Refs
const trendsCanvas = ref<HTMLCanvasElement | null>(null)
const modelsCanvas = ref<HTMLCanvasElement | null>(null)
let trendsChart: Chart | null = null
let modelsChart: Chart | null = null

// Palette for models
const modelColors = {
  input: '#6366f1',
  output: '#10b981'
}

// Chart initializers
const initTrendsChart = () => {
  if (!trendsCanvas.value || usageTrends.value.length === 0) return
  if (trendsChart) trendsChart.destroy()

  const ctx = trendsCanvas.value.getContext('2d')
  if (!ctx) return

  const gradient = ctx.createLinearGradient(0, 0, 0, 320)
  gradient.addColorStop(0, 'rgba(99, 102, 241, 0.25)')
  gradient.addColorStop(1, 'rgba(99, 102, 241, 0)')

  const labels = usageTrends.value.map(d => {
    const date = new Date(d.date)
    return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' })
  })

  trendsChart = new Chart(ctx, {
    type: 'line',
    data: {
      labels,
      datasets: [{
        label: 'Daily Tokens',
        data: usageTrends.value.map(d => d.tokens),
        borderColor: '#6366f1',
        backgroundColor: gradient,
        borderWidth: 3,
        fill: true,
        tension: 0.45,
        pointRadius: 0,
        pointHitRadius: 10,
        pointHoverRadius: 6,
        pointHoverBackgroundColor: '#6366f1',
        pointHoverBorderColor: '#fff',
        pointHoverBorderWidth: 2,
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: {
        intersect: false,
        mode: 'index',
      },
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: '#000',
          titleFont: { size: 12, weight: 'bold' },
          bodyFont: { size: 12 },
          padding: 12,
          cornerRadius: 8,
          displayColors: false,
          callbacks: {
            label: (context) => `${formatNumber(context.parsed.y)} tokens`
          }
        }
      },
      scales: {
        x: {
          grid: { display: false },
          ticks: { color: '#64748b', font: { size: 10, weight: '600' }, maxRotation: 0 }
        },
        y: {
          grid: { color: 'rgba(148, 163, 184, 0.1)' },
          border: { display: false },
          beginAtZero: true,
          ticks: {
            color: '#64748b',
            font: { size: 10, weight: '600' },
            callback: (v) => formatTokensShort(v as number)
          }
        }
      }
    }
  })
}

const initModelsChart = () => {
  if (!modelsCanvas.value || modelBreakdown.value.length === 0) return
  if (modelsChart) modelsChart.destroy()

  const ctx = modelsCanvas.value.getContext('2d')
  if (!ctx) return

  modelsChart = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: modelBreakdown.value.map(d => d.model),
      datasets: [
        {
          label: 'Input (Context)',
          data: modelBreakdown.value.map(d => d.prompt_tokens),
          backgroundColor: modelColors.input,
          borderRadius: 4,
          barThickness: 20
        },
        {
          label: 'Output (Completion)',
          data: modelBreakdown.value.map(d => d.completion_tokens),
          backgroundColor: modelColors.output,
          borderRadius: 4,
          barThickness: 20
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'bottom',
          labels: {
            usePointStyle: true,
            pointStyle: 'circle',
            padding: 25,
            color: '#64748b',
            font: { size: 10, weight: '700' }
          }
        },
        tooltip: {
          backgroundColor: '#000',
          padding: 12,
          cornerRadius: 8,
          usePointStyle: true,
          callbacks: {
            label: (context) => ` ${context.dataset.label}: ${formatNumber(context.parsed.y)}TK`
          }
        }
      },
      scales: {
        x: {
          grid: { display: false },
          ticks: { color: '#64748b', font: { size: 10, weight: '700' } }
        },
        y: {
          stacked: true,
          grid: { color: 'rgba(148, 163, 184, 0.1)' },
          border: { display: false },
          ticks: {
            color: '#64748b',
            font: { size: 10, weight: '700' },
            callback: (v) => formatTokensShort(v as number)
          }
        }
      }
    }
  })
}

// Formatters
const formatNumber = (n?: number) => {
  if (n === undefined || n === null) return '0'
  return Number(n).toLocaleString()
}

const formatUsd = (value?: number | null, decimals = 2) => {
  if (value === undefined || value === null || isNaN(Number(value))) return (0).toFixed(decimals)
  return Number(value).toFixed(decimals)
}

const formatNaira = (value: number) => {
  return new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', maximumFractionDigits: 0 }).format(value)
}

const formatTokensShort = (v: number) => {
  if (v >= 1_000_000) return `${(v / 1_000_000).toFixed(1)}M`
  if (v >= 1_000) return `${(v / 1_000).toFixed(0)}k`
  return v.toString()
}

const usageTrendsData = computed(() => usageTrends.value)
const modelData = computed(() => modelBreakdown.value)

// Period change
const changePeriod = (days: number) => {
  selectedDays.value = days
  router.get(route('admin.ai.index'), { days }, {
    preserveState: true,
    preserveScroll: true,
    only: ['profitability', 'usageTrends', 'modelBreakdown', 'selectedDays'],
    onSuccess: (page: any) => {
      profitability.value = page.props.profitability
      usageTrends.value = page.props.usageTrends ?? []
      modelBreakdown.value = page.props.modelBreakdown ?? []
      initTrendsChart()
      initModelsChart()
    },
  })
}

// WebSocket & Lifecycle
let channel: any = null

onMounted(() => {
  initTrendsChart()
  initModelsChart()

  channel = (window as any).Echo?.private('admin.ai')
  channel?.listen('.ai.provisioning.updated', (payload: any) => {
    if (payload.metrics) metrics.value = payload.metrics
    if (payload.snapshot) snapshot.value = payload.snapshot
    if (payload.modelBreakdown) {
      modelBreakdown.value = payload.modelBreakdown
      initModelsChart()
    }
  })
})

watch([usageTrends, modelBreakdown], () => {
  initTrendsChart()
  initModelsChart()
}, { deep: true })

onBeforeUnmount(() => {
  channel?.stopListening('.ai.provisioning.updated')
  if (trendsChart) trendsChart.destroy()
  if (modelsChart) modelsChart.destroy()
})

// Manual refresh
const refresh = async () => {
  try {
    isRefreshing.value = true
    const res = await fetch(route('admin.ai.refresh') + `?days=${selectedDays.value}`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
    })
    const data = await res.json()
    if (data?.metrics) {
      metrics.value = data.metrics
      if (data.metrics.balance_info) {
        balanceInfo.value = data.metrics.balance_info
      }
    }
    if (data?.snapshot) snapshot.value = data.snapshot
    if (data?.profitability) profitability.value = data.profitability
    if (data?.usageTrends) {
      usageTrends.value = data.usageTrends
      initTrendsChart()
    }
    if (data?.liabilityBreakdown) liabilityBreakdown.value = data.liabilityBreakdown
    if (data?.modelBreakdown) {
      modelBreakdown.value = data.modelBreakdown
      initModelsChart()
    }
  } catch (e) {
    console.error('Failed to refresh AI metrics', e)
  } finally {
    isRefreshing.value = false
  }
}

// Balance modal functions
const openBalanceModal = () => {
  balanceForm.value = {
    initial_balance: creditSettings.value?.initial_balance?.toString() ?? '0',
    notes: creditSettings.value?.notes ?? '',
  }
  isBalanceModalOpen.value = true
}

const saveBalance = async () => {
  try {
    isSavingBalance.value = true
    const res = await fetch(route('admin.ai.update-credit'), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
      body: JSON.stringify({
        initial_balance: parseFloat(balanceForm.value.initial_balance) || 0,
        notes: balanceForm.value.notes || null,
      }),
    })
    const data = await res.json()

    if (data.success) {
      // Update local state
      if (data.settings) {
        creditSettings.value = data.settings
      }
      if (data.balance_info) {
        balanceInfo.value = data.balance_info
      }
      if (data.metrics) {
        metrics.value = data.metrics
      }
      isBalanceModalOpen.value = false
    } else {
      console.error('Failed to save balance:', data)
    }
  } catch (e) {
    console.error('Failed to save credit balance', e)
  } finally {
    isSavingBalance.value = false
  }
}
</script>
