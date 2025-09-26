import { Node, mergeAttributes } from '@tiptap/core';
import { VueNodeViewRenderer } from '@tiptap/vue-3';
import CitationNodeView from './CitationNodeView.vue';

export const Citation = Node.create({
  name: 'citation',
  
  group: 'inline',
  inline: true,
  atom: true,
  
  addAttributes() {
    return {
      id: {
        default: null,
      },
      text: {
        default: '',
      },
      verified: {
        default: false,
      },
      confidence: {
        default: 0,
      },
      format: {
        default: 'apa',
      },
      doi: {
        default: null,
      },
      data: {
        default: {},
      },
    };
  },
  
  parseHTML() {
    return [
      {
        tag: 'span[data-citation]',
      },
    ];
  },
  
  renderHTML({ HTMLAttributes }) {
    return ['span', mergeAttributes(HTMLAttributes, { 'data-citation': '' })];
  },
  
  addNodeView() {
    return VueNodeViewRenderer(CitationNodeView);
  },
  
  addCommands() {
    return {
      insertCitation: (attributes) => ({ commands }) => {
        return commands.insertContent({
          type: this.name,
          attrs: attributes,
        });
      },
      
      updateCitation: (id, attributes) => ({ state, dispatch }) => {
        const { doc, tr } = state;
        let found = false;
        
        doc.descendants((node, pos) => {
          if (node.type.name === this.name && node.attrs.id === id) {
            tr.setNodeMarkup(pos, null, { ...node.attrs, ...attributes });
            found = true;
            return false;
          }
        });
        
        if (found) {
          dispatch(tr);
          return true;
        }
        
        return false;
      },
    };
  },
  
  addKeyboardShortcuts() {
    return {
      'Mod-Shift-c': () => this.editor.commands.insertCitation({
        text: '[Citation]',
        verified: false,
      }),
    };
  },
});