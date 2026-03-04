import { useEffect, useState } from 'react';
import { LoginForm } from './components/LoginForm';
import { ProjectsView } from './components/ProjectsView';
import { createProject, fetchProjects, login } from './lib/api';
import type { Project, User } from './lib/types';

const TOKEN_KEY = 'wbt.auth.token';
const USER_KEY = 'wbt.auth.user';

function readStoredAuth(): { token: string | null; user: User | null } {
  const token = localStorage.getItem(TOKEN_KEY);
  const rawUser = localStorage.getItem(USER_KEY);

  if (!token || !rawUser) {
    return { token: null, user: null };
  }

  try {
    const user = JSON.parse(rawUser) as User;
    return { token, user };
  } catch {
    return { token: null, user: null };
  }
}

export function App() {
  const stored = readStoredAuth();

  const [token, setToken] = useState<string | null>(stored.token);
  const [user, setUser] = useState<User | null>(stored.user);
  const [projects, setProjects] = useState<Project[]>([]);
  const [loadingProjects, setLoadingProjects] = useState(false);
  const [loggingIn, setLoggingIn] = useState(false);
  const [creating, setCreating] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function loadProjects(activeToken: string) {
    setLoadingProjects(true);
    try {
      const response = await fetchProjects(activeToken);
      setProjects(response.projects);
    } finally {
      setLoadingProjects(false);
    }
  }

  useEffect(() => {
    if (!token) {
      setProjects([]);
      return;
    }

    loadProjects(token).catch((err: unknown) => {
      const message = err instanceof Error ? err.message : 'Failed to load projects';
      setError(message);
    });
  }, [token]);

  async function handleLogin(email: string, password: string) {
    setLoggingIn(true);
    setError(null);

    try {
      const result = await login(email, password);
      localStorage.setItem(TOKEN_KEY, result.token);
      localStorage.setItem(USER_KEY, JSON.stringify(result.user));
      setToken(result.token);
      setUser(result.user);
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Login failed';
      setError(message);
    } finally {
      setLoggingIn(false);
    }
  }

  async function handleCreateProject(title: string) {
    if (!token) return;

    setCreating(true);
    setError(null);
    try {
      await createProject(token, { title, rights_attestation: true });
      await loadProjects(token);
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Failed to create project';
      setError(message);
    } finally {
      setCreating(false);
    }
  }

  async function handleRefresh() {
    if (!token) return;

    setError(null);
    try {
      await loadProjects(token);
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Failed to refresh projects';
      setError(message);
    }
  }

  function handleLogout() {
    localStorage.removeItem(TOKEN_KEY);
    localStorage.removeItem(USER_KEY);
    setToken(null);
    setUser(null);
    setProjects([]);
  }

  const isAuthenticated = token !== null && user !== null;

  return (
    <main className={`app-shell ${isAuthenticated ? '' : 'auth-shell'}`.trim()}>
      {error ? <div className="error-banner">{error}</div> : null}
      {!token || !user ? (
        <LoginForm loading={loggingIn} onSubmit={handleLogin} />
      ) : (
        <ProjectsView
          email={user.email}
          projects={projects}
          loading={loadingProjects}
          creating={creating}
          onCreateProject={handleCreateProject}
          onRefresh={handleRefresh}
          onLogout={handleLogout}
        />
      )}
    </main>
  );
}
