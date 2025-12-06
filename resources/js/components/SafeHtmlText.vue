<script setup lang="ts">
import { computed } from 'vue';
import { sanitizeHtmlContent } from '@/utils/html';

defineOptions({ inheritAttrs: false });

const props = withDefaults(
    defineProps<{
        content?: string | null;
        as?: keyof HTMLElementTagNameMap | string;
        fallback?: string;
    }>(),
    {
        as: 'span',
        fallback: '',
    },
);

const resolvedContent = computed(() => props.content || props.fallback || '');
const safeContent = computed(() => sanitizeHtmlContent(resolvedContent.value));
</script>

<template>
    <component :is="as" v-bind="$attrs" v-html="safeContent" />
</template>
