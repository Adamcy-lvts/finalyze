<template>
  <div class="spinner-container" :class="containerClasses">
    <svg 
      :class="spinnerClasses" 
      :width="size" 
      :height="size" 
      viewBox="0 0 24 24" 
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
    >
      <circle 
        cx="12" 
        cy="12" 
        :r="radius" 
        :stroke="trackColor" 
        :stroke-width="strokeWidth"
        fill="none"
      />
      <circle 
        cx="12" 
        cy="12" 
        :r="radius"
        :stroke="color" 
        :stroke-width="strokeWidth"
        fill="none"
        :stroke-linecap="linecap"
        :stroke-dasharray="circumference"
        :stroke-dashoffset="offset"
        class="spinner-circle"
        transform="rotate(-90 12 12)"
      />
    </svg>
    
    <span v-if="label" class="spinner-label" :class="labelClasses">
      {{ label }}
    </span>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps({
  size: {
    type: [Number, String],
    default: 24
  },
  color: {
    type: String,
    default: 'currentColor'
  },
  trackColor: {
    type: String,
    default: '#e5e7eb'
  },
  strokeWidth: {
    type: [Number, String],
    default: 2
  },
  linecap: {
    type: String,
    default: 'round',
    validator: (value) => ['butt', 'round', 'square'].includes(value)
  },
  label: {
    type: String,
    default: null
  },
  variant: {
    type: String,
    default: 'default',
    validator: (value) => ['default', 'dots', 'pulse'].includes(value)
  },
  speed: {
    type: String,
    default: 'normal',
    validator: (value) => ['slow', 'normal', 'fast'].includes(value)
  }
})

// Computed properties
const radius = computed(() => {
  return 12 - (Number(props.strokeWidth) / 2)
})

const circumference = computed(() => {
  return 2 * Math.PI * radius.value
})

const offset = computed(() => {
  return circumference.value * 0.75 // Show 25% of the circle
})

const containerClasses = computed(() => {
  const classes = ['inline-flex', 'items-center']
  
  if (props.label) {
    classes.push('space-x-2')
  }
  
  return classes
})

const spinnerClasses = computed(() => {
  const classes = ['spinner']
  
  // Animation speed
  if (props.speed === 'slow') {
    classes.push('animate-spin-slow')
  } else if (props.speed === 'fast') {
    classes.push('animate-spin-fast')
  } else {
    classes.push('animate-spin')
  }
  
  return classes
})

const labelClasses = computed(() => {
  return ['text-sm', 'text-gray-600', 'dark:text-gray-400']
})
</script>

<style scoped>
.spinner {
  animation-duration: 1s;
  animation-timing-function: linear;
  animation-iteration-count: infinite;
}

.spinner-circle {
  animation: spinner-dash 1.5s ease-in-out infinite;
}

@keyframes spinner-dash {
  0% {
    stroke-dasharray: 0 150;
    stroke-dashoffset: 0;
  }
  47.5% {
    stroke-dasharray: 42 150;
    stroke-dashoffset: -16;
  }
  95%, 100% {
    stroke-dasharray: 42 150;
    stroke-dashoffset: -59;
  }
}

/* Custom animation speeds */
@keyframes spin-slow {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}

@keyframes spin-fast {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}

.animate-spin-slow {
  animation: spin-slow 2s linear infinite;
}

.animate-spin-fast {
  animation: spin-fast 0.5s linear infinite;
}

/* Pulse variant */
.spinner.pulse {
  animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

@keyframes pulse {
  0%, 100% {
    opacity: 1;
  }
  50% {
    opacity: .5;
  }
}

/* Dots variant */
.spinner.dots {
  width: auto;
  height: auto;
}

.spinner.dots::before {
  content: '';
  display: inline-block;
  width: 4px;
  height: 4px;
  border-radius: 50%;
  background-color: currentColor;
  margin-right: 2px;
  animation: dots 1.4s ease-in-out infinite both;
}

.spinner.dots::after {
  content: '';
  display: inline-block;
  width: 4px;
  height: 4px;
  border-radius: 50%;
  background-color: currentColor;
  margin: 0 2px;
  animation: dots 1.4s ease-in-out infinite both;
  animation-delay: 0.16s;
}

@keyframes dots {
  0%, 80%, 100% {
    transform: scale(0);
    opacity: 0.5;
  }
  40% {
    transform: scale(1);
    opacity: 1;
  }
}
</style>