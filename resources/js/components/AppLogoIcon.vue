<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { computed } from 'vue';
import { useAppearance } from '@/composables/useAppearance';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
    variant?: 'auto' | 'light' | 'dark';
}

const props = withDefaults(defineProps<Props>(), {
    variant: 'auto',
});

const { isDark } = useAppearance();

const src = computed(() => {
    const mode = props.variant === 'auto' ? (isDark.value ? 'dark' : 'light') : props.variant;
    return mode === 'dark' ? '/img/logo-negative-space.png' : '/img/logo-v2-transparent.png';
});
</script>

<template>
    <img :src="src" alt="Finalyze Logo" :class="className" v-bind="$attrs" />
</template>
