import { ref } from 'vue';

interface AutoSaveOptions {
    delay?: number;
    onSave: (autoSave?: boolean) => Promise<void>;
}

export function useAutoSave(options: AutoSaveOptions) {
    const { delay = 10000, onSave } = options;

    const hasUnsavedChanges = ref(false);
    const isSaving = ref(false);
    const autoSaveTimer = ref<number | null>(null);

    const triggerAutoSave = () => {
        hasUnsavedChanges.value = true;

        if (autoSaveTimer.value) {
            clearTimeout(autoSaveTimer.value);
        }

        autoSaveTimer.value = setTimeout(() => {
            save(true);
        }, delay);
    };

    const save = async (autoSave = false) => {
        if (isSaving.value) return;

        isSaving.value = true;

        try {
            await onSave(autoSave);
            hasUnsavedChanges.value = false;

            if (autoSaveTimer.value) {
                clearTimeout(autoSaveTimer.value);
                autoSaveTimer.value = null;
            }
        } finally {
            isSaving.value = false;
        }
    };

    const clearAutoSave = () => {
        if (autoSaveTimer.value) {
            clearTimeout(autoSaveTimer.value);
            autoSaveTimer.value = null;
        }
    };

    return {
        hasUnsavedChanges,
        isSaving,
        triggerAutoSave,
        save,
        clearAutoSave,
    };
}
