import { FormEvent, useMemo, useState } from 'react';
import type { Project } from '../lib/types';

type ProjectsViewProps = {
  email: string;
  projects: Project[];
  loading: boolean;
  creating: boolean;
  onCreateProject: (title: string) => Promise<void>;
  onRefresh: () => Promise<void>;
  onLogout: () => void;
};

export function ProjectsView({
  email,
  projects,
  loading,
  creating,
  onCreateProject,
  onRefresh,
  onLogout
}: ProjectsViewProps) {
  const [title, setTitle] = useState('');

  const sortedProjects = useMemo(
    () => [...projects].sort((a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime()),
    [projects]
  );

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    await onCreateProject(title);
    setTitle('');
  }

  return (
    <section className="layout">
      <header className="topbar">
        <div>
          <h1>Projects</h1>
          <p className="muted">Signed in as {email}</p>
        </div>
        <div className="actions">
          <button type="button" className="ghost" onClick={onRefresh} disabled={loading}>
            {loading ? 'Refreshing...' : 'Refresh'}
          </button>
          <button type="button" className="ghost" onClick={onLogout}>
            Logout
          </button>
        </div>
      </header>

      <form className="card" onSubmit={handleSubmit}>
        <h2>Create Project</h2>
        <label>
          Project title
          <input
            type="text"
            value={title}
            onChange={(event) => setTitle(event.target.value)}
            placeholder="New compliance training module"
            required
          />
        </label>
        <button type="submit" disabled={creating}>
          {creating ? 'Creating...' : 'Create project'}
        </button>
      </form>

      <section className="card">
        <h2>Recent Projects</h2>
        {sortedProjects.length === 0 ? (
          <p className="muted">No projects yet.</p>
        ) : (
          <ul className="project-list">
            {sortedProjects.map((project) => (
              <li key={project.id}>
                <div>
                  <strong>{project.title}</strong>
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
