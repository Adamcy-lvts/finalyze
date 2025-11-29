<template>
  <SidebarProvider>
    <Sidebar variant="sidebar" collapsible="icon" class="border-r">
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
                <SidebarMenuButton :href="item.href" :data-active="isActive(item.href)">
                  <span class="text-xs w-4 text-slate-500">{{ item.icon }}</span>
                  <span>{{ item.label }}</span>
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

const page = usePage()
const user = computed(() => page.props.auth?.user)

const navItems = [
  { href: '/admin', label: 'Dashboard', icon: 'home' },
  { href: '/admin/users', label: 'Users', icon: 'users' },
  { href: '/admin/payments', label: 'Payments', icon: 'credit-card' },
  { href: '/admin/analytics', label: 'Analytics', icon: 'bar' },
  { href: '/admin/projects', label: 'Projects', icon: 'folder' },
  { href: '/admin/ai', label: 'AI Monitoring', icon: 'cpu' },
  { href: '/admin/system/features', label: 'Feature Flags', icon: 'toggle' },
  { href: '/admin/system/settings', label: 'Settings', icon: 'settings' },
  { href: '/admin/audit', label: 'Audit Logs', icon: 'list' },
  { href: '/admin/notifications', label: 'Notifications', icon: 'bell' },
]

const isActive = (href: string) => page.url.startsWith(href)
</script>
