import React, { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { fetchProject, fetchModule, updateModule } from '../lib/api';
import { useAuth } from '../context/AuthContext';
import type { Project, ModuleRecord } from '../lib/types';
import type { Module, Screen, Interaction, ImageTextLayout } from '../lib/moduleSchema';
import type { } from '../lib/moduleSchema';
import { ImagePicker, type PickedImage } from './ImagePicker';
import { BodyEditor } from './BodyEditor';

// ─── Page wrapper ─────────────────────────────────────────────────────────────

export function ModuleReviewPage() {
  const { id } = useParams<{ id: string }>();
  const { token } = useAuth();
  const [project, setProject] = useState<Project | null>(null);
  const [moduleRecord, setModuleRecord] = useState<ModuleRecord | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!token || !id) return;
    Promise.all([fetchProject(token, id), fetchModule(token, id)])
      .then(([pRes, mRes]) => {
        setProject(pRes.project);
        setModuleRecord(mRes.module);
      })
      .catch(err => setError(err instanceof Error ? err.message : 'Failed to load'))
      .finally(() => setLoading(false));
  }, [token, id]);

  if (loading) return <div className="editor-shell"><p className="muted" style={{ padding: '2rem' }}>Loading…</p></div>;

  if (error || !project || !moduleRecord?.module_json) {
    return (
      <div className="editor-shell">
        <div style={{ padding: '2rem' }}>
          <p className="muted" style={{ color: '#ef4444', marginBottom: '1rem' }}>{error ?? 'Module not ready for review.'}</p>
          <Link to={`/projects/${id}`}>← Back to project</Link>
        </div>
      </div>
    );
  }

  return <ModuleEditor project={project} moduleRecord={moduleRecord} token={token!} onSaved={setModuleRecord} />;
}

// ─── Editor ───────────────────────────────────────────────────────────────────

type SelectedView = { kind: 'meta' } | { kind: 'screen'; index: number };

function ModuleEditor({ project, moduleRecord, token, onSaved }: {
  project: Project;
  moduleRecord: ModuleRecord;
  token: string;
  onSaved: (updated: ModuleRecord) => void;
}) {
  const [draft, setDraft] = useState<Module>(() => JSON.parse(JSON.stringify(moduleRecord.module_json!)));
  const [selected, setSelected] = useState<SelectedView>({ kind: 'meta' });
  const [saving, setSaving] = useState(false);
  const [saveError, setSaveError] = useState<string | null>(null);
  const [dirty, setDirty] = useState(false);

  function applyUpdate(updater: (d: Module) => void) {
    setDraft(prev => { const next: Module = JSON.parse(JSON.stringify(prev)); updater(next); return next; });
    setDirty(true);
    setSaveError(null);
  }

  async function handleSave() {
    setSaving(true); setSaveError(null);
    try {
      const res = await updateModule(token, moduleRecord.project_id, draft);
      if (res.module) onSaved(res.module);
      setDirty(false);
    } catch (err: unknown) {
      setSaveError(err instanceof Error ? err.message : 'Save failed');
    } finally { setSaving(false); }
  }

  const openFlagCount = draft.review_flags.filter(f => f.status === 'open').length;

  return (
    <div className="editor-shell">
      <header className="editor-topbar">
        <div className="editor-topbar-left">
          <Link to={`/projects/${moduleRecord.project_id}`} className="muted">← {project.title}</Link>
          <span className="editor-topbar-title">{draft.meta.title}</span>
        </div>
        <div className="editor-topbar-right">
          {openFlagCount > 0 && <span className="flag-count-badge">{openFlagCount} open flag{openFlagCount !== 1 ? 's' : ''}</span>}
          {saveError && <span style={{ color: '#ef4444', fontSize: '0.875rem' }}>{saveError}</span>}
          <button onClick={handleSave} disabled={saving || !dirty}>
            {saving ? 'Saving…' : dirty ? 'Save changes' : 'Saved'}
          </button>
        </div>
      </header>

      <div className="editor-body">
        <nav className="editor-sidebar">
          <button
            className={`screen-item ${selected.kind === 'meta' ? 'active' : ''}`}
            onClick={() => setSelected({ kind: 'meta' })}
          >
            <span />
            <span className="screen-item-title">Module Info</span>
            <span className="screen-item-type">META</span>
          </button>

          {draft.screens.map((screen, i) => {
            const flagCount = draft.review_flags.filter(f => f.target_id === screen.id && f.status === 'open').length;
            const isActive = selected.kind === 'screen' && selected.index === i;

            function moveScreen(from: number, to: number) {
              applyUpdate(d => { const tmp = d.screens[from]; d.screens[from] = d.screens[to]; d.screens[to] = tmp; });
              if (selected.kind === 'screen') {
                if (selected.index === from) setSelected({ kind: 'screen', index: to });
                else if (selected.index === to) setSelected({ kind: 'screen', index: from });
              }
            }

            return (
              <div key={screen.id} className="screen-item-wrap">
                <button
                  className={`screen-item ${isActive ? 'active' : ''}`}
                  onClick={() => setSelected({ kind: 'screen', index: i })}
                >
                  <span className="screen-item-num">{i + 1}</span>
                  <span className="screen-item-title">{screen.title || '(untitled)'}</span>
                  <span className="screen-item-type">{screen.type}</span>
                  {flagCount > 0 && <span className="screen-item-flag">{flagCount}</span>}
                </button>
                <div className="screen-item-reorder">
                  <button type="button" className="reorder-btn" title="Move up" disabled={i === 0}
                    onClick={e => { e.stopPropagation(); moveScreen(i, i - 1); }}>↑</button>
                  <button type="button" className="reorder-btn" title="Move down" disabled={i === draft.screens.length - 1}
                    onClick={e => { e.stopPropagation(); moveScreen(i, i + 1); }}>↓</button>
                </div>
              </div>
            );
          })}
        </nav>

        <main className="editor-main">
          {selected.kind === 'meta'
            ? <MetaEditor draft={draft} onUpdate={applyUpdate} token={token} />
            : <ScreenEditor key={selected.index} screen={draft.screens[selected.index]} screenIndex={selected.index} allFlags={draft.review_flags} onUpdate={applyUpdate} token={token} />
          }
        </main>
      </div>
    </div>
  );
}

// ─── Shared: lightbox ─────────────────────────────────────────────────────────

function Lightbox({ url, onClose }: { url: string; onClose: () => void }) {
  useEffect(() => {
    function onKey(e: KeyboardEvent) { if (e.key === 'Escape') onClose(); }
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [onClose]);
  return (
    <div className="lightbox-backdrop" onClick={onClose}>
      <img className="lightbox-img" src={url} alt="" onClick={e => e.stopPropagation()} />
    </div>
  );
}

// ─── Shared: image widget ─────────────────────────────────────────────────────

function ImageWidget({ url, alt, onPickerOpen, onLightbox }: {
  url: string | null | undefined;
  alt?: string;
  onPickerOpen: () => void;
  onLightbox: (url: string) => void;
}) {
  const hasImage = url && !url.includes('placeholder');
  return (
    <div
      className={`image-widget${hasImage ? '' : ' image-widget--empty'}`}
      onClick={() => hasImage && onLightbox(url!)}
    >
      {hasImage ? (
        <>
          <img src={url} alt={alt ?? ''} className="image-widget-img" />
          <div className="image-widget-overlay">
            <button type="button" className="image-widget-change-btn"
              onClick={e => { e.stopPropagation(); onPickerOpen(); }}>
              Change image
            </button>
          </div>
        </>
      ) : (
        <button type="button" className="image-widget-add-btn"
          onClick={e => { e.stopPropagation(); onPickerOpen(); }}>
          <span className="image-widget-add-icon">⊕</span>
          <span>Add image</span>
        </button>
      )}
    </div>
  );
}

// ─── Shared: editor card ──────────────────────────────────────────────────────

function EditorCard({ label, icon, children, accent }: {
  label: string;
  icon?: string;
  children: React.ReactNode;
  accent?: 'danger';
}) {
  return (
    <section className={`editor-card${accent ? ` editor-card--${accent}` : ''}`}>
      <header className="editor-card-header">
        {icon && <span className="editor-card-icon">{icon}</span>}
        {label}
      </header>
      <div className="editor-card-body">
        {children}
      </div>
    </section>
  );
}

// ─── Meta editor ──────────────────────────────────────────────────────────────

const SCREEN_TYPE_LABELS: Record<Screen['type'], string> = {
  text:         'Image + Text',
  image_text:   'Image + Text',
  accordion:    'Accordion',
  click_reveal: 'Click Reveal',
  question:     'Question',
  tabs:         'Tabs',
  process:      'Process',
  flashcard:    'Flashcard',
  video:        'Video',
  scenario:     'Scenario',
};

function MetaEditor({ draft, onUpdate, token }: {
  draft: Module;
  onUpdate: (updater: (d: Module) => void) => void;
  token: string;
}) {
  const [pickerOpen, setPickerOpen] = useState(false);
  const [lightboxUrl, setLightboxUrl] = useState<string | null>(null);

  return (
    <div className="editor-panel">
      {pickerOpen && (
        <ImagePicker token={token} initialQuery={draft.meta.title}
          onSelect={picked => { onUpdate(d => { d.meta.cover_image = picked.url; }); setPickerOpen(false); }}
          onClose={() => setPickerOpen(false)} />
      )}
      {lightboxUrl && <Lightbox url={lightboxUrl} onClose={() => setLightboxUrl(null)} />}

      <div className="editor-page-header">
        <h2 className="editor-page-title">Module Info</h2>
      </div>

      <EditorCard label="Course details" icon="✦">
        <div className="field-group">
          <label htmlFor="meta-title">Title</label>
          <input id="meta-title" value={draft.meta.title}
            onChange={e => { const v = e.target.value; onUpdate(d => { d.meta.title = v; }); }} />
        </div>
        <div className="field-row">
          <div className="field-group">
            <label htmlFor="meta-audience">Audience</label>
            <input id="meta-audience" value={draft.meta.audience}
              onChange={e => { const v = e.target.value; onUpdate(d => { d.meta.audience = v; }); }} />
          </div>
          <div className="field-group" style={{ minWidth: '130px' }}>
            <label htmlFor="meta-duration">Duration (min)</label>
            <input id="meta-duration" type="number" min={10} max={30} value={draft.meta.duration_minutes}
              onChange={e => { const v = Number(e.target.value); onUpdate(d => { d.meta.duration_minutes = v; }); }} />
          </div>
        </div>
      </EditorCard>

      <EditorCard label="Cover image" icon="⊞">
        <ImageWidget
          url={draft.meta.cover_image}
          alt="Course cover"
          onPickerOpen={() => setPickerOpen(true)}
          onLightbox={url => setLightboxUrl(url)}
        />
      </EditorCard>

      <EditorCard label="Learning objectives" icon="◎">
        <p className="editor-card-hint">One objective per line.</p>
        <textarea
          id="meta-objectives"
          rows={6}
          value={draft.learning_objectives.join('\n')}
          onChange={e => { const v = e.target.value; onUpdate(d => { d.learning_objectives = v.split('\n'); }); }}
        />
      </EditorCard>
    </div>
  );
}

// ─── Screen editor ────────────────────────────────────────────────────────────

function ScreenEditor({ screen, screenIndex, allFlags, onUpdate, token }: {
  screen: Screen;
  screenIndex: number;
  allFlags: Module['review_flags'];
  onUpdate: (updater: (d: Module) => void) => void;
  token: string;
}) {
  const [pickerOpen, setPickerOpen] = useState(false);
  const [lightboxUrl, setLightboxUrl] = useState<string | null>(null);

  function updateScreen(updater: (s: Screen) => void) {
    onUpdate(d => { updater(d.screens[screenIndex]); });
  }

  function handleImagePicked(picked: PickedImage) {
    onUpdate(d => {
      d.screens[screenIndex].image = { url: picked.url, alt_text: picked.alt_text };
      const isPexels = picked.attribution.license.toLowerCase().includes('pexels');
      if (isPexels) {
        if (!d.attribution.pexels) d.attribution.pexels = [];
        d.attribution.pexels = d.attribution.pexels.filter(a => a.image_id !== picked.attribution.image_id);
        d.attribution.pexels.push(picked.attribution);
      } else {
        d.attribution.unsplash = d.attribution.unsplash.filter(a => a.image_id !== picked.attribution.image_id);
        d.attribution.unsplash.push(picked.attribution);
      }
    });
    setPickerOpen(false);
  }

  const screenFlags = allFlags.reduce<{ flag: Module['review_flags'][number]; globalIndex: number }[]>(
    (acc, flag, i) => { if (flag.target_id === screen.id) acc.push({ flag, globalIndex: i }); return acc; }, []
  );

  const interaction0 = screen.interactions[0];
  const hasItemsInteraction =
    (screen.type === 'accordion' || screen.type === 'click_reveal') &&
    interaction0 &&
    (interaction0.kind === 'accordion' || interaction0.kind === 'click_reveal');
  const hasChoiceInteraction =
    screen.type === 'question' &&
    interaction0 &&
    (interaction0.kind === 'single_choice' || interaction0.kind === 'multi_choice');
  const hasTabsInteraction = screen.type === 'tabs' && interaction0?.kind === 'tabs';
  const hasProcessInteraction = screen.type === 'process' && interaction0?.kind === 'process';
  const hasFlashcardInteraction = screen.type === 'flashcard' && interaction0?.kind === 'flashcard';
  const hasVideoInteraction    = screen.type === 'video'    && interaction0?.kind === 'video';
  const hasScenarioInteraction = screen.type === 'scenario' && interaction0?.kind === 'scenario';

  const openFlags = screenFlags.filter(f => f.flag.status === 'open');

  return (
    <div className="editor-panel">
      {pickerOpen && (
        <ImagePicker token={token} initialQuery={screen.title}
          onSelect={handleImagePicked} onClose={() => setPickerOpen(false)} />
      )}
      {lightboxUrl && <Lightbox url={lightboxUrl} onClose={() => setLightboxUrl(null)} />}

      <div className="editor-page-header">
        <span className={`screen-type-pill screen-type-pill--${screen.type}`}>
          {SCREEN_TYPE_LABELS[screen.type]}
        </span>
        <span className="editor-page-screen-id">Screen {screenIndex + 1} · {screen.id}</span>
      </div>

      <EditorCard label="Content" icon="✎">
        <div className="field-group">
          <label htmlFor={`screen-title-${screenIndex}`}>Title</label>
          <input id={`screen-title-${screenIndex}`} value={screen.title}
            onChange={e => { const v = e.target.value; updateScreen(s => { s.title = v; }); }} />
        </div>
        <div className="field-group">
          <label>Body</label>
          <BodyEditor value={screen.body} onChange={v => updateScreen(s => { s.body = v; })} />
        </div>
      </EditorCard>

      <EditorCard label="Media" icon="⊞">
        <ImageWidget
          url={screen.image?.url}
          alt={screen.image?.alt_text}
          onPickerOpen={() => setPickerOpen(true)}
          onLightbox={url => setLightboxUrl(url)}
        />
        {(screen.type === 'image_text' || screen.type === 'text') && (
          <div className="field-group">
            <label>Layout</label>
            <LayoutPicker value={screen.layout ?? 'top'} onChange={v => updateScreen(s => { s.layout = v; })} />
          </div>
        )}
        {screen.image && !screen.image.url.includes('placeholder') && (
          <div className="field-group">
            <label htmlFor={`screen-alt-${screenIndex}`}>Alt text</label>
            <input id={`screen-alt-${screenIndex}`} value={screen.image.alt_text}
              onChange={e => { const v = e.target.value; updateScreen(s => { if (s.image) s.image.alt_text = v; }); }} />
          </div>
        )}
      </EditorCard>

      {hasItemsInteraction && (
        <EditorCard label={screen.type === 'accordion' ? 'Accordion items' : 'Cards'} icon="⊟">
          <ItemsEditor
            items={(interaction0 as { kind: string; items: RevealItem[] }).items}
            showImages={screen.type === 'click_reveal'}
            token={token}
            onUpdate={items => updateScreen(s => {
              (s.interactions[0] as { kind: string; items: RevealItem[] }).items = items;
            })}
          />
        </EditorCard>
      )}

      {hasChoiceInteraction && (
        <QuestionInteractionEditor
          interaction={interaction0 as Extract<Interaction, { kind: 'single_choice' | 'multi_choice' }>}
          onUpdate={updated => updateScreen(s => { s.interactions[0] = updated; })}
        />
      )}

      {hasTabsInteraction && (
        <EditorCard label="Tabs" icon="⊟">
          <ItemsEditor
            items={(interaction0 as { kind: string; items: RevealItem[] }).items}
            showImages={false}
            onUpdate={items => updateScreen(s => {
              (s.interactions[0] as { kind: string; items: RevealItem[] }).items = items;
            })}
          />
        </EditorCard>
      )}

      {hasProcessInteraction && (
        <EditorCard label="Steps" icon="⊟">
          <ItemsEditor
            items={(interaction0 as { kind: string; items: RevealItem[] }).items}
            showImages={false}
            onUpdate={items => updateScreen(s => {
              (s.interactions[0] as { kind: string; items: RevealItem[] }).items = items;
            })}
          />
        </EditorCard>
      )}

      {hasFlashcardInteraction && (
        <EditorCard label="Flashcards" icon="⊟">
          <FlashcardsEditor
            cards={(interaction0 as { kind: string; cards: { front: string; back: string }[] }).cards}
            onUpdate={cards => updateScreen(s => {
              (s.interactions[0] as { kind: string; cards: { front: string; back: string }[] }).cards = cards;
            })}
          />
        </EditorCard>
      )}

      {hasVideoInteraction && (
        <EditorCard label="Video" icon="▶">
          <div className="field-group">
            <label>Video URL</label>
            <input
              type="url"
              placeholder="YouTube, Vimeo, or direct .mp4 URL"
              value={(interaction0 as { kind: 'video'; url: string; caption?: string }).url}
              onChange={e => { const v = e.target.value; updateScreen(s => {
                (s.interactions[0] as { kind: 'video'; url: string; caption?: string }).url = v;
              }); }}
            />
          </div>
          <div className="field-group">
            <label>Caption <span className="field-optional">(optional)</span></label>
            <input
              type="text"
              placeholder="Brief description shown below the video"
              value={(interaction0 as { kind: 'video'; url: string; caption?: string }).caption ?? ''}
              onChange={e => { const v = e.target.value; updateScreen(s => {
                (s.interactions[0] as { kind: 'video'; url: string; caption?: string }).caption = v || undefined;
              }); }}
            />
          </div>
        </EditorCard>
      )}

      {hasScenarioInteraction && (() => {
        type ScenarioChoice = { id: string; label: string; consequence: string; tone: 'success' | 'warning' | 'failure' };
        type ScenarioInteraction = { kind: 'scenario'; setup: string; choices: ScenarioChoice[] };
        const sc = interaction0 as ScenarioInteraction;
        return (
          <EditorCard label="Scenario" icon="⬡">
            <div className="field-group">
              <label>Setup</label>
              <textarea
                rows={4}
                placeholder="Describe the situation the learner must respond to…"
                value={sc.setup}
                onChange={e => { const v = e.target.value; updateScreen(s => {
                  (s.interactions[0] as ScenarioInteraction).setup = v;
                }); }}
              />
            </div>
            <div className="scenario-choices-editor">
              {sc.choices.map((choice, ci) => (
                <div key={choice.id} className="scenario-choice-row">
                  <div className="scenario-choice-row-header">
                    <span className="scenario-choice-num">Choice {ci + 1}</span>
                    <select
                      value={choice.tone}
                      onChange={e => { const v = e.target.value as ScenarioChoice['tone']; updateScreen(s => {
                        (s.interactions[0] as ScenarioInteraction).choices[ci].tone = v;
                      }); }}
                      className={`scenario-tone-select scenario-tone-select--${choice.tone}`}
                    >
                      <option value="success">✓ Success</option>
                      <option value="warning">⚠ Warning</option>
                      <option value="failure">✗ Failure</option>
                    </select>
                    {sc.choices.length > 2 && (
                      <button type="button" className="ghost scenario-remove-btn" onClick={() => updateScreen(s => {
                        (s.interactions[0] as ScenarioInteraction).choices.splice(ci, 1);
                      })}>Remove</button>
                    )}
                  </div>
                  <input
                    placeholder="Choice label"
                    value={choice.label}
                    onChange={e => { const v = e.target.value; updateScreen(s => {
                      (s.interactions[0] as ScenarioInteraction).choices[ci].label = v;
                    }); }}
                  />
                  <textarea
                    rows={2}
                    placeholder="Consequence shown after this choice is picked…"
                    value={choice.consequence}
                    onChange={e => { const v = e.target.value; updateScreen(s => {
                      (s.interactions[0] as ScenarioInteraction).choices[ci].consequence = v;
                    }); }}
                  />
                </div>
              ))}
              {sc.choices.length < 4 && (
                <button type="button" className="ghost" onClick={() => updateScreen(s => {
                  (s.interactions[0] as ScenarioInteraction).choices.push({
                    id: 'c' + Date.now(),
                    label: '',
                    consequence: '',
                    tone: 'warning',
                  });
                })}>+ Add choice</button>
              )}
            </div>
          </EditorCard>
        );
      })()}

      <EditorCard label="Review flags" icon="⚑" accent={openFlags.length > 0 ? 'danger' : undefined}>
        <FlagsPanel screenId={screen.id} screenFlags={screenFlags} onUpdate={onUpdate} />
      </EditorCard>
    </div>
  );
}

// ─── Layout picker (image_text) ───────────────────────────────────────────────

const LAYOUT_OPTIONS: { value: ImageTextLayout; label: string; diagram: React.ReactNode }[] = [
  {
    value: 'top', label: 'Image top',
    diagram: (
      <svg viewBox="0 0 36 28" width="36" height="28" aria-hidden="true">
        <rect x="1" y="1" width="34" height="13" rx="2" className="layout-img-block" />
        <rect x="1" y="16" width="34" height="11" rx="2" className="layout-txt-block" />
      </svg>
    ),
  },
  {
    value: 'bottom', label: 'Image bottom',
    diagram: (
      <svg viewBox="0 0 36 28" width="36" height="28" aria-hidden="true">
        <rect x="1" y="1" width="34" height="11" rx="2" className="layout-txt-block" />
        <rect x="1" y="14" width="34" height="13" rx="2" className="layout-img-block" />
      </svg>
    ),
  },
  {
    value: 'left', label: 'Image left',
    diagram: (
      <svg viewBox="0 0 36 28" width="36" height="28" aria-hidden="true">
        <rect x="1" y="1" width="16" height="26" rx="2" className="layout-img-block" />
        <rect x="19" y="1" width="16" height="26" rx="2" className="layout-txt-block" />
      </svg>
    ),
  },
  {
    value: 'right', label: 'Image right',
    diagram: (
      <svg viewBox="0 0 36 28" width="36" height="28" aria-hidden="true">
        <rect x="1" y="1" width="16" height="26" rx="2" className="layout-txt-block" />
        <rect x="19" y="1" width="16" height="26" rx="2" className="layout-img-block" />
      </svg>
    ),
  },
];

function LayoutPicker({ value, onChange }: { value: ImageTextLayout; onChange: (v: ImageTextLayout) => void }) {
  return (
    <div className="layout-picker">
      {LAYOUT_OPTIONS.map(opt => (
        <button key={opt.value} type="button"
          className={`layout-tile${value === opt.value ? ' active' : ''}`}
          onClick={() => onChange(opt.value)} title={opt.label} aria-pressed={value === opt.value}>
          {opt.diagram}
          <span className="layout-tile-label">{opt.label}</span>
        </button>
      ))}
    </div>
  );
}

// ─── Items editor (accordion / click_reveal) ──────────────────────────────────

type RevealItem = { title: string; body: string; image?: { url: string; alt_text: string } | null };

function ItemsEditor({ items, showImages = false, token, onUpdate }: {
  items: RevealItem[];
  showImages?: boolean;
  token?: string;
  onUpdate: (items: RevealItem[]) => void;
}) {
  const [pickerFor, setPickerFor] = useState<number | null>(null);
  const [lightboxUrl, setLightboxUrl] = useState<string | null>(null);

  return (
    <div className="items-editor">
      {lightboxUrl && <Lightbox url={lightboxUrl} onClose={() => setLightboxUrl(null)} />}
      {pickerFor !== null && token && (
        <ImagePicker token={token} initialQuery={items[pickerFor]?.title ?? ''}
          onSelect={picked => {
            onUpdate(items.map((it, j) => j === pickerFor ? { ...it, image: { url: picked.url, alt_text: picked.alt_text } } : it));
            setPickerFor(null);
          }}
          onClose={() => setPickerFor(null)} />
      )}

      {items.map((item, i) => (
        <div key={i} className="item-card">
          <div className="item-card-header">
            <span>Item {i + 1}</span>
          </div>
          <div className="item-card-body">
            <div className="field-group">
              <label htmlFor={`item-title-${i}`}>Title</label>
              <input id={`item-title-${i}`} value={item.title}
                onChange={e => { const v = e.target.value; onUpdate(items.map((it, j) => j === i ? { ...it, title: v } : it)); }} />
            </div>
            <div className="field-group">
              <label>Body</label>
              <BodyEditor value={item.body}
                onChange={v => onUpdate(items.map((it, j) => j === i ? { ...it, body: v } : it))} />
            </div>
            {showImages && (
              <div className="field-group">
                <label>Card image</label>
                <ImageWidget
                  url={item.image?.url}
                  alt={item.image?.alt_text}
                  onPickerOpen={() => setPickerFor(i)}
                  onLightbox={url => setLightboxUrl(url)}
                />
              </div>
            )}
          </div>
        </div>
      ))}
    </div>
  );
}

// ─── Flashcards editor ────────────────────────────────────────────────────────

function FlashcardsEditor({ cards, onUpdate }: {
  cards: { front: string; back: string }[];
  onUpdate: (cards: { front: string; back: string }[]) => void;
}) {
  return (
    <div className="items-editor">
      {cards.map((card, i) => (
        <div key={i} className="item-card">
          <div className="item-card-header"><span>Card {i + 1}</span></div>
          <div className="item-card-body">
            <div className="field-group">
              <label htmlFor={`card-front-${i}`}>Front (term)</label>
              <input id={`card-front-${i}`} value={card.front}
                onChange={e => { const v = e.target.value; onUpdate(cards.map((c, j) => j === i ? { ...c, front: v } : c)); }} />
            </div>
            <div className="field-group">
              <label htmlFor={`card-back-${i}`}>Back (definition)</label>
              <textarea id={`card-back-${i}`} rows={3} value={card.back}
                onChange={e => { const v = e.target.value; onUpdate(cards.map((c, j) => j === i ? { ...c, back: v } : c)); }} />
            </div>
          </div>
        </div>
      ))}
    </div>
  );
}

// ─── Question interaction editor ──────────────────────────────────────────────

type ChoiceInteraction = Extract<Interaction, { kind: 'single_choice' | 'multi_choice' }>;

function QuestionInteractionEditor({ interaction, onUpdate }: {
  interaction: ChoiceInteraction;
  onUpdate: (updated: ChoiceInteraction) => void;
}) {
  function update(updater: (i: ChoiceInteraction) => void) {
    const next: ChoiceInteraction = JSON.parse(JSON.stringify(interaction));
    updater(next);
    onUpdate(next);
  }

  const isSingle = interaction.kind === 'single_choice';

  return (
    <>
      <EditorCard label={isSingle ? 'Single choice question' : 'Multiple choice question'} icon="?">
        <div className="field-group">
          <label>Prompt</label>
          <input value={interaction.prompt}
            onChange={e => { const v = e.target.value; update(i => { i.prompt = v; }); }} />
        </div>

        <div className="field-group">
          <div className="choices-header">
            <label>Choices</label>
            <button type="button" className="ghost choices-shuffle" title="Shuffle order"
              onClick={() => update(i => {
                for (let j = i.choices.length - 1; j > 0; j--) {
                  const k = Math.floor(Math.random() * (j + 1));
                  [i.choices[j], i.choices[k]] = [i.choices[k], i.choices[j]];
                }
              })}>⇄ Shuffle</button>
          </div>

          <div className="choices-list">
            {interaction.choices.map((choice, ci) => (
              <div key={choice.id} className={`choice-item${choice.is_correct ? ' choice-item--correct' : ''}`}>
                <label className="choice-indicator" title={choice.is_correct ? 'Correct answer' : 'Mark as correct'}>
                  <input
                    type={isSingle ? 'radio' : 'checkbox'}
                    name={`correct-${interaction.prompt.slice(0, 20)}`}
                    checked={choice.is_correct}
                    onChange={e => {
                      const checked = e.target.checked;
                      update(i => {
                        if (i.kind === 'single_choice') i.choices.forEach((c, j) => { c.is_correct = j === ci; });
                        else i.choices[ci].is_correct = checked;
                      });
                    }}
                  />
                </label>
                <input
                  className="choice-text-input"
                  value={choice.text}
                  onChange={e => { const v = e.target.value; update(i => { i.choices[ci].text = v; }); }}
                />
                <div className="choice-actions">
                  <button type="button" className="reorder-btn" title="Move up" disabled={ci === 0}
                    onClick={() => update(i => { [i.choices[ci - 1], i.choices[ci]] = [i.choices[ci], i.choices[ci - 1]]; })}>↑</button>
                  <button type="button" className="reorder-btn" title="Move down" disabled={ci === interaction.choices.length - 1}
                    onClick={() => update(i => { [i.choices[ci], i.choices[ci + 1]] = [i.choices[ci + 1], i.choices[ci]]; })}>↓</button>
                </div>
              </div>
            ))}
          </div>
        </div>
      </EditorCard>

      <EditorCard label="Feedback" icon="✉">
        <div className="field-group">
          <label>Correct answer feedback</label>
          <input value={interaction.feedback.correct}
            onChange={e => { const v = e.target.value; update(i => { i.feedback.correct = v; }); }} />
        </div>
        <div className="field-group">
          <label>Incorrect answer feedback</label>
          <input value={interaction.feedback.incorrect}
            onChange={e => { const v = e.target.value; update(i => { i.feedback.incorrect = v; }); }} />
        </div>
      </EditorCard>
    </>
  );
}

// ─── Flags panel ──────────────────────────────────────────────────────────────

function FlagsPanel({ screenId, screenFlags, onUpdate }: {
  screenId: string;
  screenFlags: { flag: Module['review_flags'][number]; globalIndex: number }[];
  onUpdate: (updater: (d: Module) => void) => void;
}) {
  const [reason, setReason] = useState('');
  const [notes, setNotes] = useState('');

  function addFlag() {
    if (!reason.trim()) return;
    const newFlag: Module['review_flags'][number] = {
      target_id: screenId, target_type: 'screen',
      reason: reason.trim(), notes: notes.trim() || undefined, status: 'open',
    };
    onUpdate(d => { d.review_flags.push(newFlag); });
    setReason(''); setNotes('');
  }

  function resolveFlag(globalIndex: number) {
    onUpdate(d => { d.review_flags[globalIndex] = { ...d.review_flags[globalIndex], status: 'resolved' }; });
  }

  return (
    <div className="flags-panel">
      {screenFlags.length > 0 && (
        <ul className="flag-list">
          {screenFlags.map(({ flag, globalIndex }) => (
            <li key={globalIndex} className={`flag-item flag-${flag.status}`}>
              <div className="flag-item-content">
                <span className="flag-status-badge">{flag.status}</span>
                <span className="flag-reason">{flag.reason}</span>
                {flag.notes && <span className="flag-notes muted">{flag.notes}</span>}
              </div>
              {flag.status === 'open' && (
                <button type="button" className="ghost" onClick={() => resolveFlag(globalIndex)}>Resolve</button>
              )}
            </li>
          ))}
        </ul>
      )}
      <div className="flag-add-form">
        <div className="field-group">
          <label htmlFor={`flag-reason-${screenId}`}>Flag reason</label>
          <input id={`flag-reason-${screenId}`} placeholder="e.g. Inaccurate content"
            value={reason} onChange={e => setReason(e.target.value)} />
        </div>
        <div className="field-group">
          <label htmlFor={`flag-notes-${screenId}`}>Notes (optional)</label>
          <textarea id={`flag-notes-${screenId}`} rows={2} placeholder="Additional context…"
            value={notes} onChange={e => setNotes(e.target.value)} />
        </div>
        <button type="button" className="ghost" onClick={addFlag} disabled={!reason.trim()}>Add flag</button>
      </div>
    </div>
  );
}
