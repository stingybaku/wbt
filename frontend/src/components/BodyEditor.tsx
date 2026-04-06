import { useEffect, useRef } from 'react';
import { useEditor, EditorContent } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import { plainToTiptap, tiptapToPlain, type TiptapDoc } from '../lib/richTextSerializer';

type Props = {
  value: string;
  onChange: (value: string) => void;
  placeholder?: string;
};

export function BodyEditor({ value, onChange, placeholder }: Props) {
  // Track the last serialized plain-text so we can distinguish user edits
  // from external value changes without triggering infinite update loops.
  const lastPlainRef = useRef(value);

  const editor = useEditor({
    extensions: [
      StarterKit.configure({
        heading:         false,
        codeBlock:       false,
        blockquote:      false,
        horizontalRule:  false,
        strike:          false,
        code:            false,
      }),
    ],
    content: plainToTiptap(value),
    editorProps: {
      attributes: {
        class: 'body-editor-content',
        ...(placeholder ? { 'data-placeholder': placeholder } : {}),
      },
    },
    onUpdate({ editor }) {
      const plain = tiptapToPlain(editor.getJSON() as TiptapDoc);
      lastPlainRef.current = plain;
      onChange(plain);
    },
  });

  // Sync external value changes (e.g. undo at the module level)
  useEffect(() => {
    if (!editor || value === lastPlainRef.current) return;
    lastPlainRef.current = value;
    editor.commands.setContent(plainToTiptap(value), { emitUpdate: false });
  }, [value, editor]);

  if (!editor) return null;

  const btnClass = (active: boolean) =>
    `body-editor-btn${active ? ' active' : ''}`;

  return (
    <div className="body-editor">
      <div className="body-editor-toolbar" onMouseDown={e => e.preventDefault()}>
        <button
          type="button"
          className={btnClass(editor.isActive('bold'))}
          onClick={() => editor.chain().focus().toggleBold().run()}
          title="Bold (Ctrl+B)"
        >
          <strong>B</strong>
        </button>
        <button
          type="button"
          className={btnClass(editor.isActive('italic'))}
          onClick={() => editor.chain().focus().toggleItalic().run()}
          title="Italic (Ctrl+I)"
        >
          <em>I</em>
        </button>
        <div className="body-editor-divider" />
        <button
          type="button"
          className={btnClass(editor.isActive('bulletList'))}
          onClick={() => editor.chain().focus().toggleBulletList().run()}
          title="Bullet list"
        >
          ≡
        </button>
        <button
          type="button"
          className={btnClass(editor.isActive('orderedList'))}
          onClick={() => editor.chain().focus().toggleOrderedList().run()}
          title="Numbered list"
        >
          №
        </button>
      </div>
      <EditorContent editor={editor} />
    </div>
  );
}
