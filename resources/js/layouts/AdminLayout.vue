<template>
  <SidebarProvider>
    <Sidebar collapsible="icon" variant="inset" class="border-r border-border bg-sidebar">
      <SidebarHeader class="h-16 flex items-center justify-center border-b border-border/50 px-4">
        <SidebarMenu>
          <SidebarMenuItem>
            <SidebarMenuButton size="lg" class="hover:bg-transparent data-[state=open]:bg-transparent">
              <Link href="/admin" class="flex items-center gap-3 w-full">
              <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                <LayoutDashboard class="h-5 w-5" />
              </div>
              <div class="flex flex-col gap-0.5 leading-none">
                <span class="font-semibold text-foreground">Admin Panel</span>
                <span class="text-xs text-muted-foreground">v1.0.0</span>
              </div>
              </Link>
            </SidebarMenuButton>
          </SidebarMenuItem>
        </SidebarMenu>
      </SidebarHeader>
      <SidebarContent class="py-4">
        <SidebarGroup>
          <SidebarGroupLabel class="px-4 text-xs font-medium text-muted-foreground/70 uppercase tracking-wider mb-2">
            Platform</SidebarGroupLabel>
          <SidebarGroupContent>
            <SidebarMenu>
              <SidebarMenuItem v-for="item in platformNavItems" :key="item.href">
                <SidebarMenuButton as-child :is-active="isActive(item.href, item.exact)" :tooltip="item.label"
                  class="px-4 py-2 h-auto transition-colors hover:bg-muted/50 data-[active=true]:bg-primary/10 data-[active=true]:text-primary">
                  <Link :href="item.href" class="flex items-center gap-3">
                  <component :is="item.icon" class="h-4 w-4" />
                  <span class="font-medium">{{ item.label }}</span>
                  </Link>
                </SidebarMenuButton>
              </SidebarMenuItem>
            </SidebarMenu>
          </SidebarGroupContent>
        </SidebarGroup>

        <SidebarGroup class="mt-4">
          <SidebarGroupLabel class="px-4 text-xs font-medium text-muted-foreground/70 uppercase tracking-wider mb-2">
            System</SidebarGroupLabel>
          <SidebarGroupContent>
            <SidebarMenu>
              <SidebarMenuItem v-for="item in systemNavItems" :key="item.href">
                <SidebarMenuButton as-child :is-active="isActive(item.href)" :tooltip="item.label"
                  class="px-4 py-2 h-auto transition-colors hover:bg-muted/50 data-[active=true]:bg-primary/10 data-[active=true]:text-primary">
                  <Link :href="item.href" class="flex items-center gap-3">
                  <component :is="item.icon" class="h-4 w-4" />
                  <span class="font-medium">{{ item.label }}</span>
                  </Link>
                </SidebarMenuButton>
              </SidebarMenuItem>
            </SidebarMenu>
          </SidebarGroupContent>
        </SidebarGroup>
      </SidebarContent>
      <SidebarFooter class="border-t border-border/50 p-4">
        <DropdownMenu>
          <DropdownMenuTrigger as-child>
            <SidebarMenuButton size="lg"
              class="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground hover:bg-muted/50 transition-colors">
              <UserInfo v-if="user" :user="user" />
              <ChevronsUpDown class="ml-auto size-4 text-muted-foreground" />
            </SidebarMenuButton>
          </DropdownMenuTrigger>
          <DropdownMenuContent class="w-[--radix-dropdown-menu-trigger-width] min-w-56 rounded-lg" side="top"
            align="start" :side-offset="8">
            <DropdownMenuLabel class="p-0 font-normal">
              <div class="flex items-center gap-2 px-2 py-2 text-left text-sm">
                <UserInfo v-if="user" :user="user" :show-email="true" />
              </div>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuItem :as-child="true">
              <Link class="block w-full cursor-pointer" :href="route('profile.edit')" prefetch as="button">
              <Settings class="mr-2 h-4 w-4" />
              Settings
              </Link>
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem :as-child="true">
              <Link class="block w-full cursor-pointer text-rose-500 focus:text-rose-500" method="post"
                :href="route('logout')" as="button">
              <LogOut class="mr-2 h-4 w-4" />
              Log out
              </Link>
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </SidebarFooter>
      <SidebarRail />
    </Sidebar>
    <SidebarInset class="bg-background text-foreground flex min-h-screen flex-col">
      <header
        class="sticky top-0 z-10 border-b border-border bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
        <div class="max-w-7xl mx-auto px-4 md:px-8 h-16 flex items-center justify-between">
          <div class="flex items-center gap-3">
            <SidebarTrigger />
            <slot name="title">
              <div class="flex items-center gap-2">
                <LayoutDashboard class="h-4 w-4 text-muted-foreground" />
                <h2 class="text-base font-semibold">Admin</h2>
              </div>
            </slot>
          </div>
          <div class="flex items-center gap-3 md:gap-6">
            <Button variant="ghost" size="icon"
              class="relative text-muted-foreground hover:text-foreground transition-colors" @click="openDrawer">
              <Bell class="h-5 w-5" />
              <span v-if="unreadCount > 0"
                class="absolute top-2 right-2 h-2 w-2 rounded-full bg-rose-500 ring-2 ring-background"></span>
              <span class="sr-only">Notifications</span>
            </Button>

            <div class="hidden md:flex items-center gap-3 text-sm text-muted-foreground">
              <span>{{ user?.name }}</span>
              <span class="text-muted-foreground/50">•</span>
              <a href="/dashboard" class="text-primary hover:text-primary/80 text-xs font-medium transition-colors">Back
                to app</a>
            </div>
          </div>
        </div>
      </header>
      <main class="flex-1 overflow-y-auto">
        <div class="max-w-7xl mx-auto px-4 md:px-8 py-6 md:py-8">
          <slot />
        </div>
      </main>

      <Drawer v-model:open="drawerOpen" direction="right" :should-scale-background="false">
        <DrawerContent side="right" class="mt-0 h-full rounded-none border-l bg-background shadow-2xl">
          <DrawerHeader class="flex items-center justify-between border-b px-4 py-3">
            <div class="flex flex-col gap-1">
              <DrawerTitle class="text-base font-semibold text-foreground">Notifications</DrawerTitle>
              <DrawerDescription class="text-xs text-muted-foreground">Latest admin alerts</DrawerDescription>
            </div>
            <div class="flex items-center gap-2">
              <Button variant="outline" size="sm" class="text-xs" :disabled="!notifications.length"
                @click="markAllRead">
                Mark all read
              </Button>
              <DrawerClose as-child>
                <Button variant="ghost" size="icon">
                  <X class="h-4 w-4" />
                </Button>
              </DrawerClose>
            </div>
          </DrawerHeader>
          <div class="flex-1 overflow-y-auto divide-y">
            <div v-if="notificationsLoading" class="p-4 text-sm text-muted-foreground flex items-center gap-2">
              <Loader2 class="h-4 w-4 animate-spin" />
              Loading notifications...
            </div>
            <div v-else-if="!notifications.length" class="p-6 text-sm text-muted-foreground">
              No notifications yet.
            </div>
            <template v-else>
              <div v-for="notification in notifications" :key="notification.id"
                class="flex items-start gap-3 p-4 hover:bg-muted/50">
                <span class="mt-1 h-2.5 w-2.5 rounded-full"
                  :class="notification.is_read || notification.read_at ? 'bg-muted-foreground/40' : 'bg-primary'"></span>
                <div class="flex-1 space-y-1">
                  <p class="text-sm font-semibold text-foreground">
                    {{ notification.title ?? humanize(notification.type) }}
                  </p>
                  <p class="text-xs text-muted-foreground leading-relaxed">
                    {{ notification.message ?? notification.data?.message ?? 'No details provided' }}
                  </p>
                  <div class="text-[11px] text-muted-foreground">
                    {{ formatTimestamp(notification.created_at) }}
                  </div>
                </div>
                <Button v-if="!notification.is_read && !notification.read_at" variant="ghost" size="sm" class="text-xs"
                  @click="markRead(notification.id)">
                  Mark read
                </Button>
              </div>
            </template>
          </div>
        </DrawerContent>
      </Drawer>
    </SidebarInset>
  </SidebarProvider>
</template>

<script setup lang="ts">
import axios from 'axios'
import { computed, ref } from 'vue'
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
  Users,
  CreditCard,
  BarChart3,
  Folder,
  Cpu,
  ToggleLeft,
  Settings,
  List,
  Bell,
  ChevronsUpDown,
  LogOut,
  X,
  Loader2,
} from 'lucide-vue-next'
import UserInfo from '@/components/UserInfo.vue'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Button } from '@/components/ui/button'
import {
  Drawer,
  DrawerClose,
  DrawerContent,
  DrawerDescription,
  DrawerHeader,
  DrawerTitle,
} from '@/components/ui/drawer'

const page = usePage()
const user = computed(() => page.props.auth?.user)

const platformNavItems = [
  { href: '/admin', label: 'Dashboard', icon: LayoutDashboard, exact: true },
  { href: '/admin/users', label: 'Users', icon: Users },
  { href: '/admin/payments', label: 'Payments', icon: CreditCard },
  { href: '/admin/projects', label: 'Projects', icon: Folder },
  { href: '/admin/analytics', label: 'Analytics', icon: BarChart3 },
]

const systemNavItems = [
  { href: '/admin/ai', label: 'AI Monitoring', icon: Cpu },
  { href: '/admin/system/features', label: 'Feature Flags', icon: ToggleLeft },
  { href: '/admin/system/settings', label: 'Settings', icon: Settings },
  { href: '/admin/audit', label: 'Audit Logs', icon: List },
  { href: '/admin/notifications', label: 'Notifications', icon: Bell },
]

const isActive = (href: string, exact = false) => {
  if (exact) {
    return page.url === href
  }
  return page.url.startsWith(href)
}

const drawerOpen = ref(false)
const notifications = ref<any[]>([])
const notificationsLoading = ref(false)

const loadNotifications = async () => {
  try {
    notificationsLoading.value = true
    const res = await axios.get('/admin/notifications', { params: { format: 'json' } })
    notifications.value = res.data.notifications ?? []
  } catch (e) {
    console.error('Failed to load notifications', e)
  } finally {
    notificationsLoading.value = false
  }
}

const openDrawer = async () => {
  drawerOpen.value = true
  await loadNotifications()
}

const unreadCount = computed(() => notifications.value.filter((n) => !n.is_read && !n.read_at).length)

const markRead = async (id: number | string) => {
  try {
    await axios.post(`/admin/notifications/${id}/read`)
    notifications.value = notifications.value.map((n) => n.id === id ? { ...n, is_read: true, read_at: new Date().toISOString() } : n)
  } catch (e) {
    console.error('Failed to mark notification as read', e)
  }
}

const markAllRead = async () => {
  if (!notifications.value.length) return
  try {
    await axios.post('/admin/notifications/read-all')
    notifications.value = notifications.value.map((n) => ({ ...n, is_read: true, read_at: new Date().toISOString() }))
  } catch (e) {
    console.error('Failed to mark all notifications as read', e)
  }
}

const humanize = (value?: string) => {
  if (!value) return 'Notification'
  return value.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
}

const formatTimestamp = (value?: string) => {
  if (!value) return ''
  const date = new Date(value)
  return new Intl.DateTimeFormat('en', { dateStyle: 'medium', timeStyle: 'short' }).format(date)
}
</script>
