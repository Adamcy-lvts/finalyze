import { type NavItem } from '@/types';
import { BookOpen, Coins, FolderOpen, Home, Library, Plus, Settings } from 'lucide-vue-next';

export const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
        icon: Home,
    },
    {
        title: 'My Projects',
        href: '/projects',
        icon: FolderOpen,
    },
    {
        title: 'Topic Library',
        href: '/projects/topics',
        icon: Library,
    },
    {
        title: 'New Project',
        href: '/projects/create',
        icon: Plus,
    },
    {
        title: 'Buy Credits',
        href: '/pricing',
        icon: Coins,
    },
];

export const bottomNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
        icon: Home,
    },
    {
        title: 'My Projects',
        href: '/projects',
        icon: FolderOpen,
    },
    {
        title: 'New Project',
        href: '/projects/create',
        icon: Plus,
    },
    {
        title: 'Library',
        href: '/projects/topics',
        icon: Library,
    },
    {
        title: 'Settings',
        href: '/settings/profile',
        icon: Settings,
    },
];

export const footerNavItems: NavItem[] = [
    {
        title: 'Documentation',
        href: '#',
        icon: BookOpen,
    },
];
