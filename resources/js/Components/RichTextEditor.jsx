import Image from '@tiptap/extension-image';
import Link from '@tiptap/extension-link';
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor } from '@tiptap/react';
import { useEffect } from 'react';

export default function RichTextEditor({ value, onChange }) {
    const editor = useEditor({
        extensions: [
            StarterKit,
            Link.configure({ openOnClick: false, HTMLAttributes: { rel: 'noopener noreferrer', target: '_blank' } }),
            Image.configure({ HTMLAttributes: { class: 'blog-editor-image' } }),
        ],
        content: value || '<p></p>',
        editorProps: { attributes: { class: 'blog-editor-content min-h-[360px] focus:outline-none' } },
        onUpdate: ({ editor: currentEditor }) => onChange(currentEditor.getHTML()),
    });

    useEffect(() => {
        if (editor && value !== editor.getHTML()) editor.commands.setContent(value || '<p></p>', { emitUpdate: false });
    }, [editor, value]);

    if (!editor) return null;

    const addLink = () => {
        const previous = editor.getAttributes('link').href || '';
        const url = window.prompt('Pega la URL del enlace:', previous);
        if (url === null) return;
        if (!url) return editor.chain().focus().extendMarkRange('link').unsetLink().run();
        editor.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
    };
    const addImage = () => {
        const url = window.prompt('Pega la URL de la imagen (por ejemplo, Cloudinary):');
        if (url) editor.chain().focus().setImage({ src: url, alt: 'Imagen del artículo' }).run();
    };

    const button = (label, action, active = false) => (
        <button type="button" onClick={action} className={`rounded-lg px-3 py-2 text-xs font-bold transition ${active ? 'bg-blue-600 text-white' : 'bg-white text-slate-700 hover:bg-slate-100'}`}>{label}</button>
    );

    return (
        <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div className="flex flex-wrap gap-1 border-b border-slate-200 bg-slate-50 p-2">
                {button('Negrita', () => editor.chain().focus().toggleBold().run(), editor.isActive('bold'))}
                {button('Cursiva', () => editor.chain().focus().toggleItalic().run(), editor.isActive('italic'))}
                {button('Título', () => editor.chain().focus().toggleHeading({ level: 2 }).run(), editor.isActive('heading', { level: 2 }))}
                {button('Subtítulo', () => editor.chain().focus().toggleHeading({ level: 3 }).run(), editor.isActive('heading', { level: 3 }))}
                {button('Lista', () => editor.chain().focus().toggleBulletList().run(), editor.isActive('bulletList'))}
                {button('Numerada', () => editor.chain().focus().toggleOrderedList().run(), editor.isActive('orderedList'))}
                {button('Cita', () => editor.chain().focus().toggleBlockquote().run(), editor.isActive('blockquote'))}
                {button('Enlace', addLink, editor.isActive('link'))}
                {button('Imagen URL', addImage)}
                {button('Deshacer', () => editor.chain().focus().undo().run())}
                {button('Rehacer', () => editor.chain().focus().redo().run())}
            </div>
            <EditorContent editor={editor} />
        </div>
    );
}
