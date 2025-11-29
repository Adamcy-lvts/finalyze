import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';
import type { Updater } from '@tanstack/vue-table';
import type { Ref } from 'vue';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

// Helper to update tanstack state refs
export function valueUpdater<T>(updaterOrValue: Updater<T>, ref: Ref<T>) {
    ref.value = typeof updaterOrValue === 'function'
        ? (updaterOrValue as (old: T) => T)(ref.value)
        : updaterOrValue;
}
