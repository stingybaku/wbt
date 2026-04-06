/**
 * Serialization between the plain-text format used by WBTPlayer's RichText
 * renderer and Tiptap's JSON document format.
 *
 * Plain-text format rules:
 *   - Lines separated by \n
 *   - "1. text"  → ordered list item
 *   - "- text"   → unordered list item
 *   - "**text**" → bold inline
 *   - "_text_"   → italic inline
 *   - anything else → paragraph
 */

// ─── Types (minimal subset of Tiptap's JSONContent) ──────────────────────────

type Mark = { type: 'bold' } | { type: 'italic' };
type TextNode = { type: 'text'; text: string; marks?: Mark[] };
type InlineNode = TextNode;

type ParagraphNode   = { type: 'paragraph'; content?: InlineNode[] };
type ListItemNode    = { type: 'listItem'; content: [ParagraphNode] };
type BulletListNode  = { type: 'bulletList'; content: ListItemNode[] };
type OrderedListNode = { type: 'orderedList'; attrs: { start: number }; content: ListItemNode[] };
type BlockNode       = ParagraphNode | BulletListNode | OrderedListNode;
export type TiptapDoc = { type: 'doc'; content: BlockNode[] };

// ─── Plain text → Tiptap ─────────────────────────────────────────────────────

export function plainToTiptap(text: string): TiptapDoc {
  const lines = (text ?? '').split('\n');
  const blocks: BlockNode[] = [];
  let i = 0;

  while (i < lines.length) {
    const line = lines[i];
    const olMatch = line.match(/^(\d+)\.\s+(.*)/);
    const ulMatch = line.match(/^[-•*]\s+(.*)/);

    if (olMatch) {
      const items: ListItemNode[] = [];
      while (i < lines.length && lines[i].match(/^\d+\.\s+/)) {
        const m = lines[i].match(/^\d+\.\s+(.*)/);
        items.push({ type: 'listItem', content: [{ type: 'paragraph', content: parseInline(m![1]) }] });
        i++;
      }
      blocks.push({ type: 'orderedList', attrs: { start: 1 }, content: items });
      continue;
    }

    if (ulMatch) {
      const items: ListItemNode[] = [];
      while (i < lines.length && lines[i].match(/^[-•*]\s+/)) {
        const m = lines[i].match(/^[-•*]\s+(.*)/);
        items.push({ type: 'listItem', content: [{ type: 'paragraph', content: parseInline(m![1]) }] });
        i++;
      }
      blocks.push({ type: 'bulletList', content: items });
      continue;
    }

    if (line.trim()) {
      blocks.push({ type: 'paragraph', content: parseInline(line) });
    } else if (i > 0) {
      // Preserve intentional blank lines as empty paragraphs (only between content)
      blocks.push({ type: 'paragraph' });
    }
    i++;
  }

  return { type: 'doc', content: blocks.length ? blocks : [{ type: 'paragraph' }] };
}

/** Parse inline bold/italic markers from a plain-text string. */
function parseInline(text: string): InlineNode[] {
  const nodes: InlineNode[] = [];
  // Matches: **bold**, _italic_, or plain text runs
  const re = /\*\*([^*]+)\*\*|_([^_]+)_|([^*_]+)/g;
  let m: RegExpExecArray | null;
  while ((m = re.exec(text)) !== null) {
    if (m[1] !== undefined) {
      nodes.push({ type: 'text', text: m[1], marks: [{ type: 'bold' }] });
    } else if (m[2] !== undefined) {
      nodes.push({ type: 'text', text: m[2], marks: [{ type: 'italic' }] });
    } else if (m[3] !== undefined) {
      nodes.push({ type: 'text', text: m[3] });
    }
  }
  return nodes.length ? nodes : [{ type: 'text', text: '' }];
}

// ─── Tiptap → Plain text ─────────────────────────────────────────────────────

export function tiptapToPlain(doc: TiptapDoc): string {
  if (!doc?.content) return '';
  return doc.content
    .map(serializeBlock)
    .filter(s => s !== null)
    .join('\n');
}

function serializeBlock(node: BlockNode): string | null {
  switch (node.type) {
    case 'paragraph':
      return serializeInline(node.content ?? []);
    case 'bulletList':
      return node.content
        .map(item => '- ' + serializeInline(item.content[0]?.content ?? []))
        .join('\n');
    case 'orderedList':
      return node.content
        .map((item, i) => `${i + 1}. ` + serializeInline(item.content[0]?.content ?? []))
        .join('\n');
    default:
      return null;
  }
}

function serializeInline(nodes: InlineNode[]): string {
  return (nodes ?? []).map(n => {
    const marks = (n.marks ?? []).map(m => m.type);
    let text = ('text' in n ? n.text : '') ?? '';
    if (marks.includes('bold'))   text = `**${text}**`;
    if (marks.includes('italic')) text = `_${text}_`;
    return text;
  }).join('');
}
