<template>
  <SidebarProvider>
    <Sidebar variant="inset" collapsible="icon" class="border-r bg-white">
      <SidebarHeader class="px-4 py-4">
        <div>
          <h1 class="text-sm font-semibold text-slate-900">Admin</h1>
          <p class="text-xs text-slate-500">Project Companion</p>
        </div>
      </SidebarHeader>
      <SidebarContent>
        <SidebarGroup>
          <SidebarGroupContent>
            <SidebarMenu>
              <SidebarMenuItem v-for="item in navItems" :key="item.href">
                <SidebarMenuButton :href="item.href" :data-active="isActive(item.href)" class="text-sm">
                  <component :is="item.icon" class="h-4 w-4 text-slate-500" />
                  <span class="truncate">{{ item.label }}</span>
                </SidebarMenuButton>
              </SidebarMenuItem>
            </SidebarMenu>
          </SidebarGroupContent>
        </SidebarGroup>
      </SidebarContent>
      <SidebarFooter class="px-4 py-3 text-xs text-slate-500">
        <div class="flex items-center gap-2">
          <span class="inline-block h-2 w-2 rounded-full bg-green-500"></span>
          <span>Online</span>
        </div>
      </SidebarFooter>
      <SidebarRail />
    </Sidebar>
    <SidebarInset class="bg-slate-50 text-slate-900 flex min-h-screen flex-col">
      <header class="h-14 border-b border-slate-200 bg-white flex items-center justify-between px-4">
        <div class="flex items-center gap-3">
          <SidebarTrigger />
          <slot name="title">
            <h2 class="text-base font-semibold text-slate-900">Admin</h2>
          </slot>
        </div>
        <div class="flex items-center gap-3 text-sm text-slate-600">
          <span>{{ user?.name }}</span>
          <span class="text-slate-400">•</span>
          <a href="/dashboard" class="text-indigo-600 hover:text-indigo-700 text-xs">Back to app</a>
        </div>
      </header>
      <main class="flex-1 overflow-y-auto p-6">
        <slot />
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
} from '@/components/ui/sidebar'
import {
  Home,
  Users,
  CreditCard,
  BarChart3,
  Folder,
  Cpu,
  ToggleLeft,
  Settings,
  List,
  Bell,
} from 'lucide-vue-next'

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
