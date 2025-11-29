import type { LucideIcon } from 'lucide-vue-next';
import type { Config } from 'ziggy-js';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon;
    isActive?: boolean;
}

export type AppPageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    ziggy: Config & { location: string };
    sidebarOpen: boolean;
};

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export type BreadcrumbItemType = BreadcrumbItem;

export interface Project {
    id: number;
    title: string;
    slug: string;
    topic: string;
    type: string;
    status: string;
    mode: 'auto' | 'manual';
    field_of_study: string;
    university?: string;
    course?: string;
    outlines?: any; // simplified
    universityRelation?: any;
    facultyRelation?: any;
    departmentRelation?: any;
    category?: any;
}

export interface Chapter {
    id: number;
    title: string;
    chapter_number: number;
    word_count: number;
    target_word_count: number;
    slug: string;
    content: string | null;
    status: 'not_started' | 'draft' | 'in_review' | 'approved' | 'completed';
}

export interface ContentAnalysis {
    word_count: number;
    citation_count: number;
    table_count: number;
    figure_count: number;
    claim_count: number;
    has_introduction: boolean;
    has_conclusion: boolean;
    detected_issues: string[];
    quality_metrics: {
        reading_level: string;
    };
}

export type ChapterContextAnalysis = ContentAnalysis;

export interface UserChapterSuggestion {
    id: number;
    content: string;
    suggestion_type: string;
    suggestion_content: string;
}

