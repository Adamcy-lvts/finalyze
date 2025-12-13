import { computed, ref, watch } from "vue";
import { usePage } from "@inertiajs/vue3";
import { toast } from "vue-sonner";
import { useAppearance } from "@/composables/useAppearance";
import type { ChapterEditorProps } from "@/types/chapter-editor";

export function useChapterUiState(props: ChapterEditorProps) {
    const page = usePage();

    watch(
        () => page.props?.flash,
        (flash: any) => {
            if (!flash) return;
            if (flash.success) {
                toast.success(flash.success);
            }
            if (flash.error) {
                toast.error(flash.error);
            }
        },
        { deep: true, immediate: true }
    );

    const chapterTitle = ref(props.chapter.title || '');
    const chapterContent = ref(props.chapter.content || '');
    const showPreview = ref(true);

    const { isDark, toggle } = useAppearance();
    const isEditorDark = computed(() => isDark.value);

    const initChapterTheme = () => {
        // Global theme is initialized in `resources/views/app.blade.php` and `useAppearance`.
    };

    const toggleChapterTheme = () => {
        toggle();
    };

    const isNativeFullscreen = ref(false);
    const showAISidebar = ref(false);
    const showStatistics = ref(false);
    const activeTab = ref('write');
    const selectedText = ref('');
    const cursorPosition = ref(0);
    const showChatMode = ref(false);
    const showCitationMode = ref(false);
    const showDefensePrep = ref(true);

    const loadChatModeFromStorage = () => {
        try {
            const stored = localStorage.getItem(`chatMode_${props.project.id}_${props.chapter.chapter_number}`);
            return stored === 'true';
        } catch (error) {
            console.warn('Failed to load chat mode from localStorage:', error);
            return false;
        }
    };

    const saveChatModeToStorage = (isActive: boolean) => {
        try {
            localStorage.setItem(`chatMode_${props.project.id}_${props.chapter.chapter_number}`, String(isActive));
        } catch (error) {
            console.warn('Failed to save chat mode to localStorage:', error);
        }
    };

    const showLeftSidebar = ref(false);
    const showRightSidebar = ref(false);
    const isMobile = ref(false);
    const isLeftSidebarCollapsed = ref(false);
    const isRightSidebarCollapsed = ref(false);
    const showLeftSidebarInFullscreen = ref(true);
    const showRightSidebarInFullscreen = ref(true);

    return {
        chapterTitle,
        chapterContent,
        showPreview,
        isEditorDark,
        initChapterTheme,
        toggleChapterTheme,
        isNativeFullscreen,
        showAISidebar,
        showStatistics,
        activeTab,
        selectedText,
        cursorPosition,
        showChatMode,
        showCitationMode,
        showDefensePrep,
        loadChatModeFromStorage,
        saveChatModeToStorage,
        showLeftSidebar,
        showRightSidebar,
        isMobile,
        isLeftSidebarCollapsed,
        isRightSidebarCollapsed,
        showLeftSidebarInFullscreen,
        showRightSidebarInFullscreen,
    };
}
