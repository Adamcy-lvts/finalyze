<template>
  <SidebarProvider>
    <Sidebar collapsible="icon" variant="inset">
      <SidebarHeader>
        <SidebarMenu>
          <SidebarMenuItem>
            <SidebarMenuButton size="lg">
              <Link href="/admin" class="flex items-center gap-3">
                <AppLogo />
                <div class="flex flex-col items-start">
                  <span class="text-sm font-semibold text-foreground">Admin</span>
                  <span class="text-xs text-muted-foreground">Project Companion</span>
                </div>
              </Link>
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
      <SidebarFooter class="px-2 py-3 text-xs text-muted-foreground space-y-3">
        <NavFooter :items="footerNavItems" />
        <DropdownMenu>
          <DropdownMenuTrigger as-child>
            <SidebarMenu>
              <SidebarMenuItem>
                <SidebarMenuButton size="lg" class="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground">
                  <UserInfo v-if="user" :user="user" />
                  <ChevronsUpDown class="ml-auto size-4" />
                </SidebarMenuButton>
              </SidebarMenuItem>
            </SidebarMenu>
          </DropdownMenuTrigger>
          <DropdownMenuContent class="min-w-56 rounded-lg" side="top" align="start" :side-offset="8">
            <DropdownMenuLabel class="p-0 font-normal">
              <div class="flex items-center gap-2 px-2 py-2 text-left text-sm">
                <UserInfo v-if="user" :user="user" :show-email="true" />
              </div>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuItem :as-child="true">
              <Link class="block w-full" :href="route('profile.edit')" prefetch as="button">
                <Settings class="mr-2 h-4 w-4" />
                Settings
              </Link>
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem :as-child="true">
              <Link class="block w-full" method="post" :href="route('logout')" as="button">
                <LogOut class="mr-2 h-4 w-4" />
                Log out
              </Link>
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
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
import { Link, usePage } from '@inertiajs/vue3'
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
import {
  LayoutDashboard,
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
  BookOpen,
  Folder as FolderIcon,
  ChevronsUpDown,
  LogOut,
} from 'lucide-vue-next'
import NavFooter from '@/components/NavFooter.vue'
import AppLogo from '@/components/AppLogo.vue'
import UserInfo from '@/components/UserInfo.vue'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'

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

const footerNavItems = [
  { title: 'Github Repo', href: 'https://github.com/Adamcy-lvts/finalyze', icon: FolderIcon },
  { title: 'Documentation', href: '#', icon: BookOpen },
]

const isActive = (href: string) => page.url.startsWith(href)
</script>
