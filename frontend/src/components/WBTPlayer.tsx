import React, { useEffect, useMemo, useRef, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { fetchModule, fetchProject } from '../lib/api';
import { useAuth } from '../context/AuthContext';
import type { ModuleRecord, Project } from '../lib/types';
import type { Module, Screen, Interaction } from '../lib/moduleSchema';

// ─── Entry direction ──────────────────────────────────────────────────────────

type EntryDir = 'up' | 'left' | 'right';
const DIRS: EntryDir[] = ['up', 'left', 'right'];
function randomDir(): EntryDir { return DIRS[Math.floor(Math.random() * DIRS.length)]; }

// ─── Rich text renderer ───────────────────────────────────────────────────────

function RichText({ text, className }: { text: string; className?: string }) {
  const lines = text.split('\n');
  const nodes: React.ReactNode[] = [];
  let olItems: string[] = [];
  let ulItems: string[] = [];

  function flushOl() {
    if (olItems.length) {
      nodes.push(<ol key={nodes.length} className="player-list player-list--ol">{olItems.map((t, i) => <li key={i}>{t}</li>)}</ol>);
      olItems = [];
    }
  }
  function flushUl() {
    if (ulItems.length) {
      nodes.push(<ul key={nodes.length} className="player-list player-list--ul">{ulItems.map((t, i) => <li key={i}>{t}</li>)}</ul>);
      ulItems = [];
    }
  }

  for (const raw of lines) {
    const line = raw.trimStart();
    const olMatch = line.match(/^(\d+)\.\s+(.*)/);
    const ulMatch = line.match(/^[-•*]\s+(.*)/);

    if (olMatch) {
      flushUl();
      olItems.push(olMatch[2]);
    } else if (ulMatch) {
      flushOl();
      ulItems.push(ulMatch[1]);
    } else {
      flushOl();
      flushUl();
      if (line) nodes.push(<p key={nodes.length} className={className ?? 'player-body'}>{line}</p>);
    }
  }
  flushOl();
  flushUl();

  return <>{nodes}</>;
}

// ─── Page wrapper ─────────────────────────────────────────────────────────────

export function WBTPlayerPage() {
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
      <div className="player-shell">
        <p className="muted" style={{ padding: '2rem' }}>Loading…</p>
      </div>
    );
  }

  if (error || !project || !moduleRecord?.module_json) {
    return (
      <div className="player-shell">
        <div style={{ padding: '2rem' }}>
          <p style={{ color: '#ef4444', marginBottom: '1rem' }}>
            {error ?? 'Module not ready.'}
          </p>
          <Link to={`/projects/${id}`}>← Back to project</Link>
        </div>
      </div>
    );
  }

  return (
    <WBTPlayer
      module={moduleRecord.module_json}
      projectId={project.id}
    />
  );
}

// ─── Player ───────────────────────────────────────────────────────────────────

type PlayerPhase = 'cover' | 'training' | 'complete';

function WBTPlayer({ module, projectId }: { module: Module; projectId: string }) {
  const [phase, setPhase] = useState<PlayerPhase>('cover');
  const [screenIndex, setScreenIndex] = useState(0);

  const screens = module.screens;
  const currentScreen = screens[screenIndex];

  function goNext() {
    if (screenIndex < screens.length - 1) {
      setScreenIndex(i => i + 1);
    } else {
      setPhase('complete');
    }
  }

  function goPrev() {
    if (screenIndex > 0) setScreenIndex(i => i - 1);
  }

  if (phase === 'cover') {
    return (
      <CoverScreen
        module={module}
        projectId={projectId}
        onStart={() => setPhase('training')}
      />
    );
  }

  if (phase === 'complete') {
    return <CompletionScreen module={module} projectId={projectId} />;
  }

  return (
    <div className="player-shell">
      <div className="player-header">
        <header className="player-topbar">
          <span className="player-title">{module.meta.title}</span>
          <div className="player-topbar-right">
            <span className="player-progress">
              {screenIndex + 1} / {screens.length}
            </span>
            <Link to={`/projects/${projectId}`} className="player-exit-btn" aria-label="Exit training">
              ✕
            </Link>
          </div>
        </header>

        <div className="player-progress-bar-track">
          <div
            className="player-progress-bar-fill"
            style={{ width: `${((screenIndex + 1) / screens.length) * 100}%` }}
          />
        </div>
      </div>

      <ScreenRenderer
        key={currentScreen.id}
        screen={currentScreen}
        screenIndex={screenIndex}
        totalScreens={screens.length}
        onNext={goNext}
        onPrev={goPrev}
        attribution={module.attribution}
      />
    </div>
  );
}

// ─── Cover screen ─────────────────────────────────────────────────────────────

function CoverScreen({
  module,
  projectId,
  onStart,
}: {
  module: Module;
  projectId: string;
  onStart: () => void;
}) {
  return (
    <div className="player-shell player-cover">
      <div className="player-cover-card">
        <p className="player-cover-meta">
          {module.meta.audience} · {module.meta.duration_minutes} min
        </p>
        <h1 className="player-cover-title">{module.meta.title}</h1>

        {module.learning_objectives.length > 0 && (
          <div className="player-cover-objectives">
            <p className="player-cover-objectives-label">Learning objectives</p>
            <ul>
              {module.learning_objectives.filter(Boolean).map((obj, i) => (
                <li key={i}>{obj}</li>
              ))}
            </ul>
          </div>
        )}

        <div className="player-cover-actions">
          <button onClick={onStart} className="player-start-btn">
            Start training →
          </button>
          <Link to={`/projects/${projectId}`} className="muted">
            ← Back to project
          </Link>
        </div>
      </div>
    </div>
  );
}

// ─── Screen type labels ───────────────────────────────────────────────────────

const screenTypeLabel: Record<Screen['type'], string> = {
  text:         'Read',
  image_text:   'Visual',
  accordion:    'Explore',
  click_reveal: 'Discover',
  question:     'Knowledge Check',
};

// ─── Screen renderer ──────────────────────────────────────────────────────────

function ScreenRenderer({
  screen,
  screenIndex,
  totalScreens,
  onNext,
  onPrev,
  attribution,
}: {
  screen: Screen;
  screenIndex: number;
  totalScreens: number;
  onNext: () => void;
  onPrev: () => void;
  attribution: Module['attribution'];
}) {
  const [questionReady, setQuestionReady] = useState(screen.type !== 'question');
  const titleRef = useRef<HTMLHeadingElement>(null);
  const heroDir = useMemo(randomDir, []);
  const contentDir = useMemo(randomDir, []);

  useEffect(() => {
    titleRef.current?.focus();
  }, []);

  const isFirst = screenIndex === 0;
  const isLast = screenIndex === totalScreens - 1;

  const imageAttribution =
    screen.image && attribution.unsplash.find(a => screen.image?.url.includes(a.image_id));

  return (
    <>
      <div
        className={`player-hero player-hero--enter-${heroDir}${screen.image ? ' player-hero--image' : ''}`}
        style={screen.image ? { '--hero-img': `url(${screen.image.url})` } as React.CSSProperties : undefined}
        aria-hidden="true"
      >
        <div className="player-hero-inner">
          <span className="player-screen-label">{screenTypeLabel[screen.type]}</span>
          <h2 className="player-screen-title" tabIndex={-1} ref={titleRef}>
            {screen.title}
          </h2>
        </div>
      </div>

      <main className="player-content" aria-label={`Screen ${screenIndex + 1} of ${totalScreens}`}>
        <article className={`player-screen player-screen--enter-${contentDir}`}>
          {screen.image && screen.type !== 'image_text' && (
            <figure className="player-figure">
              <img
                src={screen.image.url}
                alt={screen.image.alt_text}
                className="player-image"
              />
              {imageAttribution && (
                <figcaption className="player-attribution">
                  Photo by{' '}
                  <a href={imageAttribution.url} target="_blank" rel="noopener noreferrer">
                    {imageAttribution.photographer}
                  </a>{' '}
                  on Unsplash
                </figcaption>
              )}
            </figure>
          )}

          {screen.type === 'text' && <TextScreen screen={screen} />}
          {screen.type === 'image_text' && (
            <ImageTextScreen screen={screen} attribution={imageAttribution ?? null} />
          )}
          {screen.type === 'accordion' && <AccordionScreen screen={screen} />}
          {screen.type === 'click_reveal' && <ClickRevealScreen screen={screen} />}
          {screen.type === 'question' && (
            <QuestionScreen screen={screen} onReady={() => setQuestionReady(true)} />
          )}
        </article>
      </main>

      <nav className="player-nav" aria-label="Screen navigation">
        <button
          onClick={onPrev}
          disabled={isFirst}
          className="ghost player-nav-btn"
          aria-label="Previous screen"
        >
          ← Previous
        </button>
        <button
          onClick={onNext}
          disabled={!questionReady}
          className="player-nav-btn"
          aria-label={isLast ? 'Finish' : 'Next screen'}
        >
          {isLast ? 'Finish ✓' : 'Next →'}
        </button>
      </nav>
    </>
  );
}

// ─── Text screen ──────────────────────────────────────────────────────────────

function TextScreen({ screen }: { screen: Screen }) {
  return <RichText text={screen.body} />;
}

// ─── Image + text screen ──────────────────────────────────────────────────────

function ImageTextScreen({
  screen,
  attribution,
}: {
  screen: Screen;
  attribution: Module['attribution']['unsplash'][number] | null;
}) {
  const layout = screen.layout ?? 'top';

  const imageEl = screen.image ? (
    <figure className="player-figure">
      <img src={screen.image.url} alt={screen.image.alt_text} className="player-image" />
      {attribution && (
        <figcaption className="player-attribution">
          Photo by{' '}
          <a href={attribution.url} target="_blank" rel="noopener noreferrer">
            {attribution.photographer}
          </a>{' '}
          on Unsplash
        </figcaption>
      )}
    </figure>
  ) : null;

  const textEl = <div className="player-image-text-body"><RichText text={screen.body} /></div>;

  if (layout === 'left' || layout === 'right') {
    return (
      <div className={`player-image-text player-image-text--${layout}`}>
        {layout === 'left' ? imageEl : textEl}
        {layout === 'left' ? textEl : imageEl}
      </div>
    );
  }

  // top (default) or bottom
  return (
    <div className={`player-image-text player-image-text--${layout}`}>
      {layout === 'bottom' ? textEl : imageEl}
      {layout === 'bottom' ? imageEl : textEl}
    </div>
  );
}

// ─── Accordion screen ─────────────────────────────────────────────────────────

function AccordionScreen({ screen }: { screen: Screen }) {
  const [openItems, setOpenItems] = useState<Set<number>>(new Set());

  const interaction = screen.interactions[0];
  if (!interaction || interaction.kind !== 'accordion') return <RichText text={screen.body} />;

  function toggle(i: number) {
    setOpenItems(prev => {
      const next = new Set(prev);
      next.has(i) ? next.delete(i) : next.add(i);
      return next;
    });
  }

  return (
    <>
      {screen.body && <RichText text={screen.body} />}
      <div className="player-accordion" role="list">
        {interaction.items.map((item, i) => {
          const isOpen = openItems.has(i);
          return (
            <div key={i} className="player-accordion-item" role="listitem">
              <button
                type="button"
                className="player-accordion-trigger"
                aria-expanded={isOpen}
                onClick={() => toggle(i)}
              >
                <span>{item.title}</span>
                <span className="player-accordion-icon" aria-hidden="true">
                  {isOpen ? '−' : '+'}
                </span>
              </button>
              {isOpen && (
                <div className="player-accordion-body">
                  <RichText text={item.body} className="player-accordion-body-text" />
                </div>
              )}
            </div>
          );
        })}
      </div>
    </>
  );
}

// ─── Click-reveal screen ──────────────────────────────────────────────────────

const REVEAL_GRADIENTS = [
  'linear-gradient(135deg, #ff5d34 0%, #f10b1b 100%)',
  'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
  'linear-gradient(135deg, #0ea5e9 0%, #2563eb 100%)',
  'linear-gradient(135deg, #10b981 0%, #059669 100%)',
  'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)',
  'linear-gradient(135deg, #ec4899 0%, #db2777 100%)',
];

function ClickRevealScreen({ screen }: { screen: Screen }) {
  const [revealed, setRevealed] = useState<Set<number>>(new Set());
  const [activeItem, setActiveItem] = useState<number | null>(null);
  const modalRef = useRef<HTMLDivElement>(null);
  const triggerRefs = useRef<(HTMLButtonElement | null)[]>([]);

  const interaction = screen.interactions[0];
  if (!interaction || interaction.kind !== 'click_reveal') return <RichText text={screen.body} />;

  function open(i: number) {
    setActiveItem(i);
    setRevealed(prev => new Set(prev).add(i));
  }

  function close() {
    const idx = activeItem;
    setActiveItem(null);
    // return focus to the card that opened the modal
    setTimeout(() => triggerRefs.current[idx!]?.focus(), 0);
  }

  useEffect(() => {
    if (activeItem === null) return;
    modalRef.current?.focus();
    function onKey(e: KeyboardEvent) { if (e.key === 'Escape') close(); }
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [activeItem]);

  const activeData = activeItem !== null ? interaction.items[activeItem] : null;

  return (
    <>
      {screen.body && <RichText text={screen.body} />}

      <div className="reveal-grid">
        {interaction.items.map((item, i) => {
          const isDone = revealed.has(i);
          const hasImage = !!item.image?.url;
          return (
            <button
              key={i}
              ref={el => { triggerRefs.current[i] = el; }}
              type="button"
              className={`reveal-card${hasImage ? ' reveal-card--image' : ''}${isDone ? ' reveal-card--done' : ''}`}
              style={
                hasImage
                  ? { '--reveal-img': `url(${item.image!.url})` } as React.CSSProperties
                  : { background: REVEAL_GRADIENTS[i % REVEAL_GRADIENTS.length] } as React.CSSProperties
              }
              onClick={() => open(i)}
              aria-label={`${item.title}${isDone ? ' — viewed' : ''}`}
            >
              {isDone && <span className="reveal-card-check" aria-hidden="true">✓</span>}
              <span className="reveal-card-title">{item.title}</span>
            </button>
          );
        })}
      </div>

      {activeData && (
        <div
          className="reveal-modal-backdrop"
          role="presentation"
          onClick={close}
        >
          <div
            ref={modalRef}
            className="reveal-modal"
            role="dialog"
            aria-modal="true"
            aria-label={activeData.title}
            tabIndex={-1}
            onClick={e => e.stopPropagation()}
          >
            <div className="reveal-modal-header">
              <h3 className="reveal-modal-title">{activeData.title}</h3>
              <button type="button" className="reveal-modal-close" onClick={close} aria-label="Close">✕</button>
            </div>
            <div className="reveal-modal-body">
              <RichText text={activeData.body} />
            </div>
          </div>
        </div>
      )}
    </>
  );
}

// ─── Question screen ──────────────────────────────────────────────────────────

type ChoiceInteraction = Extract<Interaction, { kind: 'single_choice' | 'multi_choice' }>;

function QuestionScreen({
  screen,
  onReady,
}: {
  screen: Screen;
  onReady: () => void;
}) {
  const interaction = screen.interactions[0] as ChoiceInteraction | undefined;
  const [selected, setSelected] = useState<Set<string>>(new Set());
  const [submitted, setSubmitted] = useState(false);
  const [isCorrect, setIsCorrect] = useState(false);
  const feedbackRef = useRef<HTMLDivElement>(null);

  if (!interaction) return <RichText text={screen.body} />;

  const isSingle = interaction.kind === 'single_choice';

  function toggleChoice(id: string) {
    if (submitted) return;
    setSelected(prev => {
      if (isSingle) return new Set([id]);
      const next = new Set(prev);
      next.has(id) ? next.delete(id) : next.add(id);
      return next;
    });
  }

  function handleSubmit() {
    const correctIds = new Set(
      interaction!.choices.filter(c => c.is_correct).map(c => c.id)
    );
    const correct =
      selected.size === correctIds.size &&
      [...selected].every(id => correctIds.has(id));
    setIsCorrect(correct);
    setSubmitted(true);
    onReady();
    // Move focus to feedback for screen readers
    setTimeout(() => feedbackRef.current?.focus(), 50);
  }

  return (
    <>
      {screen.body && <RichText text={screen.body} />}

      <fieldset className="player-choices" disabled={submitted}>
        <legend className="player-question-prompt">{interaction.prompt}</legend>
        {interaction.choices.map(choice => (
          <label
            key={choice.id}
            className={`player-choice ${submitted && choice.is_correct ? 'correct' : ''} ${submitted && selected.has(choice.id) && !choice.is_correct ? 'wrong' : ''}`}
          >
            <input
              type={isSingle ? 'radio' : 'checkbox'}
              name={`q-${screen.id}`}
              value={choice.id}
              checked={selected.has(choice.id)}
              onChange={() => toggleChoice(choice.id)}
            />
            {choice.text}
          </label>
        ))}
      </fieldset>

      {!submitted && (
        <button
          type="button"
          onClick={handleSubmit}
          disabled={selected.size === 0}
          className="player-check-btn"
        >
          Check answer
        </button>
      )}

      {submitted && (
        <div
          ref={feedbackRef}
          tabIndex={-1}
          className={`player-feedback ${isCorrect ? 'correct' : 'incorrect'}`}
          role="alert"
          aria-live="polite"
        >
          <span className="player-feedback-icon" aria-hidden="true">
            {isCorrect ? '✓' : '✗'}
          </span>
          {isCorrect ? interaction.feedback.correct : interaction.feedback.incorrect}
        </div>
      )}
    </>
  );
}

// ─── Completion screen ────────────────────────────────────────────────────────

function CompletionScreen({ module, projectId }: { module: Module; projectId: string }) {
  return (
    <div className="player-shell player-cover">
      <div className="player-cover-card player-complete-card">
        <div className="player-complete-icon" aria-hidden="true">✓</div>
        <h1 className="player-cover-title">Training complete</h1>
        <p className="player-complete-subtitle">{module.meta.title}</p>
        <div className="player-cover-actions">
          <Link to={`/projects/${projectId}`}>
            <button type="button">Back to project</button>
          </Link>
        </div>
      </div>
    </div>
  );
}
