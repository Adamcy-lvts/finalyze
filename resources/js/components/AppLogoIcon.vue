<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();
</script>

<template>
    <img src="/img/logo-negative-space.png" alt="Finalyze Logo" :class="className" v-bind="$attrs" />
</template>
