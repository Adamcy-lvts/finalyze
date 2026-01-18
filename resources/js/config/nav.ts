import { type NavItem } from '@/types';
import { BookOpen, Coins, FolderOpen, Home, Library, Plus, Settings, Wallet, Users } from 'lucide-vue-next';

const standardMainNavItems: NavItem[] = [
    { title: 'Dashboard', href: '/dashboard', icon: Home },
    { title: 'My Projects', href: '/projects', icon: FolderOpen },
    { title: 'Topic Library', href: '/projects/topics', icon: Library },
    { title: 'New Project', href: '/projects/create', icon: Plus },
    { title: 'Buy Credits', href: '/pricing', icon: Coins },
];

const affiliateMainNavItems: NavItem[] = [
    { title: 'Affiliate Dashboard', href: '/affiliate', icon: Wallet },
    { title: 'Earnings', href: '/affiliate/earnings', icon: Coins },
    { title: 'Referrals', href: '/affiliate/referrals', icon: Users },
];

const standardBottomNavItems: NavItem[] = [
    { title: 'Dashboard', href: '/dashboard', icon: Home },
    { title: 'My Projects', href: '/projects', icon: FolderOpen },
    { title: 'New Project', href: '/projects/create', icon: Plus },
    { title: 'Library', href: '/projects/topics', icon: Library },
    { title: 'Settings', href: '/settings/profile', icon: Settings },
];

const affiliateBottomNavItems: NavItem[] = [
    { title: 'Affiliate', href: '/affiliate', icon: Wallet },
    { title: 'Earnings', href: '/affiliate/earnings', icon: Coins },
    { title: 'Referrals', href: '/affiliate/referrals', icon: Users },
    { title: 'Settings', href: '/settings/profile', icon: Settings },
];

export const getMainNavItems = (options: {
    isAffiliate: boolean;
    isPureAffiliate: boolean;
    isAffiliateRoute: boolean;
}): NavItem[] => {
    if (options.isAffiliate && (options.isPureAffiliate || options.isAffiliateRoute)) {
        return affiliateMainNavItems;
    }

    return standardMainNavItems;
};

export const getBottomNavItems = (options: {
    isAffiliate: boolean;
    isPureAffiliate: boolean;
    isAffiliateRoute: boolean;
}): NavItem[] => {
    if (options.isAffiliate && (options.isPureAffiliate || options.isAffiliateRoute)) {
        return affiliateBottomNavItems;
    }

    return standardBottomNavItems;
};

export const footerNavItems: NavItem[] = [
    { title: 'Documentation', href: '#', icon: BookOpen },
];
