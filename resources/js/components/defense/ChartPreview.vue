<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Chart } from 'chart.js/auto';

const props = defineProps<{
    chart: {
        type?: string;
        title?: string;
        x?: string[];
        series?: { name?: string; data?: number[] }[];
    };
}>();

const canvasRef = ref<HTMLCanvasElement | null>(null);
let chartInstance: Chart | null = null;

const palette = ['#6366F1', '#22C55E', '#F59E0B', '#EC4899', '#0EA5E9', '#A855F7'];

const renderChart = () => {
    if (!canvasRef.value) return;
    if (chartInstance) {
        chartInstance.destroy();
    }

    const labels = props.chart?.x ?? [];
    const datasets = (props.chart?.series ?? []).map((series, index) => ({
        label: series?.name ?? `Series ${index + 1}`,
        data: series?.data ?? [],
        backgroundColor: palette[index % palette.length],
        borderColor: palette[index % palette.length],
        borderWidth: 2,
        fill: false,
    }));

    const chartType = (props.chart?.type ?? 'bar') as any;

    chartInstance = new Chart(canvasRef.value, {
        type: chartType,
        data: {
            labels,
            datasets,
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    labels: {
                        color: '#CBD5F5',
                    },
                },
                title: {
                    display: !!props.chart?.title,
                    text: props.chart?.title ?? '',
                    color: '#E2E8F0',
                },
            },
            scales: {
                x: {
                    ticks: {
                        color: '#94A3B8',
                    },
                    grid: {
                        color: 'rgba(148, 163, 184, 0.2)',
                    },
                },
                y: {
                    ticks: {
                        color: '#94A3B8',
                    },
                    grid: {
                        color: 'rgba(148, 163, 184, 0.2)',
                    },
                },
            },
        },
    });
};

watch(() => props.chart, renderChart, { deep: true });

onMounted(() => {
    renderChart();
});

onBeforeUnmount(() => {
    if (chartInstance) {
        chartInstance.destroy();
    }
});
</script>

<template>
    <div class="bg-zinc-900/50 border border-white/5 rounded-xl p-3">
        <div class="relative h-48">
            <canvas ref="canvasRef" />
        </div>
    </div>
</template>
