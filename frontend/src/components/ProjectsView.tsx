import { useEffect, useMemo, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { fetchFrontendTexts, fetchCurrentSubscription } from '../lib/api';
import { createTextResolver, type FrontendTextMap } from '../lib/text';
import { useAuth } from '../context/AuthContext';
import type { BillingSubscription, Project } from '../lib/types';

function canCreateProject(subscription: BillingSubscription | null | undefined): boolean {
  if (subscription === undefined) return true; // still loading — optimistically allow
  if (!subscription) return false;
  return subscription.modules_available > 0;
}

function PlanBanner({ subscription }: { subscription: BillingSubscription | null }) {
  if (!subscription) {
    return (
      <div className="plan-no-sub card">
        <div>
          <p className="plan-no-sub-title">No active plan</p>
          <p className="plan-no-sub-sub">Purchase a plan to start creating and sharing training modules.</p>
        </div>
        <Link to="/billing">
          <button type="button">View plans</button>
        </Link>
      </div>
    );
  }

  const isOneTime  = subscription.plan.type === 'one_time';
  const totalSlots = subscription.modules_used + subscription.modules_available;
  const modulePct  = totalSlots === 0 ? 0 : (subscription.modules_used / totalSlots) * 100;
  const seatPct    = subscription.seats_used / (subscription.plan.seat_limit || 1) * 100;

  return (
    <div className="plan-dashboard">
      {/* Plan name + status */}
      <div className="stat-card plan-dashboard-name-card">
        <span className="stat-value plan-dashboard-plan-name">{subscription.plan.name}</span>
        <span className={`billing-status-pill billing-status--${subscription.status}`}>
          {subscription.status}
        </span>
        <Link to="/billing" className="plan-banner-link" style={{ marginTop: 'auto' }}>
          Manage plan →
        </Link>
      </div>

      {/* Modules */}
      <div className={`stat-card${modulePct >= 80 ? ' plan-dashboard-card--warn' : ''}`}>
        <span className="stat-value">
          {subscription.modules_used}
          <span className="stat-value-sub"> / {totalSlots}</span>
        </span>
        <span className="stat-label">Modules used</span>
        {modulePct >= 80 && (
          <span className="plan-dashboard-warn-note">Running low</span>
        )}
      </div>

      {/* Seats */}
      {isOneTime ? (
        <div className="stat-card">
          <span className="stat-value">{subscription.plan.seat_limit}</span>
          <span className="stat-label">Seats per module</span>
        </div>
      ) : (
        <div className={`stat-card${seatPct >= 80 ? ' plan-dashboard-card--warn' : ''}`}>
          <span className="stat-value">
            {subscription.seats_used}
            <span className="stat-value-sub"> / {subscription.plan.seat_limit}</span>
          </span>
          <span className="stat-label">Seats used</span>
          {seatPct >= 80 && (
            <span className="plan-dashboard-warn-note">Running low</span>
          )}
        </div>
      )}

      {/* Billing period / renews */}
      {isOneTime ? (
        <div className="stat-card">
          <span className="stat-value">$39</span>
          <span className="stat-label">Per module</span>
        </div>
      ) : (
        <div className="stat-card">
          <span className="stat-value plan-dashboard-renews">
            {subscription.current_period_end
              ? new Date(subscription.current_period_end).toLocaleDateString(undefined, { month: 'short', day: 'numeric' })
              : '—'}
          </span>
          <span className="stat-label">
            {subscription.status === 'cancelled' ? 'Access until' : 'Renews'}
          </span>
        </div>
      )}
    </div>
  );
}

type ProjectsViewProps = {
  projects: Project[];
  loading: boolean;
  onRefresh: () => Promise<void>;
};

export function ProjectsView({ projects, loading, onRefresh }: ProjectsViewProps) {
  const routeKey = 'projects.list';
  const { token } = useAuth();
  const [frontendTexts, setFrontendTexts] = useState<FrontendTextMap | null>(null);
  const [subscription, setSubscription] = useState<BillingSubscription | null | undefined>(undefined);
  const hasLoggedFetchError = useRef(false);

  useEffect(() => {
    fetchFrontendTexts(routeKey)
      .then((response) => setFrontendTexts(response.texts))
      .catch((error: unknown) => {
        if (!hasLoggedFetchError.current) {
          console.error('Failed to fetch frontend texts for projects.list', error);
          hasLoggedFetchError.current = true;
        }
      });
  }, []);

  useEffect(() => {
    if (!token) return;
    fetchCurrentSubscription(token)
      .then((res) => setSubscription(res.subscription))
      .catch(() => setSubscription(null));
  }, [token]);

  const sortedProjects = useMemo(
    () => [...projects].sort((a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime()),
    [projects]
  );
  const t = useMemo(() => createTextResolver(frontendTexts), [frontendTexts]);

  return (
    <section className="layout">
      <header className="topbar">
        <div>
          <h1>{t('projects.title', 'Projects')}</h1>
        </div>
        <div className="actions">
          {canCreateProject(subscription) ? (
            <Link to="/projects/new">
              <button type="button">{t('projects.new_button', 'New Project')}</button>
            </Link>
          ) : (
            <Link to="/billing">
              <button type="button" title="No module quota remaining — upgrade your plan">
                {subscription === null ? 'Get a plan' : 'Upgrade plan'}
              </button>
            </Link>
          )}
          <button type="button" className="ghost" onClick={onRefresh} disabled={loading}>
            {loading ? t('projects.refreshing_button', 'Refreshing...') : t('projects.refresh_button', 'Refresh')}
          </button>
        </div>
      </header>

      {subscription !== undefined && (
        <section>
          <h2 className="projects-grid-heading">Current Plan</h2>
          <PlanBanner subscription={subscription} />
        </section>
      )}

      <section>
        <h2 className="projects-grid-heading">{t('projects.recent_title', 'Recent Projects')}</h2>
        {sortedProjects.length === 0 ? (
          <p className="muted">{t('projects.empty_state', 'No projects yet. Create your first one.')}</p>
        ) : (
          <ul className="project-grid">
            {sortedProjects.map((project) => (
              <li key={project.id}>
                <Link to={`/projects/${project.id}`} className="project-card">
                  <div
                    className="project-card-cover"
                    style={project.cover_image_url ? { backgroundImage: `url(${project.cover_image_url})` } : undefined}
                  >
                    {!project.cover_image_url && <span className="project-card-cover-placeholder">⬡</span>}
                    {project.source_summary && (
                      <div className="project-card-overlay">
                        <p className="project-card-overlay-text">{project.source_summary}</p>
                      </div>
                    )}
                  </div>
                  <div className="project-card-body">
                    <strong className="project-card-title">{project.title}</strong>
                    {project.screen_count !== null && (
                      <div className="project-card-stats">
                        {project.duration_minutes !== null && (
                          <span className="project-card-stat">{project.duration_minutes} min</span>
                        )}
                        <span className="project-card-stat">{project.screen_count} screens</span>
                        <span className="project-card-stat">{project.invite_count} {project.invite_count === 1 ? 'invitee' : 'invitees'}</span>
                      </div>
                    )}
                    <time className="project-card-date">
                      {new Date(project.created_at).toLocaleDateString(undefined, {
                        year: 'numeric', month: 'short', day: 'numeric',
                      })}
                    </time>
                  </div>
                </Link>
              </li>
            ))}
          </ul>
        )}
      </section>
    </section>
  );
}
