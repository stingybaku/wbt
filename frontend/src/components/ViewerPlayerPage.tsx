import { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import { fetchViewerModule, updateViewerProgress, verifyViewer } from '../lib/api';
import { WBTPlayer } from './WBTPlayer';
import type { Module } from '../lib/moduleSchema';

const STORAGE_KEY = (token: string) => `viewer_verified_${token}`;

type ViewerState =
  | { phase: 'gate' }
  | { phase: 'loading' }
  | { phase: 'ready'; module: Module; projectTitle: string; initialScreenIndex: number }
  | { phase: 'error'; message: string };

export function ViewerPlayerPage() {
  const { token } = useParams<{ token: string }>();
  const [state, setState] = useState<ViewerState>({ phase: 'gate' });
  const [email, setEmail] = useState('');
  const [verifying, setVerifying] = useState(false);
  const [gateError, setGateError] = useState<string | null>(null);

  // On mount: if this token was already verified in localStorage, skip the gate
  useEffect(() => {
    if (!token) return;
    const saved = localStorage.getItem(STORAGE_KEY(token));
    if (saved) {
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
      setState({
        phase: 'ready',
        module: res.module,
        projectTitle: res.project_title,
        initialScreenIndex,
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
      await verifyViewer(token, email.trim());
      localStorage.setItem(STORAGE_KEY(token), email.trim());
      await loadCourse(token);
    } catch (err: unknown) {
      setGateError(err instanceof Error ? err.message : 'Verification failed.');
    } finally {
      setVerifying(false);
    }
  }

  function handleScreenChange(index: number, total: number) {
    if (!token) return;
    updateViewerProgress(token, { current_screen_index: index, total_screens: total }).catch(() => {});
  }

  if (state.phase === 'gate') {
    return (
      <div className="viewer-gate-shell">
        <div className="viewer-gate-card">
          <div className="viewer-gate-brand">WBT Forge</div>
          <h1 className="viewer-gate-title">Access your course</h1>
          <p className="viewer-gate-subtitle">
            Enter the email address to which this invitation was sent.
          </p>
          <form className="viewer-gate-form" onSubmit={handleGateSubmit}>
            <input
              type="email"
              placeholder="you@example.com"
              value={email}
              onChange={e => setEmail(e.target.value)}
              required
              autoFocus
              disabled={verifying}
            />
            <button type="submit" disabled={verifying || !email.trim()}>
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
      onScreenChange={handleScreenChange}
    />
  );
}
