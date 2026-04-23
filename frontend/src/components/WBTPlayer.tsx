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

/** Render inline bold/italic markers within a plain-text string. */
function RichInline({ text }: { text: string }) {
  // Split on **bold** and _italic_ spans
  const re = /\*\*([^*]+)\*\*|_([^_]+)_/g;
  const parts: React.ReactNode[] = [];
  let last = 0;
  let m: RegExpExecArray | null;
  while ((m = re.exec(text)) !== null) {
    if (m.index > last) parts.push(text.slice(last, m.index));
    if (m[1] !== undefined) parts.push(<strong key={m.index}>{m[1]}</strong>);
    if (m[2] !== undefined) parts.push(<em key={m.index}>{m[2]}</em>);
    last = m.index + m[0].length;
  }
  if (last < text.length) parts.push(text.slice(last));
  return <>{parts}</>;
}

function RichText({ text, className }: { text: string; className?: string }) {
  const lines = text.split('\n');
  const nodes: React.ReactNode[] = [];
  let olItems: string[] = [];
  let ulItems: string[] = [];

  function flushOl() {
    if (olItems.length) {
      nodes.push(
        <ol key={nodes.length} className="player-list player-list--ol">
          {olItems.map((t, i) => <li key={i}><RichInline text={t} /></li>)}
        </ol>
      );
      olItems = [];
    }
  }
  function flushUl() {
    if (ulItems.length) {
      nodes.push(
        <ul key={nodes.length} className="player-list player-list--ul">
          {ulItems.map((t, i) => <li key={i}><RichInline text={t} /></li>)}
        </ul>
      );
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
    } else if (line === '') {
      // Blank lines between list items must not flush the list — Claude often
      // separates numbered items with a blank line, which would otherwise create
      // individual <ol> elements each starting at 1.
    } else {
      flushOl();
      flushUl();
      const calloutMatch = line.match(/^(IMPORTANT|NOTE|WARNING|TIP|CAUTION|REMINDER):\s*(.*)/i);
      if (calloutMatch) {
        const kind = calloutMatch[1].toUpperCase();
        nodes.push(
          <div key={nodes.length} className={`player-callout player-callout--${kind.toLowerCase()}`} role="note">
            <span className="player-callout-label">{kind}</span>
            <span className="player-callout-body"><RichInline text={calloutMatch[2]} /></span>
          </div>
        );
      } else {
        nodes.push(
          <p key={nodes.length} className={className ?? 'player-body'}>
            <RichInline text={line} />
          </p>
        );
      }
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
      projectTitle={project.title}
    />
  );
}

// ─── Progress persistence ─────────────────────────────────────────────────────

type QuestionAnswer = { selected: string[]; submitted: boolean; isCorrect: boolean };

type SavedProgress = {
  screenIndex: number;
  visited: number[];
  answers: Record<string, QuestionAnswer>;
};

function progressKey(projectId: string) { return `wbt_progress_${projectId}`; }

function loadProgress(projectId: string): SavedProgress | null {
  if (!projectId) return null; // viewer mode — DB handles resume
  try {
    const raw = localStorage.getItem(progressKey(projectId));
    return raw ? (JSON.parse(raw) as SavedProgress) : null;
  } catch { return null; }
}

function saveProgress(projectId: string, data: SavedProgress) {
  if (!projectId) return; // viewer mode — DB handles resume
  try { localStorage.setItem(progressKey(projectId), JSON.stringify(data)); } catch { /* ignore */ }
}

// ─── Player ───────────────────────────────────────────────────────────────────

type PlayerPhase = 'cover' | 'training' | 'complete';

export function WBTPlayer({
  module,
  projectId,
  projectTitle,
  viewerMode = false,
  initialScreenIndex = 0,
  initialAnswers,
  initialVisited,
  completedAt,
  onScreenChange,
  onAnswered,
}: {
  module: Module;
  projectId: string;
  projectTitle?: string;
  viewerMode?: boolean;
  initialScreenIndex?: number;
  initialAnswers?: Record<string, QuestionAnswer>;
  initialVisited?: number[];
  completedAt?: string;
  onScreenChange?: (index: number, total: number, visited: number[]) => void;
  onAnswered?: (screenId: string, answer: QuestionAnswer) => void;
}) {
  const screens = module.screens;

  // ── Restore from localStorage on first mount ──
  const saved = useMemo(() => loadProgress(projectId), [projectId]);

  const [phase, setPhase] = useState<PlayerPhase>(() =>
    (saved?.screenIndex ?? initialScreenIndex) > 0 ? 'training' : 'cover'
  );
  const [screenIndex, setScreenIndex] = useState(() => saved?.screenIndex ?? initialScreenIndex);
  const [visitedSet, setVisitedSet] = useState<Set<number>>(() => {
    const idx = saved?.screenIndex ?? initialScreenIndex;
    return new Set<number>([idx, ...(saved?.visited ?? initialVisited ?? [])]);
  });
  const [questionAnswers, setQuestionAnswers] = useState<Record<string, QuestionAnswer>>(
    () => saved?.answers ?? initialAnswers ?? {}
  );

  const currentScreen = screens[screenIndex];

  // ── Persist whenever progress changes ──
  useEffect(() => {
    saveProgress(projectId, {
      screenIndex,
      visited: [...visitedSet],
      answers: questionAnswers,
    });
  }, [projectId, screenIndex, visitedSet, questionAnswers]);

  function goTo(index: number) {
    const newVisited = new Set(visitedSet).add(index);
    setScreenIndex(index);
    setVisitedSet(newVisited);
    onScreenChange?.(index, screens.length, [...newVisited]);
  }

  function goNext() {
    if (screenIndex < screens.length - 1) {
      goTo(screenIndex + 1);
    } else {
      setPhase('complete');
      onScreenChange?.(screens.length - 1, screens.length, [...visitedSet]);
    }
  }

  function goPrev() {
    if (screenIndex > 0) goTo(screenIndex - 1);
  }

  function jumpTo(index: number) { goTo(index); }

  function handleAnswered(screenId: string, answer: QuestionAnswer) {
    setQuestionAnswers(prev => ({ ...prev, [screenId]: answer }));
    onAnswered?.(screenId, answer);
  }

  if (phase === 'cover') {
    return (
      <CoverScreen
        module={module}
        projectId={projectId}
        viewerMode={viewerMode}
        onStart={() => { setPhase('training'); goTo(screenIndex); }}
      />
    );
  }

  if (phase === 'complete') {
    return <CompletionScreen module={module} projectId={projectId} viewerMode={viewerMode} />;
  }

  return (
    <div className="player-shell">
      <div className="player-header">
        <header className="player-topbar">
          <span className="player-title">{projectTitle ?? module.meta.title}</span>
          <div className="player-topbar-right">
            <span className="player-progress">
              {screenIndex + 1} / {screens.length}
            </span>
            {projectId && (
              <Link to={`/projects/${projectId}`} className="player-exit-btn" aria-label="Exit training">
                ✕
              </Link>
            )}
          </div>
        </header>

        <div className="player-progress-bar-track">
          <div
            className="player-progress-bar-fill"
            style={{ width: `${((screenIndex + 1) / screens.length) * 100}%` }}
          />
        </div>

        {completedAt && (
          <div className="player-completion-banner">
            <span className="player-completion-banner-icon">✓</span>
            You completed this course on {new Date(completedAt).toLocaleDateString(undefined, { month: 'long', day: 'numeric', year: 'numeric' })}
          </div>
        )}
      </div>

      <PlayerScreenNav screens={screens} currentIndex={screenIndex} visitedSet={visitedSet} onJump={jumpTo} />

      <ScreenRenderer
        key={currentScreen.id}
        screen={currentScreen}
        screenIndex={screenIndex}
        totalScreens={screens.length}
        questionAnswer={questionAnswers[currentScreen.id]}
        onAnswered={handleAnswered}
        onNext={goNext}
        onPrev={goPrev}
        attribution={module.attribution}
      />
    </div>
  );
}

// ─── Floating screen nav ──────────────────────────────────────────────────────

function PlayerScreenNav({ screens, currentIndex, visitedSet, onJump }: {
  screens: Screen[];
  currentIndex: number;
  visitedSet: Set<number>;
  onJump: (index: number) => void;
}) {
  const activeRef = useRef<HTMLButtonElement>(null);

  useEffect(() => {
    activeRef.current?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
  }, [currentIndex]);

  return (
    <nav className="player-sidenav" aria-label="Course screens">
      {screens.map((screen, i) => {
        const isActive  = i === currentIndex;
        const isVisited = visitedSet.has(i) && !isActive;
        const canJump   = visitedSet.has(i);
        return (
          <button
            key={screen.id}
            ref={isActive ? activeRef : undefined}
            type="button"
            className={`player-sidenav-item${isActive ? ' active' : isVisited ? ' visited' : ' future'}`}
            onClick={() => canJump && onJump(i)}
            disabled={!canJump}
            aria-current={isActive ? 'step' : undefined}
            title={screen.title}
          >
            <span className="player-sidenav-dot" aria-hidden="true" />
            <span className="player-sidenav-label">{screen.title}</span>
          </button>
        );
      })}
    </nav>
  );
}

// ─── Cover screen ─────────────────────────────────────────────────────────────

function CoverScreen({
  module,
  projectId,
  viewerMode,
  onStart,
}: {
  module: Module;
  projectId: string;
  viewerMode: boolean;
  onStart: () => void;
}) {
  const coverImage = module.meta.cover_image;
  return (
    <div
      className={`player-shell player-cover${coverImage ? ' player-cover--has-image' : ''}`}
      style={coverImage ? { '--cover-image': `url(${coverImage})` } as React.CSSProperties : undefined}
    >
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
          {!viewerMode && (
            <Link to={`/projects/${projectId}`} className="muted">
              ← Back to project
            </Link>
          )}
        </div>
      </div>
    </div>
  );
}

// ─── Screen type labels ───────────────────────────────────────────────────────

const screenTypeLabel: Record<Screen['type'], string> = {
  text:         'Visual', // legacy — renders as image_text
  image_text:   'Visual',
  accordion:    'Explore',
  click_reveal: 'Discover',
  question:     'Knowledge Check',
  tabs:         'Navigate',
  process:      'Follow',
  flashcard:    'Recall',
  video:        'Watch',
  scenario:     'Decide',
};

// ─── Screen renderer ──────────────────────────────────────────────────────────

function ScreenRenderer({
  screen,
  screenIndex,
  totalScreens,
  questionAnswer,
  onAnswered,
  onNext,
  onPrev,
  attribution,
}: {
  screen: Screen;
  screenIndex: number;
  totalScreens: number;
  questionAnswer?: QuestionAnswer;
  onAnswered: (screenId: string, answer: QuestionAnswer) => void;
  onNext: () => void;
  onPrev: () => void;
  attribution: Module['attribution'];
}) {
  // Question is "ready" (Next unlocked) once it's been submitted — either
  // just now or restored from a previous visit.
  const questionReady =
    (screen.type !== 'question' && screen.type !== 'scenario') ||
    (questionAnswer?.submitted ?? false);
  const titleRef = useRef<HTMLHeadingElement>(null);
  const heroDir = useMemo(randomDir, []);
  const contentDir = useMemo(randomDir, []);

  useEffect(() => {
    titleRef.current?.focus();
  }, []);

  const isFirst = screenIndex === 0;
  const isLast = screenIndex === totalScreens - 1;

  const allAttributions = [...attribution.unsplash, ...(attribution.pexels ?? [])];
  const imageAttribution =
    screen.image && allAttributions.find(a => screen.image?.url.includes(a.image_id));

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
          {(screen.type === 'text' || screen.type === 'image_text') && (
            <ImageTextScreen screen={screen} attribution={imageAttribution ?? null} />
          )}
          {screen.type === 'accordion' && (
            <>
              {screen.image && <ScreenImage image={screen.image} attribution={imageAttribution ?? null} />}
              <AccordionScreen screen={screen} />
            </>
          )}
          {screen.type === 'click_reveal' && (
            <>
              {screen.image && <ScreenImage image={screen.image} attribution={imageAttribution ?? null} />}
              <ClickRevealScreen screen={screen} />
            </>
          )}
          {screen.type === 'question' && (
            <>
              {screen.image && <ScreenImage image={screen.image} attribution={imageAttribution ?? null} />}
              <QuestionScreen
                screen={screen}
                savedAnswer={questionAnswer}
                onAnswered={answer => onAnswered(screen.id, answer)}
              />
            </>
          )}
          {screen.type === 'tabs' && (
            <>
              {screen.image && <ScreenImage image={screen.image} attribution={imageAttribution ?? null} />}
              <TabsScreen screen={screen} />
            </>
          )}
          {screen.type === 'process' && (
            <>
              {screen.image && <ScreenImage image={screen.image} attribution={imageAttribution ?? null} />}
              <ProcessScreen screen={screen} />
            </>
          )}
          {screen.type === 'flashcard' && (
            <>
              {screen.image && <ScreenImage image={screen.image} attribution={imageAttribution ?? null} />}
              <FlashcardScreen screen={screen} />
            </>
          )}
          {screen.type === 'video' && <VideoScreen screen={screen} />}
          {screen.type === 'scenario' && (
            <>
              {screen.image && <ScreenImage image={screen.image} attribution={imageAttribution ?? null} />}
              <ScenarioScreen
                screen={screen}
                savedAnswer={questionAnswer}
                onAnswered={answer => onAnswered(screen.id, answer)}
              />
            </>
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

// ─── Shared screen image ──────────────────────────────────────────────────────

type AttributionEntry = Module['attribution']['unsplash'][number];

function ScreenImage({ image, attribution }: {
  image: NonNullable<Screen['image']>;
  attribution: AttributionEntry | null;
}) {
  return (
    <figure className="player-screen-image">
      <img
        src={image.url}
        alt={image.alt_text}
        className="player-image"
        onError={(e) => { (e.currentTarget as HTMLImageElement).parentElement!.style.display = 'none'; }}
      />
      {attribution && (
        <figcaption className="player-attribution">
          Photo by{' '}
          <a href={attribution.url} target="_blank" rel="noopener noreferrer">
            {attribution.photographer}
          </a>{' '}
          on {attribution.license.replace(' License', '')}
        </figcaption>
      )}
    </figure>
  );
}

// ─── Image + text screen ──────────────────────────────────────────────────────

function ImageTextScreen({
  screen,
  attribution,
}: {
  screen: Screen;
  attribution: AttributionEntry | null;
}) {
  const layout = screen.layout ?? 'top';

  const imageEl = screen.image ? (
    <figure className="player-figure">
      <img
        src={screen.image.url}
        alt={screen.image.alt_text}
        className="player-image"
        onError={(e) => { (e.currentTarget as HTMLImageElement).style.display = 'none'; }}
      />
      {attribution && (
        <figcaption className="player-attribution">
          Photo by{' '}
          <a href={attribution.url} target="_blank" rel="noopener noreferrer">
            {attribution.photographer}
          </a>{' '}
          on {attribution.license.replace(' License', '')}
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
  savedAnswer,
  onAnswered,
}: {
  screen: Screen;
  savedAnswer?: QuestionAnswer;
  onAnswered: (answer: QuestionAnswer) => void;
}) {
  const interaction = screen.interactions[0] as ChoiceInteraction | undefined;
  const [selected, setSelected] = useState<Set<string>>(
    () => new Set(savedAnswer?.selected ?? [])
  );
  const [submitted, setSubmitted] = useState(() => savedAnswer?.submitted ?? false);
  const [isCorrect, setIsCorrect] = useState(() => savedAnswer?.isCorrect ?? false);
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
    onAnswered({ selected: [...selected], submitted: true, isCorrect: correct });
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

// ─── Tabs screen ─────────────────────────────────────────────────────────────

function TabsScreen({ screen }: { screen: Screen }) {
  const [active, setActive] = useState(0);

  const interaction = screen.interactions[0];
  if (!interaction || interaction.kind !== 'tabs') return <RichText text={screen.body} />;

  return (
    <>
      {screen.body && <RichText text={screen.body} />}
      <div className="player-tabs">
        <div className="player-tabs-bar" role="tablist">
          {interaction.items.map((item, i) => (
            <button
              key={i}
              type="button"
              role="tab"
              aria-selected={i === active}
              className={`player-tab-btn${i === active ? ' active' : ''}`}
              onClick={() => setActive(i)}
            >
              {item.title}
            </button>
          ))}
        </div>
        <div className="player-tab-panel" role="tabpanel">
          <RichText text={interaction.items[active].body} />
        </div>
      </div>
    </>
  );
}

// ─── Process screen ───────────────────────────────────────────────────────────

function ProcessScreen({ screen }: { screen: Screen }) {
  const interaction = screen.interactions[0];
  if (!interaction || interaction.kind !== 'process') return <RichText text={screen.body} />;

  return (
    <>
      {screen.body && <RichText text={screen.body} />}
      <ol className="player-process">
        {interaction.items.map((step, i) => (
          <li key={i} className="player-process-step">
            <div className="player-process-step-num" aria-hidden="true">{i + 1}</div>
            <div className="player-process-step-content">
              <strong className="player-process-step-title">{step.title}</strong>
              {step.body && <RichText text={step.body} className="player-process-step-body" />}
            </div>
          </li>
        ))}
      </ol>
    </>
  );
}

// ─── Flashcard screen ─────────────────────────────────────────────────────────

function FlashcardScreen({ screen }: { screen: Screen }) {
  const [flipped, setFlipped] = useState<Set<number>>(new Set());

  const interaction = screen.interactions[0];
  if (!interaction || interaction.kind !== 'flashcard') return <RichText text={screen.body} />;

  function toggle(i: number) {
    setFlipped(prev => {
      const next = new Set(prev);
      next.has(i) ? next.delete(i) : next.add(i);
      return next;
    });
  }

  return (
    <>
      {screen.body && <RichText text={screen.body} />}
      <div className="player-flashcard-grid">
        {interaction.cards.map((card, i) => {
          const isFlipped = flipped.has(i);
          return (
            <div key={i} className={`player-flashcard-wrap${isFlipped ? ' player-flashcard-wrap--flipped' : ''}`}>
              <button
                type="button"
                className="player-flashcard"
                onClick={() => toggle(i)}
                aria-label={isFlipped ? `Definition: ${card.back}` : `Term: ${card.front}. Click to flip.`}
              >
                <div className="player-flashcard-inner" aria-hidden="true">
                  <div className="player-flashcard-face player-flashcard-front">
                    <span className="player-flashcard-label">Term</span>
                    <span className="player-flashcard-text">{card.front}</span>
                  </div>
                  <div className="player-flashcard-face player-flashcard-back">
                    <span className="player-flashcard-label">Definition</span>
                    <span className="player-flashcard-text">{card.back || '—'}</span>
                  </div>
                </div>
              </button>
            </div>
          );
        })}
      </div>
      <p className="player-flashcard-hint muted">Click any card to flip it</p>
    </>
  );
}

// ─── Video screen ─────────────────────────────────────────────────────────────

function VideoScreen({ screen }: { screen: Screen }) {
  const interaction = screen.interactions[0];
  if (!interaction || interaction.kind !== 'video') return null;

  const { url, caption } = interaction;
  if (!url) return <p className="muted">No video URL has been set for this screen.</p>;

  const embedUrl = (() => {
    // YouTube
    const yt = url.match(/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([A-Za-z0-9_-]{11})/);
    if (yt) return `https://www.youtube.com/embed/${yt[1]}?rel=0`;
    // Vimeo
    const vm = url.match(/vimeo\.com\/(?:video\/)?(\d+)/);
    if (vm) return `https://player.vimeo.com/video/${vm[1]}`;
    return null;
  })();

  return (
    <div className="player-video-wrap">
      {screen.body && <RichText text={screen.body} />}
      <div className="player-video-container">
        {embedUrl ? (
          <iframe
            src={embedUrl}
            className="player-video-iframe"
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
            allowFullScreen
            title={screen.title}
          />
        ) : (
          <video src={url} className="player-video-native" controls />
        )}
      </div>
      {caption && <p className="player-video-caption muted">{caption}</p>}
    </div>
  );
}

// ─── Scenario screen ──────────────────────────────────────────────────────────

function ScenarioScreen({
  screen,
  savedAnswer,
  onAnswered,
}: {
  screen: Screen;
  savedAnswer?: QuestionAnswer;
  onAnswered: (answer: QuestionAnswer) => void;
}) {
  const interaction = screen.interactions[0];
  if (!interaction || interaction.kind !== 'scenario') return null;

  const [picked, setPicked] = useState<string | null>(
    savedAnswer?.submitted ? (savedAnswer.selected[0] ?? null) : null
  );

  const chosen = picked ? interaction.choices.find(c => c.id === picked) : null;

  function handlePick(id: string) {
    if (picked) return; // locked after first pick
    setPicked(id);
    onAnswered({ selected: [id], submitted: true, isCorrect: true });
  }

  return (
    <>
      <div className="player-scenario-setup">
        <RichText text={interaction.setup} />
      </div>

      {!chosen && (
        <div className="player-scenario-choices">
          {interaction.choices.map(choice => (
            <button
              key={choice.id}
              type="button"
              className="player-scenario-choice"
              onClick={() => handlePick(choice.id)}
            >
              {choice.label}
            </button>
          ))}
        </div>
      )}

      {chosen && (
        <div className={`player-scenario-consequence player-scenario-consequence--${chosen.tone}`}>
          <span className="player-scenario-consequence-icon" aria-hidden="true">
            {chosen.tone === 'success' ? '✓' : chosen.tone === 'failure' ? '✗' : '⚠'}
          </span>
          <div>
            <p className="player-scenario-choice-label">{chosen.label}</p>
            <p className="player-scenario-consequence-text">{chosen.consequence}</p>
          </div>
        </div>
      )}
    </>
  );
}

// ─── Completion screen ────────────────────────────────────────────────────────

function CompletionScreen({ module, projectId, viewerMode }: { module: Module; projectId: string; viewerMode: boolean }) {
  const sources = module.sources && module.sources.length > 0 ? module.sources : null;
  return (
    <div className="player-shell player-cover">
      <div className="player-cover-card player-complete-card">
        <div className="player-complete-icon" aria-hidden="true">✓</div>
        <h1 className="player-cover-title">Training complete</h1>
        <p className="player-complete-subtitle">{module.meta.title}</p>
        {sources && (
          <div className="player-sources">
            <h3 className="player-sources-heading">Sources</h3>
            <ul className="player-sources-list">
              {sources.map((src, i) => (
                <li key={i} className="player-sources-item">{src}</li>
              ))}
            </ul>
          </div>
        )}
        {!viewerMode && (
          <div className="player-cover-actions">
            <Link to={`/projects/${projectId}`}>
              <button type="button">Back to project</button>
            </Link>
          </div>
        )}
      </div>
    </div>
  );
}
