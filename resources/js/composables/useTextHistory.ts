import { ref } from 'vue';

export function useTextHistory(initialContent: string = '', maxHistorySize: number = 50) {
    const contentHistory = ref<string[]>([initialContent]);
    const historyIndex = ref(0);

    const addToHistory = (content: string) => {
        // Don't add if content is the same as current
        if (contentHistory.value[historyIndex.value] === content) return;

        // Remove any history after current index (when undoing then making new changes)
        contentHistory.value = contentHistory.value.slice(0, historyIndex.value + 1);

        // Add new content
        contentHistory.value.push(content);

        // Limit history size
        if (contentHistory.value.length > maxHistorySize) {
            contentHistory.value.shift();
        } else {
            historyIndex.value++;
        }
    };

    const undo = (): string | null => {
        if (historyIndex.value > 0) {
            historyIndex.value--;
            return contentHistory.value[historyIndex.value];
        }
        return null;
    };

    const redo = (): string | null => {
        if (historyIndex.value < contentHistory.value.length - 1) {
            historyIndex.value++;
            return contentHistory.value[historyIndex.value];
        }
        return null;
    };

    const canUndo = () => historyIndex.value > 0;
    const canRedo = () => historyIndex.value < contentHistory.value.length - 1;

    return {
        contentHistory,
        historyIndex,
        addToHistory,
        undo,
        redo,
        canUndo,
        canRedo,
    };
}
