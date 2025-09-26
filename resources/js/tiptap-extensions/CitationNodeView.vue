<template>
  <NodeViewWrapper 
    class="citation-node inline-block relative cursor-pointer"
    :class="citationClasses"
    @click="handleClick"
  >
    <span 
      class="citation-text px-2 py-1 rounded text-sm font-medium transition-all duration-200"
      :class="textClasses"
    >
      {{ displayText }}
    </span>
    
    <!-- Verification Status Indicator -->
    <span class="verification-indicator ml-1" :class="indicatorClasses">
      <CheckCircleIcon v-if="node.attrs.verified" class="w-3 h-3" />
      <ExclamationCircleIcon v-else-if="isUnverified" class="w-3 h-3" />
      <ClockIcon v-else class="w-3 h-3" />
    </span>
    
    <!-- Citation Details Popover -->
    <div 
      v-if="showDetails" 
      class="citation-popover absolute z-50 mt-2 p-4 bg-white dark:bg-gray-800 rounded-lg shadow-lg border max-w-sm"
      :style="popoverPosition"
    >
      <div class="space-y-2">
        <div class="font-semibold text-sm">
          {{ node.attrs.text || '[Citation]' }}
        </div>
        
        <div v-if="node.attrs.data && node.attrs.data.title" class="text-xs text-gray-600 dark:text-gray-300">
          {{ node.attrs.data.title }}
        </div>
        
        <div class="flex items-center space-x-2 text-xs">
          <span class="px-2 py-1 rounded" :class="statusBadgeClasses">
            {{ statusText }}
          </span>
          
          <span v-if="node.attrs.confidence" class="text-gray-500">
            {{ Math.round(node.attrs.confidence * 100) }}% confidence
          </span>
        </div>
        
        <div v-if="node.attrs.doi" class="text-xs text-blue-600 dark:text-blue-400">
          DOI: {{ node.attrs.doi }}
        </div>
        
        <div class="flex space-x-2 pt-2 border-t">
          <button
            v-if="!node.attrs.verified"
            @click="verifyCitation"
            class="text-xs px-2 py-1 bg-blue-600 text-white rounded hover:bg-blue-700"
          >
            Verify
          </button>
          
          <button
            @click="editCitation"
            class="text-xs px-2 py-1 bg-gray-600 text-white rounded hover:bg-gray-700"
          >
            Edit
          </button>
          
          <button
            @click="removeCitation"
            class="text-xs px-2 py-1 bg-red-600 text-white rounded hover:bg-red-700"
          >
            Remove
          </button>
        </div>
      </div>
    </div>
    
    <!-- Click outside handler -->
    <div 
      v-if="showDetails"
      class="fixed inset-0 z-40"
      @click="hideDetails"
    />
  </NodeViewWrapper>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { NodeViewWrapper } from '@tiptap/vue-3'
import { 
  CheckCircleIcon, 
  ExclamationCircleIcon, 
  ClockIcon 
} from '@heroicons/vue/24/solid'

const props = defineProps({
  node: {
    type: Object,
    required: true,
  },
  editor: {
    type: Object,
    required: true,
  },
  getPos: {
    type: Function,
    required: true,
  },
  updateAttributes: {
    type: Function,
    required: true,
  },
})

const emit = defineEmits(['verify', 'edit', 'remove'])

// State
const showDetails = ref(false)
const popoverPosition = ref({ top: '0px', left: '0px' })

// Computed properties
const displayText = computed(() => {
  return props.node.attrs.text || '[Citation]'
})

const isUnverified = computed(() => {
  return props.node.attrs.verified === false && props.node.attrs.confidence < 0.8
})

const citationClasses = computed(() => ({
  'citation-verified': props.node.attrs.verified,
  'citation-unverified': isUnverified.value,
  'citation-pending': !props.node.attrs.verified && !isUnverified.value,
}))

const textClasses = computed(() => {
  if (props.node.attrs.verified) {
    return 'bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 border border-green-300 dark:border-green-700'
  } else if (isUnverified.value) {
    return 'bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200 border border-red-300 dark:border-red-700'
  } else {
    return 'bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200 border border-yellow-300 dark:border-yellow-700'
  }
})

const indicatorClasses = computed(() => {
  if (props.node.attrs.verified) {
    return 'text-green-600 dark:text-green-400'
  } else if (isUnverified.value) {
    return 'text-red-600 dark:text-red-400'
  } else {
    return 'text-yellow-600 dark:text-yellow-400'
  }
})

const statusText = computed(() => {
  if (props.node.attrs.verified) {
    return 'Verified'
  } else if (isUnverified.value) {
    return 'Needs Review'
  } else {
    return 'Pending'
  }
})

const statusBadgeClasses = computed(() => {
  if (props.node.attrs.verified) {
    return 'bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200'
  } else if (isUnverified.value) {
    return 'bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200'
  } else {
    return 'bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200'
  }
})

// Methods
const handleClick = (event) => {
  event.stopPropagation()
  
  if (showDetails.value) {
    hideDetails()
  } else {
    // Calculate popover position
    const rect = event.target.getBoundingClientRect()
    popoverPosition.value = {
      top: `${rect.height + 5}px`,
      left: '0px',
    }
    
    showDetails.value = true
  }
}

const hideDetails = () => {
  showDetails.value = false
}

const verifyCitation = async () => {
  try {
    const response = await axios.post('/api/citations/verify', {
      citation_id: props.node.attrs.id,
      citation_text: props.node.attrs.text,
    })
    
    if (response.data.verified) {
      props.updateAttributes({
        verified: true,
        confidence: response.data.confidence,
        data: response.data.citation,
        doi: response.data.citation.doi,
      })
    }
    
    emit('verify', response.data)
    hideDetails()
  } catch (error) {
    console.error('Failed to verify citation:', error)
  }
}

const editCitation = () => {
  emit('edit', {
    id: props.node.attrs.id,
    text: props.node.attrs.text,
    attributes: props.node.attrs,
  })
  hideDetails()
}

const removeCitation = () => {
  if (confirm('Are you sure you want to remove this citation?')) {
    const pos = props.getPos()
    props.editor.commands.deleteRange({ from: pos, to: pos + 1 })
    emit('remove', props.node.attrs.id)
  }
  hideDetails()
}

// Handle keyboard shortcuts when focused
const handleKeydown = (event) => {
  if (event.key === 'Enter' || event.key === ' ') {
    event.preventDefault()
    handleClick(event)
  } else if (event.key === 'Escape') {
    hideDetails()
  }
}

onMounted(() => {
  // Add keyboard event listener
  document.addEventListener('keydown', handleKeydown)
})

onUnmounted(() => {
  // Cleanup event listener
  document.removeEventListener('keydown', handleKeydown)
})
</script>

<style scoped>
.citation-node {
  display: inline-block;
  position: relative;
}

.citation-text {
  user-select: none;
}

.citation-popover {
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
  backdrop-filter: blur(10px);
}

.citation-verified .citation-text {
  background-color: rgb(220 252 231);
  color: rgb(22 101 52);
  border-color: rgb(134 239 172);
}

.citation-unverified .citation-text {
  background-color: rgb(254 226 226);
  color: rgb(153 27 27);
  border-color: rgb(252 165 165);
  animation: pulse 2s infinite;
}

.citation-pending .citation-text {
  background-color: rgb(254 249 195);
  color: rgb(133 77 14);
  border-color: rgb(253 224 71);
}

@media (prefers-color-scheme: dark) {
  .citation-verified .citation-text {
    background-color: rgb(20 83 45);
    color: rgb(187 247 208);
    border-color: rgb(21 128 61);
  }

  .citation-unverified .citation-text {
    background-color: rgb(127 29 29);
    color: rgb(254 202 202);
    border-color: rgb(185 28 28);
  }

  .citation-pending .citation-text {
    background-color: rgb(133 77 14);
    color: rgb(254 240 138);
    border-color: rgb(180 83 9);
  }
}

@keyframes pulse {
  0%, 100% {
    opacity: 1;
  }
  50% {
    opacity: 0.7;
  }
}
</style>