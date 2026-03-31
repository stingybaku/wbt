import { useEffect, useRef, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { fetchModule, fetchProject } from '../lib/api';
import { useAuth } from '../context/AuthContext';
import type { ModuleRecord, Project } from '../lib/types';
import type { Module, Screen, Interaction } from '../lib/moduleSchema';

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
      <header className="player-topbar">
        <span className="player-title">{module.meta.title}</span>
        <span className="player-progress">
          {screenIndex + 1} / {screens.length}
        </span>
      </header>

      <div className="player-progress-bar-track">
        <div
          className="player-progress-bar-fill"
          style={{ width: `${((screenIndex + 1) / screens.length) * 100}%` }}
        />
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

  // Focus the screen title on mount for keyboard/screen reader users
  useEffect(() => {
    titleRef.current?.focus();
  }, []);

  const isFirst = screenIndex === 0;
  const isLast = screenIndex === totalScreens - 1;

  // Find Unsplash attribution for any image on this screen
  const imageAttribution =
    screen.image && attribution.unsplash.find(a => screen.image?.url.includes(a.image_id));

  return (
    <main className="player-content" aria-label={`Screen ${screenIndex + 1} of ${totalScreens}`}>
      <article className="player-screen">
        <h2 className="player-screen-title" tabIndex={-1} ref={titleRef}>
          {screen.title}
        </h2>

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
    </main>
  );
}

// ─── Text screen ──────────────────────────────────────────────────────────────

function TextScreen({ screen }: { screen: Screen }) {
  return <p className="player-body">{screen.body}</p>;
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

  const textEl = <p className="player-body">{screen.body}</p>;

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
  if (!interaction || interaction.kind !== 'accordion') return <p className="player-body">{screen.body}</p>;

  function toggle(i: number) {
    setOpenItems(prev => {
      const next = new Set(prev);
      next.has(i) ? next.delete(i) : next.add(i);
      return next;
    });
  }

  return (
    <>
      {screen.body && <p className="player-body">{screen.body}</p>}
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
                  {item.body}
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

function ClickRevealScreen({ screen }: { screen: Screen }) {
  const [revealed, setRevealed] = useState<Set<number>>(new Set());

  const interaction = screen.interactions[0];
  if (!interaction || interaction.kind !== 'click_reveal') return <p className="player-body">{screen.body}</p>;

  function reveal(i: number) {
    setRevealed(prev => new Set(prev).add(i));
  }

  return (
    <>
      {screen.body && <p className="player-body">{screen.body}</p>}
      <div className="player-reveal-grid">
        {interaction.items.map((item, i) => {
          const isRevealed = revealed.has(i);
          return (
            <div key={i} className={`player-reveal-item ${isRevealed ? 'revealed' : ''}`}>
              <button
                type="button"
                className="player-reveal-trigger"
                onClick={() => reveal(i)}
                aria-expanded={isRevealed}
                disabled={isRevealed}
              >
                {item.title}
              </button>
              {isRevealed && (
                <p className="player-reveal-body" role="region" aria-label={item.title}>
                  {item.body}
                </p>
              )}
            </div>
          );
        })}
      </div>
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

  if (!interaction) return <p className="player-body">{screen.body}</p>;

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
      {screen.body && <p className="player-body">{screen.body}</p>}

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
