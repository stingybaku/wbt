import { useEffect, useRef, useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { fetchProject, fetchModule, generateModule } from '../lib/api';
import { useAuth } from '../context/AuthContext';
import type { Project, ModuleRecord } from '../lib/types';

const POLL_INTERVAL_MS = 3000;

export function ProjectDetailView() {
  const { id } = useParams<{ id: string }>();
  const { token } = useAuth();

  const [project, setProject] = useState<Project | null>(null);
  const [module, setModule] = useState<ModuleRecord | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [retrying, setRetrying] = useState(false);

  const pollRef = useRef<ReturnType<typeof setInterval> | null>(null);

  function stopPolling() {
    if (pollRef.current) {
      clearInterval(pollRef.current);
      pollRef.current = null;
    }
  }

  async function pollModule() {
    if (!token || !id) return;
    try {
      const res = await fetchModule(token, id);
      setModule(res.module);
      if (res.module?.status !== 'generating') {
        stopPolling();
      }
    } catch {
      stopPolling();
    }
  }

  function startPolling() {
    stopPolling();
    pollRef.current = setInterval(pollModule, POLL_INTERVAL_MS);
  }

  useEffect(() => {
    if (!token || !id) return;

    setLoading(true);
    Promise.all([fetchProject(token, id), fetchModule(token, id)])
      .then(([projectRes, moduleRes]) => {
        setProject(projectRes.project);
        setModule(moduleRes.module);
        if (moduleRes.module?.status === 'generating') {
          startPolling();
        }
      })
      .catch((err: unknown) => {
        setError(err instanceof Error ? err.message : 'Failed to load project');
      })
      .finally(() => setLoading(false));

    return () => stopPolling();
  }, [token, id]);

  async function handleRetry() {
    if (!token || !id || !project) return;
    setRetrying(true);
    setError(null);
    try {
      await generateModule(token, id, { duration_minutes: 20, assessment_count: 5 });
      const res = await fetchModule(token, id);
      setModule(res.module);
      startPolling();
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Failed to start generation');
    } finally {
      setRetrying(false);
    }
  }

  if (loading) {
    return (
      <main className="app-shell">
        <section className="layout">
          <p className="muted">Loading...</p>
        </section>
      </main>
    );
  }

  if (error || !project) {
    return (
      <main className="app-shell">
        <section className="layout">
          <div className="error-banner">{error ?? 'Project not found'}</div>
          <Link to="/projects">← Back to projects</Link>
        </section>
      </main>
    );
  }

  return (
    <main className="app-shell">
      <section className="layout">
        <header className="topbar">
          <div>
            <Link to="/projects" className="muted">← Projects</Link>
            <h1>{project.title}</h1>
          </div>
        </header>

        <div className="card">
          <h2>Project Details</h2>
          <p><strong>Rights attested:</strong> {project.rights_attestation ? 'Yes' : 'No'}</p>
          {project.source_type ? (
            <p>
              <strong>Source:</strong>{' '}
              {project.source_type === 'url' ? project.source_url : 'Uploaded file'}
              {project.source_character_count != null && (
                <span className="muted"> — {project.source_character_count.toLocaleString()} characters extracted</span>
              )}
            </p>
          ) : (
            <p className="muted">No content source attached.</p>
          )}
          <p><strong>Created:</strong> {new Date(project.created_at).toLocaleString()}</p>
        </div>

        <div className="card">
          <h2>Training Module</h2>
          <ModuleStatusPanel
            module={module}
            hasSource={!!project.source_type}
            retrying={retrying}
            onRetry={handleRetry}
          />
        </div>
      </section>
    </main>
  );
}

function ModuleStatusPanel({
  module,
  hasSource,
  retrying,
  onRetry,
}: {
  module: ModuleRecord | null;
  hasSource: boolean;
  retrying: boolean;
  onRetry: () => void;
}) {
  if (!module && !hasSource) {
    return <p className="muted">No content source attached. <Link to="/projects/new">Start a new project</Link> to upload content.</p>;
  }

  if (!module || module.status === 'generating') {
    return (
      <div>
        <p className="muted">
          <span className="generating-indicator">●</span> Generating your training module...
        </p>
        <p className="muted" style={{ fontSize: '0.85em' }}>This can take up to 60 seconds. The page will update automatically.</p>
      </div>
    );
  }

  if (module.status === 'failed') {
    return (
      <div>
        <div className="error-banner">{module.generation_error ?? 'Generation failed.'}</div>
        <button type="button" onClick={onRetry} disabled={retrying} style={{ marginTop: '0.75rem' }}>
          {retrying ? 'Retrying...' : 'Retry generation'}
        </button>
      </div>
    );
  }

  if (module.status === 'generated') {
    const screenCount = module.module_json?.screens.length ?? 0;
    const title = module.module_json?.meta.title ?? 'Untitled';
    const objectives = module.module_json?.learning_objectives ?? [];
    const openFlags = (module.module_json?.review_flags ?? []).filter(f => f.status === 'open').length;

    return (
      <div>
        <p><strong>{title}</strong> — {screenCount} screens generated</p>
        {objectives.length > 0 && (
          <ul style={{ marginTop: '0.5rem' }}>
            {objectives.map((obj, i) => <li key={i}>{obj}</li>)}
          </ul>
        )}
        <div style={{ marginTop: '1rem', display: 'flex', gap: '0.75rem', alignItems: 'center' }}>
          <Link to={`/projects/${module.project_id}/review`}>
            <button type="button">Review module</button>
          </Link>
          <Link to={`/projects/${module.project_id}/play`}>
            <button type="button" className="ghost">Preview</button>
          </Link>
          {openFlags > 0 && (
            <span className="muted">{openFlags} open flag{openFlags !== 1 ? 's' : ''}</span>
          )}
        </div>
      </div>
    );
  }

  return null;
}
