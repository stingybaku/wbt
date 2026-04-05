import { createBrowserRouter, RouterProvider, Navigate, Outlet, useNavigate } from 'react-router-dom';
import { AuthProvider, useAuth } from './context/AuthContext';
import { TopNav } from './components/TopNav';
import { LoginForm } from './components/LoginForm';
import { ChangePasswordPage } from './components/ChangePasswordPage';
import { ProjectsView } from './components/ProjectsView';
import { ProjectDetailView } from './components/ProjectDetailView';
import { CreateProjectWizard } from './components/CreateProjectWizard';
import { ModuleReviewPage } from './components/ModuleEditorView';
import { WBTPlayerPage } from './components/WBTPlayer';
import { ViewerPlayerPage } from './components/ViewerPlayerPage';
import { login, fetchProjects } from './lib/api';
import { useState, useEffect } from 'react';
import type { Project } from './lib/types';

function RequireAuth() {
  const { token } = useAuth();
  if (!token) return <Navigate to="/login" replace />;
  return <Outlet />;
}

// Layout with the top nav bar — used for all main app pages except players
function AppLayout() {
  return (
    <>
      <TopNav />
      <main className="app-shell with-topnav">
        <Outlet />
      </main>
    </>
  );
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
      navigate(result.must_change_password ? '/change-password' : '/projects');
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
  const { token } = useAuth();
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
    <>
      {error ? <div className="error-banner">{error}</div> : null}
      <ProjectsView
        projects={projects}
        loading={loadingProjects}
        onRefresh={loadProjects}
      />
    </>
  );
}

const router = createBrowserRouter([
  { path: '/login', element: <LoginPage /> },
  { path: '/view/:token', element: <ViewerPlayerPage /> },
  {
    element: <RequireAuth />,
    children: [
      // Pages without top nav (full-screen or forced flows)
      { path: '/change-password', element: <ChangePasswordPage /> },
      { path: '/projects/:id/play', element: <WBTPlayerPage /> },
      // Main app layout with top nav
      {
        element: <AppLayout />,
        children: [
          { path: '/', element: <Navigate to="/projects" replace /> },
          { path: '/projects', element: <ProjectsPage /> },
          { path: '/projects/new', element: <CreateProjectWizard /> },
          { path: '/projects/:id', element: <ProjectDetailView /> },
          { path: '/projects/:id/review', element: <ModuleReviewPage /> },
        ],
      },
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
