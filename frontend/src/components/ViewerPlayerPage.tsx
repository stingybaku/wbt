import { useEffect, useRef, useState } from 'react';
import { useParams } from 'react-router-dom';
import { fetchViewerModule, updateViewerProgress, verifyViewer } from '../lib/api';
import type { ViewerQuestionAnswer } from '../lib/api';
import { WBTPlayer } from './WBTPlayer';
import type { Module } from '../lib/moduleSchema';

const STORAGE_KEY = (token: string) => `viewer_verified_${token}`;
const STORAGE_NAME_KEY = (token: string) => `viewer_name_${token}`;

type ViewerState =
  | { phase: 'gate' }
  | { phase: 'loading' }
  | { phase: 'ready'; module: Module; projectTitle: string; initialScreenIndex: number; initialAnswers: Record<string, ViewerQuestionAnswer>; initialVisited: number[] }
  | { phase: 'error'; message: string };

export function ViewerPlayerPage() {
  const { token } = useParams<{ token: string }>();
  const [state, setState] = useState<ViewerState>({ phase: 'gate' });
  const [email, setEmail] = useState('');
  const [name, setName] = useState('');
  const [verifying, setVerifying] = useState(false);
  const [gateError, setGateError] = useState<string | null>(null);

  // Track accumulated progress for DB sync
  const answersRef = useRef<Record<string, ViewerQuestionAnswer>>({});
  const visitedRef = useRef<number[]>([]);
  const screenRef = useRef<{ index: number; total: number }>({ index: 0, total: 0 });

  // On mount: if this token was already verified in localStorage, skip the gate
  useEffect(() => {
    if (!token) return;
    const saved = localStorage.getItem(STORAGE_KEY(token));
    if (saved) {
      const savedName = localStorage.getItem(STORAGE_NAME_KEY(token));
      if (savedName) setName(savedName);
      loadCourse(token);
    }
  }, [token]);

  async function loadCourse(tok: string) {
    setState({ phase: 'loading' });
    try {
      const res = await fetchViewerModule(tok);
      const savedIndex = res.progress?.current_screen_index ?? 0;
      // If the viewer completed the course, start from the beginning
      const initialScreenIndex = res.progress?.completed_at ? 0 : savedIndex;
      const initialAnswers = res.progress?.question_answers ?? {};
      const initialVisited = res.progress?.visited_screens ?? [];
      // Seed refs so first sync has correct values
      answersRef.current = initialAnswers;
      visitedRef.current = initialVisited;
      screenRef.current = { index: initialScreenIndex, total: res.module.screens.length };
      setState({
        phase: 'ready',
        module: res.module,
        projectTitle: res.project_title,
        initialScreenIndex,
        initialAnswers,
        initialVisited,
      });
    } catch (err: unknown) {
      setState({ phase: 'error', message: err instanceof Error ? err.message : 'Failed to load course.' });
    }
  }

  async function handleGateSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!token) return;
    setGateError(null);
    setVerifying(true);
    try {
      await verifyViewer(token, email.trim(), name.trim());
      localStorage.setItem(STORAGE_KEY(token), email.trim());
      localStorage.setItem(STORAGE_NAME_KEY(token), name.trim());
      await loadCourse(token);
    } catch (err: unknown) {
      setGateError(err instanceof Error ? err.message : 'Verification failed.');
    } finally {
      setVerifying(false);
    }
  }

  function syncProgress(index: number, total: number) {
    if (!token) return;
    updateViewerProgress(token, {
      current_screen_index: index,
      total_screens: total,
      question_answers: answersRef.current,
      visited_screens: visitedRef.current,
    }).catch(() => {});
  }

  function handleScreenChange(index: number, total: number, visited: number[]) {
    screenRef.current = { index, total };
    visitedRef.current = visited;
    syncProgress(index, total);
  }

  function handleAnswered(screenId: string, answer: ViewerQuestionAnswer) {
    answersRef.current = { ...answersRef.current, [screenId]: answer };
    syncProgress(screenRef.current.index, screenRef.current.total);
  }

  if (state.phase === 'gate') {
    return (
      <div className="viewer-gate-shell">
        <div className="viewer-gate-card">
          <div className="viewer-gate-brand">WBT Forge</div>
          <h1 className="viewer-gate-title">Access your course</h1>
          <p className="viewer-gate-subtitle">
            Enter your name and the email address to which this invitation was sent.
          </p>
          <form className="viewer-gate-form" onSubmit={handleGateSubmit}>
            <input
              type="text"
              placeholder="Your full name"
              value={name}
              onChange={e => setName(e.target.value)}
              required
              autoFocus
              disabled={verifying}
            />
            <input
              type="email"
              placeholder="you@example.com"
              value={email}
              onChange={e => setEmail(e.target.value)}
              required
              disabled={verifying}
            />
            <button type="submit" disabled={verifying || !name.trim() || !email.trim()}>
              {verifying ? 'Verifying…' : 'Continue →'}
            </button>
            {gateError && <p className="viewer-gate-error">{gateError}</p>}
          </form>
        </div>
      </div>
    );
  }

  if (state.phase === 'loading') {
    return (
      <div className="viewer-loading">
        <p>Loading course…</p>
      </div>
    );
  }

  if (state.phase === 'error') {
    return (
      <div className="viewer-error">
        <h1>Link unavailable</h1>
        <p>{state.message}</p>
      </div>
    );
  }

  return (
    <WBTPlayer
      module={state.module}
      projectId=""
      projectTitle={state.projectTitle}
      viewerMode
      initialScreenIndex={state.initialScreenIndex}
      initialAnswers={state.initialAnswers}
      initialVisited={state.initialVisited}
      onScreenChange={handleScreenChange}
      onAnswered={handleAnswered}
    />
  );
}
