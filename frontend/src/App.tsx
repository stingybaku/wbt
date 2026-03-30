import { createBrowserRouter, RouterProvider, Navigate, Outlet, useNavigate } from 'react-router-dom';
import { AuthProvider, useAuth } from './context/AuthContext';
import { LoginForm } from './components/LoginForm';
import { ProjectsView } from './components/ProjectsView';
import { ProjectDetailView } from './components/ProjectDetailView';
import { CreateProjectWizard } from './components/CreateProjectWizard';
import { ModuleReviewPage } from './components/ModuleEditorView';
import { WBTPlayerPage } from './components/WBTPlayer';
import { login, fetchProjects } from './lib/api';
import { useState, useEffect } from 'react';
import type { Project } from './lib/types';

function RequireAuth() {
  const { token } = useAuth();
  if (!token) return <Navigate to="/login" replace />;
  return <Outlet />;
}

function LoginPage() {
  const { token, setAuth } = useAuth();
  const navigate = useNavigate();
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  if (token) return <Navigate to="/projects" replace />;

  async function handleLogin(email: string, password: string) {
    setLoading(true);
    setError(null);
    try {
      const result = await login(email, password);
      setAuth(result.token, result.user);
      navigate('/projects');
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Login failed');
    } finally {
      setLoading(false);
    }
  }

  return (
    <main className="app-shell auth-shell">
      {error ? <div className="error-banner">{error}</div> : null}
      <LoginForm loading={loading} onSubmit={handleLogin} />
    </main>
  );
}

function ProjectsPage() {
  const { token, user, clearAuth } = useAuth();
  const [projects, setProjects] = useState<Project[]>([]);
  const [loadingProjects, setLoadingProjects] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function loadProjects() {
    if (!token) return;
    setLoadingProjects(true);
    try {
      const response = await fetchProjects(token);
      setProjects(response.projects);
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Failed to load projects');
    } finally {
      setLoadingProjects(false);
    }
  }

  useEffect(() => {
    loadProjects();
  }, []);

  return (
    <main className="app-shell">
      {error ? <div className="error-banner">{error}</div> : null}
      <ProjectsView
        email={user?.email ?? ''}
        projects={projects}
        loading={loadingProjects}
        onRefresh={loadProjects}
        onLogout={clearAuth}
      />
    </main>
  );
}

const router = createBrowserRouter([
  { path: '/login', element: <LoginPage /> },
  {
    element: <RequireAuth />,
    children: [
      { path: '/', element: <Navigate to="/projects" replace /> },
      { path: '/projects', element: <ProjectsPage /> },
      { path: '/projects/new', element: <CreateProjectWizard /> },
      { path: '/projects/:id', element: <ProjectDetailView /> },
      { path: '/projects/:id/review', element: <ModuleReviewPage /> },
      { path: '/projects/:id/play', element: <WBTPlayerPage /> },
    ],
  },
]);

export function App() {
  return (
    <AuthProvider>
      <RouterProvider router={router} />
    </AuthProvider>
  );
}
