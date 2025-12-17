import { Node, mergeAttributes } from '@tiptap/core'
import { VueNodeViewRenderer } from '@tiptap/vue-3'
import ImageNodeView from './ImageNodeView.vue'
import { Plugin, PluginKey } from '@tiptap/pm/state'

export interface ResizableImageOptions {
  HTMLAttributes: Record<string, unknown>
  maxWidth: number
}

declare module '@tiptap/core' {
  interface Commands<ReturnType> {
    resizableImage: {
      /**
       * Set an image
       */
      setImage: (options: {
        src: string
        alt?: string
        title?: string
        width?: number
        height?: number
        caption?: string
      }) => ReturnType
      /**
       * Update image size
       */
      updateImageSize: (options: { width: number; height: number }) => ReturnType
      /**
       * Set image alignment
       */
      setImageAlignment: (alignment: 'left' | 'center' | 'right') => ReturnType
    }
  }
}

export const ResizableImage = Node.create<ResizableImageOptions>({
  name: 'resizableImage',

  group: 'block',

  atom: true,

  draggable: true,

  addOptions() {
    return {
      HTMLAttributes: {
        class: 'resizable-image-block',
      },
      maxWidth: 800,
    }
  },

  addAttributes() {
    return {
      src: {
        default: null,
        parseHTML: (element) => {
          const img = element.querySelector('img')
          return img?.getAttribute('src') || element.getAttribute('src')
        },
      },
      alt: {
        default: '',
        parseHTML: (element) => {
          const img = element.querySelector('img')
          return img?.getAttribute('alt') || element.getAttribute('alt') || ''
        },
      },
      title: {
        default: '',
        parseHTML: (element) => {
          const img = element.querySelector('img')
          return img?.getAttribute('title') || element.getAttribute('title') || ''
        },
      },
      width: {
        default: null,
        parseHTML: (element) => {
          const img = element.querySelector('img')
          const width = img?.getAttribute('width') || element.getAttribute('data-width')
          return width ? parseInt(width, 10) : null
        },
      },
      height: {
        default: null,
        parseHTML: (element) => {
          const img = element.querySelector('img')
          const height = img?.getAttribute('height') || element.getAttribute('data-height')
          return height ? parseInt(height, 10) : null
        },
      },
      alignment: {
        default: 'center',
        parseHTML: (element) => element.getAttribute('data-alignment') || 'center',
      },
      aspectRatio: {
        default: null,
        parseHTML: (element) => {
          const ratio = element.getAttribute('data-aspect-ratio')
          return ratio ? parseFloat(ratio) : null
        },
      },
      caption: {
        default: '',
        parseHTML: (element) => {
          const figcaption = element.querySelector('figcaption')
          return figcaption?.textContent || element.getAttribute('data-caption') || ''
        },
      },
    }
  },

  parseHTML() {
    return [
      {
        tag: 'figure[data-resizable-image]',
      },
      {
        tag: 'div[data-resizable-image]',
      },
      {
        tag: 'img[src]',
        getAttrs: (node) => {
          if (typeof node === 'string') return false
          const element = node as HTMLElement
          return {
            src: element.getAttribute('src'),
            alt: element.getAttribute('alt'),
            title: element.getAttribute('title'),
            width: element.getAttribute('width') ? parseInt(element.getAttribute('width')!, 10) : null,
            height: element.getAttribute('height') ? parseInt(element.getAttribute('height')!, 10) : null,
          }
        },
      },
    ]
  },

  renderHTML({ HTMLAttributes, node }) {
    const attrs = mergeAttributes(this.options.HTMLAttributes, {
      'data-resizable-image': '',
      'data-alignment': node.attrs.alignment,
      'data-width': node.attrs.width,
      'data-height': node.attrs.height,
      'data-aspect-ratio': node.attrs.aspectRatio,
      'data-caption': node.attrs.caption || '',
    })

    const imgAttrs: Record<string, string | number | null> = {
      src: HTMLAttributes.src || node.attrs.src,
      alt: node.attrs.alt || '',
      title: node.attrs.title || '',
    }

    if (node.attrs.width) {
      imgAttrs.width = node.attrs.width
    }
    if (node.attrs.height) {
      imgAttrs.height = node.attrs.height
    }

    const children: any[] = [['img', imgAttrs]]

    if (node.attrs.caption) {
      children.push(['figcaption', {}, node.attrs.caption])
    }

    return ['figure', attrs, ...children]
  },

  addNodeView() {
    return VueNodeViewRenderer(ImageNodeView)
  },

  addCommands() {
    return {
      setImage:
        (options) =>
        ({ commands }) => {
          return commands.insertContent({
            type: this.name,
            attrs: {
              src: options.src,
              alt: options.alt || '',
              title: options.title || '',
              width: options.width || null,
              height: options.height || null,
              caption: options.caption || '',
              aspectRatio: options.width && options.height ? options.width / options.height : null,
            },
          })
        },

      updateImageSize:
        (options) =>
        ({ commands }) => {
          return commands.updateAttributes(this.name, {
            width: options.width,
            height: options.height,
          })
        },

      setImageAlignment:
        (alignment) =>
        ({ commands }) => {
          return commands.updateAttributes(this.name, {
            alignment,
          })
        },
    }
  },

  addProseMirrorPlugins() {
    return [
      new Plugin({
        key: new PluginKey('resizableImageDropPaste'),
        props: {
          handleDrop: (view, event, _slice, moved) => {
            if (moved || !event.dataTransfer?.files?.length) {
              return false
            }

            const file = event.dataTransfer.files[0]
            if (!file.type.startsWith('image/')) {
              return false
            }

            event.preventDefault()

            // Dispatch custom event for the parent component to handle upload
            document.dispatchEvent(
              new CustomEvent('tiptap-image-drop', {
                detail: { file, view, pos: view.posAtCoords({ left: event.clientX, top: event.clientY }) },
              })
            )

            return true
          },

          handlePaste: (view, event) => {
            const items = event.clipboardData?.items
            if (!items) return false

            for (const item of items) {
              if (item.type.startsWith('image/')) {
                event.preventDefault()
                const file = item.getAsFile()
                if (file) {
                  // Dispatch custom event for the parent component to handle upload
                  document.dispatchEvent(
                    new CustomEvent('tiptap-image-paste', {
                      detail: { file, view },
                    })
                  )
                }
                return true
              }
            }

            return false
          },
        },
      }),
    ]
  },
})

export default ResizableImage
