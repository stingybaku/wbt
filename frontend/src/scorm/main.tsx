import { useState } from 'react';
import { createRoot } from 'react-dom/client';
import { WBTPlayer } from '../components/WBTPlayer';
import type { Module } from '../lib/moduleSchema';
import { findApi, ScormSession, type ScormWindow } from './runtime';
import '../styles/app.css';

function Course({ module, session }: { module: Module; session: ScormSession }) {
  const [error, setError] = useState<string | null>(null);
  function save(action: () => void) {
    try { action(); setError(null); }
    catch (err) { setError(err instanceof Error ? err.message : 'Progress could not be saved.'); }
  }
  return <>
    {error && <div role="alert" className="error-banner">{error}</div>}
    <WBTPlayer module={module} projectId="" viewerMode
      initialScreenIndex={session.index} initialVisited={session.visited} initialAnswers={session.answers}
      onScreenChange={(index, _total, visited, complete) => save(() => session.progress(index, visited, complete))}
      onAnswered={(id, answer) => save(() => session.answer(id, answer.selected))} />
  </>;
}

const root = createRoot(document.getElementById('root')!);
async function start() {
  try {
    const api = findApi(window as unknown as ScormWindow);
    if (!api) throw new Error('Open this course from a SCORM 1.2 compatible LMS.');
    const response = await fetch('./module.json');
    if (!response.ok) throw new Error('The course package is missing its content.');
    const module: Module = await response.json();
    if (!module.screens?.length) throw new Error('The course has no screens.');
    const session = new ScormSession(api, module.screens);
    window.addEventListener('pagehide', () => {
      try { session.finish(); } catch { /* The runtime already attempts commit before finishing. */ }
    });
    root.render(<Course module={module} session={session} />);
  } catch (err) {
    root.render(<div role="alert" className="error-banner">{err instanceof Error ? err.message : 'Unable to open course.'}</div>);
  }
}
void start();
