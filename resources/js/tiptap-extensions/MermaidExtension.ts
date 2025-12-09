import { Node, mergeAttributes } from '@tiptap/core'
import { VueNodeViewRenderer } from '@tiptap/vue-3'
import MermaidNodeView from './MermaidNodeView.vue'

export interface MermaidOptions {
  HTMLAttributes: Record<string, unknown>
}

declare module '@tiptap/core' {
  interface Commands<ReturnType> {
    mermaid: {
      /**
       * Insert a mermaid diagram
       */
      insertMermaid: (code?: string) => ReturnType
      /**
       * Update mermaid diagram code
       */
      updateMermaid: (code: string) => ReturnType
      /**
       * Toggle mermaid view mode (code/diagram)
       */
      toggleMermaidView: () => ReturnType
    }
  }
}

export const Mermaid = Node.create<MermaidOptions>({
  name: 'mermaid',

  group: 'block',

  atom: true,

  draggable: true,

  addOptions() {
    return {
      HTMLAttributes: {
        class: 'mermaid-block',
      },
    }
  },

  addAttributes() {
    return {
      code: {
        default: `flowchart TD
    A[Start] --> B{Decision}
    B -->|Yes| C[Result 1]
    B -->|No| D[Result 2]`,
        parseHTML: (element) => {
          // Try to get code from data attribute first
          const dataCode = element.getAttribute('data-mermaid-code')
          if (dataCode) {
            return dataCode
          }
          // Fallback to inner text (for code blocks)
          const codeElement = element.querySelector('code')
          if (codeElement) {
            return codeElement.textContent || ''
          }
          return element.textContent || ''
        },
      },
      viewMode: {
        default: 'diagram', // 'code' | 'diagram'
        parseHTML: (element) => element.getAttribute('data-view-mode') || 'diagram',
      },
      error: {
        default: null,
        parseHTML: () => null,
      },
      diagramType: {
        default: 'flowchart',
        parseHTML: (element) => element.getAttribute('data-diagram-type') || 'flowchart',
      },
      scale: {
        default: 100,
        parseHTML: (element) => {
          const scale = element.getAttribute('data-scale')
          return scale ? parseInt(scale, 10) : 100
        },
      },
    }
  },

  parseHTML() {
    return [
      // Match our custom mermaid blocks
      {
        tag: 'div[data-mermaid]',
      },
      // Match code blocks with language-mermaid class
      {
        tag: 'pre',
        getAttrs: (node) => {
          if (typeof node === 'string') return false
          const element = node as HTMLElement
          const codeElement = element.querySelector('code.language-mermaid')
          if (codeElement) {
            return {}
          }
          return false
        },
      },
      // Match div with mermaid class (from rendered content)
      {
        tag: 'div.mermaid-container',
      },
    ]
  },

  renderHTML({ HTMLAttributes, node }) {
    return [
      'div',
      mergeAttributes(this.options.HTMLAttributes, HTMLAttributes, {
        'data-mermaid': '',
        'data-mermaid-code': node.attrs.code,
        'data-view-mode': node.attrs.viewMode,
        'data-diagram-type': node.attrs.diagramType,
        'data-scale': String(node.attrs.scale || 100),
      }),
      ['pre', {}, ['code', { class: 'language-mermaid' }, node.attrs.code]],
    ]
  },

  addNodeView() {
    return VueNodeViewRenderer(MermaidNodeView)
  },

  addCommands() {
    return {
      insertMermaid:
        (code?: string) =>
        ({ commands }) => {
          const defaultCode =
            code ||
            `flowchart TD
    A[Start] --> B{Decision}
    B -->|Yes| C[Result 1]
    B -->|No| D[Result 2]`

          return commands.insertContent({
            type: this.name,
            attrs: {
              code: defaultCode,
              viewMode: 'diagram',
              diagramType: detectDiagramType(defaultCode),
            },
          })
        },

      updateMermaid:
        (code: string) =>
        ({ commands, state }) => {
          const { selection } = state
          const node = state.doc.nodeAt(selection.from)

          if (node?.type.name !== this.name) {
            return false
          }

          return commands.updateAttributes(this.name, {
            code,
            diagramType: detectDiagramType(code),
            error: null,
          })
        },

      toggleMermaidView:
        () =>
        ({ commands, state }) => {
          const { selection } = state
          const node = state.doc.nodeAt(selection.from)

          if (node?.type.name !== this.name) {
            return false
          }

          const newViewMode = node.attrs.viewMode === 'diagram' ? 'code' : 'diagram'

          return commands.updateAttributes(this.name, {
            viewMode: newViewMode,
          })
        },
    }
  },

  addKeyboardShortcuts() {
    return {
      // Insert mermaid diagram with Mod+Shift+M
      'Mod-Shift-m': () => this.editor.commands.insertMermaid(),
    }
  },
})

/**
 * Detect the type of mermaid diagram from the code
 */
function detectDiagramType(code: string): string {
  const trimmedCode = code.trim().toLowerCase()

  if (trimmedCode.startsWith('flowchart') || trimmedCode.startsWith('graph')) {
    return 'flowchart'
  }
  if (trimmedCode.startsWith('sequencediagram') || trimmedCode.startsWith('sequence')) {
    return 'sequence'
  }
  if (trimmedCode.startsWith('classdiagram') || trimmedCode.startsWith('class')) {
    return 'class'
  }
  if (trimmedCode.startsWith('statediagram') || trimmedCode.startsWith('state')) {
    return 'state'
  }
  if (trimmedCode.startsWith('erdiagram') || trimmedCode.startsWith('er')) {
    return 'er'
  }
  if (trimmedCode.startsWith('gantt')) {
    return 'gantt'
  }
  if (trimmedCode.startsWith('pie')) {
    return 'pie'
  }
  if (trimmedCode.startsWith('journey')) {
    return 'journey'
  }
  if (trimmedCode.startsWith('gitgraph')) {
    return 'git'
  }
  if (trimmedCode.startsWith('mindmap')) {
    return 'mindmap'
  }
  if (trimmedCode.startsWith('timeline')) {
    return 'timeline'
  }
  if (trimmedCode.startsWith('quadrantchart')) {
    return 'quadrant'
  }
  if (trimmedCode.startsWith('requirementdiagram')) {
    return 'requirement'
  }
  if (trimmedCode.startsWith('c4context') || trimmedCode.startsWith('c4container') || trimmedCode.startsWith('c4component')) {
    return 'c4'
  }

  return 'unknown'
}

export default Mermaid
