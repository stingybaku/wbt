import { useEffect, useRef, useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { fetchProject, fetchModule, generateModule, fetchInvites, createInvite, revokeInvite, downloadCertificate, fetchProjectReport } from '../lib/api';
import type { ProjectReport } from '../lib/api';
import { useAuth } from '../context/AuthContext';
import type { Project, ModuleRecord, ProjectInvite } from '../lib/types';

const POLL_INTERVAL_MS = 3000;
const MAX_INVITES = 10;

export function ProjectDetailView() {
  const { id } = useParams<{ id: string }>();
  const { token } = useAuth();

  const [project, setProject] = useState<Project | null>(null);
  const [module, setModule] = useState<ModuleRecord | null>(null);
  const [invites, setInvites] = useState<ProjectInvite[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [retrying, setRetrying] = useState(false);
  const [activeTab, setActiveTab] = useState<'overview' | 'report'>('overview');

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
    return <section className="layout"><p className="muted">Loading...</p></section>;
  }

  if (error || !project) {
    return (
      <section className="layout">
        <div className="error-banner">{error ?? 'Project not found'}</div>
        <Link to="/projects">← Back to projects</Link>
      </section>
    );
  }

  const screenCount  = module?.module_json?.screens.length ?? null;
  const duration     = module?.module_json?.meta.duration_minutes ?? null;
  const activeViewers    = invites.filter(i => i.is_active).length;
  const completedViewers = invites.filter(i => i.progress?.completed_at).length;
  const openFlags = (module?.module_json?.review_flags ?? []).filter((f: { status: string }) => f.status === 'open').length;

  return (
    <>
      <div
        className={`project-detail-hero${project.cover_image_url ? '' : ' project-detail-hero--no-image'}`}
        style={project.cover_image_url ? { backgroundImage: `url(${project.cover_image_url})` } : undefined}
      >
        <div className="project-detail-hero-inner">
          <Link to="/projects" className="project-detail-hero-back">← Projects</Link>
          <h1>{project.title}</h1>
        </div>
      </div>

      <section className="layout">
        {/* Project metadata — replaces the Project Info card */}
        <div className="detail-meta">
          <span>
            {project.source_type === 'file'
              ? 'Uploaded file'
              : project.source_type === 'url'
                ? project.source_url
                : 'No source attached'}
          </span>
          {project.source_character_count != null && (
            <><span className="detail-meta-sep">·</span><span>{project.source_character_count.toLocaleString()} chars</span></>
          )}
          <span className="detail-meta-sep">·</span>
          <span>Created {new Date(project.created_at).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })}</span>
          {project.rights_attestation && (
            <><span className="detail-meta-sep">·</span><span>Rights attested</span></>
          )}
        </div>

        {/* Stats strip */}
        <div className="stats-strip">
          <div className="stat-card">
            <span className="stat-value">{screenCount ?? '—'}</span>
            <span className="stat-label">Screens</span>
          </div>
          <div className="stat-card">
            <span className="stat-value">
              {duration ? <>{duration}<span className="stat-value-sub"> min</span></> : '—'}
            </span>
            <span className="stat-label">Duration</span>
          </div>
          <div className="stat-card">
            <span className="stat-value">
              {activeViewers}<span className="stat-value-sub"> / 10</span>
            </span>
            <span className="stat-label">Active Viewers</span>
          </div>
          <div className="stat-card">
            <span className="stat-value">
              {completedViewers}
              {invites.length > 0 && <span className="stat-value-sub"> of {invites.length}</span>}
            </span>
            <span className="stat-label">Completed</span>
          </div>
        </div>

        {/* Primary actions */}
        {module?.status === 'generated' && (
          <div className="detail-actions">
            <Link to={`/projects/${id}/review`}>
              <button type="button">Review module</button>
            </Link>
            <Link to={`/projects/${id}/play`}>
              <button type="button" className="ghost">Preview</button>
            </Link>
            {openFlags > 0 && (
              <span className="muted">{openFlags} open flag{openFlags !== 1 ? 's' : ''}</span>
            )}
          </div>
        )}

        {/* Tab bar */}
        <div className="detail-tabs">
          <button
            type="button"
            className={`detail-tab${activeTab === 'overview' ? ' detail-tab--active' : ''}`}
            onClick={() => setActiveTab('overview')}
          >
            Overview
          </button>
          <button
            type="button"
            className={`detail-tab${activeTab === 'report' ? ' detail-tab--active' : ''}`}
            onClick={() => setActiveTab('report')}
          >
            Report
          </button>
        </div>

        {activeTab === 'overview' ? (
          <div className="detail-grid">
            {/* Left column */}
            <div className="detail-main">
              <div className="card">
                <h2>Module</h2>
                <ModuleStatusPanel
                  module={module}
                  hasSource={!!project.source_type}
                  retrying={retrying}
                  onRetry={handleRetry}
                />
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
        ) : (
          <ReportPanel authToken={token ?? ''} projectId={id ?? ''} />
        )}
      </section>
    </>
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
    return (
      <div className="module-status-visual">
        <div className="module-status-icon module-status-icon--pending">○</div>
        <div className="module-status-text">
          <strong>No source attached</strong>
          <p>Start a new project to upload content.</p>
        </div>
      </div>
    );
  }

  if (!module || module.status === 'generating') {
    return (
      <div className="module-status-visual">
        <div className="module-status-icon module-status-icon--generating">
          <span className="generating-indicator">●</span>
        </div>
        <div className="module-status-text">
          <strong>Generating…</strong>
          <p>This can take a few minutes. The page will update automatically.</p>
        </div>
      </div>
    );
  }

  if (module.status === 'failed') {
    return (
      <div>
        <div className="module-status-visual" style={{ marginBottom: '0.75rem' }}>
          <div className="module-status-icon module-status-icon--failed">✕</div>
          <div className="module-status-text">
            <strong>Generation failed</strong>
            <p>{module.generation_error ?? 'An unknown error occurred.'}</p>
          </div>
        </div>
        <button type="button" onClick={onRetry} disabled={retrying}>
          {retrying ? 'Retrying…' : 'Retry generation'}
        </button>
      </div>
    );
  }

  if (module.status === 'generated') {
    const title      = module.module_json?.meta.title ?? 'Untitled';
    const objectives = module.module_json?.learning_objectives ?? [];

    return (
      <div>
        <div className="module-status-visual" style={{ marginBottom: objectives.length ? '0.75rem' : 0 }}>
          <div className="module-status-icon module-status-icon--success">✓</div>
          <div className="module-status-text">
            <strong>{title}</strong>
            <p>Ready to review and share with learners.</p>
          </div>
        </div>
        {objectives.length > 0 && (
          <ul style={{ paddingLeft: '1.25rem', margin: 0 }}>
            {objectives.map((obj, i) => (
              <li key={i} className="muted" style={{ fontSize: '0.82rem', marginBottom: '0.2rem' }}>{obj}</li>
            ))}
          </ul>
        )}
      </div>
    );
  }

  return null;
}

// ─── Report panel ────────────────────────────────────────────────────────────

function ReportPanel({ authToken, projectId }: { authToken: string; projectId: string }) {
  const [report, setReport] = useState<ProjectReport | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    setLoading(true);
    fetchProjectReport(authToken, projectId)
      .then(setReport)
      .catch((err: unknown) => setError(err instanceof Error ? err.message : 'Failed to load report'))
      .finally(() => setLoading(false));
  }, [authToken, projectId]);

  if (loading) return <p className="muted">Loading report…</p>;
  if (error)   return <div className="error-banner">{error}</div>;
  if (!report) return null;

  const { summary, viewers, questions } = report;

  return (
    <div className="report-panel">
      {/* Summary strip */}
      <div className="report-summary">
        <div className="stat-card">
          <span className="stat-value">{summary.invited}</span>
          <span className="stat-label">Invited</span>
        </div>
        <div className="stat-card">
          <span className="stat-value">{summary.started}</span>
          <span className="stat-label">Started</span>
        </div>
        <div className="stat-card">
          <span className="stat-value">{summary.completed}</span>
          <span className="stat-label">Completed</span>
        </div>
        <div className="stat-card">
          <span className="stat-value">
            {summary.avg_score != null ? `${Math.round(summary.avg_score * 100)}%` : '—'}
          </span>
          <span className="stat-label">Avg Score</span>
        </div>
      </div>

      {/* Viewers table */}
      <div className="card report-viewers-card">
        <h2>Viewers</h2>
        {viewers.length === 0 ? (
          <p className="muted" style={{ fontSize: '0.85rem' }}>No viewers invited yet.</p>
        ) : (
          <table className="report-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Progress</th>
                <th>Score</th>
                <th>Status</th>
                <th>Last Active</th>
              </tr>
            </thead>
            <tbody>
              {viewers.map((v, i) => {
                const progressPct = v.progress
                  ? Math.round((v.progress.current / v.progress.total) * 100)
                  : null;
                const scorePct = v.score
                  ? Math.round((v.score.correct / v.score.total) * 100)
                  : null;
                return (
                  <tr key={i}>
                    <td>{v.name ?? <span className="muted">—</span>}</td>
                    <td className="report-email">{v.email}</td>
                    <td>
                      {v.progress ? (
                        <div className="report-progress-cell">
                          <div className="report-progress-track">
                            <div
                              className="report-progress-fill"
                              style={{ width: `${progressPct}%` }}
                            />
                          </div>
                          <span className="report-progress-label">
                            {v.progress.current}/{v.progress.total}
                          </span>
                        </div>
                      ) : (
                        <span className="muted">—</span>
                      )}
                    </td>
                    <td>
                      {v.score != null ? (
                        <span className={`report-score${scorePct != null && scorePct >= 70 ? ' report-score--pass' : ' report-score--fail'}`}>
                          {v.score.correct}/{v.score.total}
                          <span className="report-score-pct"> ({scorePct}%)</span>
                        </span>
                      ) : (
                        <span className="muted">—</span>
                      )}
                    </td>
                    <td>
                      {v.completed_at ? (
                        <span className="report-badge report-badge--completed">
                          Completed {new Date(v.completed_at).toLocaleDateString(undefined, { month: 'short', day: 'numeric' })}
                        </span>
                      ) : v.progress ? (
                        <span className="report-badge report-badge--progress">In progress</span>
                      ) : (
                        <span className="report-badge report-badge--pending">Not started</span>
                      )}
                    </td>
                    <td className="muted report-date">
                      {v.last_active_at
                        ? new Date(v.last_active_at).toLocaleDateString(undefined, { month: 'short', day: 'numeric' })
                        : '—'}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        )}
      </div>

      {/* Questions breakdown */}
      {questions.length > 0 && (
        <div className="card report-questions-card">
          <h2>Questions</h2>
          <div className="report-questions-list">
            {questions.map((q) => (
              <div key={q.screen_id} className="report-question">
                <div className="report-question-header">
                  <p className="report-question-prompt">{q.prompt}</p>
                  <span className="report-question-stat">
                    {q.respondents === 0
                      ? 'No responses yet'
                      : `${Math.round((q.pct_correct ?? 0) * 100)}% correct · ${q.respondents} response${q.respondents !== 1 ? 's' : ''}`}
                  </span>
                </div>
                <div className="report-choices">
                  {q.choices.map((c) => {
                    const pct = q.respondents > 0
                      ? Math.round((c.pick_count / q.respondents) * 100)
                      : 0;
                    return (
                      <div key={c.id} className={`report-choice${c.is_correct ? ' report-choice--correct' : ''}`}>
                        <div className="report-choice-bar-wrap">
                          <div
                            className="report-choice-bar"
                            style={{ width: `${pct}%` }}
                          />
                        </div>
                        <span className="report-choice-text">
                          {c.text}
                          {c.is_correct && <span className="report-choice-correct-badge">Correct</span>}
                        </span>
                        <span className="report-choice-pct">{pct}%</span>
                      </div>
                    );
                  })}
                </div>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
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

  const completedCount   = invites.filter(i => i.progress?.completed_at).length;
  const inProgressCount  = invites.filter(i => i.progress && !i.progress.completed_at).length;
  const notStartedCount  = invites.filter(i => i.is_active && !i.progress).length;

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

      {invites.length > 0 && (
        <div className="viewer-summary">
          {completedCount > 0 && (
            <span className="viewer-summary-item viewer-summary-item--completed">
              <span className="viewer-summary-dot" />
              {completedCount} completed
            </span>
          )}
          {inProgressCount > 0 && (
            <span className="viewer-summary-item viewer-summary-item--progress">
              <span className="viewer-summary-dot" />
              {inProgressCount} in progress
            </span>
          )}
          {notStartedCount > 0 && (
            <span className="viewer-summary-item viewer-summary-item--pending">
              <span className="viewer-summary-dot" />
              {notStartedCount} not started
            </span>
          )}
        </div>
      )}

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
