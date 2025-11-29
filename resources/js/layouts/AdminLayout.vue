<template>
  <SidebarProvider>
    <Sidebar collapsible="icon" variant="inset">
      <SidebarHeader>
        <SidebarMenu>
          <SidebarMenuItem>
            <SidebarMenuButton size="lg">
              <div class="flex flex-col items-start">
                <span class="text-sm font-semibold text-foreground">Admin</span>
                <span class="text-xs text-muted-foreground">Project Companion</span>
              </div>
            </SidebarMenuButton>
          </SidebarMenuItem>
        </SidebarMenu>
      </SidebarHeader>
      <SidebarContent>
        <SidebarGroup class="px-2 py-0">
          <SidebarGroupLabel>Admin</SidebarGroupLabel>
          <SidebarGroupContent>
            <SidebarMenu>
              <SidebarMenuItem v-for="item in navItems" :key="item.href">
                <SidebarMenuButton as-child :is-active="isActive(item.href)" :tooltip="item.label">
                  <Link :href="item.href">
                    <component :is="item.icon" />
                    <span class="truncate">{{ item.label }}</span>
                  </Link>
                </SidebarMenuButton>
              </SidebarMenuItem>
            </SidebarMenu>
          </SidebarGroupContent>
        </SidebarGroup>
      </SidebarContent>
      <SidebarFooter class="px-4 py-3 text-xs text-muted-foreground">
        <div class="flex items-center gap-2">
          <span class="inline-block h-2 w-2 rounded-full bg-green-500"></span>
          <span>Online</span>
        </div>
      </SidebarFooter>
      <SidebarRail />
    </Sidebar>
    <SidebarInset class="bg-slate-50 text-slate-900 flex min-h-screen flex-col">
      <header class="border-b border-slate-200 bg-white">
        <div class="max-w-7xl mx-auto px-4 md:px-8 h-14 flex items-center justify-between">
          <div class="flex items-center gap-3">
            <SidebarTrigger />
            <slot name="title">
              <div class="flex items-center gap-2">
                <LayoutDashboard class="h-4 w-4 text-slate-500" />
                <h2 class="text-base font-semibold text-slate-900">Admin</h2>
              </div>
            </slot>
          </div>
          <div class="flex items-center gap-3 text-sm text-slate-600">
            <span>{{ user?.name }}</span>
            <span class="text-slate-400">•</span>
            <a href="/dashboard" class="text-indigo-600 hover:text-indigo-700 text-xs">Back to app</a>
          </div>
        </div>
      </header>
      <main class="flex-1 overflow-y-auto">
        <div class="max-w-7xl mx-auto px-4 md:px-8 py-6 md:py-8">
          <slot />
        </div>
      </main>
    </SidebarInset>
  </SidebarProvider>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarGroup,
  SidebarGroupContent,
  SidebarHeader,
  SidebarInset,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarProvider,
  SidebarRail,
  SidebarTrigger,
  SidebarGroupLabel,
} from '@/components/ui/sidebar'
import { LayoutDashboard, Home, Users, CreditCard, BarChart3, Folder, Cpu, ToggleLeft, Settings, List, Bell } from 'lucide-vue-next'
import { Link, usePage } from '@inertiajs/vue3'
const page = usePage()
const user = computed(() => page.props.auth?.user)

const navItems = [
  { href: '/admin', label: 'Dashboard', icon: Home },
  { href: '/admin/users', label: 'Users', icon: Users },
  { href: '/admin/payments', label: 'Payments', icon: CreditCard },
  { href: '/admin/analytics', label: 'Analytics', icon: BarChart3 },
  { href: '/admin/projects', label: 'Projects', icon: Folder },
  { href: '/admin/ai', label: 'AI Monitoring', icon: Cpu },
  { href: '/admin/system/features', label: 'Feature Flags', icon: ToggleLeft },
  { href: '/admin/system/settings', label: 'Settings', icon: Settings },
  { href: '/admin/audit', label: 'Audit Logs', icon: List },
  { href: '/admin/notifications', label: 'Notifications', icon: Bell },
]

const isActive = (href: string) => page.url.startsWith(href)
</script>
