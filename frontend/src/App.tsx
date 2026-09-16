import { createBrowserRouter, RouterProvider, Navigate, Outlet, useNavigate, useSearchParams } from 'react-router-dom';
import { AuthProvider, useAuth } from './context/AuthContext';
import { TopNav } from './components/TopNav';
import { LoginForm } from './components/LoginForm';
import { RegisterForm } from './components/RegisterForm';
import { ForgotPasswordPage } from './components/ForgotPasswordPage';
import { ResetPasswordPage } from './components/ResetPasswordPage';
import { ChangePasswordPage } from './components/ChangePasswordPage';
import { ProjectsView } from './components/ProjectsView';
import { ProjectDetailView } from './components/ProjectDetailView';
import { CreateProjectWizard } from './components/CreateProjectWizard';
import { useIdleTimeout } from './hooks/useIdleTimeout';
import { login, register, resendVerification, fetchProjects } from './lib/api';
import { useState, useEffect, lazy, Suspense } from 'react';
import type { Project } from './lib/types';

// Split out of the initial bundle. The editor pulls in TipTap, and the two
// players are large; none of them is needed to render the login or projects
// screens, and an invited viewer on /view/:token needs only the player.
const named = <T extends string>(key: T) =>
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  (m: any) => ({ default: m[key] });

const ModuleReviewPage = lazy(() => import('./components/ModuleEditorView').then(named('ModuleReviewPage')));
const WBTPlayerPage = lazy(() => import('./components/WBTPlayer').then(named('WBTPlayerPage')));
const ViewerPlayerPage = lazy(() => import('./components/ViewerPlayerPage').then(named('ViewerPlayerPage')));
const BillingView = lazy(() => import('./components/BillingView').then(named('BillingView')));
const BillingSuccessPage = lazy(() => import('./components/BillingView').then(named('BillingSuccessPage')));
const BillingCancelPage = lazy(() => import('./components/BillingView').then(named('BillingCancelPage')));

const IDLE_TIMEOUT_MS = (Number(import.meta.env.VITE_IDLE_TIMEOUT_MINUTES) || 120) * 60 * 1000;

function RequireAuth() {
  const { token, clearAuth } = useAuth();
  const navigate = useNavigate();

  useIdleTimeout(() => {
    clearAuth();
    navigate('/login');
  }, IDLE_TIMEOUT_MS);

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
  const [searchParams] = useSearchParams();
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [unverifiedEmail, setUnverifiedEmail] = useState<string | null>(null);
  const [resendStatus, setResendStatus] = useState<string | null>(null);

  if (token) return <Navigate to="/projects" replace />;

  const justReset = searchParams.get('reset') === '1';
  const justVerified = searchParams.get('verified') === '1';
  const alreadyVerified = searchParams.get('verified') === 'already';
  const verifyError = searchParams.get('verified') === 'error';

  async function handleLogin(email: string, password: string) {
    setLoading(true);
    setError(null);
    setUnverifiedEmail(null);
    try {
      const result = await login(email, password);
      setAuth(result.token, result.user);
      navigate(result.must_change_password ? '/change-password' : '/projects');
    } catch (err: unknown) {
      const text = err instanceof Error ? err.message : 'Login failed';
      try {
        const parsed = JSON.parse(text);
        if (parsed.code === 'email_unverified') {
          setUnverifiedEmail(email);
          setError(parsed.message);
        } else {
          setError(parsed.message ?? text);
        }
      } catch {
        setError(text);
      }
    } finally {
      setLoading(false);
    }
  }

  async function handleResend() {
    if (!unverifiedEmail) return;
    try {
      await resendVerification(unverifiedEmail);
      setResendStatus('Verification email sent. Please check your inbox.');
    } catch {
      setResendStatus('Failed to resend. Please try again.');
    }
  }

  return (
    <main className="app-shell auth-shell">
      {justReset && <div className="success-banner">Password reset successfully. You can now sign in.</div>}
      {justVerified && <div className="success-banner">Email verified! You can now sign in.</div>}
      {alreadyVerified && <div className="success-banner">Your email is already verified. Please sign in.</div>}
      {verifyError && <div className="error-banner">Verification link is invalid or has expired. Please request a new one.</div>}
      {error && !justVerified && (
        <div className="error-banner">
          {error}
          {unverifiedEmail && (
            <> &mdash; <button type="button" className="inline-link" onClick={handleResend}>Resend verification email</button></>
          )}
        </div>
      )}
      {resendStatus && <div className="success-banner">{resendStatus}</div>}
      <LoginForm loading={loading} onSubmit={handleLogin} />
    </main>
  );
}

function RegisterPage() {
  const { token } = useAuth();
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState(false);

  if (token) return <Navigate to="/projects" replace />;
  if (success) {
    return (
      <main className="app-shell auth-shell">
        <div className="card auth-success-card">
          <h2>Check your inbox</h2>
          <p>We've sent a verification link to your email address. Click the link to activate your account, then <a href="/login">sign in</a>.</p>
        </div>
      </main>
    );
  }

  async function handleRegister(name: string, email: string, password: string, passwordConfirmation: string) {
    setLoading(true);
    setError(null);
    try {
      await register(name, email, password, passwordConfirmation);
      setSuccess(true);
    } catch (err: unknown) {
      const text = err instanceof Error ? err.message : 'Registration failed';
      try {
        const parsed = JSON.parse(text);
        const firstError = parsed.errors ? Object.values(parsed.errors as Record<string, string[]>)[0]?.[0] : parsed.message;
        setError(firstError ?? text);
      } catch {
        setError(text);
      }
    } finally {
      setLoading(false);
    }
  }

  return (
    <main className="app-shell auth-shell">
      <RegisterForm loading={loading} error={error} onSubmit={handleRegister} />
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
  { path: '/register', element: <RegisterPage /> },
  { path: '/forgot-password', element: <ForgotPasswordPage /> },
  { path: '/reset-password', element: <ResetPasswordPage /> },
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
          { path: '/billing', element: <BillingView /> },
          { path: '/billing/success', element: <BillingSuccessPage /> },
          { path: '/billing/cancel', element: <BillingCancelPage /> },
        ],
      },
    ],
  },
]);

export function App() {
  return (
    <AuthProvider>
      <Suspense fallback={<div className="app-shell route-loading">Loading…</div>}>
        <RouterProvider router={router} />
      </Suspense>
    </AuthProvider>
  );
}
