import './bootstrap';
import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Highlight from '@tiptap/extension-highlight';
import Table from '@tiptap/extension-table';
import TableRow from '@tiptap/extension-table-row';
import TableCell from '@tiptap/extension-table-cell';
import TableHeader from '@tiptap/extension-table-header';
import Image from '@tiptap/extension-image';
import Link from '@tiptap/extension-link';

/**
 * Rich text editor for official notes (Admin → Departments & notes).
 * Usage: <div x-data="noteEditor($wire.entangle('body.en'))"> … <div x-ref="editor"></div></div>
 * The Tiptap instance lives outside Alpine's reactive state (proxies break it).
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('noteEditor', (model) => {
        let editor = null;

        return {
            content: model,
            active: {},
            dir: 'ltr',

            init() {
                editor = new Editor({
                    element: this.$refs.editor,
                    extensions: [
                        StarterKit.configure({ heading: { levels: [2, 3] } }),
                        Highlight,
                        Table.configure({ resizable: false }),
                        TableRow, TableHeader, TableCell,
                        Image,
                        Link.configure({ openOnClick: false }),
                    ],
                    content: this.content || '',
                    editorProps: { attributes: { class: 'prose-note ProseMirror px-4 py-3 text-[15px]' } },
                    onUpdate: ({ editor }) => {
                        this.content = editor.getHTML();
                        this.refresh();
                    },
                    onSelectionUpdate: () => this.refresh(),
                });

                // When Livewire swaps the language tab the bound value changes underneath us.
                this.$watch('content', (value) => {
                    if (editor && value !== editor.getHTML()) {
                        editor.commands.setContent(value || '', false);
                    }
                });
            },

            refresh() {
                if (!editor) return;
                this.active = {
                    bold: editor.isActive('bold'),
                    italic: editor.isActive('italic'),
                    h2: editor.isActive('heading', { level: 2 }),
                    h3: editor.isActive('heading', { level: 3 }),
                    mark: editor.isActive('highlight'),
                    bullet: editor.isActive('bulletList'),
                    ordered: editor.isActive('orderedList'),
                    table: editor.isActive('table'),
                };
            },

            run(cmd, ...args) {
                if (!editor) return;
                const chain = editor.chain().focus();
                ({
                    bold: () => chain.toggleBold(),
                    italic: () => chain.toggleItalic(),
                    h2: () => chain.toggleHeading({ level: 2 }),
                    h3: () => chain.toggleHeading({ level: 3 }),
                    mark: () => chain.toggleHighlight(),
                    bullet: () => chain.toggleBulletList(),
                    ordered: () => chain.toggleOrderedList(),
                    table: () => chain.insertTable({ rows: 3, cols: 2, withHeaderRow: true }),
                    addRow: () => chain.addRowAfter(),
                    addCol: () => chain.addColumnAfter(),
                    delRow: () => chain.deleteRow(),
                    delTable: () => chain.deleteTable(),
                    image: () => chain.setImage({ src: args[0] }),
                    undo: () => chain.undo(),
                    redo: () => chain.redo(),
                })[cmd]().run();
                this.refresh();
            },

            destroy() {
                editor?.destroy();
            },
        };
    });
});
