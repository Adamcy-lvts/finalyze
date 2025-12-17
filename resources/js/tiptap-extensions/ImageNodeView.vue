<script setup lang="ts">
import { ref, computed, onBeforeUnmount, watch } from 'vue'
import { NodeViewWrapper } from '@tiptap/vue-3'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import axios from 'axios'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Label } from '@/components/ui/label'
import {
  AlignLeft,
  AlignCenter,
  AlignRight,
  Trash2,
  Type,
  Loader2,
} from 'lucide-vue-next'

const props = defineProps<{
  node: {
    attrs: {
      src: string
      alt: string
      title: string
      width: number | null
      height: number | null
      alignment: 'left' | 'center' | 'right'
      aspectRatio: number | null
      caption: string
    }
  }
  updateAttributes: (attrs: Record<string, unknown>) => void
  deleteNode: () => void
  selected: boolean
  editor: any
}>()

// State
const imageRef = ref<HTMLImageElement | null>(null)
const containerRef = ref<HTMLDivElement | null>(null)
const isResizing = ref(false)
const resizeDirection = ref<string | null>(null)
const startX = ref(0)
const startY = ref(0)
const startWidth = ref(0)
const startHeight = ref(0)
const currentWidth = ref(props.node.attrs.width || 400)
const currentHeight = ref(props.node.attrs.height || 300)
const isLoading = ref(true)
const hasError = ref(false)
const altDialogOpen = ref(false)
const editAlt = ref('')
const editTitle = ref('')
const captionText = ref(props.node.attrs.caption || '')

// Computed
const alignmentClass = computed(() => {
  switch (props.node.attrs.alignment) {
    case 'left':
      return 'mr-auto'
    case 'right':
      return 'ml-auto'
    default:
      return 'mx-auto'
  }
})

const imageStyle = computed(() => ({
  width: `${currentWidth.value}px`,
  height: `${currentHeight.value}px`,
  maxWidth: '100%',
}))

// Methods
const onImageLoad = () => {
  isLoading.value = false
  hasError.value = false

  if (imageRef.value && !props.node.attrs.width) {
    const naturalWidth = imageRef.value.naturalWidth
    const naturalHeight = imageRef.value.naturalHeight
    const maxWidth = 600

    if (naturalWidth > maxWidth) {
      const ratio = maxWidth / naturalWidth
      currentWidth.value = maxWidth
      currentHeight.value = Math.round(naturalHeight * ratio)
    } else {
      currentWidth.value = naturalWidth
      currentHeight.value = naturalHeight
    }

    props.updateAttributes({
      width: currentWidth.value,
      height: currentHeight.value,
      aspectRatio: naturalWidth / naturalHeight,
    })
  }
}

const onImageError = () => {
  isLoading.value = false
  hasError.value = true
}

const startResize = (direction: string, event: MouseEvent | TouchEvent) => {
  if (!props.selected) return

  event.preventDefault()
  event.stopPropagation()

  isResizing.value = true
  resizeDirection.value = direction

  const clientX = 'touches' in event ? event.touches[0].clientX : event.clientX
  const clientY = 'touches' in event ? event.touches[0].clientY : event.clientY

  startX.value = clientX
  startY.value = clientY
  startWidth.value = currentWidth.value
  startHeight.value = currentHeight.value

  document.addEventListener('mousemove', onResize)
  document.addEventListener('mouseup', stopResize)
  document.addEventListener('touchmove', onResize)
  document.addEventListener('touchend', stopResize)
}

const onResize = (event: MouseEvent | TouchEvent) => {
  if (!isResizing.value) return

  const clientX = 'touches' in event ? event.touches[0].clientX : event.clientX
  const clientY = 'touches' in event ? event.touches[0].clientY : event.clientY

  const deltaX = clientX - startX.value
  const deltaY = clientY - startY.value

  const aspectRatio = props.node.attrs.aspectRatio || startWidth.value / startHeight.value
  const direction = resizeDirection.value

  let newWidth = startWidth.value
  let newHeight = startHeight.value

  // Calculate new dimensions based on resize direction
  if (direction?.includes('e')) {
    newWidth = Math.max(100, startWidth.value + deltaX)
  }
  if (direction?.includes('w')) {
    newWidth = Math.max(100, startWidth.value - deltaX)
  }
  if (direction?.includes('s')) {
    newHeight = Math.max(100, startHeight.value + deltaY)
  }
  if (direction?.includes('n')) {
    newHeight = Math.max(100, startHeight.value - deltaY)
  }

  // Maintain aspect ratio for corner handles
  if (direction?.length === 2) {
    if (Math.abs(deltaX) > Math.abs(deltaY)) {
      newHeight = Math.round(newWidth / aspectRatio)
    } else {
      newWidth = Math.round(newHeight * aspectRatio)
    }
  }

  // Clamp to max width
  const maxWidth = 800
  if (newWidth > maxWidth) {
    newWidth = maxWidth
    newHeight = Math.round(newWidth / aspectRatio)
  }

  currentWidth.value = newWidth
  currentHeight.value = newHeight
}

const stopResize = () => {
  if (!isResizing.value) return

  isResizing.value = false
  resizeDirection.value = null

  props.updateAttributes({
    width: currentWidth.value,
    height: currentHeight.value,
  })

  document.removeEventListener('mousemove', onResize)
  document.removeEventListener('mouseup', stopResize)
  document.removeEventListener('touchmove', onResize)
  document.removeEventListener('touchend', stopResize)
}

const setAlignment = (alignment: 'left' | 'center' | 'right') => {
  props.updateAttributes({ alignment })
}

const openAltDialog = () => {
  editAlt.value = props.node.attrs.alt || ''
  editTitle.value = props.node.attrs.title || ''
  altDialogOpen.value = true
}

const saveAltText = () => {
  props.updateAttributes({
    alt: editAlt.value,
    title: editTitle.value,
  })
  altDialogOpen.value = false
}

const saveCaption = () => {
  props.updateAttributes({
    caption: captionText.value,
  })
}

const deleteImage = async () => {
  const src = props.node.attrs.src

  // Only delete from storage if it's a local upload (starts with /storage/)
  if (src && src.startsWith('/storage/')) {
    try {
      await axios.delete('/editor/images', {
        data: { url: src }
      })
    } catch (error) {
      // Log but don't block deletion from editor
      console.warn('Failed to delete image from storage:', error)
    }
  }

  // Always remove from editor
  props.deleteNode()
}

// Watch for caption changes from outside
watch(
  () => props.node.attrs.caption,
  (newCaption) => {
    if (newCaption !== captionText.value) {
      captionText.value = newCaption || ''
    }
  }
)

// Sync width/height from props
watch(
  () => [props.node.attrs.width, props.node.attrs.height],
  ([width, height]) => {
    if (width && !isResizing.value) {
      currentWidth.value = width
    }
    if (height && !isResizing.value) {
      currentHeight.value = height
    }
  },
  { immediate: true }
)

onBeforeUnmount(() => {
  document.removeEventListener('mousemove', onResize)
  document.removeEventListener('mouseup', stopResize)
  document.removeEventListener('touchmove', onResize)
  document.removeEventListener('touchend', stopResize)
})
</script>

<template>
  <NodeViewWrapper class="resizable-image-wrapper my-4">
    <figure
      ref="containerRef"
      class="relative inline-block"
      :class="[alignmentClass, { 'ring-2 ring-primary ring-offset-2': selected }]"
    >
      <!-- Loading State -->
      <div
        v-if="isLoading"
        class="flex items-center justify-center bg-muted/50 rounded-lg"
        :style="imageStyle"
      >
        <Loader2 class="w-8 h-8 animate-spin text-muted-foreground" />
      </div>

      <!-- Error State -->
      <div
        v-else-if="hasError"
        class="flex flex-col items-center justify-center bg-destructive/10 border border-destructive/30 rounded-lg p-4"
        :style="imageStyle"
      >
        <span class="text-destructive text-sm">Failed to load image</span>
        <span class="text-muted-foreground text-xs mt-1 truncate max-w-full">{{ node.attrs.src }}</span>
      </div>

      <!-- Image -->
      <img
        v-show="!isLoading && !hasError"
        ref="imageRef"
        :src="node.attrs.src"
        :alt="node.attrs.alt"
        :title="node.attrs.title"
        :style="imageStyle"
        class="block rounded-lg object-contain"
        :class="{ 'cursor-move': selected }"
        @load="onImageLoad"
        @error="onImageError"
        draggable="false"
      />

      <!-- Caption Input (when selected) -->
      <div v-if="selected && !hasError" class="mt-2 w-full">
        <input
          v-model="captionText"
          type="text"
          placeholder="Add a caption..."
          class="w-full text-center text-sm text-muted-foreground bg-transparent border-none focus:outline-none focus:ring-0 placeholder:text-muted-foreground/50"
          @blur="saveCaption"
          @keydown.enter="saveCaption"
        />
      </div>

      <!-- Caption Display (when not selected) -->
      <figcaption
        v-else-if="node.attrs.caption && !hasError"
        class="mt-2 text-center text-sm text-muted-foreground italic"
      >
        {{ node.attrs.caption }}
      </figcaption>

      <!-- Resize Handles (only when selected and no error) -->
      <template v-if="selected && !hasError && !isLoading">
        <!-- Corner handles -->
        <div
          class="absolute -top-1.5 -left-1.5 w-3 h-3 bg-primary border-2 border-background rounded-full cursor-nw-resize z-20 hover:scale-125 transition-transform"
          @mousedown="startResize('nw', $event)"
          @touchstart="startResize('nw', $event)"
        />
        <div
          class="absolute -top-1.5 -right-1.5 w-3 h-3 bg-primary border-2 border-background rounded-full cursor-ne-resize z-20 hover:scale-125 transition-transform"
          @mousedown="startResize('ne', $event)"
          @touchstart="startResize('ne', $event)"
        />
        <div
          class="absolute -bottom-1.5 -left-1.5 w-3 h-3 bg-primary border-2 border-background rounded-full cursor-sw-resize z-20 hover:scale-125 transition-transform"
          @mousedown="startResize('sw', $event)"
          @touchstart="startResize('sw', $event)"
        />
        <div
          class="absolute -bottom-1.5 -right-1.5 w-3 h-3 bg-primary border-2 border-background rounded-full cursor-se-resize z-20 hover:scale-125 transition-transform"
          @mousedown="startResize('se', $event)"
          @touchstart="startResize('se', $event)"
        />

        <!-- Edge handles -->
        <div
          class="absolute top-1/2 -left-1.5 w-3 h-3 bg-primary border-2 border-background rounded-full cursor-w-resize z-20 -translate-y-1/2 hover:scale-125 transition-transform"
          @mousedown="startResize('w', $event)"
          @touchstart="startResize('w', $event)"
        />
        <div
          class="absolute top-1/2 -right-1.5 w-3 h-3 bg-primary border-2 border-background rounded-full cursor-e-resize z-20 -translate-y-1/2 hover:scale-125 transition-transform"
          @mousedown="startResize('e', $event)"
          @touchstart="startResize('e', $event)"
        />
        <div
          class="absolute -top-1.5 left-1/2 w-3 h-3 bg-primary border-2 border-background rounded-full cursor-n-resize z-20 -translate-x-1/2 hover:scale-125 transition-transform"
          @mousedown="startResize('n', $event)"
          @touchstart="startResize('n', $event)"
        />
        <div
          class="absolute -bottom-1.5 left-1/2 w-3 h-3 bg-primary border-2 border-background rounded-full cursor-s-resize z-20 -translate-x-1/2 hover:scale-125 transition-transform"
          @mousedown="startResize('s', $event)"
          @touchstart="startResize('s', $event)"
        />
      </template>

      <!-- Toolbar -->
      <div
        v-if="selected"
        class="absolute -top-12 left-1/2 -translate-x-1/2 flex items-center gap-1.5 px-3 py-1.5 bg-background/95 backdrop-blur-md rounded-lg border border-border shadow-lg z-10 min-w-max"
      >
        <template v-if="!hasError">
          <!-- Alignment buttons -->
          <Button
            variant="ghost"
            size="sm"
            class="h-7 w-7 p-0"
            :class="{ 'bg-primary/10 text-primary': node.attrs.alignment === 'left' }"
            @click="setAlignment('left')"
          >
            <AlignLeft class="w-4 h-4" />
          </Button>
          <Button
            variant="ghost"
            size="sm"
            class="h-7 w-7 p-0"
            :class="{ 'bg-primary/10 text-primary': node.attrs.alignment === 'center' }"
            @click="setAlignment('center')"
          >
            <AlignCenter class="w-4 h-4" />
          </Button>
          <Button
            variant="ghost"
            size="sm"
            class="h-7 w-7 p-0"
            :class="{ 'bg-primary/10 text-primary': node.attrs.alignment === 'right' }"
            @click="setAlignment('right')"
          >
            <AlignRight class="w-4 h-4" />
          </Button>

          <div class="w-px h-5 bg-border mx-1" />

          <!-- Alt text button -->
          <Button variant="ghost" size="sm" class="h-7 px-2 text-xs gap-1" @click="openAltDialog">
            <Type class="w-3 h-3" />
            Alt
          </Button>

          <div class="w-px h-5 bg-border mx-1" />

          <!-- Size display -->
          <span class="text-xs text-muted-foreground whitespace-nowrap">
            {{ Math.round(currentWidth) }} x {{ Math.round(currentHeight) }}
          </span>

          <div class="w-px h-5 bg-border mx-1" />
        </template>

        <!-- Delete button (always visible) -->
        <Button variant="ghost" size="sm" class="h-7 w-7 p-0 text-destructive hover:text-destructive" @click="deleteImage">
          <Trash2 class="w-4 h-4" />
        </Button>
      </div>
    </figure>

    <!-- Alt Text Dialog -->
    <Dialog :open="altDialogOpen" @update:open="altDialogOpen = $event">
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Image Properties</DialogTitle>
          <DialogDescription>Edit the alt text and title for this image.</DialogDescription>
        </DialogHeader>
        <div class="grid gap-4 py-4">
          <div class="grid gap-2">
            <Label for="alt-text">Alt Text</Label>
            <Input
              id="alt-text"
              v-model="editAlt"
              placeholder="Describe this image for accessibility..."
            />
            <p class="text-xs text-muted-foreground">
              Alt text helps screen readers describe the image to visually impaired users.
            </p>
          </div>
          <div class="grid gap-2">
            <Label for="title-text">Title</Label>
            <Input
              id="title-text"
              v-model="editTitle"
              placeholder="Optional title shown on hover..."
            />
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" @click="altDialogOpen = false">Cancel</Button>
          <Button @click="saveAltText">Save</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </NodeViewWrapper>
</template>

<style scoped>
.resizable-image-wrapper {
  display: flex;
  justify-content: center;
}

.resizable-image-wrapper figure {
  transition: box-shadow 0.2s ease;
}
</style>
