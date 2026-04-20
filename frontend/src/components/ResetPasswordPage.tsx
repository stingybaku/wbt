import { FormEvent, useState } from 'react';
import { useNavigate, useSearchParams, Link } from 'react-router-dom';
import { resetPassword } from '../lib/api';

export function ResetPasswordPage() {
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  const token = searchParams.get('token') ?? '';
  const email = searchParams.get('email') ?? '';

  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  if (!token || !email) {
    return (
      <main className="app-shell auth-shell">
        <div className="card auth-success-card">
          <h2>Invalid link</h2>
          <p>This password reset link is missing required information. Please request a new one.</p>
          <p style={{ marginTop: '1rem' }}>
            <Link to="/forgot-password">Request a new link</Link>
          </p>
        </div>
      </main>
    );
  }

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setLoading(true);
    setError(null);
    try {
      await resetPassword(token, email, password, passwordConfirmation);
      navigate('/login?reset=1');
    } catch (err) {
      const text = err instanceof Error ? err.message : 'Something went wrong.';
      try {
        const parsed = JSON.parse(text);
        setError(parsed.message ?? text);
      } catch {
        setError(text);
      }
    } finally {
      setLoading(false);
    }
  }

  return (
    <main className="app-shell auth-shell">
      {error && <div className="error-banner">{error}</div>}
      <form className="card login-card login-card--narrow" onSubmit={handleSubmit}>
        <section className="login-form-panel">
          <p className="login-kicker">ACCOUNT RECOVERY</p>
          <h1 className="login-brand">New password</h1>
          <p className="muted login-subtitle">Choose a strong password for your account.</p>

          <label className="login-field">
            <span className="sr-only">New password</span>
            <input
              type="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              required
              autoComplete="new-password"
              placeholder="New password (min. 8 characters)"
              minLength={8}
            />
          </label>
          <label className="login-field">
            <span className="sr-only">Confirm new password</span>
            <input
              type="password"
              value={passwordConfirmation}
              onChange={(e) => setPasswordConfirmation(e.target.value)}
              required
              autoComplete="new-password"
              placeholder="Confirm new password"
              minLength={8}
            />
          </label>

          <button className="login-submit" type="submit" disabled={loading}>
            {loading ? 'RESETTING...' : 'RESET PASSWORD'}
          </button>
        </section>
      </form>
    </main>
  );
}
