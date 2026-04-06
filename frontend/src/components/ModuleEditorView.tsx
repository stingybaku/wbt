import React, { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { fetchProject, fetchModule, updateModule } from '../lib/api';
import { useAuth } from '../context/AuthContext';
import type { Project, ModuleRecord } from '../lib/types';
import type { Module, Screen, Interaction, ImageTextLayout } from '../lib/moduleSchema';
import { imageTextLayouts } from '../lib/moduleSchema';
import { ImagePicker, type PickedImage } from './ImagePicker';

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

  if (loading) {
    return (
      <div className="editor-shell">
        <p className="muted" style={{ padding: '2rem' }}>Loading…</p>
      </div>
    );
  }

  if (error || !project || !moduleRecord?.module_json) {
    return (
      <div className="editor-shell">
        <div style={{ padding: '2rem' }}>
          <p className="muted" style={{ color: '#ef4444', marginBottom: '1rem' }}>
            {error ?? 'Module not ready for review.'}
          </p>
          <Link to={`/projects/${id}`}>← Back to project</Link>
        </div>
      </div>
    );
  }

  return (
    <ModuleEditor
      project={project}
      moduleRecord={moduleRecord}
      token={token!}
      onSaved={setModuleRecord}
    />
  );
}

// ─── Editor ───────────────────────────────────────────────────────────────────

type SelectedView = { kind: 'meta' } | { kind: 'screen'; index: number };

function ModuleEditor({
  project,
  moduleRecord,
  token,
  onSaved,
}: {
  project: Project;
  moduleRecord: ModuleRecord;
  token: string;
  onSaved: (updated: ModuleRecord) => void;
}) {
  const [draft, setDraft] = useState<Module>(() =>
    JSON.parse(JSON.stringify(moduleRecord.module_json!))
  );
  const [selected, setSelected] = useState<SelectedView>({ kind: 'meta' });
  const [saving, setSaving] = useState(false);
  const [saveError, setSaveError] = useState<string | null>(null);
  const [dirty, setDirty] = useState(false);

  function applyUpdate(updater: (d: Module) => void) {
    setDraft(prev => {
      const next: Module = JSON.parse(JSON.stringify(prev));
      updater(next);
      return next;
    });
    setDirty(true);
    setSaveError(null);
  }

  async function handleSave() {
    setSaving(true);
    setSaveError(null);
    try {
      const res = await updateModule(token, moduleRecord.project_id, draft);
      if (res.module) onSaved(res.module);
      setDirty(false);
    } catch (err: unknown) {
      setSaveError(err instanceof Error ? err.message : 'Save failed');
    } finally {
      setSaving(false);
    }
  }

  const openFlagCount = draft.review_flags.filter(f => f.status === 'open').length;

  return (
    <div className="editor-shell">
      <header className="editor-topbar">
        <div className="editor-topbar-left">
          <Link to={`/projects/${moduleRecord.project_id}`} className="muted">
            ← {project.title}
          </Link>
          <span className="editor-topbar-title">{draft.meta.title}</span>
        </div>
        <div className="editor-topbar-right">
          {openFlagCount > 0 && (
            <span className="flag-count-badge">
              {openFlagCount} open flag{openFlagCount !== 1 ? 's' : ''}
            </span>
          )}
          {saveError && (
            <span style={{ color: '#ef4444', fontSize: '0.875rem' }}>{saveError}</span>
          )}
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
            const flagCount = draft.review_flags.filter(
              f => f.target_id === screen.id && f.status === 'open'
            ).length;
            const isActive = selected.kind === 'screen' && selected.index === i;
            return (
              <button
                key={screen.id}
                className={`screen-item ${isActive ? 'active' : ''}`}
                onClick={() => setSelected({ kind: 'screen', index: i })}
              >
                <span className="screen-item-num">{i + 1}</span>
                <span className="screen-item-title">{screen.title || '(untitled)'}</span>
                <span className="screen-item-type">{screen.type}</span>
                {flagCount > 0 && <span className="screen-item-flag">{flagCount}</span>}
              </button>
            );
          })}
        </nav>

        <main className="editor-main">
          {selected.kind === 'meta' ? (
            <MetaEditor draft={draft} onUpdate={applyUpdate} />
          ) : (
            <ScreenEditor
              key={selected.index}
              screen={draft.screens[selected.index]}
              screenIndex={selected.index}
              allFlags={draft.review_flags}
              onUpdate={applyUpdate}
              token={token}
            />
          )}
        </main>
      </div>
    </div>
  );
}

// ─── Meta editor ──────────────────────────────────────────────────────────────

function MetaEditor({
  draft,
  onUpdate,
}: {
  draft: Module;
  onUpdate: (updater: (d: Module) => void) => void;
}) {
  return (
    <div className="editor-panel">
      <h2>Module Info</h2>

      <div className="field-group">
        <label htmlFor="meta-title">Title</label>
        <input
          id="meta-title"
          value={draft.meta.title}
          onChange={e => {
            const v = e.target.value;
            onUpdate(d => { d.meta.title = v; });
          }}
        />
      </div>

      <div className="field-group">
        <label htmlFor="meta-audience">Audience</label>
        <input
          id="meta-audience"
          value={draft.meta.audience}
          onChange={e => {
            const v = e.target.value;
            onUpdate(d => { d.meta.audience = v; });
          }}
        />
      </div>

      <div className="field-group">
        <label htmlFor="meta-duration">Duration (minutes)</label>
        <input
          id="meta-duration"
          type="number"
          min={10}
          max={30}
          value={draft.meta.duration_minutes}
          onChange={e => {
            const v = Number(e.target.value);
            onUpdate(d => { d.meta.duration_minutes = v; });
          }}
        />
      </div>

      <div className="field-group">
        <label htmlFor="meta-objectives">Learning Objectives (one per line)</label>
        <textarea
          id="meta-objectives"
          rows={6}
          value={draft.learning_objectives.join('\n')}
          onChange={e => {
            const v = e.target.value;
            onUpdate(d => { d.learning_objectives = v.split('\n'); });
          }}
        />
      </div>
    </div>
  );
}

// ─── Screen editor ────────────────────────────────────────────────────────────

function ScreenEditor({
  screen,
  screenIndex,
  allFlags,
  onUpdate,
  token,
}: {
  screen: Screen;
  screenIndex: number;
  allFlags: Module['review_flags'];
  onUpdate: (updater: (d: Module) => void) => void;
  token: string;
}) {
  const [pickerOpen, setPickerOpen] = useState(false);

  function updateScreen(updater: (s: Screen) => void) {
    onUpdate(d => { updater(d.screens[screenIndex]); });
  }

  function handleImagePicked(picked: PickedImage) {
    onUpdate(d => {
      d.screens[screenIndex].image = { url: picked.url, alt_text: picked.alt_text };
      // Deduplicate attribution by image_id
      d.attribution.unsplash = d.attribution.unsplash.filter(
        a => a.image_id !== picked.attribution.image_id
      );
      d.attribution.unsplash.push(picked.attribution);
    });
    setPickerOpen(false);
  }

  const screenFlags = allFlags.reduce<{ flag: Module['review_flags'][number]; globalIndex: number }[]>(
    (acc, flag, i) => {
      if (flag.target_id === screen.id) acc.push({ flag, globalIndex: i });
      return acc;
    },
    []
  );

  const interaction0 = screen.interactions[0];
  const isAccordionOrReveal = screen.type === 'accordion' || screen.type === 'click_reveal';
  const hasItemsInteraction =
    isAccordionOrReveal &&
    interaction0 &&
    (interaction0.kind === 'accordion' || interaction0.kind === 'click_reveal');
  const hasChoiceInteraction =
    screen.type === 'question' &&
    interaction0 &&
    (interaction0.kind === 'single_choice' || interaction0.kind === 'multi_choice');

  const showImageSection = true;

  return (
    <div className="editor-panel">
      {pickerOpen && (
        <ImagePicker
          token={token}
          initialQuery={screen.title}
          onSelect={handleImagePicked}
          onClose={() => setPickerOpen(false)}
        />
      )}

      <div className="screen-editor-header">
        <span className="screen-type-badge">{screen.type}</span>
        <span className="muted">Screen {screenIndex + 1} · {screen.id}</span>
      </div>

      <div className="field-group">
        <label htmlFor={`screen-title-${screenIndex}`}>Title</label>
        <input
          id={`screen-title-${screenIndex}`}
          value={screen.title}
          onChange={e => {
            const v = e.target.value;
            updateScreen(s => { s.title = v; });
          }}
        />
      </div>

      <div className="field-group">
        <label htmlFor={`screen-body-${screenIndex}`}>Body</label>
        <textarea
          id={`screen-body-${screenIndex}`}
          rows={5}
          value={screen.body}
          onChange={e => {
            const v = e.target.value;
            updateScreen(s => { s.body = v; });
          }}
        />
      </div>

      {screen.type === 'image_text' && (
        <div className="field-group">
          <span className="field-label">Layout</span>
          <LayoutPicker
            value={screen.layout ?? 'top'}
            onChange={v => updateScreen(s => { s.layout = v; })}
          />
        </div>
      )}

      {showImageSection && (
        <div className="field-group">
          <span className="field-label">Image</span>
          {screen.image?.url && (
            <img
              src={screen.image.url}
              alt={screen.image.alt_text}
              className="screen-image-preview"
            />
          )}
          <button
            type="button"
            className="ghost"
            onClick={() => setPickerOpen(true)}
          >
            {screen.image ? 'Change image' : 'Add image'}
          </button>
          {screen.image && (
            <div className="field-group">
              <label htmlFor={`screen-alt-${screenIndex}`}>Alt text</label>
              <input
                id={`screen-alt-${screenIndex}`}
                value={screen.image.alt_text}
                onChange={e => {
                  const v = e.target.value;
                  updateScreen(s => { if (s.image) s.image.alt_text = v; });
                }}
              />
            </div>
          )}
        </div>
      )}

      {hasItemsInteraction && (
        <ItemsEditor
          label={screen.type === 'accordion' ? 'Accordion items' : 'Click-reveal items'}
          items={(interaction0 as { kind: string; items: { title: string; body: string }[] }).items}
          onUpdate={items =>
            updateScreen(s => {
              (s.interactions[0] as { kind: string; items: { title: string; body: string }[] }).items = items;
            })
          }
        />
      )}

      {hasChoiceInteraction && (
        <QuestionInteractionEditor
          interaction={interaction0 as Extract<Interaction, { kind: 'single_choice' | 'multi_choice' }>}
          onUpdate={updated => updateScreen(s => { s.interactions[0] = updated; })}
        />
      )}

      <FlagsPanel
        screenId={screen.id}
        screenFlags={screenFlags}
        onUpdate={onUpdate}
      />
    </div>
  );
}

// ─── Layout picker (image_text) ───────────────────────────────────────────────

const LAYOUT_OPTIONS: { value: ImageTextLayout; label: string; diagram: React.ReactNode }[] = [
  {
    value: 'top',
    label: 'Image top',
    diagram: (
      <svg viewBox="0 0 36 28" width="36" height="28" aria-hidden="true">
        <rect x="1" y="1" width="34" height="13" rx="2" className="layout-img-block" />
        <rect x="1" y="16" width="34" height="11" rx="2" className="layout-txt-block" />
      </svg>
    ),
  },
  {
    value: 'bottom',
    label: 'Image bottom',
    diagram: (
      <svg viewBox="0 0 36 28" width="36" height="28" aria-hidden="true">
        <rect x="1" y="1" width="34" height="11" rx="2" className="layout-txt-block" />
        <rect x="1" y="14" width="34" height="13" rx="2" className="layout-img-block" />
      </svg>
    ),
  },
  {
    value: 'left',
    label: 'Image left',
    diagram: (
      <svg viewBox="0 0 36 28" width="36" height="28" aria-hidden="true">
        <rect x="1" y="1" width="16" height="26" rx="2" className="layout-img-block" />
        <rect x="19" y="1" width="16" height="26" rx="2" className="layout-txt-block" />
      </svg>
    ),
  },
  {
    value: 'right',
    label: 'Image right',
    diagram: (
      <svg viewBox="0 0 36 28" width="36" height="28" aria-hidden="true">
        <rect x="1" y="1" width="16" height="26" rx="2" className="layout-txt-block" />
        <rect x="19" y="1" width="16" height="26" rx="2" className="layout-img-block" />
      </svg>
    ),
  },
];

function LayoutPicker({
  value,
  onChange,
}: {
  value: ImageTextLayout;
  onChange: (v: ImageTextLayout) => void;
}) {
  return (
    <div className="layout-picker">
      {LAYOUT_OPTIONS.map(opt => (
        <button
          key={opt.value}
          type="button"
          className={`layout-tile${value === opt.value ? ' active' : ''}`}
          onClick={() => onChange(opt.value)}
          title={opt.label}
          aria-pressed={value === opt.value}
        >
          {opt.diagram}
          <span className="layout-tile-label">{opt.label}</span>
        </button>
      ))}
    </div>
  );
}

// ─── Items editor (accordion / click_reveal) ──────────────────────────────────

function ItemsEditor({
  label,
  items,
  onUpdate,
}: {
  label: string;
  items: { title: string; body: string }[];
  onUpdate: (items: { title: string; body: string }[]) => void;
}) {
  return (
    <div className="field-group">
      <span className="field-label">{label}</span>
      {items.map((item, i) => (
        <div key={i} className="item-editor">
          <div className="field-group">
            <label htmlFor={`item-title-${i}`}>Item {i + 1} — title</label>
            <input
              id={`item-title-${i}`}
              value={item.title}
              onChange={e => {
                const v = e.target.value;
                const next = items.map((it, j) => j === i ? { ...it, title: v } : it);
                onUpdate(next);
              }}
            />
          </div>
          <div className="field-group">
            <label htmlFor={`item-body-${i}`}>Item {i + 1} — body</label>
            <textarea
              id={`item-body-${i}`}
              rows={3}
              value={item.body}
              onChange={e => {
                const v = e.target.value;
                const next = items.map((it, j) => j === i ? { ...it, body: v } : it);
                onUpdate(next);
              }}
            />
          </div>
        </div>
      ))}
    </div>
  );
}

// ─── Question interaction editor ──────────────────────────────────────────────

type ChoiceInteraction = Extract<Interaction, { kind: 'single_choice' | 'multi_choice' }>;

function QuestionInteractionEditor({
  interaction,
  onUpdate,
}: {
  interaction: ChoiceInteraction;
  onUpdate: (updated: ChoiceInteraction) => void;
}) {
  function update(updater: (i: ChoiceInteraction) => void) {
    const next: ChoiceInteraction = JSON.parse(JSON.stringify(interaction));
    updater(next);
    onUpdate(next);
  }

  return (
    <div className="field-group">
      <span className="field-label">
        Interaction — {interaction.kind === 'single_choice' ? 'Single choice' : 'Multiple choice'}
      </span>

      <div className="field-group">
        <label>Prompt</label>
        <input
          value={interaction.prompt}
          onChange={e => {
            const v = e.target.value;
            update(i => { i.prompt = v; });
          }}
        />
      </div>

      <div className="field-group">
        <span className="field-label">Choices</span>
        {interaction.choices.map((choice, ci) => (
          <div key={choice.id} className="choice-row">
            <input
              className="choice-text"
              value={choice.text}
              onChange={e => {
                const v = e.target.value;
                update(i => { i.choices[ci].text = v; });
              }}
            />
            <label className="choice-correct">
              <input
                type={interaction.kind === 'single_choice' ? 'radio' : 'checkbox'}
                name={`correct-${screenIndexFromId(interaction)}`}
                checked={choice.is_correct}
                onChange={e => {
                  const checked = e.target.checked;
                  update(i => {
                    if (i.kind === 'single_choice') {
                      i.choices.forEach((c, j) => { c.is_correct = j === ci; });
                    } else {
                      i.choices[ci].is_correct = checked;
                    }
                  });
                }}
              />
              Correct
            </label>
          </div>
        ))}
      </div>

      <div className="field-group">
        <label>Feedback — correct</label>
        <input
          value={interaction.feedback.correct}
          onChange={e => {
            const v = e.target.value;
            update(i => { i.feedback.correct = v; });
          }}
        />
      </div>

      <div className="field-group">
        <label>Feedback — incorrect</label>
        <input
          value={interaction.feedback.incorrect}
          onChange={e => {
            const v = e.target.value;
            update(i => { i.feedback.incorrect = v; });
          }}
        />
      </div>
    </div>
  );
}

// Use prompt as a stable key for radio group name
function screenIndexFromId(interaction: ChoiceInteraction) {
  return interaction.prompt.slice(0, 20);
}

// ─── Flags panel ──────────────────────────────────────────────────────────────

function FlagsPanel({
  screenId,
  screenFlags,
  onUpdate,
}: {
  screenId: string;
  screenFlags: { flag: Module['review_flags'][number]; globalIndex: number }[];
  onUpdate: (updater: (d: Module) => void) => void;
}) {
  const [reason, setReason] = useState('');
  const [notes, setNotes] = useState('');

  function addFlag() {
    if (!reason.trim()) return;
    const newFlag: Module['review_flags'][number] = {
      target_id: screenId,
      target_type: 'screen',
      reason: reason.trim(),
      notes: notes.trim() || undefined,
      status: 'open',
    };
    onUpdate(d => { d.review_flags.push(newFlag); });
    setReason('');
    setNotes('');
  }

  function resolveFlag(globalIndex: number) {
    onUpdate(d => {
      d.review_flags[globalIndex] = { ...d.review_flags[globalIndex], status: 'resolved' };
    });
  }

  return (
    <div className="flags-panel">
      <span className="field-label">Review flags</span>

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
                <button type="button" className="ghost" onClick={() => resolveFlag(globalIndex)}>
                  Resolve
                </button>
              )}
            </li>
          ))}
        </ul>
      )}

      <div className="flag-add-form">
        <div className="field-group">
          <label htmlFor={`flag-reason-${screenId}`}>Flag reason</label>
          <input
            id={`flag-reason-${screenId}`}
            placeholder="e.g. Inaccurate content"
            value={reason}
            onChange={e => setReason(e.target.value)}
          />
        </div>
        <div className="field-group">
          <label htmlFor={`flag-notes-${screenId}`}>Notes (optional)</label>
          <textarea
            id={`flag-notes-${screenId}`}
            rows={2}
            placeholder="Additional context…"
            value={notes}
            onChange={e => setNotes(e.target.value)}
          />
        </div>
        <button type="button" className="ghost" onClick={addFlag} disabled={!reason.trim()}>
          Add flag
        </button>
      </div>
    </div>
  );
}
