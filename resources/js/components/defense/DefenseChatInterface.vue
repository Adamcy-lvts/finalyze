<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { ArrowLeft, History } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import type { DefenseMessage } from '@/types/defense'

const props = defineProps<{
    messages: DefenseMessage[]
    personaLookup: Record<string, string>
    modelValue: string
    isSending: boolean
}>()

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void
    (e: 'submit'): void
    (e: 'requestHint'): void
}>()

const chatContainer = ref<HTMLElement | null>(null)

const responseInput = computed({
    get: () => props.modelValue,
    set: value => emit('update:modelValue', value),
})

const scrollToBottom = async () => {
    await nextTick()
    if (chatContainer.value) {
        chatContainer.value.scrollTop = chatContainer.value.scrollHeight
    }
}

const formatMessageTime = (message: DefenseMessage) => {
    if (!message.created_at) return ''
    return new Date(message.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
}

watch(() => props.messages, () => {
    scrollToBottom()
}, { deep: true })

onMounted(() => {
    scrollToBottom()
})
</script>

<template>
    <div
        class="lg:col-span-8 flex flex-col bg-zinc-900/40 rounded-[2.5rem] border border-zinc-800/50 backdrop-blur-xl overflow-hidden">
        <div ref="chatContainer" class="flex-grow p-8 overflow-y-auto custom-scrollbar">
            <div class="space-y-10 max-w-3xl mx-auto py-8">
                <div v-for="(msg, i) in messages" :key="i"
                    class="group animate-in fade-in slide-in-from-bottom-2 duration-500"
                    :class="msg.role === 'student' ? 'flex flex-col items-end' : 'flex flex-col items-start'">
                    <div class="flex items-center gap-2 mb-2 px-1">
                        <span class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest">
                            {{ msg.role === 'panelist' ? (personaLookup[msg.panelist_persona || ''] || 'Panelist') :
                                'Candidate' }}
                        </span>
                        <span class="text-[8px] text-zinc-600">{{ formatMessageTime(msg) }}</span>
                    </div>
                    <div class="p-5 rounded-3xl text-sm leading-relaxed max-w-[85%]" :class="msg.role === 'student'
                        ? 'bg-zinc-100 text-zinc-950 rounded-tr-none'
                        : 'bg-zinc-800/80 text-zinc-200 border border-zinc-700/50 rounded-tl-none'">
                        {{ msg.content }}
                    </div>
                </div>
            </div>
        </div>

        <div class="p-4 md:p-8 border-t border-zinc-800/50 bg-black/20">
            <div class="relative max-w-3xl mx-auto">
                <textarea v-model="responseInput" placeholder="Type your defense answer..."
                    class="w-full bg-zinc-950 border-zinc-800 rounded-2xl py-4 md:py-6 px-4 md:px-6 pr-20 md:pr-24 text-zinc-100 focus:ring-primary/40 focus:border-primary/40 placeholder-zinc-700 transition-all resize-none shadow-2xl text-sm md:text-base"
                    :disabled="isSending" rows="2"></textarea>
                <div class="absolute right-3 md:right-4 top-1/2 -translate-y-1/2 flex items-center gap-2">
                    <Button size="icon" @click="emit('submit')" :disabled="isSending"
                        class="h-9 w-9 md:h-10 md:w-10 rounded-full bg-primary text-primary-foreground hover:bg-primary/90 shadow-lg shadow-primary/20">
                        <ArrowLeft class="h-4 w-4 md:h-5 md:w-5 rotate-180" />
                    </Button>
                </div>
            </div>
            <div class="mt-4 flex justify-center gap-6">
                <button
                    class="flex items-center gap-2 text-[10px] font-bold text-zinc-500 hover:text-white transition-colors uppercase tracking-widest"
                    @click="emit('requestHint')">
                    <History class="h-3.5 w-3.5" />
                    Request Hint
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #27272a;
    border-radius: 10px;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #3f3f46;
}
</style>
