import { FormEvent, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { changePassword } from '../lib/api';

export function ChangePasswordPage() {
  const { token } = useAuth();
  const navigate = useNavigate();

  const [currentPassword, setCurrentPassword] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    if (!token) return;

    setLoading(true);
    setError(null);

    try {
      await changePassword(token, {
        current_password: currentPassword,
        new_password: newPassword,
        new_password_confirmation: confirmPassword,
      });
      navigate('/projects');
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Password change failed');
    } finally {
      setLoading(false);
    }
  }

  return (
    <main className="app-shell auth-shell">
      {error ? <div className="error-banner">{error}</div> : null}
      <form className="card login-card" onSubmit={handleSubmit}>
        <section className="login-form-panel">
          <p className="login-kicker">SECURITY</p>
          <h1 className="login-brand" style={{ fontSize: '1.75rem' }}>Change Password</h1>
          <p className="muted login-subtitle">
            Your account requires a password change before you can continue.
          </p>

          <label className="login-field">
            <span className="sr-only">Current Password</span>
            <input
              type="password"
              value={currentPassword}
              onChange={(e) => setCurrentPassword(e.target.value)}
              required
              autoComplete="current-password"
              placeholder="Current Password"
            />
          </label>

          <label className="login-field">
            <span className="sr-only">New Password</span>
            <input
              type="password"
              value={newPassword}
              onChange={(e) => setNewPassword(e.target.value)}
              required
              minLength={8}
              autoComplete="new-password"
              placeholder="New Password (min. 8 characters)"
            />
          </label>

          <label className="login-field">
            <span className="sr-only">Confirm New Password</span>
            <input
              type="password"
              value={confirmPassword}
              onChange={(e) => setConfirmPassword(e.target.value)}
              required
              autoComplete="new-password"
              placeholder="Confirm New Password"
            />
          </label>

          <button className="login-submit" type="submit" disabled={loading}>
            {loading ? 'UPDATING...' : 'SET NEW PASSWORD'}
          </button>
        </section>

        <aside className="login-promo" aria-hidden="true">
          <div className="login-promo-content">
            <p className="login-promo-brand">SECURITY</p>
            <p>
              Please set a strong, unique password to protect your account. You will not be
              prompted again after this step.
            </p>
          </div>
        </aside>
      </form>
    </main>
  );
}
