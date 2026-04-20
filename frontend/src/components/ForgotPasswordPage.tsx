import { FormEvent, useState } from 'react';
import { Link } from 'react-router-dom';
import { forgotPassword } from '../lib/api';

export function ForgotPasswordPage() {
  const [email, setEmail] = useState('');
  const [loading, setLoading] = useState(false);
  const [submitted, setSubmitted] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setLoading(true);
    setError(null);
    try {
      await forgotPassword(email);
      setSubmitted(true);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Something went wrong.');
    } finally {
      setLoading(false);
    }
  }

  if (submitted) {
    return (
      <main className="app-shell auth-shell">
        <div className="card auth-success-card">
          <h2>Check your inbox</h2>
          <p>If that email is registered, we've sent a password reset link. It expires in 60 minutes.</p>
          <p style={{ marginTop: '1rem' }}>
            <Link to="/login">Back to sign in</Link>
          </p>
        </div>
      </main>
    );
  }

  return (
    <main className="app-shell auth-shell">
      {error && <div className="error-banner">{error}</div>}
      <form className="card login-card login-card--narrow" onSubmit={handleSubmit}>
        <section className="login-form-panel">
          <p className="login-kicker">ACCOUNT RECOVERY</p>
          <h1 className="login-brand">Forgot password?</h1>
          <p className="muted login-subtitle">Enter your email and we'll send you a reset link.</p>

          <label className="login-field">
            <span className="sr-only">Email</span>
            <input
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              required
              autoComplete="email"
              placeholder="Email"
            />
          </label>

          <button className="login-submit" type="submit" disabled={loading}>
            {loading ? 'SENDING...' : 'SEND RESET LINK'}
          </button>

          <p className="login-meta">
            <Link to="/login">Back to sign in</Link>
          </p>
        </section>
      </form>
    </main>
  );
}
