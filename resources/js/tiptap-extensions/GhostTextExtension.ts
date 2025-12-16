import { Extension } from '@tiptap/core'
import { Plugin, PluginKey } from 'prosemirror-state'
import { Decoration, DecorationSet } from 'prosemirror-view'

export interface GhostTextState {
  text: string | null
  format: 'text' | 'html'
  position: number
  isVisible: boolean
}

type GhostMeta =
  | { type: 'set'; text: string; format: 'text' | 'html'; position: number }
  | { type: 'clear' }

const pluginKey = new PluginKey<GhostTextState>('ghostText')

declare module '@tiptap/core' {
  interface Commands<ReturnType> {
    ghostText: {
      setGhostText: (params: { text: string; format?: 'text' | 'html'; position?: number }) => ReturnType
      clearGhostText: () => ReturnType
    }
  }
}

export const GhostTextExtension = Extension.create({
  name: 'ghostText',

  addOptions() {
    return {
      onManualTrigger: null as null | (() => void),
      onAccepted: null as null | ((text: string) => void),
      onDismissed: null as null | (() => void),
    }
  },

  addCommands() {
    return {
      setGhostText:
        ({ text, position, format }) =>
        ({ tr, state, dispatch }) => {
          const pos = typeof position === 'number' ? position : state.selection.to
          tr.setMeta(pluginKey, { type: 'set', text, format: format ?? 'text', position: pos } satisfies GhostMeta)
          dispatch?.(tr)
          return true
        },
      clearGhostText:
        () =>
        ({ tr, dispatch }) => {
          tr.setMeta(pluginKey, { type: 'clear' } satisfies GhostMeta)
          dispatch?.(tr)
          return true
        },
    }
  },

  addKeyboardShortcuts() {
    return {
      Tab: () => {
        const pluginState = pluginKey.getState(this.editor.state)
        if (!pluginState?.isVisible || !pluginState.text) return false
        if (!this.editor.state.selection.empty) return false

        const insertAt = pluginState.position
        const text = pluginState.text

        this.editor
          .chain()
          .focus()
          .insertContentAt({ from: insertAt, to: insertAt }, text)
          .command(({ tr, dispatch }) => {
            tr.setMeta(pluginKey, { type: 'clear' } satisfies GhostMeta)
            dispatch?.(tr)
            return true
          })
          .run()

        this.options.onAccepted?.(text)
        return true
      },
      Escape: () => {
        const pluginState = pluginKey.getState(this.editor.state)
        if (!pluginState?.isVisible) return false

        this.editor.commands.clearGhostText()
        this.options.onDismissed?.()
        return true
      },
      'Mod-Space': () => {
        this.options.onManualTrigger?.()
        return true
      },
    }
  },

  addProseMirrorPlugins() {
    return [
      new Plugin<GhostTextState>({
        key: pluginKey,
        state: {
          init: () => ({ text: null, format: 'text', position: 0, isVisible: false }),
          apply: (tr, prev) => {
            const meta = tr.getMeta(pluginKey) as GhostMeta | undefined
            if (meta?.type === 'clear') {
              return { text: null, format: 'text', position: 0, isVisible: false }
            }
            if (meta?.type === 'set') {
              return { text: meta.text, format: meta.format, position: meta.position, isVisible: true }
            }

            if (tr.docChanged) {
              if (prev.isVisible) {
                this.options.onDismissed?.()
              }
              return { text: null, format: 'text', position: 0, isVisible: false }
            }

            if (tr.selectionSet && prev.isVisible) {
              // Cursor moves should dismiss to avoid suggestions "following" into headings/other blocks.
              this.options.onDismissed?.()
              return { text: null, format: 'text', position: 0, isVisible: false }
            }

            return prev
          },
        },
        props: {
          decorations: (state) => {
            const pluginState = pluginKey.getState(state)
            if (!pluginState?.isVisible || !pluginState.text) return null

            const pos = Math.min(Math.max(0, pluginState.position), state.doc.content.size)

            const deco = Decoration.widget(
              pos,
              () => {
                const span = document.createElement('span')
                span.className = 'tiptap-ghost-text'
                if (pluginState.format === 'html' && pluginState.text.includes('<')) {
                  const tmp = document.createElement('div')
                  tmp.innerHTML = pluginState.text
                  span.textContent = (tmp.textContent || '').trim()
                } else {
                  span.textContent = pluginState.text ?? ''
                }
                return span
              },
              { side: 1 },
            )

            return DecorationSet.create(state.doc, [deco])
          },
        },
      }),
    ]
  },
})
