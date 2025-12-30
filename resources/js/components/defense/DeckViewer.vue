<script setup lang="ts">
import { ref, watch, computed, nextTick, onBeforeUnmount, onMounted } from 'vue';
import axios from 'axios';
import { useSwipe } from '@vueuse/core';
import {
    ChevronRight,
    Presentation,
    Image as ImageIcon,
    Maximize2,
    X,
    ChevronLeft,
    Play,
    Copy,
    Trash2,
    Plus,
    FileText,
    ChevronUp,
    ChevronDown,
    Type,
    List,
    Columns2,
    PanelLeft,
    PanelRight
} from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';

interface SlideHeading {
    heading: string;
    content: string;
}

interface MixedContentItem {
    type: 'paragraph' | 'bullet';
    text: string;
}

interface DefenseDeckSlide {
    title: string;
    content_type?: 'bullets' | 'paragraphs' | 'mixed';
    bullets: string[];
    paragraphs?: string[];
    headings?: SlideHeading[];
    layout?: string;
    mixed_content?: MixedContentItem[];
    visuals?: string;
    speaker_notes?: string;
    image_url?: string;
    image_scale?: number;
    image_fit?: 'cover' | 'contain';
    image_position_x?: number;
    image_position_y?: number;
    charts?: unknown[];
    tables?: unknown[];
}

const props = withDefaults(defineProps<{
    project?: { title: string };
    slides: DefenseDeckSlide[];
    activeIndex: number;
    isSaving?: boolean;
    compact?: boolean;
    canDownload?: boolean;
    showPptx?: boolean;
    pptxUrl?: string | null;
    pptxBusy?: boolean;
}>(), {
    isSaving: false,
    compact: false,
    canDownload: false,
    showPptx: false,
    pptxUrl: null,
    pptxBusy: false,
});

const emit = defineEmits<{
    (e: 'update:activeIndex', index: number): void;
    (e: 'update:slides', slides: DefenseDeckSlide[]): void;
    (e: 'toggle-expand'): void;
    (e: 'download-pptx'): void;
    (e: 'export-pptx'): void;
}>();

const draftSlides = ref<DefenseDeckSlide[]>([]);
const isPresenting = ref(false);
const draggingIndex = ref<number | null>(null);
const dragOverIndex = ref<number | null>(null);
const selectedTemplate = ref('title');
const showDeleteDialog = ref(false);
const pendingDeleteIndex = ref<number | null>(null);
const editingBuffer = ref<{ type: 'title' | 'bullet'; index?: number; html: string } | null>(null);
const imageUploadInput = ref<HTMLInputElement | null>(null);
const isImageUploading = ref(false);
const imageUploadError = ref<string | null>(null);
const imageReplaceTarget = ref<string | null>(null);
const imageDragState = ref<{
    startX: number;
    startY: number;
    originX: number;
    originY: number;
    container: HTMLElement | null;
} | null>(null);
const presentationContainer = ref<HTMLElement | null>(null);
const presentationSlideRef = ref<HTMLElement | null>(null);

const cloneSlides = (slides: DefenseDeckSlide[]) => JSON.parse(JSON.stringify(slides)) as DefenseDeckSlide[];

// Flag to prevent watcher from resetting during our own updates
const isInternalUpdate = ref(false);

watch(() => props.slides, (value) => {
    if (isInternalUpdate.value) {
        isInternalUpdate.value = false;
        return;
    }
    draftSlides.value = cloneSlides(value || []);
}, { deep: true, immediate: true });

const updateSlides = (slides: DefenseDeckSlide[]) => {
    isInternalUpdate.value = true;
    draftSlides.value = slides;
    emit('update:slides', slides);
};

const duplicateSlide = (index: number) => {
    if (!draftSlides.value.length) return;
    const next = cloneSlides(draftSlides.value);
    const copy = cloneSlides([next[index]])[0];
    const insertIndex = index + 1;
    next.splice(insertIndex, 0, copy);
    updateSlides(next);
    emit('update:activeIndex', insertIndex);
};

const deleteSlide = (index: number) => {
    if (!draftSlides.value.length) return;
    const next = cloneSlides(draftSlides.value);
    next.splice(index, 1);
    updateSlides(next);

    if (!next.length) {
        emit('update:activeIndex', 0);
        return;
    }

    const nextIndex = index <= props.activeIndex
        ? Math.max(0, props.activeIndex - 1)
        : props.activeIndex;
    emit('update:activeIndex', nextIndex);
};

const requestDeleteSlide = (index: number) => {
    pendingDeleteIndex.value = index;
    showDeleteDialog.value = true;
};

const confirmDeleteSlide = () => {
    if (pendingDeleteIndex.value === null) return;
    deleteSlide(pendingDeleteIndex.value);
    showDeleteDialog.value = false;
    pendingDeleteIndex.value = null;
};

const openImagePicker = () => {
    imageUploadError.value = null;
    imageUploadInput.value?.click();
};

const deleteSlideImage = async (url: string | null) => {
    if (!url) return;
    try {
        await axios.delete('/editor/images', { data: { url } });
    } catch {
        // Ignore delete failures; slide will still clear image.
    }
};

const uploadSlideImage = async (file: File) => {
    if (!file.type.startsWith('image/')) {
        imageUploadError.value = 'Please select an image file.';
        return;
    }

    isImageUploading.value = true;
    imageUploadError.value = null;

    try {
        const formData = new FormData();
        formData.append('image', file);
        const response = await axios.post('/editor/images', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });

        if (response.data?.success && response.data.url) {
            const previousUrl = imageReplaceTarget.value;
            updateField(props.activeIndex, 'image_url', response.data.url);
            updateField(props.activeIndex, 'image_scale', 1);
            updateField(props.activeIndex, 'image_fit', 'cover');
            updateField(props.activeIndex, 'image_position_x', 50);
            updateField(props.activeIndex, 'image_position_y', 50);
            if (previousUrl) {
                await deleteSlideImage(previousUrl);
            }
        } else {
            imageUploadError.value = 'Upload failed.';
        }
    } catch (error: any) {
        imageUploadError.value = error?.response?.data?.message || 'Upload failed.';
    } finally {
        isImageUploading.value = false;
        imageReplaceTarget.value = null;
        if (imageUploadInput.value) {
            imageUploadInput.value.value = '';
        }
    }
};

const handleImageSelect = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (file) {
        void uploadSlideImage(file);
    }
};

const replaceSlideImage = () => {
    imageReplaceTarget.value = activeSlide.value?.image_url || null;
    openImagePicker();
};

const removeSlideImage = async () => {
    const url = activeSlide.value?.image_url || null;
    updateField(props.activeIndex, 'image_url', '');
    updateField(props.activeIndex, 'image_scale', 1);
    updateField(props.activeIndex, 'image_fit', 'cover');
    updateField(props.activeIndex, 'image_position_x', 50);
    updateField(props.activeIndex, 'image_position_y', 50);
    await deleteSlideImage(url);
};

const updateImageScale = (event: Event) => {
    const value = Number((event.target as HTMLInputElement).value);
    if (Number.isNaN(value)) return;
    updateField(props.activeIndex, 'image_scale', value);
};

const updateImageFit = (event: Event) => {
    const value = (event.target as HTMLSelectElement).value as 'cover' | 'contain';
    updateField(props.activeIndex, 'image_fit', value);
};

const clamp = (value: number, min: number, max: number) => Math.min(Math.max(value, min), max);

const startImageDrag = (event: MouseEvent | TouchEvent) => {
    if (!activeSlide.value?.image_url) return;
    const container = event.currentTarget as HTMLElement | null;
    if (!container) return;
    const point = 'touches' in event ? event.touches[0] : event;
    imageDragState.value = {
        startX: point.clientX,
        startY: point.clientY,
        originX: activeSlide.value.image_position_x ?? 50,
        originY: activeSlide.value.image_position_y ?? 50,
        container,
    };
};

const moveImageDrag = (event: MouseEvent | TouchEvent) => {
    if (!imageDragState.value) return;
    const point = 'touches' in event ? event.touches[0] : event;
    const { startX, startY, originX, originY, container } = imageDragState.value;
    if (!container) return;
    const rect = container.getBoundingClientRect();
    const dxPercent = ((point.clientX - startX) / rect.width) * 100;
    const dyPercent = ((point.clientY - startY) / rect.height) * 100;
    const nextX = clamp(originX + dxPercent, 0, 100);
    const nextY = clamp(originY + dyPercent, 0, 100);
    updateField(props.activeIndex, 'image_position_x', Math.round(nextX));
    updateField(props.activeIndex, 'image_position_y', Math.round(nextY));
};

const endImageDrag = () => {
    imageDragState.value = null;
};

const updateEditingBuffer = (payload: { type: 'title' | 'bullet'; index?: number; html: string }) => {
    editingBuffer.value = payload;
};

const applyFormat = (command: 'bold' | 'italic') => {
    document.execCommand(command, false);
    const target = document.activeElement as HTMLElement | null;
    if (!target) return;
    const html = sanitizeInlineHtml(target.innerHTML);
    const bulletIndex = target.dataset?.bulletIndex;
    if (bulletIndex !== undefined) {
        const index = Number(bulletIndex);
        if (!Number.isNaN(index)) {
            updateEditingBuffer({ type: 'bullet', index, html });
        }
        return;
    }
    if (target.getAttribute('contenteditable') === 'true') {
        updateEditingBuffer({ type: 'title', html });
    }
};

const addBullet = async () => {
    const next = cloneSlides(draftSlides.value);
    const bullets = Array.isArray(next[props.activeIndex]?.bullets) ? [...next[props.activeIndex].bullets] : [];
    bullets.push('');
    next[props.activeIndex] = {
        ...next[props.activeIndex],
        bullets,
    };
    updateSlides(next);
    await nextTick();
    const selector = `[data-bullet-index="${bullets.length - 1}"]`;
    const target = document.querySelector(selector) as HTMLElement | null;
    target?.focus();
};

const sanitizeInlineHtml = (html: string) => {
    const container = document.createElement('div');
    container.innerHTML = html;

    const walk = (node: Node): string => {
        if (node.nodeType === Node.TEXT_NODE) {
            return escapeHtml(node.textContent || '');
        }
        if (node.nodeType !== Node.ELEMENT_NODE) {
            return '';
        }
        const element = node as HTMLElement;
        const tag = element.tagName.toLowerCase();
        const inner = Array.from(element.childNodes).map(walk).join('');
        if (tag === 'strong' || tag === 'b') return `<strong>${inner}</strong>`;
        if (tag === 'em' || tag === 'i') return `<em>${inner}</em>`;
        if (tag === 'br') return '<br />';
        return inner;
    };

    return Array.from(container.childNodes).map(walk).join('');
};

const handleBulletInput = (index: number, event: Event) => {
    const target = event.target as HTMLElement | null;
    if (!target) return;
    const cleaned = sanitizeInlineHtml(target.innerHTML);
    updateEditingBuffer({ type: 'bullet', index, html: cleaned });
};

const handleBulletBlur = (index: number, event: Event) => {
    const target = event.target as HTMLElement | null;
    if (!target) return;
    const cleaned = sanitizeInlineHtml(target.innerHTML);
    updateBulletAt(props.activeIndex, index, cleaned);
    if (editingBuffer.value?.type === 'bullet' && editingBuffer.value.index === index) {
        editingBuffer.value = null;
    }
};

const handleBulletKeydown = async (index: number, event: KeyboardEvent) => {
    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'b') {
        event.preventDefault();
        applyFormat('bold');
        return;
    }
    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'i') {
        event.preventDefault();
        applyFormat('italic');
        return;
    }
    if (event.key === 'Enter') {
        event.preventDefault();
        const target = event.target as HTMLElement | null;
        if (target) {
            updateBulletAt(props.activeIndex, index, sanitizeInlineHtml(target.innerHTML));
        }
        const next = cloneSlides(draftSlides.value);
        const bullets = Array.isArray(next[props.activeIndex]?.bullets) ? [...next[props.activeIndex].bullets] : [];
        bullets.splice(index + 1, 0, '');
        next[props.activeIndex] = {
            ...next[props.activeIndex],
            bullets,
        };
        updateSlides(next);
        await nextTick();
        const selector = `[data-bullet-index="${index + 1}"]`;
        const nextTarget = document.querySelector(selector) as HTMLElement | null;
        nextTarget?.focus();
        return;
    }
    if (event.key === 'Backspace') {
        const target = event.target as HTMLElement | null;
        if (target && target.innerText.trim() === '') {
            const next = cloneSlides(draftSlides.value);
            const bullets = Array.isArray(next[props.activeIndex]?.bullets) ? [...next[props.activeIndex].bullets] : [];
            if (bullets.length > 1) {
                event.preventDefault();
                bullets.splice(index, 1);
                next[props.activeIndex] = {
                    ...next[props.activeIndex],
                    bullets,
                };
                updateSlides(next);
                await nextTick();
                const nextIndex = Math.max(0, index - 1);
                const selector = `[data-bullet-index="${nextIndex}"]`;
                const focusTarget = document.querySelector(selector) as HTMLElement | null;
                focusTarget?.focus();
            }
        }
    }
};

const handleTitleInput = (event: Event) => {
    const target = event.target as HTMLElement | null;
    if (!target) return;
    updateEditingBuffer({ type: 'title', html: sanitizeInlineHtml(target.innerHTML) });
};

const handleTitleBlur = (event: Event) => {
    const target = event.target as HTMLElement | null;
    if (!target) return;
    updateField(props.activeIndex, 'title', target.innerText.trim());
    if (editingBuffer.value?.type === 'title') {
        editingBuffer.value = null;
    }
};

const templates: Record<string, DefenseDeckSlide> = {
    title: {
        title: 'Title Slide',
        bullets: ['Project title', 'Name • Department • Date'],
        layout: 'title',
        visuals: '',
        speaker_notes: '',
    },
    problem: {
        title: 'Research Problem',
        bullets: ['Context and motivation', 'Key gap or pain point', 'Why it matters'],
        layout: 'bullets',
        visuals: 'Problem landscape diagram',
        speaker_notes: '',
    },
    objectives: {
        title: 'Objectives',
        bullets: ['Primary objective', 'Secondary objective', 'Success criteria'],
        layout: 'bullets',
        visuals: 'Objective hierarchy diagram',
        speaker_notes: '',
    },
    methodology: {
        title: 'Methodology',
        bullets: ['Research design', 'Data collection', 'Analysis approach'],
        layout: 'image_left',
        visuals: 'Methodology flowchart',
        speaker_notes: '',
    },
    results: {
        title: 'Results',
        bullets: ['Key finding #1', 'Key finding #2', 'Performance metrics'],
        layout: 'two_column',
        visuals: 'Results summary chart',
        speaker_notes: '',
    },
    conclusion: {
        title: 'Conclusion',
        bullets: ['Summary of contributions', 'Impact and implications', 'Future work'],
        layout: 'bullets',
        visuals: '',
        speaker_notes: '',
    },
};

const addSlideFromTemplate = () => {
    const template = templates[selectedTemplate.value] || templates.title;
    const next = cloneSlides(draftSlides.value);
    const insertIndex = props.activeIndex + 1;
    next.splice(insertIndex, 0, { ...template });
    updateSlides(next);
    emit('update:activeIndex', insertIndex);
};

const reorderSlides = (from: number, to: number) => {
    if (from === to) return;
    const next = cloneSlides(draftSlides.value);
    const [moved] = next.splice(from, 1);
    next.splice(to, 0, moved);
    updateSlides(next);

    let nextActive = props.activeIndex;
    if (props.activeIndex === from) {
        nextActive = to;
    } else if (from < to && props.activeIndex > from && props.activeIndex <= to) {
        nextActive = props.activeIndex - 1;
    } else if (from > to && props.activeIndex >= to && props.activeIndex < from) {
        nextActive = props.activeIndex + 1;
    }
    emit('update:activeIndex', nextActive);
};

const handleDragStart = (event: DragEvent, index: number) => {
    draggingIndex.value = index;
    dragOverIndex.value = index;
    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', String(index));
    }
};

const handleDragOver = (index: number) => {
    if (draggingIndex.value === null) return;
    dragOverIndex.value = index;
};

const handleDragLeave = (index: number) => {
    if (dragOverIndex.value === index) {
        dragOverIndex.value = null;
    }
};

const handleDrop = (index: number) => {
    if (draggingIndex.value === null) return;
    reorderSlides(draggingIndex.value, index);
    draggingIndex.value = null;
    dragOverIndex.value = null;
};

const handleDragEnd = () => {
    draggingIndex.value = null;
    dragOverIndex.value = null;
};

const updateField = (index: number, field: keyof DefenseDeckSlide, value: any) => {
    const next = cloneSlides(draftSlides.value);
    next[index] = {
        ...next[index],
        [field]: value,
    };
    updateSlides(next);
};

const updateBulletAt = (slideIndex: number, bulletIndex: number, value: any) => {
    const next = cloneSlides(draftSlides.value);
    const bullets = Array.isArray(next[slideIndex]?.bullets) ? [...next[slideIndex].bullets] : [];
    bullets[bulletIndex] = value;
    next[slideIndex] = {
        ...next[slideIndex],
        bullets,
    };
    updateSlides(next);
};

const escapeHtml = (value: string) => {
    return value
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
};

const renderBullet = (value: string) => {
    if (value.includes('<')) {
        return sanitizeInlineHtml(value);
    }
    const escaped = escapeHtml(value);
    const withBold = escaped.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
    return withBold.replace(/\*(.+?)\*/g, '<em>$1</em>');
};

const splitBullets = (bullets: string[] = []) => {
    const midpoint = Math.ceil(bullets.length / 2);
    return [bullets.slice(0, midpoint), bullets.slice(midpoint)];
};

const splitBulletsWithIndex = (bullets: string[] = []) => {
    const midpoint = Math.ceil(bullets.length / 2);
    const left = bullets.slice(0, midpoint).map((text, index) => ({ text, index }));
    const right = bullets.slice(midpoint).map((text, index) => ({ text, index: index + midpoint }));
    return [left, right];
};

const splitParagraphs = (paragraphs: string[] = []) => {
    const midpoint = Math.ceil(paragraphs.length / 2);
    return [paragraphs.slice(0, midpoint), paragraphs.slice(midpoint)];
};

const splitParagraphsWithIndex = (paragraphs: string[] = []) => {
    const midpoint = Math.ceil(paragraphs.length / 2);
    const left = paragraphs.slice(0, midpoint).map((text, index) => ({ text, index }));
    const right = paragraphs.slice(midpoint).map((text, index) => ({ text, index: index + midpoint }));
    return [left, right];
};

const handleParagraphInput = (index: number, event: Event) => {
    const target = event.target as HTMLElement;
    const newText = target.innerText.trim();
    const currentParagraphs = [...(activeSlide.value.paragraphs || [])];
    currentParagraphs[index] = newText;
    updateField(props.activeIndex, 'paragraphs', currentParagraphs);
};

const handleParagraphBlur = (index: number, event: FocusEvent) => {
    const target = event.target as HTMLElement;
    const newText = target.innerText.trim();
    if (!newText) {
        // Remove empty paragraph
        const currentParagraphs = [...(activeSlide.value.paragraphs || [])];
        currentParagraphs.splice(index, 1);
        updateField(props.activeIndex, 'paragraphs', currentParagraphs);
    }
};

const addParagraph = () => {
    const currentParagraphs = [...(activeSlide.value.paragraphs || [])];
    currentParagraphs.push('New paragraph');
    updateField(props.activeIndex, 'paragraphs', currentParagraphs);
};

const addParagraphToColumn = (column: 'left' | 'right') => {
    const currentParagraphs = [...(activeSlide.value.paragraphs || [])];
    const midpoint = Math.ceil(currentParagraphs.length / 2);
    if (column === 'left') {
        currentParagraphs.splice(midpoint, 0, 'New paragraph');
    } else {
        currentParagraphs.push('New paragraph');
    }
    updateField(props.activeIndex, 'paragraphs', currentParagraphs);
};

const addBulletToSlide = async () => {
    const next = cloneSlides(draftSlides.value);
    const currentSlide = next[props.activeIndex];
    const bullets = Array.isArray(currentSlide?.bullets) ? [...currentSlide.bullets] : [];
    bullets.push('New bullet point');

    // If the slide has paragraphs or is paragraphs type, convert to mixed
    const hasParagraphs = (currentSlide?.paragraphs?.length ?? 0) > 0;
    const isParagraphsType = currentSlide?.content_type === 'paragraphs';

    next[props.activeIndex] = {
        ...currentSlide,
        bullets,
        content_type: (hasParagraphs || isParagraphsType) ? 'mixed' : (currentSlide?.content_type || 'bullets'),
    };
    updateSlides(next);
    await nextTick();
    const selector = `[data-bullet-index="${bullets.length - 1}"]`;
    const target = document.querySelector(selector) as HTMLElement | null;
    target?.focus();
};

// Mixed content helpers - converts separate arrays to unified array for ordering
const getMixedContent = computed((): MixedContentItem[] => {
    const slide = activeSlide.value;
    if (slide.mixed_content?.length) {
        return slide.mixed_content;
    }
    // Build from separate arrays if mixed_content doesn't exist
    const items: MixedContentItem[] = [];
    (slide.paragraphs || []).forEach(text => items.push({ type: 'paragraph', text }));
    (slide.bullets || []).forEach(text => items.push({ type: 'bullet', text }));
    return items;
});

const saveMixedContent = (items: MixedContentItem[]) => {
    if (!items) return;
    const next = cloneSlides(draftSlides.value);
    next[props.activeIndex] = {
        ...next[props.activeIndex],
        mixed_content: items,
        content_type: items.length > 0 ? 'mixed' : 'bullets',
        // Keep separate arrays synced for backward compatibility (PPTX export, etc.)
        paragraphs: items.filter(i => i.type === 'paragraph').map(i => i.text),
        bullets: items.filter(i => i.type === 'bullet').map(i => i.text),
    };
    updateSlides(next);
};

const moveContentItem = (index: number, direction: 'up' | 'down') => {
    const items = [...getMixedContent.value];
    if (!items.length) return;
    const newIndex = direction === 'up' ? index - 1 : index + 1;
    if (newIndex < 0 || newIndex >= items.length) return;
    // Swap items
    [items[index], items[newIndex]] = [items[newIndex], items[index]];
    saveMixedContent(items);
};

const updateMixedContentItem = (index: number, text: string) => {
    const items = [...getMixedContent.value];
    if (items[index]) {
        items[index] = { ...items[index], text };
        saveMixedContent(items);
    }
};

const removeMixedContentItem = (index: number) => {
    const items = [...getMixedContent.value];
    items.splice(index, 1);
    if (items.length === 0) {
        // If all items removed, reset to non-mixed state with empty content
        const next = cloneSlides(draftSlides.value);
        next[props.activeIndex] = {
            ...next[props.activeIndex],
            mixed_content: [],
            content_type: 'bullets',
            paragraphs: [],
            bullets: [],
        };
        updateSlides(next);
        return;
    }
    saveMixedContent(items);
};

const addMixedContentItem = (type: 'paragraph' | 'bullet') => {
    const items = [...getMixedContent.value];
    items.push({ type, text: type === 'paragraph' ? 'New paragraph' : 'New bullet point' });
    saveMixedContent(items);
};

// Custom directive to set initial text content without reactive binding
// This prevents Vue from re-rendering contenteditable content during typing
const vInitText = {
    mounted(el: HTMLElement, binding: { value: string }) {
        el.innerText = binding.value || '';
    },
    updated(el: HTMLElement, binding: { value: string }) {
        // Only update when element is NOT focused (not being edited)
        if (document.activeElement !== el) {
            el.innerText = binding.value || '';
        }
    }
};

const handleMixedContentBlur = (index: number, event: FocusEvent) => {
    const target = event.target as HTMLElement;
    const newText = target.innerText.trim();
    updateMixedContentItem(index, newText);
};

const nextSlide = () => {
    if (props.activeIndex < draftSlides.value.length - 1) {
        emit('update:activeIndex', props.activeIndex + 1);
    }
};

const prevSlide = () => {
    if (props.activeIndex > 0) {
        emit('update:activeIndex', props.activeIndex - 1);
    }
};

const activeSlide = computed(() => {
    const fallback: DefenseDeckSlide = { title: '', bullets: [], speaker_notes: '', visuals: '', charts: [], tables: [] };
    if (!draftSlides.value || draftSlides.value.length === 0) return fallback;
    return draftSlides.value[props.activeIndex] || fallback;
});

const enterFullscreen = async () => {
    isPresenting.value = true;
    await nextTick();
    if (presentationContainer.value) {
        try {
            if (presentationContainer.value.requestFullscreen) {
                await presentationContainer.value.requestFullscreen();
            } else if ((presentationContainer.value as any).webkitRequestFullscreen) {
                await (presentationContainer.value as any).webkitRequestFullscreen();
            } else if ((presentationContainer.value as any).msRequestFullscreen) {
                await (presentationContainer.value as any).msRequestFullscreen();
            }
        } catch (err) {
            console.warn('Fullscreen request failed:', err);
        }
    }
};

const slideContentScale = ref(1);
const updateSlideScale = () => {
    if (!presentationSlideRef.value) return;
    const container = presentationSlideRef.value;
    const content = container.querySelector('.slide-content') as HTMLElement;
    if (!content) return;

    const containerHeight = container.clientHeight;
    // We target about 80% of the container height to leave some breathing room
    const targetHeight = containerHeight * 0.8;
    const contentHeight = content.scrollHeight;

    if (contentHeight > targetHeight) {
        slideContentScale.value = targetHeight / contentHeight;
    } else {
        slideContentScale.value = 1;
    }
};

watch([isPresenting, () => props.activeIndex], () => {
    if (isPresenting.value) {
        nextTick(updateSlideScale);
    }
});

const handleResize = () => {
    if (isPresenting.value) {
        updateSlideScale();
    }
};

const exitPresentation = () => {
    if (document.fullscreenElement) {
        document.exitFullscreen().catch(() => { });
    }
    isPresenting.value = false;
};

// Editor Preview Swipe Logic
const editorPreviewContainer = ref<HTMLElement | null>(null);
const { isSwiping, direction } = useSwipe(editorPreviewContainer);

watch(isSwiping, (swiping) => {
    if (!swiping && direction.value && !isPresenting.value) {
        if (direction.value === 'left') nextSlide();
        if (direction.value === 'right') prevSlide();
    }
});

// Presentation Swipe Logic
const { isSwiping: isPresentingSwiping, direction: presentingDirection } = useSwipe(presentationSlideRef);

watch(isPresentingSwiping, (swiping) => {
    if (!swiping && isPresenting.value && presentingDirection.value) {
        if (presentingDirection.value === 'left') nextSlide();
        if (presentingDirection.value === 'right') prevSlide();
    }
});

// Listen for fullscreen change to sync isPresenting state
onMounted(() => {
    const handleFullscreenChange = () => {
        if (!document.fullscreenElement && isPresenting.value) {
            isPresenting.value = false;
        }
    };
    document.addEventListener('fullscreenchange', handleFullscreenChange);
    window.addEventListener('resize', handleResize);
    onBeforeUnmount(() => {
        document.removeEventListener('fullscreenchange', handleFullscreenChange);
        window.removeEventListener('resize', handleResize);
    });
});

// Keyboard navigation
const handleKeydown = (e: KeyboardEvent) => {
    if (isPresenting.value) {
        if (e.key === 'ArrowRight' || e.key === ' ') nextSlide();
        if (e.key === 'ArrowLeft') prevSlide();
        if (e.key === 'Escape') exitPresentation(); // Use exitPresentation
    }
};

if (typeof window !== 'undefined') {
    window.addEventListener('keydown', handleKeydown);
    window.addEventListener('mousemove', moveImageDrag);
    window.addEventListener('mouseup', endImageDrag);
    window.addEventListener('touchmove', moveImageDrag);
    window.addEventListener('touchend', endImageDrag);
}

onBeforeUnmount(() => {
    if (typeof window === 'undefined') return;
    window.removeEventListener('keydown', handleKeydown);
    window.removeEventListener('mousemove', moveImageDrag);
    window.removeEventListener('mouseup', endImageDrag);
    window.removeEventListener('touchmove', moveImageDrag);
    window.removeEventListener('touchend', endImageDrag);
});
</script>

<template>
    <template v-if="draftSlides.length">
        <div class="flex flex-col h-full bg-zinc-950/20 overflow-hidden rounded-2xl border border-white/5">
            <!-- Main Toolbar (Re-designed for clarity) -->
            <div v-if="!compact"
                class="flex items-center justify-between px-3 py-2 bg-zinc-900/80 border-b border-white/5 shrink-0 gap-4">
                <div class="flex items-center gap-3 shrink-0">
                    <div class="p-1.5 bg-indigo-500/10 rounded-lg hidden xs:flex">
                        <Presentation class="h-4 w-4 text-indigo-400" />
                    </div>
                    <div class="flex items-center gap-2">
                        <h3 class="text-[11px] font-bold text-white whitespace-nowrap">Slide Editor</h3>
                        <Badge variant="outline"
                            class="text-[9px] px-1.5 h-4 bg-white/5 border-white/10 text-zinc-400 whitespace-nowrap">
                            {{ props.activeIndex + 1 }} / {{ draftSlides.length }}
                        </Badge>
                        <div v-if="props.isSaving" class="h-1 w-1 rounded-full bg-indigo-500 animate-pulse"></div>
                    </div>
                </div>

                <div class="flex items-center gap-2 overflow-x-auto no-scrollbar py-0.5">
                    <!-- Slide Creator -->
                    <div class="flex items-center gap-1 bg-white/5 pl-2 pr-1 py-0.5 rounded-md border border-white/10">
                        <select
                            class="bg-transparent border-none text-[10px] text-zinc-400 focus:ring-0 outline-none w-20 cursor-pointer h-6"
                            v-model="selectedTemplate">
                            <option value="title">Title</option>
                            <option value="problem">Problem</option>
                            <option value="objectives">Objectives</option>
                            <option value="methodology">Methodology</option>
                            <option value="results">Results</option>
                            <option value="conclusion">Conclusion</option>
                        </select>
                        <Button variant="ghost" size="icon" class="h-6 w-6 text-indigo-400 hover:text-white"
                            @click="addSlideFromTemplate" title="Add Slide">
                            <Plus class="h-3.5 w-3.5" />
                        </Button>
                    </div>

                    <div class="h-4 w-[1px] bg-white/10 shrink-0"></div>

                    <!-- Layout Buttons -->
                    <div class="flex items-center gap-1 bg-white/5 rounded-md border border-white/10 p-0.5">
                        <Button variant="ghost" size="icon"
                            class="h-6 w-6 transition-colors"
                            :class="(activeSlide.layout || 'bullets') === 'title' ? 'bg-indigo-500/20 text-indigo-400' : 'text-zinc-400 hover:text-white'"
                            @click="updateField(props.activeIndex, 'layout', 'title')" title="Title Layout">
                            <Type class="h-3.5 w-3.5" />
                        </Button>
                        <Button variant="ghost" size="icon"
                            class="h-6 w-6 transition-colors"
                            :class="(activeSlide.layout || 'bullets') === 'bullets' ? 'bg-indigo-500/20 text-indigo-400' : 'text-zinc-400 hover:text-white'"
                            @click="updateField(props.activeIndex, 'layout', 'bullets')" title="Bullets">
                            <List class="h-3.5 w-3.5" />
                        </Button>
                        <Button variant="ghost" size="icon"
                            class="h-6 w-6 transition-colors"
                            :class="(activeSlide.layout || 'bullets') === 'two_column' ? 'bg-indigo-500/20 text-indigo-400' : 'text-zinc-400 hover:text-white'"
                            @click="updateField(props.activeIndex, 'layout', 'two_column')" title="Two Columns">
                            <Columns2 class="h-3.5 w-3.5" />
                        </Button>
                        <Button variant="ghost" size="icon"
                            class="h-6 w-6 transition-colors"
                            :class="(activeSlide.layout || 'bullets') === 'image_left' ? 'bg-indigo-500/20 text-indigo-400' : 'text-zinc-400 hover:text-white'"
                            @click="updateField(props.activeIndex, 'layout', 'image_left')" title="Image Left">
                            <PanelLeft class="h-3.5 w-3.5" />
                        </Button>
                        <Button variant="ghost" size="icon"
                            class="h-6 w-6 transition-colors"
                            :class="(activeSlide.layout || 'bullets') === 'image_right' ? 'bg-indigo-500/20 text-indigo-400' : 'text-zinc-400 hover:text-white'"
                            @click="updateField(props.activeIndex, 'layout', 'image_right')" title="Image Right">
                            <PanelRight class="h-3.5 w-3.5" />
                        </Button>
                    </div>

                    <!-- Text Formatting -->
                    <div class="hidden md:flex items-center gap-1 bg-white/5 rounded-md border border-white/10 p-0.5">
                        <Button variant="ghost" size="icon" class="h-6 w-6 text-zinc-400 hover:text-white"
                            @click="applyFormat('bold')" title="Bold"><span
                                class="font-bold text-[10px]">B</span></Button>
                        <Button variant="ghost" size="icon" class="h-6 w-6 text-zinc-400 hover:text-white"
                            @click="applyFormat('italic')" title="Italic"><span
                                class="italic text-[10px]">I</span></Button>
                        <Button variant="ghost" size="icon" class="h-6 w-6 text-zinc-400 hover:text-white"
                            @click="addMixedContentItem('paragraph')" title="Add Paragraph">
                            <FileText class="h-3.5 w-3.5" />
                        </Button>
                        <Button variant="ghost" size="icon" class="h-6 w-6 text-zinc-400 hover:text-white"
                            @click="addMixedContentItem('bullet')" title="Add Bullet">
                            <Plus class="h-3 w-3" />
                        </Button>
                        <Button variant="ghost" size="icon" class="h-6 w-6 text-zinc-400 hover:text-white"
                            title="Upload Image" :disabled="isImageUploading" @click="openImagePicker">
                            <ImageIcon class="h-3.5 w-3.5" />
                        </Button>
                    </div>

                    <div class="h-4 w-[1px] bg-white/10 shrink-0"></div>

                    <!-- Slide Actions -->
                    <div class="flex items-center gap-1">
                        <Button variant="ghost" size="icon" class="h-7 w-7 text-zinc-500 hover:text-white"
                            @click="duplicateSlide(props.activeIndex)" title="Duplicate">
                            <Copy class="h-3.5 w-3.5" />
                        </Button>
                        <Button variant="ghost" size="icon" class="h-7 w-7 text-zinc-500 hover:text-rose-400"
                            @click="requestDeleteSlide(props.activeIndex)" title="Delete">
                            <Trash2 class="h-3.5 w-3.5" />
                        </Button>
                    </div>

                    <div class="h-4 w-[1px] bg-white/10 shrink-0"></div>

                    <!-- Mode Toggle -->
                    <div class="flex items-center gap-2 shrink-0">
                        <Button variant="secondary" size="sm" class="h-7 text-[10px] px-2.5 gap-1.5 font-bold"
                            @click="enterFullscreen">
                            <Play class="h-3 w-3 fill-current" /> <span class="hidden xs:inline">PRESENT</span>
                        </Button>
                        <div class="flex items-center">
                            <Button v-if="props.showPptx" variant="ghost" size="icon"
                                class="h-7 w-7 text-zinc-500 hover:text-white" :disabled="props.pptxBusy"
                                @click="props.pptxUrl ? emit('download-pptx') : emit('export-pptx')"
                                :title="props.pptxUrl ? 'Download PPTX' : 'Export PPTX'">
                                <FileText class="h-3.5 w-3.5" />
                            </Button>
                            <Button v-if="compact" variant="ghost" size="icon"
                                class="h-7 w-7 text-zinc-500 hover:text-white" @click="emit('toggle-expand')"
                                title="Expand">
                                <Maximize2 class="h-3.5 w-3.5" />
                            </Button>
                        </div>
                    </div>
                </div>
            </div>


            <input ref="imageUploadInput" type="file" class="hidden" accept="image/*" @change="handleImageSelect" />
            <div v-if="imageUploadError && !compact"
                class="px-4 py-2 text-xs text-rose-300 bg-rose-500/10 border-b border-rose-500/20">
                {{ imageUploadError }}
            </div>

            <div class="flex-1 flex overflow-hidden min-h-0 relative group">
                <!-- Sidebar Navigator (Visible only in non-compact mode) -->
                <div v-if="!compact" ref="sidebarRef"
                    class="w-64 bg-zinc-900/60 border-r border-white/5 flex flex-col h-full overflow-hidden transition-all duration-300">
                    <div class="px-4 py-3 border-b border-white/5 flex items-center justify-between">
                        <span class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest">Navigator</span>
                        <Badge variant="outline" class="text-[9px] bg-white/5 border-white/10">{{ draftSlides.length }}
                            Slides</Badge>
                    </div>
                    <div class="flex-1 overflow-y-auto custom-scrollbar p-3 space-y-3">
                        <div v-for="(slide, index) in draftSlides" :key="index" draggable="true"
                            @dragstart="handleDragStart($event, index)" @dragover.prevent="handleDragOver(index)"
                            @dragleave="handleDragLeave(index)" @drop="handleDrop(index)" @dragend="handleDragEnd"
                            @click="emit('update:activeIndex', index)"
                            class="group/thumb relative aspect-video bg-zinc-950 rounded-lg border cursor-pointer transition-all duration-300 overflow-hidden"
                            :class="[
                                props.activeIndex === index
                                    ? 'border-indigo-500 ring-2 ring-indigo-500/20 shadow-[0_0_15px_rgba(99,102,241,0.2)]'
                                    : 'border-white/5 hover:border-white/20',
                                dragOverIndex === index ? 'translate-y-2' : ''
                            ]">
                            <!-- Slide Number -->
                            <div
                                class="absolute top-2 left-2 z-10 h-5 w-5 rounded bg-black/60 backdrop-blur-md flex items-center justify-center border border-white/10">
                                <span class="text-[10px] font-bold"
                                    :class="props.activeIndex === index ? 'text-indigo-400' : 'text-zinc-500'">{{ index
                                        + 1 }}</span>
                            </div>

                            <!-- Content Preview -->
                            <div
                                class="p-3 h-full flex flex-col gap-1.5 opacity-60 group-hover/thumb:opacity-100 transition-opacity">
                                <div class="h-1.5 w-1/2 bg-white/20 rounded"></div>
                                <div class="h-1 w-3/4 bg-white/5 rounded"></div>
                                <div class="h-1 w-2/3 bg-white/5 rounded"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Navigation Controls Overlay (Always visible or on hover) -->
                <div class="absolute inset-y-0 left-0 w-12 flex items-center justify-center z-20 pointer-events-none">
                    <Button variant="ghost" size="icon"
                        class="h-10 w-10 rounded-full bg-black/40 border border-white/10 text-white backdrop-blur-md opacity-0 group-hover:opacity-100 transition-all pointer-events-auto -translate-x-4 group-hover:translate-x-2"
                        :disabled="props.activeIndex === 0" @click="prevSlide">
                        <ChevronLeft class="h-6 w-6" />
                    </Button>
                </div>
                <div class="absolute inset-y-0 right-0 w-12 flex items-center justify-center z-20 pointer-events-none">
                    <Button variant="ghost" size="icon"
                        class="h-10 w-10 rounded-full bg-black/40 border border-white/10 text-white backdrop-blur-md opacity-0 group-hover:opacity-100 transition-all pointer-events-auto translate-x-4 group-hover:-translate-x-2"
                        :disabled="props.activeIndex === draftSlides.length - 1" @click="nextSlide">
                        <ChevronRight class="h-6 w-6" />
                    </Button>
                </div>

                <!-- Center: Slide Preview -->
                <div class="flex-1 flex flex-col min-w-0 overflow-hidden relative"
                    :class="compact ? 'bg-zinc-950/60' : 'bg-zinc-950/40'">

                    <template v-if="compact">
                        <!-- Compact Presentation Guide Layout -->
                        <div class="flex-1 flex flex-col overflow-y-auto custom-scrollbar p-5 space-y-6"
                            ref="editorPreviewContainer">
                            <!-- Progress & Sub-Navigation -->
                            <div class="space-y-3 shrink-0">
                                <div class="h-1 w-full bg-white/5 rounded-full overflow-hidden">
                                    <div class="h-full bg-indigo-500 shadow-[0_0_10px_rgba(99,102,241,0.4)] transition-all duration-700 ease-out"
                                        :style="{ width: `${((props.activeIndex + 1) / draftSlides.length) * 100}%` }">
                                    </div>
                                </div>
                                <div class="flex flex-col items-center gap-1.5 px-1">
                                    <div class="flex items-center gap-6">
                                        <button @click="prevSlide" :disabled="props.activeIndex === 0"
                                            class="p-1 text-zinc-500 hover:text-white disabled:opacity-10 transition-colors">
                                            <ChevronLeft class="h-5 w-5" />
                                        </button>
                                        <div class="text-[9px] font-bold text-indigo-500/80 uppercase tracking-[0.2em]">
                                            Slide {{ props.activeIndex + 1 }} of {{ draftSlides.length }}
                                        </div>
                                        <button @click="nextSlide"
                                            :disabled="props.activeIndex === draftSlides.length - 1"
                                            class="p-1 text-zinc-500 hover:text-white disabled:opacity-10 transition-colors">
                                            <ChevronRight class="h-5 w-5" />
                                        </button>
                                    </div>
                                    <div class="text-sm font-display font-bold text-white tracking-tight text-center">
                                        {{ activeSlide.title }}
                                    </div>
                                </div>
                            </div>

                            <div class="h-[1px] bg-white/5 w-full"></div>

                            <!-- Content Sections -->
                            <div class="space-y-5">
                                <!-- Visuals Guide -->
                                <div v-if="activeSlide.visuals"
                                    class="p-4 bg-indigo-500/5 border border-indigo-500/10 rounded-2xl space-y-2.5 animate-in fade-in slide-in-from-bottom-2 duration-500">
                                    <div class="flex items-center gap-2 text-indigo-400">
                                        <ImageIcon class="h-3.5 w-3.5" />
                                        <span class="text-[10px] font-bold uppercase tracking-widest">Visual
                                            Strategy</span>
                                    </div>
                                    <p class="text-xs text-zinc-300 leading-relaxed font-medium italic">"{{
                                        activeSlide.visuals }}"</p>
                                </div>

                                <!-- Key Points -->
                                <div class="space-y-4">
                                    <div v-if="activeSlide.bullets && activeSlide.bullets.length" class="space-y-4">
                                        <div v-for="(bullet, idx) in activeSlide.bullets" :key="idx"
                                            class="flex gap-4 group animate-in fade-in slide-in-from-left-2 duration-500"
                                            :style="{ animationDelay: `${idx * 100}ms` }">
                                            <div
                                                class="mt-1.5 h-1.5 w-1.5 rounded-full bg-indigo-500 shrink-0 shadow-[0_0_8px_rgba(99,102,241,0.6)]">
                                            </div>
                                            <p class="text-xs lg:text-sm text-zinc-200 leading-relaxed font-light"
                                                v-html="renderBullet(bullet)"></p>
                                        </div>
                                    </div>

                                    <!-- Alternative content types for guide -->
                                    <div v-else-if="activeSlide.paragraphs && activeSlide.paragraphs.length"
                                        class="space-y-4">
                                        <p v-for="(para, idx) in activeSlide.paragraphs" :key="idx"
                                            class="text-xs text-zinc-300 leading-relaxed border-l-2 border-white/5 pl-4 py-1">
                                            {{ para }}
                                        </p>
                                    </div>
                                </div>

                                <!-- Speaker Notes (Bonus for Guide) -->
                                <div v-if="activeSlide.speaker_notes" class="mt-8 pt-6 border-t border-white/5">
                                    <div class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest mb-3">
                                        Speaker Notes</div>
                                    <p class="text-[11px] text-zinc-400 leading-relaxed italic">{{
                                        activeSlide.speaker_notes }}</p>
                                </div>
                            </div>
                        </div>
                    </template>

                    <template v-else>
                        <!-- Standard Slide Preview Canvas (Premium Presentation Style) -->
                        <div class="flex-1 flex flex-col items-center justify-center overflow-auto custom-scrollbar relative bg-black/20 p-6"
                            ref="editorPreviewContainer">

                            <div
                                class="w-full max-w-6xl aspect-video bg-zinc-900 rounded-lg shadow-2xl border border-white/10 relative overflow-hidden flex flex-col group/canvas transition-all duration-500">
                                <!-- Premium Background Effects -->
                                <div
                                    class="absolute inset-0 bg-gradient-to-br from-indigo-500/5 via-transparent to-purple-500/5 pointer-events-none">
                                </div>
                                <div class="relative z-10 p-4 lg:p-8 flex flex-col h-full">
                                    <div class="mb-2">
                                        <span
                                            class="text-[8px] lg:text-[10px] uppercase tracking-[0.2em] font-bold text-indigo-500/60 mb-1 block">Premium
                                            Deck</span>
                                        <h1 class="text-sm lg:text-2xl font-display font-bold text-white tracking-tight leading-tight line-clamp-2 focus:outline-none inline-edit"
                                            contenteditable="true" data-placeholder="Slide Title"
                                            @input="handleTitleInput" @blur="handleTitleBlur" @keydown.enter.prevent>
                                            {{ activeSlide.title }}
                                        </h1>
                                    </div>

                                    <div class="flex-1 mt-2 lg:mt-4 overflow-hidden">
                                        <template v-if="(activeSlide.layout || 'bullets') === 'title'">
                                            <div
                                                class="h-full flex items-center justify-center border-2 border-dashed border-white/5 rounded-lg">
                                                <p class="text-zinc-600 text-xs italic">Title-only layout.</p>
                                            </div>
                                        </template>
                                        <template v-else-if="(activeSlide.layout || 'bullets') === 'two_column'">
                                            <div class="grid grid-cols-2 gap-6 h-full">
                                                <!-- Left Column -->
                                                <div class="space-y-3 overflow-auto">
                                                    <!-- Paragraphs in left column -->
                                                    <template v-if="activeSlide.paragraphs && activeSlide.paragraphs.length">
                                                        <div class="space-y-3">
                                                            <p v-for="item in splitParagraphsWithIndex(activeSlide.paragraphs || [])[0]" :key="'p-' + item.index"
                                                                class="text-sm lg:text-base text-zinc-300 leading-relaxed focus:outline-none inline-edit cursor-text"
                                                                contenteditable="true"
                                                                :data-paragraph-index="item.index"
                                                                data-placeholder="Paragraph"
                                                                @input="handleParagraphInput(item.index, $event)"
                                                                @blur="handleParagraphBlur(item.index, $event)">
                                                                {{ item.text }}
                                                            </p>
                                                            <!-- Bullets in left column (first half) -->
                                                            <ul v-if="activeSlide.bullets && activeSlide.bullets.length"
                                                                class="space-y-2 lg:space-y-3 mt-2">
                                                                <li v-for="item in splitBulletsWithIndex(activeSlide.bullets || [])[0]" :key="'bl-' + item.index"
                                                                    class="flex gap-3 text-zinc-300">
                                                                    <span class="text-indigo-500 font-bold">•</span>
                                                                    <span class="text-sm lg:text-base leading-relaxed focus:outline-none inline-edit"
                                                                        contenteditable="true" :data-bullet-index="item.index"
                                                                        data-placeholder="Bullet"
                                                                        @input="handleBulletInput(item.index, $event)"
                                                                        @keydown="handleBulletKeydown(item.index, $event)"
                                                                        @blur="handleBulletBlur(item.index, $event)"
                                                                        v-html="renderBullet(item.text)"></span>
                                                                </li>
                                                            </ul>
                                                        </div>
                                                        <div class="flex gap-2">
                                                            <button @click="addParagraphToColumn('left')"
                                                                class="flex-1 py-2 border border-dashed border-white/10 rounded text-zinc-600 text-xs hover:border-indigo-500/50 hover:text-indigo-400 transition-colors">
                                                                + Paragraph
                                                            </button>
                                                            <button @click="addBulletToSlide"
                                                                class="flex-1 py-2 border border-dashed border-white/10 rounded text-zinc-600 text-xs hover:border-indigo-500/50 hover:text-indigo-400 transition-colors">
                                                                + Bullet
                                                            </button>
                                                        </div>
                                                    </template>
                                                    <!-- Headings in left column -->
                                                    <template v-else-if="activeSlide.headings && activeSlide.headings.length">
                                                        <div v-for="(h, idx) in activeSlide.headings.slice(0, Math.ceil(activeSlide.headings.length / 2))" :key="'h-' + idx" class="space-y-1">
                                                            <h3 class="text-sm lg:text-base font-semibold text-indigo-400">{{ h.heading }}</h3>
                                                            <p class="text-sm lg:text-base text-zinc-300 leading-relaxed">{{ h.content }}</p>
                                                        </div>
                                                    </template>
                                                    <!-- Bullets left column -->
                                                    <template v-else-if="activeSlide.bullets && activeSlide.bullets.length">
                                                        <ul class="space-y-2 lg:space-y-3">
                                                            <li v-for="item in splitBulletsWithIndex(activeSlide.bullets || [])[0]" :key="item.index"
                                                                class="flex gap-3 text-zinc-300">
                                                                <span class="text-indigo-500 font-bold">•</span>
                                                                <span class="text-sm lg:text-base leading-relaxed focus:outline-none inline-edit"
                                                                    contenteditable="true" :data-bullet-index="item.index"
                                                                    data-placeholder="Bullet"
                                                                    @input="handleBulletInput(item.index, $event)"
                                                                    @keydown="handleBulletKeydown(item.index, $event)"
                                                                    @blur="handleBulletBlur(item.index, $event)"
                                                                    v-html="renderBullet(item.text)"></span>
                                                            </li>
                                                        </ul>
                                                    </template>
                                                    <button v-else @click="addParagraphToColumn('left')"
                                                        class="h-full w-full flex items-center justify-center border-2 border-dashed border-white/5 rounded-lg hover:border-indigo-500/30 transition-colors cursor-pointer">
                                                        <p class="text-zinc-600 text-xs italic hover:text-indigo-400">+ Add content</p>
                                                    </button>
                                                </div>
                                                <!-- Right Column -->
                                                <div class="space-y-3 overflow-auto">
                                                    <!-- Paragraphs right column (second half) -->
                                                    <template v-if="activeSlide.paragraphs && activeSlide.paragraphs.length > 1">
                                                        <div class="space-y-3">
                                                            <p v-for="item in splitParagraphsWithIndex(activeSlide.paragraphs || [])[1]" :key="'pr-' + item.index"
                                                                class="text-sm lg:text-base text-zinc-300 leading-relaxed focus:outline-none inline-edit cursor-text"
                                                                contenteditable="true"
                                                                :data-paragraph-index="item.index"
                                                                data-placeholder="Paragraph"
                                                                @input="handleParagraphInput(item.index, $event)"
                                                                @blur="handleParagraphBlur(item.index, $event)">
                                                                {{ item.text }}
                                                            </p>
                                                            <!-- Bullets in right column (second half) -->
                                                            <ul v-if="activeSlide.bullets && activeSlide.bullets.length > 1"
                                                                class="space-y-2 lg:space-y-3 mt-2">
                                                                <li v-for="item in splitBulletsWithIndex(activeSlide.bullets || [])[1]" :key="'br-' + item.index"
                                                                    class="flex gap-3 text-zinc-300">
                                                                    <span class="text-indigo-500 font-bold">•</span>
                                                                    <span class="text-sm lg:text-base leading-relaxed focus:outline-none inline-edit"
                                                                        contenteditable="true" :data-bullet-index="item.index"
                                                                        data-placeholder="Bullet"
                                                                        @input="handleBulletInput(item.index, $event)"
                                                                        @keydown="handleBulletKeydown(item.index, $event)"
                                                                        @blur="handleBulletBlur(item.index, $event)"
                                                                        v-html="renderBullet(item.text)"></span>
                                                                </li>
                                                            </ul>
                                                        </div>
                                                        <div class="flex gap-2">
                                                            <button @click="addParagraphToColumn('right')"
                                                                class="flex-1 py-2 border border-dashed border-white/10 rounded text-zinc-600 text-xs hover:border-indigo-500/50 hover:text-indigo-400 transition-colors">
                                                                + Paragraph
                                                            </button>
                                                            <button @click="addBulletToSlide"
                                                                class="flex-1 py-2 border border-dashed border-white/10 rounded text-zinc-600 text-xs hover:border-indigo-500/50 hover:text-indigo-400 transition-colors">
                                                                + Bullet
                                                            </button>
                                                        </div>
                                                    </template>
                                                    <!-- Headings right column (second half) -->
                                                    <template v-else-if="activeSlide.headings && activeSlide.headings.length > 1">
                                                        <div v-for="(h, idx) in activeSlide.headings.slice(Math.ceil(activeSlide.headings.length / 2))" :key="'hr-' + idx" class="space-y-1">
                                                            <h3 class="text-sm lg:text-base font-semibold text-indigo-400">{{ h.heading }}</h3>
                                                            <p class="text-sm lg:text-base text-zinc-300 leading-relaxed">{{ h.content }}</p>
                                                        </div>
                                                    </template>
                                                    <!-- Bullets right column -->
                                                    <template v-else-if="activeSlide.bullets && activeSlide.bullets.length > 1">
                                                        <ul class="space-y-2 lg:space-y-3">
                                                            <li v-for="item in splitBulletsWithIndex(activeSlide.bullets || [])[1]" :key="item.index"
                                                                class="flex gap-3 text-zinc-300">
                                                                <span class="text-indigo-500 font-bold">•</span>
                                                                <span class="text-sm lg:text-base leading-relaxed focus:outline-none inline-edit"
                                                                    contenteditable="true" :data-bullet-index="item.index"
                                                                    data-placeholder="Bullet"
                                                                    @input="handleBulletInput(item.index, $event)"
                                                                    @keydown="handleBulletKeydown(item.index, $event)"
                                                                    @blur="handleBulletBlur(item.index, $event)"
                                                                    v-html="renderBullet(item.text)"></span>
                                                            </li>
                                                        </ul>
                                                    </template>
                                                    <button v-else @click="addParagraphToColumn('right')"
                                                        class="h-full w-full flex items-center justify-center border-2 border-dashed border-white/5 rounded-lg hover:border-indigo-500/30 transition-colors cursor-pointer">
                                                        <p class="text-zinc-600 text-xs italic hover:text-indigo-400">+ Add content</p>
                                                    </button>
                                                </div>
                                            </div>
                                        </template>
                                        <template
                                            v-else-if="(activeSlide.layout || 'bullets') === 'image_left' || (activeSlide.layout || 'bullets') === 'image_right'">
                                            <div class="flex gap-6 h-full">
                                                <div class="w-1/2 rounded-lg border border-white/10 bg-white/5 flex items-center justify-center relative overflow-hidden"
                                                    :class="(activeSlide.layout || 'bullets') === 'image_left' ? 'order-1' : 'order-2'"
                                                    @mousedown.prevent="startImageDrag"
                                                    @touchstart.prevent="startImageDrag">
                                                    <button v-if="!activeSlide.image_url" type="button"
                                                        class="absolute inset-0 z-10" @click="openImagePicker"></button>
                                                    <img v-if="activeSlide.image_url" :src="activeSlide.image_url"
                                                        alt="Slide visual" class="h-full w-full cursor-grab" :style="{
                                                            objectFit: activeSlide.image_fit || 'cover',
                                                            objectPosition: `${activeSlide.image_position_x ?? 50}% ${activeSlide.image_position_y ?? 50}%`,
                                                            transform: `scale(${activeSlide.image_scale || 1})`
                                                        }" />
                                                    <span v-else
                                                        class="text-zinc-500 text-xs uppercase tracking-widest">Click
                                                        to
                                                        insert image</span>
                                                    <div v-if="activeSlide.image_url"
                                                        class="absolute bottom-2 left-2 right-2 z-20 bg-black/60 border border-white/10 rounded-md p-2 flex items-center gap-2">
                                                        <Button variant="ghost" size="sm"
                                                            class="h-6 text-[10px] gap-1 text-zinc-200 hover:bg-white/10"
                                                            @click.stop="replaceSlideImage">
                                                            Replace
                                                        </Button>
                                                        <Button variant="ghost" size="sm"
                                                            class="h-6 text-[10px] gap-1 text-rose-200 hover:bg-white/10"
                                                            @click.stop="removeSlideImage">
                                                            Remove
                                                        </Button>
                                                        <select
                                                            class="ml-auto h-6 bg-zinc-950/60 border border-white/10 rounded-md px-1 text-[10px] text-zinc-200 focus:ring-1 focus:ring-indigo-500 outline-none"
                                                            :value="activeSlide.image_fit || 'cover'"
                                                            @change="updateImageFit">
                                                            <option value="cover">Cover</option>
                                                            <option value="contain">Contain</option>
                                                        </select>
                                                        <input type="range" min="0.5" max="2" step="0.05" class="w-20"
                                                            :value="activeSlide.image_scale || 1"
                                                            @input="updateImageScale" />
                                                    </div>
                                                </div>
                                                <div class="flex-1 overflow-auto flex flex-col"
                                                    :class="(activeSlide.layout || 'bullets') === 'image_left' ? 'order-2' : 'order-1'">
                                                    <!-- Mixed content with reorderable items -->
                                                    <template v-if="(activeSlide.paragraphs?.length && activeSlide.bullets?.length) || activeSlide.mixed_content?.length">
                                                        <div class="space-y-2 flex-1">
                                                            <div v-for="(item, idx) in getMixedContent" :key="'mixed-' + idx"
                                                                class="group/item flex items-start gap-2 relative">
                                                                <!-- Move buttons -->
                                                                <div class="flex flex-col gap-0.5 opacity-0 group-hover/item:opacity-100 transition-opacity shrink-0">
                                                                    <button @click="moveContentItem(idx, 'up')" :disabled="idx === 0"
                                                                        class="p-0.5 text-zinc-500 hover:text-indigo-400 disabled:opacity-20 disabled:cursor-not-allowed">
                                                                        <ChevronUp class="h-3 w-3" />
                                                                    </button>
                                                                    <button @click="moveContentItem(idx, 'down')" :disabled="idx === getMixedContent.length - 1"
                                                                        class="p-0.5 text-zinc-500 hover:text-indigo-400 disabled:opacity-20 disabled:cursor-not-allowed">
                                                                        <ChevronDown class="h-3 w-3" />
                                                                    </button>
                                                                </div>
                                                                <!-- Content item -->
                                                                <div class="flex-1 flex items-start gap-2">
                                                                    <span v-if="item.type === 'bullet'" class="text-indigo-500 font-bold mt-0.5">•</span>
                                                                    <span
                                                                        class="flex-1 text-sm lg:text-base leading-relaxed focus:outline-none inline-edit cursor-text text-zinc-300"
                                                                        contenteditable="true"
                                                                        :data-mixed-index="idx"
                                                                        v-init-text="item.text"
                                                                        @blur="handleMixedContentBlur(idx, $event)"></span>
                                                                </div>
                                                                <!-- Delete button -->
                                                                <button @click="removeMixedContentItem(idx)"
                                                                    class="p-1 text-zinc-600 hover:text-rose-400 opacity-0 group-hover/item:opacity-100 transition-opacity shrink-0">
                                                                    <Trash2 class="h-3 w-3" />
                                                                </button>
                                                            </div>
                                                            <div class="flex gap-2 mt-3">
                                                                <button @click="addMixedContentItem('paragraph')"
                                                                    class="flex-1 py-2 border border-dashed border-white/10 rounded text-zinc-600 text-xs hover:border-indigo-500/50 hover:text-indigo-400 transition-colors">
                                                                    + Paragraph
                                                                </button>
                                                                <button @click="addMixedContentItem('bullet')"
                                                                    class="flex-1 py-2 border border-dashed border-white/10 rounded text-zinc-600 text-xs hover:border-indigo-500/50 hover:text-indigo-400 transition-colors">
                                                                    + Bullet
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </template>
                                                    <!-- Paragraphs only (no bullets yet) -->
                                                    <template v-else-if="activeSlide.paragraphs && activeSlide.paragraphs.length">
                                                        <div class="space-y-3">
                                                            <p v-for="(para, idx) in activeSlide.paragraphs" :key="'p-' + idx"
                                                                class="text-sm lg:text-base text-zinc-300 leading-relaxed focus:outline-none inline-edit cursor-text"
                                                                contenteditable="true"
                                                                :data-paragraph-index="idx"
                                                                data-placeholder="Paragraph"
                                                                @input="handleParagraphInput(idx, $event)"
                                                                @blur="handleParagraphBlur(idx, $event)">
                                                                {{ para }}
                                                            </p>
                                                            <div class="flex gap-2">
                                                                <button @click="addParagraph"
                                                                    class="flex-1 py-2 border border-dashed border-white/10 rounded text-zinc-600 text-xs hover:border-indigo-500/50 hover:text-indigo-400 transition-colors">
                                                                    + Paragraph
                                                                </button>
                                                                <button @click="addBulletToSlide"
                                                                    class="flex-1 py-2 border border-dashed border-white/10 rounded text-zinc-600 text-xs hover:border-indigo-500/50 hover:text-indigo-400 transition-colors">
                                                                    + Bullet
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </template>
                                                    <!-- Headings content -->
                                                    <template v-else-if="activeSlide.headings && activeSlide.headings.length">
                                                        <div class="space-y-3">
                                                            <div v-for="(h, idx) in activeSlide.headings" :key="'h-' + idx" class="space-y-1">
                                                                <h3 class="text-sm lg:text-base font-semibold text-indigo-400">{{ h.heading }}</h3>
                                                                <p class="text-sm lg:text-base text-zinc-300 leading-relaxed">{{ h.content }}</p>
                                                            </div>
                                                            <button @click="addBulletToSlide"
                                                                class="w-full py-2 border border-dashed border-white/10 rounded text-zinc-600 text-xs hover:border-indigo-500/50 hover:text-indigo-400 transition-colors">
                                                                + Add bullet
                                                            </button>
                                                        </div>
                                                    </template>
                                                    <!-- Bullets content -->
                                                    <template v-else-if="activeSlide.bullets && activeSlide.bullets.length">
                                                        <ul class="space-y-2 lg:space-y-4">
                                                            <li v-for="(bullet, idx) in activeSlide.bullets" :key="idx"
                                                                class="flex gap-3 text-zinc-300">
                                                                <span class="text-indigo-500 font-bold">•</span>
                                                                <span
                                                                    class="text-sm lg:text-base leading-relaxed focus:outline-none inline-edit"
                                                                    contenteditable="true" :data-bullet-index="idx"
                                                                    data-placeholder="Bullet"
                                                                    @input="handleBulletInput(idx, $event)"
                                                                    @keydown="handleBulletKeydown(idx, $event)"
                                                                    @blur="handleBulletBlur(idx, $event)"
                                                                    v-html="renderBullet(bullet)"></span>
                                                            </li>
                                                        </ul>
                                                    </template>
                                                    <div v-else class="flex flex-col h-full">
                                                        <div class="flex-1 flex items-center justify-center border-2 border-dashed border-white/5 rounded-lg">
                                                            <p class="text-zinc-600 text-xs italic">No content yet</p>
                                                        </div>
                                                        <div class="flex gap-2 mt-3">
                                                            <button @click="addMixedContentItem('paragraph')"
                                                                class="flex-1 py-2 border border-dashed border-white/10 rounded text-zinc-600 text-xs hover:border-indigo-500/50 hover:text-indigo-400 transition-colors">
                                                                + Paragraph
                                                            </button>
                                                            <button @click="addMixedContentItem('bullet')"
                                                                class="flex-1 py-2 border border-dashed border-white/10 rounded text-zinc-600 text-xs hover:border-indigo-500/50 hover:text-indigo-400 transition-colors">
                                                                + Bullet
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>
                                        <template v-else>
                                            <!-- Paragraphs content type -->
                                            <template v-if="activeSlide.content_type === 'paragraphs'">
                                                <div v-if="activeSlide.paragraphs && activeSlide.paragraphs.length"
                                                    class="space-y-3 lg:space-y-4">
                                                    <p v-for="(para, idx) in activeSlide.paragraphs" :key="idx"
                                                        class="text-sm lg:text-base text-zinc-300 leading-relaxed focus:outline-none inline-edit cursor-text"
                                                        contenteditable="true"
                                                        :data-paragraph-index="idx"
                                                        data-placeholder="Paragraph"
                                                        @input="handleParagraphInput(idx, $event)"
                                                        @blur="handleParagraphBlur(idx, $event)">
                                                        {{ para }}
                                                    </p>
                                                    <div class="flex gap-2">
                                                        <button @click="addParagraph"
                                                            class="flex-1 py-2 border border-dashed border-white/10 rounded text-zinc-600 text-xs hover:border-indigo-500/50 hover:text-indigo-400 transition-colors">
                                                            + Add paragraph
                                                        </button>
                                                        <button @click="addBulletToSlide"
                                                            class="flex-1 py-2 border border-dashed border-white/10 rounded text-zinc-600 text-xs hover:border-indigo-500/50 hover:text-indigo-400 transition-colors">
                                                            + Add bullet
                                                        </button>
                                                    </div>
                                                </div>
                                                <div v-else-if="activeSlide.headings && activeSlide.headings.length"
                                                    class="space-y-4">
                                                    <div v-for="(h, idx) in activeSlide.headings" :key="idx"
                                                        class="space-y-1.5">
                                                        <h3 class="text-sm lg:text-base font-semibold text-indigo-400">
                                                            {{ h.heading }}</h3>
                                                        <p class="text-sm lg:text-base text-zinc-300 leading-relaxed">
                                                            {{ h.content }}</p>
                                                    </div>
                                                    <button @click="addBulletToSlide"
                                                        class="w-full py-2 border border-dashed border-white/10 rounded text-zinc-600 text-xs hover:border-indigo-500/50 hover:text-indigo-400 transition-colors">
                                                        + Add bullet
                                                    </button>
                                                </div>
                                                <button v-else @click="addParagraph"
                                                    class="h-full w-full flex items-center justify-center border-2 border-dashed border-white/5 rounded-lg hover:border-indigo-500/30 transition-colors cursor-pointer">
                                                    <p class="text-zinc-600 text-xs italic hover:text-indigo-400">+ Add paragraph</p>
                                                </button>
                                            </template>
                                            <!-- Mixed content type with reorderable items -->
                                            <template v-else-if="activeSlide.content_type === 'mixed'">
                                                <div class="space-y-2">
                                                    <!-- Reorderable content items -->
                                                    <div v-for="(item, idx) in getMixedContent" :key="'mixed-' + idx"
                                                        class="group/item flex items-start gap-2 relative">
                                                        <!-- Move buttons -->
                                                        <div class="flex flex-col gap-0.5 opacity-0 group-hover/item:opacity-100 transition-opacity shrink-0">
                                                            <button @click="moveContentItem(idx, 'up')" :disabled="idx === 0"
                                                                class="p-0.5 text-zinc-500 hover:text-indigo-400 disabled:opacity-20 disabled:cursor-not-allowed">
                                                                <ChevronUp class="h-3 w-3" />
                                                            </button>
                                                            <button @click="moveContentItem(idx, 'down')" :disabled="idx === getMixedContent.length - 1"
                                                                class="p-0.5 text-zinc-500 hover:text-indigo-400 disabled:opacity-20 disabled:cursor-not-allowed">
                                                                <ChevronDown class="h-3 w-3" />
                                                            </button>
                                                        </div>
                                                        <!-- Content item -->
                                                        <div class="flex-1 flex items-start gap-2">
                                                            <span v-if="item.type === 'bullet'" class="text-indigo-500 font-bold mt-0.5">•</span>
                                                            <span
                                                                class="flex-1 text-sm lg:text-base leading-relaxed focus:outline-none inline-edit cursor-text text-zinc-300"
                                                                contenteditable="true"
                                                                :data-mixed-index="idx"
                                                                v-init-text="item.text"
                                                                @blur="handleMixedContentBlur(idx, $event)"></span>
                                                        </div>
                                                        <!-- Delete button -->
                                                        <button @click="removeMixedContentItem(idx)"
                                                            class="p-1 text-zinc-600 hover:text-rose-400 opacity-0 group-hover/item:opacity-100 transition-opacity shrink-0">
                                                            <Trash2 class="h-3 w-3" />
                                                        </button>
                                                    </div>

                                                    <div class="flex gap-2 mt-3">
                                                        <button @click="addMixedContentItem('paragraph')"
                                                            class="flex-1 py-2 border border-dashed border-white/10 rounded text-zinc-600 text-xs hover:border-indigo-500/50 hover:text-indigo-400 transition-colors">
                                                            + Paragraph
                                                        </button>
                                                        <button @click="addMixedContentItem('bullet')"
                                                            class="flex-1 py-2 border border-dashed border-white/10 rounded text-zinc-600 text-xs hover:border-indigo-500/50 hover:text-indigo-400 transition-colors">
                                                            + Bullet
                                                        </button>
                                                    </div>
                                                </div>
                                            </template>
                                            <!-- Default bullets content type -->
                                            <template v-else>
                                                <template v-if="activeSlide.bullets && activeSlide.bullets.length">
                                                    <ul class="space-y-2 lg:space-y-4">
                                                        <li v-for="(bullet, idx) in activeSlide.bullets" :key="idx"
                                                            class="flex gap-3 text-zinc-300">
                                                            <span class="text-indigo-500 font-bold">•</span>
                                                            <span
                                                                class="text-sm lg:text-base leading-relaxed focus:outline-none inline-edit"
                                                                contenteditable="true" :data-bullet-index="idx"
                                                                data-placeholder="Bullet"
                                                                @input="handleBulletInput(idx, $event)"
                                                                @keydown="handleBulletKeydown(idx, $event)"
                                                                @blur="handleBulletBlur(idx, $event)"
                                                                v-html="renderBullet(bullet)"></span>
                                                        </li>
                                                    </ul>
                                                </template>
                                                <div v-else class="flex flex-col h-full">
                                                    <div class="flex-1 flex items-center justify-center border-2 border-dashed border-white/5 rounded-lg">
                                                        <p class="text-zinc-600 text-xs italic">No content yet</p>
                                                    </div>
                                                    <div class="flex gap-2 mt-3">
                                                        <button @click="addMixedContentItem('paragraph')"
                                                            class="flex-1 py-2 border border-dashed border-white/10 rounded text-zinc-600 text-xs hover:border-indigo-500/50 hover:text-indigo-400 transition-colors">
                                                            + Paragraph
                                                        </button>
                                                        <button @click="addMixedContentItem('bullet')"
                                                            class="flex-1 py-2 border border-dashed border-white/10 rounded text-zinc-600 text-xs hover:border-indigo-500/50 hover:text-indigo-400 transition-colors">
                                                            + Bullet
                                                        </button>
                                                    </div>
                                                </div>
                                            </template>
                                        </template>
                                    </div>
                                </div>
                            </div>

                            <!-- Navigation Dots (Large Screen only) -->
                            <div class="mt-6 flex items-center justify-center gap-1.5 py-2">
                                <button v-for="(_, i) in draftSlides" :key="i" @click="emit('update:activeIndex', i)"
                                    class="w-1.5 h-1.5 rounded-full transition-all focus:outline-none"
                                    :class="props.activeIndex === i ? 'bg-indigo-500 w-4' : 'bg-white/10 hover:bg-white/20'"
                                    :title="`Go to slide ${i + 1}`">
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- FULLSCREEN PRESENTATION MODE -->
            <div v-if="isPresenting" ref="presentationContainer"
                class="fixed inset-0 z-[100] bg-zinc-950 flex flex-col animate-in fade-in duration-300">
                <!-- Close Button -->
                <button @click="exitPresentation"
                    class="absolute top-6 right-6 z-[110] p-2 bg-white/5 hover:bg-white/10 rounded-full text-zinc-400 hover:text-white transition-all">
                    <X class="h-6 w-6" />
                </button>

                <!-- Navigation Controls (Overlay) -->
                <div
                    class="absolute bottom-12 left-1/2 -translate-x-1/2 z-[110] flex items-center gap-6 px-6 py-3 bg-zinc-900/80 backdrop-blur-xl rounded-full border border-white/10 shadow-2xl opacity-0 hover:opacity-100 transition-opacity duration-300">
                    <button @click="prevSlide" :disabled="props.activeIndex === 0"
                        class="p-2 text-zinc-400 hover:text-white disabled:opacity-30 transition-all">
                        <ChevronLeft class="h-8 w-8" />
                    </button>
                    <div class="flex flex-col items-center min-w-[120px]">
                        <span class="text-[10px] font-bold text-indigo-500 uppercase tracking-widest">Slide</span>
                        <span class="text-2xl font-display font-bold text-white">{{ props.activeIndex + 1 }} /
                            {{
                                draftSlides.length }}</span>
                    </div>
                    <button @click="nextSlide" :disabled="props.activeIndex === draftSlides.length - 1"
                        class="p-2 text-zinc-400 hover:text-white disabled:opacity-30 transition-all">
                        <ChevronRight class="h-8 w-8" />
                    </button>
                </div>

                <!-- Presenter Status Indicator -->
                <div class="absolute bottom-10 right-10 z-[110] hidden md:block">
                    <div
                        class="flex items-center gap-3 px-4 py-2 bg-indigo-500/10 border border-indigo-500/20 rounded-full backdrop-blur-md">
                        <div class="h-2 w-2 rounded-full bg-indigo-500 animate-pulse"></div>
                        <span class="text-[10px] font-mono font-bold text-indigo-400 uppercase tracking-[0.2em]">Live
                            Presentation</span>
                    </div>
                </div>

                <!-- The Slide Area -->
                <div class="flex-1 flex items-center justify-center p-0" ref="presentationSlideRef">
                    <div :key="props.activeIndex"
                        class="w-full h-full bg-zinc-950 relative overflow-hidden flex flex-col p-12 lg:p-24 lg:px-32 slide-in-premium">

                        <!-- Premium Background Effects -->
                        <div
                            class="absolute inset-0 bg-[radial-gradient(circle_at_50%_50%,rgba(99,102,241,0.08),transparent_70%)] pointer-events-none">
                        </div>
                        <div
                            class="absolute inset-0 bg-gradient-to-br from-indigo-500/5 via-transparent to-purple-500/5 pointer-events-none">
                        </div>

                        <div class="relative z-10 flex flex-col h-full max-w-7xl mx-auto w-full">
                            <!-- Content Area with scale to prevent cut-off -->
                            <div class="flex-1 min-h-0 flex flex-col items-center justify-center">
                                <div class="slide-content w-full transition-transform duration-500 origin-center"
                                    :style="{ transform: `scale(${slideContentScale})` }">

                                    <div class="mb-12 lg:mb-16">
                                        <span
                                            class="text-xs lg:text-sm uppercase tracking-[0.4em] font-bold text-indigo-500/60 mb-6 block drop-shadow-[0_0_10px_rgba(99,102,241,0.3)]">
                                            {{ project?.title || 'DEFENSE PREPARATION' }}
                                        </span>
                                        <h1 v-if="(activeSlide.layout || 'bullets') !== 'title'"
                                            class="text-4xl lg:text-7xl font-display font-bold text-white tracking-tight leading-tight">
                                            {{ activeSlide.title }}
                                        </h1>
                                    </div>

                                    <div class="relative w-full">
                                        <template v-if="(activeSlide.layout || 'bullets') === 'title'">
                                            <div class="flex flex-col items-center justify-center text-center py-20">
                                                <div
                                                    class="h-1 w-24 bg-indigo-500 mb-12 shadow-[0_0_20px_rgba(99,102,241,0.6)]">
                                                </div>
                                                <h1
                                                    class="text-6xl lg:text-9xl font-display font-bold text-white tracking-tighter leading-none mb-8">
                                                    {{ activeSlide.title }}
                                                </h1>
                                                <p v-if="activeSlide.bullets && activeSlide.bullets[0]"
                                                    class="text-2xl lg:text-4xl text-zinc-400 font-light tracking-widest uppercase"
                                                    v-html="renderBullet(activeSlide.bullets[0])"></p>
                                                <div v-if="activeSlide.bullets && activeSlide.bullets[1]"
                                                    class="mt-12 text-sm lg:text-lg text-indigo-400/60 font-mono tracking-[0.4em] uppercase"
                                                    v-html="renderBullet(activeSlide.bullets[1])"></div>
                                            </div>
                                        </template>

                                        <template v-else-if="(activeSlide.layout || 'bullets') === 'two_column'">
                                            <div class="grid grid-cols-2 gap-12 lg:gap-20">
                                                <!-- Left Column -->
                                                <div class="space-y-10">
                                                    <!-- Paragraphs in left column -->
                                                    <template v-if="activeSlide.paragraphs && activeSlide.paragraphs.length">
                                                        <p v-for="(para, idx) in activeSlide.paragraphs" :key="'p-' + idx"
                                                            class="text-2xl lg:text-4xl text-zinc-100 font-medium leading-relaxed">
                                                            {{ para }}
                                                        </p>
                                                    </template>
                                                    <!-- Headings in left column -->
                                                    <template v-else-if="activeSlide.headings && activeSlide.headings.length">
                                                        <div v-for="(h, idx) in activeSlide.headings.slice(0, Math.ceil(activeSlide.headings.length / 2))" :key="'h-' + idx" class="space-y-4">
                                                            <h3 class="text-3xl lg:text-5xl font-bold text-indigo-400 tracking-tight">{{ h.heading }}</h3>
                                                            <p class="text-2xl lg:text-4xl text-zinc-300 leading-relaxed font-light">{{ h.content }}</p>
                                                        </div>
                                                    </template>
                                                    <!-- Bullets left column -->
                                                    <template v-else-if="activeSlide.bullets && activeSlide.bullets.length">
                                                        <ul class="space-y-10">
                                                            <li v-for="(bullet, bIdx) in splitBullets(activeSlide.bullets || [])[0]" :key="bIdx"
                                                                class="flex gap-10 items-start group">
                                                                <div class="mt-5 h-3.5 w-3.5 rounded-full bg-indigo-500 shadow-[0_0_20px_rgba(99,102,241,0.6)] group-hover:scale-125 transition-transform duration-300 shrink-0"></div>
                                                                <p class="text-3xl lg:text-5xl text-zinc-100 font-medium leading-[1.15] tracking-tight" v-html="renderBullet(bullet)"></p>
                                                            </li>
                                                        </ul>
                                                    </template>
                                                </div>
                                                <!-- Right Column -->
                                                <div class="space-y-10">
                                                    <!-- Headings right column -->
                                                    <template v-if="activeSlide.headings && activeSlide.headings.length > 1">
                                                        <div v-for="(h, idx) in activeSlide.headings.slice(Math.ceil(activeSlide.headings.length / 2))" :key="'hr-' + idx" class="space-y-4">
                                                            <h3 class="text-3xl lg:text-5xl font-bold text-indigo-400 tracking-tight">{{ h.heading }}</h3>
                                                            <p class="text-2xl lg:text-4xl text-zinc-300 leading-relaxed font-light">{{ h.content }}</p>
                                                        </div>
                                                    </template>
                                                    <!-- Bullets right column -->
                                                    <template v-else-if="activeSlide.bullets && activeSlide.bullets.length > 1">
                                                        <ul class="space-y-10">
                                                            <li v-for="(bullet, bIdx) in splitBullets(activeSlide.bullets || [])[1]" :key="bIdx"
                                                                class="flex gap-10 items-start group">
                                                                <div class="mt-5 h-3.5 w-3.5 rounded-full bg-indigo-500 shadow-[0_0_20px_rgba(99,102,241,0.6)] group-hover:scale-125 transition-transform duration-300 shrink-0"></div>
                                                                <p class="text-3xl lg:text-5xl text-zinc-100 font-medium leading-[1.15] tracking-tight" v-html="renderBullet(bullet)"></p>
                                                            </li>
                                                        </ul>
                                                    </template>
                                                </div>
                                            </div>
                                        </template>

                                        <template
                                            v-else-if="(activeSlide.layout || 'bullets') === 'image_left' || (activeSlide.layout || 'bullets') === 'image_right'">
                                            <div class="flex flex-col lg:flex-row gap-12 lg:gap-20 h-full">
                                                <div class="w-full lg:w-1/2 min-h-[400px] lg:min-h-0 rounded-3xl border border-white/10 bg-white/5 flex items-center justify-center relative overflow-hidden shadow-2xl"
                                                    :class="(activeSlide.layout || 'bullets') === 'image_left' ? 'lg:order-1' : 'lg:order-2'">
                                                    <img v-if="activeSlide.image_url" :src="activeSlide.image_url"
                                                        alt="Slide visual" class="h-full w-full object-contain" :style="{
                                                            objectPosition: `${activeSlide.image_position_x ?? 50}% ${activeSlide.image_position_y ?? 50}%`,
                                                            transform: `scale(${activeSlide.image_scale || 1})`
                                                        }" />
                                                    <span v-else
                                                        class="text-zinc-500 text-sm uppercase tracking-widest">No
                                                        Visual Content</span>
                                                </div>
                                                <div class="flex-1"
                                                    :class="(activeSlide.layout || 'bullets') === 'image_left' ? 'lg:order-2' : 'lg:order-1'">
                                                    <!-- Paragraphs content -->
                                                    <template v-if="activeSlide.paragraphs && activeSlide.paragraphs.length">
                                                        <div class="space-y-8">
                                                            <p v-for="(para, idx) in activeSlide.paragraphs" :key="'p-' + idx"
                                                                class="text-2xl lg:text-4xl text-zinc-100 font-medium leading-relaxed">
                                                                {{ para }}
                                                            </p>
                                                        </div>
                                                    </template>
                                                    <!-- Headings content -->
                                                    <template v-else-if="activeSlide.headings && activeSlide.headings.length">
                                                        <div class="space-y-8">
                                                            <div v-for="(h, idx) in activeSlide.headings" :key="'h-' + idx" class="space-y-4">
                                                                <h3 class="text-3xl lg:text-5xl font-bold text-indigo-400 tracking-tight">{{ h.heading }}</h3>
                                                                <p class="text-2xl lg:text-4xl text-zinc-300 leading-relaxed font-light">{{ h.content }}</p>
                                                            </div>
                                                        </div>
                                                    </template>
                                                    <!-- Bullets content -->
                                                    <template v-else-if="activeSlide.bullets && activeSlide.bullets.length">
                                                        <ul class="space-y-10">
                                                            <li v-for="(bullet, bIdx) in activeSlide.bullets" :key="bIdx"
                                                                class="flex gap-10 items-start">
                                                                <div
                                                                    class="mt-5 h-3.5 w-3.5 rounded-full bg-indigo-500 shadow-[0_0_20px_rgba(99,102,241,0.6)] shrink-0">
                                                                </div>
                                                                <p class="text-3xl lg:text-5xl text-zinc-100 font-medium leading-[1.15] tracking-tight"
                                                                    v-html="renderBullet(bullet)"></p>
                                                            </li>
                                                        </ul>
                                                    </template>
                                                </div>
                                            </div>
                                        </template>
                                        <template v-else>
                                            <!-- Paragraphs/Headings Flow -->
                                            <div class="space-y-12">
                                                <template v-if="activeSlide.content_type === 'paragraphs'">
                                                    <div v-if="activeSlide.paragraphs && activeSlide.paragraphs.length"
                                                        class="space-y-8">
                                                        <p v-for="(para, idx) in activeSlide.paragraphs" :key="idx"
                                                            class="text-3xl lg:text-5xl text-zinc-100 font-medium leading-[1.15] tracking-tight">
                                                            {{ para }}
                                                        </p>
                                                    </div>
                                                    <div v-else-if="activeSlide.headings && activeSlide.headings.length"
                                                        class="space-y-12">
                                                        <div v-for="(h, idx) in activeSlide.headings" :key="idx"
                                                            class="space-y-4">
                                                            <h3
                                                                class="text-3xl lg:text-5xl font-bold text-indigo-400 tracking-tight">
                                                                {{ h.heading }}</h3>
                                                            <p
                                                                class="text-2xl lg:text-4xl text-zinc-300 leading-relaxed font-light">
                                                                {{ h.content }}</p>
                                                        </div>
                                                    </div>
                                                </template>
                                                <template v-else-if="activeSlide.content_type === 'mixed'">
                                                    <div class="space-y-10">
                                                        <div v-if="activeSlide.paragraphs && activeSlide.paragraphs.length"
                                                            class="space-y-6">
                                                            <p v-for="(para, idx) in activeSlide.paragraphs"
                                                                :key="'p-' + idx"
                                                                class="text-2xl lg:text-4xl text-zinc-100 leading-relaxed">
                                                                {{ para }}
                                                            </p>
                                                        </div>
                                                        <div v-if="activeSlide.headings && activeSlide.headings.length"
                                                            class="space-y-8">
                                                            <div v-for="(h, idx) in activeSlide.headings"
                                                                :key="'h-' + idx" class="space-y-3">
                                                                <h3
                                                                    class="text-2xl lg:text-4xl font-bold text-indigo-400">
                                                                    {{ h.heading }}</h3>
                                                                <p
                                                                    class="text-xl lg:text-3xl text-zinc-300 leading-relaxed font-light">
                                                                    {{ h.content }}</p>
                                                            </div>
                                                        </div>
                                                        <ul v-if="activeSlide.bullets && activeSlide.bullets.length"
                                                            class="space-y-8">
                                                            <li v-for="(bullet, bIdx) in activeSlide.bullets"
                                                                :key="'b-' + bIdx" class="flex gap-10 items-start">
                                                                <div
                                                                    class="mt-4 h-3 w-3 rounded-full bg-indigo-500 shadow-[0_0_20px_rgba(99,102,241,0.6)] shrink-0">
                                                                </div>
                                                                <p class="text-3xl lg:text-5xl text-zinc-100 font-medium leading-[1.15] tracking-tight"
                                                                    v-html="renderBullet(bullet)"></p>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                </template>
                                                <template v-else>
                                                    <ul v-if="activeSlide.bullets && activeSlide.bullets.length"
                                                        class="space-y-10">
                                                        <li v-for="(bullet, bIdx) in activeSlide.bullets" :key="bIdx"
                                                            class="flex gap-10 items-start">
                                                            <div
                                                                class="mt-5 h-3.5 w-3.5 rounded-full bg-indigo-500 shadow-[0_0_20px_rgba(99,102,241,0.6)] shrink-0">
                                                            </div>
                                                            <p class="text-3xl lg:text-5xl text-zinc-100 font-medium leading-[1.15] tracking-tight"
                                                                v-html="renderBullet(bullet)"></p>
                                                        </li>
                                                    </ul>
                                                </template>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>

                            <!-- Premium Footer -->
                            <div
                                class="mt-12 lg:mt-16 pt-8 border-t border-white/5 flex justify-between items-center opacity-40 shrink-0">
                                <div class="text-2xl lg:text-3xl font-bold tracking-tighter text-white">
                                    FINALYZE<span class="text-indigo-500">.</span>
                                </div>
                                <div class="flex items-center gap-8">
                                    <div
                                        class="text-sm lg:text-base font-mono tracking-[0.3em] text-zinc-400 uppercase">
                                        {{ project?.title || 'DEFENSE PREPARATION' }}
                                    </div>
                                    <div class="text-lg lg:text-xl font-display font-medium text-white">
                                        Slide {{ props.activeIndex + 1 }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>
    <div v-else
        class="flex flex-col items-center justify-center py-20 text-center space-y-6 h-full bg-zinc-950/20 rounded-2xl border border-dashed border-white/5">
        <div class="h-20 w-20 rounded-full bg-indigo-500/10 flex items-center justify-center animate-bounce">
            <Presentation class="h-10 w-10 text-indigo-500/50" />
        </div>
        <div class="space-y-2 px-6">
            <h3 class="text-xl font-bold text-white">No Slides Found</h3>
            <p class="text-sm text-zinc-500 max-w-xs mx-auto">
                Generate your defense deck to start building the perfect presentation.
            </p>
        </div>
    </div>
    <AlertDialog :open="showDeleteDialog" @update:open="val => { showDeleteDialog = val }">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Delete slide?</AlertDialogTitle>
                <AlertDialogDescription>
                    This will permanently remove the slide from your deck.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel @click="showDeleteDialog = false; pendingDeleteIndex = null">Cancel
                </AlertDialogCancel>
                <AlertDialogAction class="bg-rose-500 text-white hover:bg-rose-600" @click="confirmDeleteSlide">
                    Delete
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 5px;
    height: 5px;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 10px;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.1);
}

.aspect-video {
    aspect-ratio: 16 / 9;
}

.inline-edit:focus {
    outline: 1px solid rgba(99, 102, 241, 0.6);
    outline-offset: 2px;
    border-radius: 4px;
}

.inline-edit[contenteditable="true"]:empty:before {
    content: attr(data-placeholder);
    color: rgba(148, 163, 184, 0.6);
}

/* Animations */
.animate-in {
    animation: fadeIn 0.3s ease-out forwards;
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }

    to {
        opacity: 1;
    }
}

.scale-in {
    animation: scaleIn 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

.slide-in-premium {
    animation: slideInPremium 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

@keyframes slideInPremium {
    from {
        transform: translateY(20px) scale(0.98);
        opacity: 0;
    }

    to {
        transform: translateY(0) scale(1);
        opacity: 1;
    }
}

@keyframes scaleIn {
    from {
        transform: scale(0.95);
        opacity: 0;
    }

    to {
        transform: scale(1);
        opacity: 1;
    }
}
</style>
