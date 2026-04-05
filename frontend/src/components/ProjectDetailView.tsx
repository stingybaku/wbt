import { useEffect, useRef, useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { fetchProject, fetchModule, generateModule, fetchInvites, createInvite, revokeInvite, downloadCertificate } from '../lib/api';
import { useAuth } from '../context/AuthContext';
import type { Project, ModuleRecord, ProjectInvite } from '../lib/types';

const POLL_INTERVAL_MS = 3000;
const MAX_INVITES = 5;

export function ProjectDetailView() {
  const { id } = useParams<{ id: string }>();
  const { token } = useAuth();

  const [project, setProject] = useState<Project | null>(null);
  const [module, setModule] = useState<ModuleRecord | null>(null);
  const [invites, setInvites] = useState<ProjectInvite[]>([]);
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
      if (res.module?.status !== 'generating') stopPolling();
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
    Promise.all([
      fetchProject(token, id),
      fetchModule(token, id),
      fetchInvites(token, id),
    ])
      .then(([projectRes, moduleRes, invitesRes]) => {
        setProject(projectRes.project);
        setModule(moduleRes.module);
        setInvites(invitesRes.invites);
        if (moduleRes.module?.status === 'generating') startPolling();
      })
      .catch((err: unknown) => setError(err instanceof Error ? err.message : 'Failed to load project'))
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

  async function handleInvite(email: string, expiresDays: number) {
    if (!token || !id) return;
    const res = await createInvite(token, id, { email, expires_days: expiresDays });
    setInvites(prev => {
      const without = prev.filter(i => i.email !== email);
      return [res.invite, ...without];
    });
  }

  async function handleRevoke(inviteId: string) {
    if (!token || !id) return;
    await revokeInvite(token, id, inviteId);
    setInvites(prev => prev.map(i => i.id === inviteId ? { ...i, is_active: false, revoked_at: new Date().toISOString() } : i));
  }

  if (loading) {
    return (
      <main className="app-shell">
        <section className="layout"><p className="muted">Loading...</p></section>
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
        {project.cover_image_url && (
          <div
            className="project-detail-hero"
            style={{ backgroundImage: `url(${project.cover_image_url})` }}
          />
        )}

        <header className="topbar">
          <div>
            <Link to="/projects" className="muted">← Projects</Link>
            <h1>{project.title}</h1>
          </div>
        </header>

        <div className="detail-grid">
          {/* Left column */}
          <div className="detail-main">
            <div className="card">
              <h2>Training Module</h2>
              <ModuleStatusPanel
                module={module}
                hasSource={!!project.source_type}
                retrying={retrying}
                onRetry={handleRetry}
              />
            </div>

            <div className="card">
              <h2>Project Info</h2>
              <dl className="detail-dl">
                <dt>Source</dt>
                <dd>
                  {project.source_type
                    ? project.source_type === 'url'
                      ? project.source_url
                      : 'Uploaded file'
                    : <span className="muted">No source attached</span>}
                  {project.source_character_count != null && (
                    <span className="muted"> — {project.source_character_count.toLocaleString()} chars</span>
                  )}
                </dd>
                <dt>Rights attested</dt>
                <dd>{project.rights_attestation ? 'Yes' : 'No'}</dd>
                <dt>Created</dt>
                <dd>{new Date(project.created_at).toLocaleString()}</dd>
              </dl>
            </div>
          </div>

          {/* Right column */}
          <div className="detail-side">
            <ViewersPanel
              invites={invites}
              moduleReady={module?.status === 'generated'}
              onInvite={handleInvite}
              onRevoke={handleRevoke}
              authToken={token ?? ''}
              projectId={id ?? ''}
            />
          </div>
        </div>
      </section>
    </main>
  );
}

// ─── Module status ────────────────────────────────────────────────────────────

function ModuleStatusPanel({
  module, hasSource, retrying, onRetry,
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
        <p className="muted"><span className="generating-indicator">●</span> Generating your training module...</p>
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
        <p><strong>{title}</strong> — {screenCount} screens</p>
        {objectives.length > 0 && (
          <ul style={{ marginTop: '0.5rem', paddingLeft: '1.25rem' }}>
            {objectives.map((obj, i) => <li key={i} className="muted" style={{ fontSize: '0.875rem' }}>{obj}</li>)}
          </ul>
        )}
        <div style={{ marginTop: '1rem', display: 'flex', gap: '0.75rem', alignItems: 'center', flexWrap: 'wrap' }}>
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

// ─── Viewers panel ────────────────────────────────────────────────────────────

function ViewersPanel({
  invites,
  moduleReady,
  onInvite,
  onRevoke,
  authToken,
  projectId,
}: {
  invites: ProjectInvite[];
  moduleReady: boolean;
  onInvite: (email: string, expiresDays: number) => Promise<void>;
  onRevoke: (inviteId: string) => Promise<void>;
  authToken: string;
  projectId: string;
}) {
  const [email, setEmail] = useState('');
  const [expiresDays, setExpiresDays] = useState(30);
  const [sending, setSending] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);
  const [revoking, setRevoking] = useState<string | null>(null);
  const [downloading, setDownloading] = useState<string | null>(null);
  const [downloadError, setDownloadError] = useState<{ [inviteId: string]: string }>({});

  const activeInvites = invites.filter(i => i.is_active);
  const slotsLeft = MAX_INVITES - activeInvites.length;

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setFormError(null);
    setSending(true);
    try {
      await onInvite(email.trim(), expiresDays);
      setEmail('');
    } catch (err: unknown) {
      setFormError(err instanceof Error ? err.message : 'Failed to send invite');
    } finally {
      setSending(false);
    }
  }

  async function handleRevoke(inviteId: string) {
    setRevoking(inviteId);
    try {
      await onRevoke(inviteId);
    } finally {
      setRevoking(null);
    }
  }

  async function handleDownloadCertificate(inviteId: string) {
    setDownloading(inviteId);
    setDownloadError(prev => ({ ...prev, [inviteId]: '' }));
    try {
      await downloadCertificate(authToken, projectId, inviteId);
    } catch (err: unknown) {
      setDownloadError(prev => ({
        ...prev,
        [inviteId]: err instanceof Error ? err.message : 'Download failed.',
      }));
    } finally {
      setDownloading(null);
    }
  }

  return (
    <div className="card viewers-panel">
      <div className="viewers-panel-header">
        <h2>Viewers</h2>
        <span className="viewers-slots-badge">
          {activeInvites.length} / {MAX_INVITES}
        </span>
      </div>

      {!moduleReady && (
        <p className="muted" style={{ fontSize: '0.85rem', marginBottom: '1rem' }}>
          Invites can be sent once the module is generated.
        </p>
      )}

      {moduleReady && slotsLeft > 0 && (
        <form className="invite-form" onSubmit={handleSubmit}>
          <input
            type="email"
            placeholder="Email address"
            value={email}
            onChange={e => setEmail(e.target.value)}
            required
            disabled={sending}
          />
          <div className="invite-form-row">
            <label className="invite-expires-label">
              Expires in
              <input
                type="number"
                min={1}
                max={365}
                value={expiresDays}
                onChange={e => setExpiresDays(Number(e.target.value))}
                disabled={sending}
                className="invite-days-input"
              />
              days
            </label>
            <button type="submit" disabled={sending || !email.trim()}>
              {sending ? 'Sending…' : 'Invite'}
            </button>
          </div>
          {formError && <p className="invite-form-error">{formError}</p>}
        </form>
      )}

      {moduleReady && slotsLeft === 0 && (
        <p className="muted" style={{ fontSize: '0.85rem', marginBottom: '1rem' }}>
          Basic plan limit reached ({MAX_INVITES} active viewers).
        </p>
      )}

      {invites.length === 0 ? (
        <p className="muted" style={{ fontSize: '0.85rem' }}>No viewers invited yet.</p>
      ) : (
        <ul className="invite-list">
          {invites.map(invite => (
            <li key={invite.id} className={`invite-item${invite.is_active ? '' : ' invite-item--inactive'}`}>
              <div className="invite-item-info">
                <span className="invite-item-email">{invite.email}</span>
                <div className="invite-item-meta">
                  {invite.is_active ? (
                    <span className="invite-badge invite-badge--active">Active</span>
                  ) : invite.revoked_at ? (
                    <span className="invite-badge invite-badge--revoked">Revoked</span>
                  ) : (
                    <span className="invite-badge invite-badge--expired">Expired</span>
                  )}
                  <span className="muted" style={{ fontSize: '0.75rem' }}>
                    Expires {new Date(invite.expires_at).toLocaleDateString()}
                  </span>
                </div>
              </div>

              <div className="invite-item-progress">
                {invite.progress ? (
                  <>
                    <div className="invite-progress-bar-track">
                      <div
                        className="invite-progress-bar-fill"
                        style={{ width: `${invite.progress.percent}%` }}
                      />
                    </div>
                    <span className="invite-progress-label">
                      {invite.progress.completed_at
                        ? 'Completed'
                        : `${invite.progress.percent}% · screen ${invite.progress.current_screen_index + 1}/${invite.progress.total_screens}`}
                    </span>
                  </>
                ) : (
                  <span className="muted" style={{ fontSize: '0.75rem' }}>Not started</span>
                )}
              </div>

              {invite.progress?.completed_at && (
                <div className="invite-certificate-action">
                  <button
                    type="button"
                    className="ghost invite-certificate-btn"
                    onClick={() => handleDownloadCertificate(invite.id)}
                    disabled={downloading === invite.id}
                  >
                    {downloading === invite.id ? 'Downloading…' : 'Download Certificate'}
                  </button>
                  {downloadError[invite.id] && (
                    <p className="invite-certificate-error">{downloadError[invite.id]}</p>
                  )}
                </div>
              )}

              {invite.is_active && (
                <button
                  type="button"
                  className="ghost invite-revoke-btn"
                  onClick={() => handleRevoke(invite.id)}
                  disabled={revoking === invite.id}
                >
                  {revoking === invite.id ? '…' : 'Revoke'}
                </button>
              )}
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
