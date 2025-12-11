export interface Chapter {
    id: number;
    chapter_number: number;
    title: string;
    content: string | null;
    word_count: number;
    target_word_count?: number | null;
    status: 'not_started' | 'draft' | 'in_review' | 'approved';
    slug?: string;
}

export interface ProjectCategory {
    id: number;
    name: string;
    slug: string;
    default_chapter_count: number;
    chapter_structure: any[];
    target_word_count: number;
}

export interface ChapterSection {
    id: number;
    section_number: string;
    section_title: string;
    section_description: string;
    target_word_count: number;
    current_word_count: number;
    is_completed: boolean;
    is_required: boolean;
}

export interface ProjectOutline {
    id: number;
    chapter_number: number;
    chapter_title: string;
    target_word_count: number;
    completion_threshold: number;
    description: string;
    sections: ChapterSection[];
}

export interface FacultyChapter {
    number: number;
    title: string;
    word_count: number;
    completion_threshold: number;
    description: string;
    is_required: boolean;
    sections: Array<{
        number: string;
        title: string;
        description: string;
        word_count: number;
        is_required: boolean;
        tips?: string[];
    }>;
}

export interface Project {
    id: number;
    slug: string;
    title: string;
    topic: string;
    type: string;
    status: string;
    mode: 'auto' | 'manual';
    field_of_study: string;
    university: string;
    course: string;
    project_category_id?: number;
    category?: ProjectCategory;
    outlines?: ProjectOutline[];
}

export interface ChapterEditorProps {
    project: Project;
    chapter: Chapter;
    allChapters: Chapter[];
    facultyChapters?: FacultyChapter[];
    mode?: 'write' | 'edit';
}
