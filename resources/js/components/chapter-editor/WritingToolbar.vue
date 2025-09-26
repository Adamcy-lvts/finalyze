<!-- /resources/js/components/chapter-editor/WritingToolbar.vue -->
<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Bold, BookMarked, ChevronDown, Code, Hash, Italic, Link, List, ListOrdered, Quote, Redo, Type, Underline, Undo } from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    historyIndex: number;
    contentHistoryLength: number;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    undo: [];
    redo: [];
    formatText: [format: string];
}>();

// Computed
const canUndo = computed(() => props.historyIndex > 0);
const canRedo = computed(() => props.historyIndex < props.contentHistoryLength - 1);

// Methods
const handleUndo = () => emit('undo');
const handleRedo = () => emit('redo');
const handleFormatText = (format: string) => emit('formatText', format);
</script>

<template>
    <div class="flex flex-wrap items-center gap-1 rounded-lg border border-border/50 bg-muted/30 p-2 sm:gap-2 sm:p-3">
        <!-- Undo/Redo -->
        <div class="flex">
            <Button @click="handleUndo" :disabled="!canUndo" variant="ghost" size="icon" class="h-7 w-7 sm:h-8 sm:w-8" title="Undo (Ctrl+Z)">
                <Undo class="h-3 w-3 sm:h-4 sm:w-4" />
            </Button>
            <Button @click="handleRedo" :disabled="!canRedo" variant="ghost" size="icon" class="h-7 w-7 sm:h-8 sm:w-8" title="Redo (Ctrl+Shift+Z)">
                <Redo class="h-3 w-3 sm:h-4 sm:w-4" />
            </Button>
        </div>

        <!-- Basic Formatting -->
        <div class="flex">
            <Button @click="handleFormatText('bold')" variant="ghost" size="icon" class="h-7 w-7 sm:h-8 sm:w-8" title="Bold (Ctrl+B)">
                <Bold class="h-3 w-3 sm:h-4 sm:w-4" />
            </Button>
            <Button @click="handleFormatText('italic')" variant="ghost" size="icon" class="h-7 w-7 sm:h-8 sm:w-8" title="Italic (Ctrl+I)">
                <Italic class="h-3 w-3 sm:h-4 sm:w-4" />
            </Button>
            <Button @click="handleFormatText('underline')" variant="ghost" size="icon" class="hidden h-7 w-7 sm:flex sm:h-8 sm:w-8" title="Underline">
                <Underline class="h-4 w-4" />
            </Button>
        </div>

        <!-- Headers Dropdown -->
        <div class="flex">
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button variant="ghost" size="sm" class="h-7 text-xs sm:h-8 sm:text-sm" title="Text Style">
                        <Type class="mr-1 h-3 w-3 sm:mr-2 sm:h-4 sm:w-4" />
                        <span class="hidden sm:inline">Style</span>
                        <ChevronDown class="ml-1 h-3 w-3" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent>
                    <DropdownMenuItem @click="handleFormatText('heading1')"> <Hash class="mr-2 h-4 w-4" /> Heading 1 </DropdownMenuItem>
                    <DropdownMenuItem @click="handleFormatText('heading2')"> <Hash class="mr-2 h-4 w-4" /> Heading 2 </DropdownMenuItem>
                    <DropdownMenuItem @click="handleFormatText('heading3')"> <Hash class="mr-2 h-3 w-3" /> Heading 3 </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>

        <!-- Lists -->
        <div class="flex">
            <Button @click="handleFormatText('list')" variant="ghost" size="icon" class="h-7 w-7 sm:h-8 sm:w-8" title="Bullet List">
                <List class="h-3 w-3 sm:h-4 sm:w-4" />
            </Button>
            <Button
                @click="handleFormatText('ordered-list')"
                variant="ghost"
                size="icon"
                class="hidden h-7 w-7 sm:flex sm:h-8 sm:w-8"
                title="Numbered List"
            >
                <ListOrdered class="h-4 w-4" />
            </Button>
        </div>

        <!-- Special -->
        <div class="flex">
            <Button @click="handleFormatText('quote')" variant="ghost" size="icon" class="hidden h-7 w-7 sm:flex sm:h-8 sm:w-8" title="Blockquote">
                <Quote class="h-4 w-4" />
            </Button>
            <Button @click="handleFormatText('code')" variant="ghost" size="icon" class="hidden h-7 w-7 sm:flex sm:h-8 sm:w-8" title="Inline Code">
                <Code class="h-4 w-4" />
            </Button>
            <Button @click="handleFormatText('link')" variant="ghost" size="icon" class="h-7 w-7 sm:h-8 sm:w-8" title="Insert Link (Ctrl+K)">
                <Link class="h-3 w-3 sm:h-4 sm:w-4" />
            </Button>
            <Button
                @click="handleFormatText('citation')"
                variant="ghost"
                size="icon"
                class="hidden h-7 w-7 sm:flex sm:h-8 sm:w-8"
                title="Add Citation"
            >
                <BookMarked class="h-4 w-4" />
            </Button>
        </div>
    </div>
</template>
