import { useEffect, useMemo, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { fetchFrontendTexts } from '../lib/api';
import { createTextResolver, type FrontendTextMap } from '../lib/text';
import type { Project } from '../lib/types';

type ProjectsViewProps = {
  email: string;
  projects: Project[];
  loading: boolean;
  onRefresh: () => Promise<void>;
  onLogout: () => void;
};

export function ProjectsView({ email, projects, loading, onRefresh, onLogout }: ProjectsViewProps) {
  const routeKey = 'projects.list';
  const [frontendTexts, setFrontendTexts] = useState<FrontendTextMap | null>(null);
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
          <p className="muted">
            {t('projects.subtitle_signed_in_as', 'Signed in as')} {email}
          </p>
        </div>
        <div className="actions">
          <Link to="/projects/new">
            <button type="button">{t('projects.new_button', 'New Project')}</button>
          </Link>
          <button type="button" className="ghost" onClick={onRefresh} disabled={loading}>
            {loading ? t('projects.refreshing_button', 'Refreshing...') : t('projects.refresh_button', 'Refresh')}
          </button>
          <button type="button" className="ghost" onClick={onLogout}>
            {t('projects.logout_button', 'Logout')}
          </button>
        </div>
      </header>

      <section className="card">
        <h2>{t('projects.recent_title', 'Recent Projects')}</h2>
        {sortedProjects.length === 0 ? (
          <p className="muted">{t('projects.empty_state', 'No projects yet. Create your first one.')}</p>
        ) : (
          <ul className="project-list">
            {sortedProjects.map((project) => (
              <li key={project.id}>
                <div>
                  <strong>
                    <Link to={`/projects/${project.id}`}>{project.title}</Link>
                  </strong>
                  <p className="muted">{project.id}</p>
                </div>
                <time>{new Date(project.created_at).toLocaleString()}</time>
              </li>
            ))}
          </ul>
        )}
      </section>
    </section>
  );
}
