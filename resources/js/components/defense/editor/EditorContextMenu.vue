<script setup lang="ts">
/**
 * EditorContextMenu - Right-click context menu for the WYSIWYG editor
 * Provides quick access to common operations like cut, copy, paste, delete, etc.
 */
import { ref, computed, watch } from 'vue';
import {
  ContextMenu,
  ContextMenuContent,
  ContextMenuItem,
  ContextMenuSeparator,
  ContextMenuShortcut,
  ContextMenuSub,
  ContextMenuSubContent,
  ContextMenuSubTrigger,
  ContextMenuTrigger,
} from '@/components/ui/context-menu';
import {
  Copy,
  Clipboard,
  Scissors,
  Trash2,
  CopyPlus,
  Lock,
  Unlock,
  ArrowUpToLine,
  ArrowDownToLine,
  ArrowUp,
  ArrowDown,
  Group,
  Ungroup,
  Type,
  Image,
  Square,
  BarChart3,
  Table,
  Layers,
} from 'lucide-vue-next';

interface Props {
  hasSelection: boolean;
  isMultiSelection: boolean;
  canPaste: boolean;
  isLocked?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  isLocked: false,
});

const emit = defineEmits<{
  cut: [];
  copy: [];
  paste: [];
  duplicate: [];
  delete: [];
  lock: [];
  unlock: [];
  bringToFront: [];
  sendToBack: [];
  bringForward: [];
  sendBackward: [];
  group: [];
  ungroup: [];
  addText: [];
  addShape: [shapeType: string];
  addImage: [];
  addChart: [];
  addTable: [];
}>();

// Handle actions
function handleCut() {
  emit('cut');
}

function handleCopy() {
  emit('copy');
}

function handlePaste() {
  emit('paste');
}

function handleDuplicate() {
  emit('duplicate');
}

function handleDelete() {
  emit('delete');
}

function handleLock() {
  emit('lock');
}

function handleUnlock() {
  emit('unlock');
}

function handleBringToFront() {
  emit('bringToFront');
}

function handleSendToBack() {
  emit('sendToBack');
}

function handleBringForward() {
  emit('bringForward');
}

function handleSendBackward() {
  emit('sendBackward');
}

function handleGroup() {
  emit('group');
}

function handleUngroup() {
  emit('ungroup');
}

function handleAddText() {
  emit('addText');
}

function handleAddShape(type: string) {
  emit('addShape', type);
}

function handleAddImage() {
  emit('addImage');
}

function handleAddChart() {
  emit('addChart');
}

function handleAddTable() {
  emit('addTable');
}
</script>

<template>
  <ContextMenu>
    <ContextMenuTrigger as-child>
      <slot />
    </ContextMenuTrigger>

    <ContextMenuContent class="w-56">
      <!-- Clipboard Operations -->
      <template v-if="hasSelection">
        <ContextMenuItem @select="handleCut" :disabled="isLocked">
          <Scissors class="mr-2 h-4 w-4" />
          Cut
          <ContextMenuShortcut>Ctrl+X</ContextMenuShortcut>
        </ContextMenuItem>
        <ContextMenuItem @select="handleCopy">
          <Copy class="mr-2 h-4 w-4" />
          Copy
          <ContextMenuShortcut>Ctrl+C</ContextMenuShortcut>
        </ContextMenuItem>
      </template>

      <ContextMenuItem @select="handlePaste" :disabled="!canPaste">
        <Clipboard class="mr-2 h-4 w-4" />
        Paste
        <ContextMenuShortcut>Ctrl+V</ContextMenuShortcut>
      </ContextMenuItem>

      <template v-if="hasSelection">
        <ContextMenuItem @select="handleDuplicate" :disabled="isLocked">
          <CopyPlus class="mr-2 h-4 w-4" />
          Duplicate
          <ContextMenuShortcut>Ctrl+D</ContextMenuShortcut>
        </ContextMenuItem>

        <ContextMenuSeparator />

        <!-- Delete -->
        <ContextMenuItem @select="handleDelete" variant="destructive" :disabled="isLocked">
          <Trash2 class="mr-2 h-4 w-4" />
          Delete
          <ContextMenuShortcut>Del</ContextMenuShortcut>
        </ContextMenuItem>

        <ContextMenuSeparator />

        <!-- Lock/Unlock -->
        <ContextMenuItem v-if="!isLocked" @select="handleLock">
          <Lock class="mr-2 h-4 w-4" />
          Lock
        </ContextMenuItem>
        <ContextMenuItem v-else @select="handleUnlock">
          <Unlock class="mr-2 h-4 w-4" />
          Unlock
        </ContextMenuItem>

        <ContextMenuSeparator />

        <!-- Arrange Submenu -->
        <ContextMenuSub>
          <ContextMenuSubTrigger>
            <Layers class="mr-2 h-4 w-4" />
            Arrange
          </ContextMenuSubTrigger>
          <ContextMenuSubContent class="w-48">
            <ContextMenuItem @select="handleBringToFront" :disabled="isLocked">
              <ArrowUpToLine class="mr-2 h-4 w-4" />
              Bring to Front
            </ContextMenuItem>
            <ContextMenuItem @select="handleBringForward" :disabled="isLocked">
              <ArrowUp class="mr-2 h-4 w-4" />
              Bring Forward
            </ContextMenuItem>
            <ContextMenuItem @select="handleSendBackward" :disabled="isLocked">
              <ArrowDown class="mr-2 h-4 w-4" />
              Send Backward
            </ContextMenuItem>
            <ContextMenuItem @select="handleSendToBack" :disabled="isLocked">
              <ArrowDownToLine class="mr-2 h-4 w-4" />
              Send to Back
            </ContextMenuItem>
          </ContextMenuSubContent>
        </ContextMenuSub>

        <!-- Group/Ungroup (only for multi-selection) -->
        <template v-if="isMultiSelection">
          <ContextMenuItem @select="handleGroup" :disabled="isLocked">
            <Group class="mr-2 h-4 w-4" />
            Group
            <ContextMenuShortcut>Ctrl+G</ContextMenuShortcut>
          </ContextMenuItem>
        </template>
        <ContextMenuItem @select="handleUngroup">
          <Ungroup class="mr-2 h-4 w-4" />
          Ungroup
          <ContextMenuShortcut>Ctrl+Shift+G</ContextMenuShortcut>
        </ContextMenuItem>
      </template>

      <!-- Add Elements (when no selection) -->
      <template v-if="!hasSelection">
        <ContextMenuSeparator />

        <ContextMenuSub>
          <ContextMenuSubTrigger>
            <Square class="mr-2 h-4 w-4" />
            Add Element
          </ContextMenuSubTrigger>
          <ContextMenuSubContent class="w-48">
            <ContextMenuItem @select="handleAddText">
              <Type class="mr-2 h-4 w-4" />
              Text Box
            </ContextMenuItem>

            <!-- Shapes Submenu -->
            <ContextMenuSub>
              <ContextMenuSubTrigger>
                <Square class="mr-2 h-4 w-4" />
                Shape
              </ContextMenuSubTrigger>
              <ContextMenuSubContent class="w-40">
                <ContextMenuItem @select="handleAddShape('rectangle')">
                  Rectangle
                </ContextMenuItem>
                <ContextMenuItem @select="handleAddShape('rounded-rectangle')">
                  Rounded Rectangle
                </ContextMenuItem>
                <ContextMenuItem @select="handleAddShape('circle')">
                  Circle
                </ContextMenuItem>
                <ContextMenuItem @select="handleAddShape('triangle')">
                  Triangle
                </ContextMenuItem>
                <ContextMenuItem @select="handleAddShape('arrow')">
                  Arrow
                </ContextMenuItem>
                <ContextMenuItem @select="handleAddShape('line')">
                  Line
                </ContextMenuItem>
              </ContextMenuSubContent>
            </ContextMenuSub>

            <ContextMenuItem @select="handleAddImage">
              <Image class="mr-2 h-4 w-4" />
              Image
            </ContextMenuItem>
            <ContextMenuItem @select="handleAddChart">
              <BarChart3 class="mr-2 h-4 w-4" />
              Chart
            </ContextMenuItem>
            <ContextMenuItem @select="handleAddTable">
              <Table class="mr-2 h-4 w-4" />
              Table
            </ContextMenuItem>
          </ContextMenuSubContent>
        </ContextMenuSub>
      </template>
    </ContextMenuContent>
  </ContextMenu>
</template>
