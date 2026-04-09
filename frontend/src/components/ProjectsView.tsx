import { useEffect, useMemo, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { fetchFrontendTexts, fetchCurrentSubscription } from '../lib/api';
import { createTextResolver, type FrontendTextMap } from '../lib/text';
import { useAuth } from '../context/AuthContext';
import type { BillingSubscription, Project } from '../lib/types';

function PlanBanner({ subscription }: { subscription: BillingSubscription | null | undefined }) {
  // Still loading — render nothing to avoid layout shift
  if (subscription === undefined) return null;

  if (!subscription) {
    return (
      <div className="plan-banner plan-banner--none">
        <span>You don't have an active plan.</span>
        <Link to="/billing" className="plan-banner-link">View plans →</Link>
      </div>
    );
  }

  const modulePct = (subscription.modules_used / (subscription.plan.module_limit || 1)) * 100;
  const seatPct   = subscription.seats_used / (subscription.plan.seat_limit || 1) * 100;
  const isWarn    = modulePct >= 80 || seatPct >= 80;

  return (
    <div className={`plan-banner${isWarn ? ' plan-banner--warn' : ''}`}>
      <span className="plan-banner-name">{subscription.plan.name}</span>
      <span className="plan-banner-sep">·</span>
      <span className={modulePct >= 80 ? 'plan-banner-stat--warn' : undefined}>
        Modules: {subscription.modules_used}/{subscription.plan.module_limit} used
      </span>
      <span className="plan-banner-sep">·</span>
      <span className={seatPct >= 80 ? 'plan-banner-stat--warn' : undefined}>
        Seats: {subscription.seats_used}/{subscription.plan.seat_limit} used
      </span>
      <Link to="/billing" className="plan-banner-link">Manage plan →</Link>
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
          <Link to="/projects/new">
            <button type="button">{t('projects.new_button', 'New Project')}</button>
          </Link>
          <button type="button" className="ghost" onClick={onRefresh} disabled={loading}>
            {loading ? t('projects.refreshing_button', 'Refreshing...') : t('projects.refresh_button', 'Refresh')}
          </button>
        </div>
      </header>

      <PlanBanner subscription={subscription} />

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
