import { FormEvent, useState } from 'react';
import { Link } from 'react-router-dom';
import logo from '../assets/logo.png';

type RegisterFormProps = {
  loading: boolean;
  error: string | null;
  onSubmit: (name: string, email: string, password: string, passwordConfirmation: string) => Promise<void>;
};

export function RegisterForm({ loading, error, onSubmit }: RegisterFormProps) {
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    await onSubmit(name, email, password, passwordConfirmation);
  }

  return (
    <form className="card login-card" onSubmit={handleSubmit}>
      <section className="login-form-panel">
        <p className="login-kicker">GET STARTED</p>
        <img src={logo} alt="WBT Forge" className="login-logo" />
        <p className="muted login-subtitle">Create your account to start building training modules.</p>

        {error && <div className="error-banner" style={{ marginBottom: '1rem' }}>{error}</div>}

        <label className="login-field">
          <span className="sr-only">Full name</span>
          <input
            type="text"
            value={name}
            onChange={(e) => setName(e.target.value)}
            required
            autoComplete="name"
            placeholder="Full name"
          />
        </label>
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
        <label className="login-field">
          <span className="sr-only">Password</span>
          <input
            type="password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            required
            autoComplete="new-password"
            placeholder="Password (min. 8 characters)"
            minLength={8}
          />
        </label>
        <label className="login-field">
          <span className="sr-only">Confirm password</span>
          <input
            type="password"
            value={passwordConfirmation}
            onChange={(e) => setPasswordConfirmation(e.target.value)}
            required
            autoComplete="new-password"
            placeholder="Confirm password"
            minLength={8}
          />
        </label>

        <button className="login-submit" type="submit" disabled={loading}>
          {loading ? 'CREATING ACCOUNT...' : 'CREATE ACCOUNT'}
        </button>

        <p className="login-meta">
          Already have an account? <Link to="/login">Sign in</Link>
        </p>
      </section>

      <aside className="login-promo" aria-hidden="true">
        <div className="login-promo-content">
          <img src={logo} alt="WBT Forge" className="login-promo-logo" />
          <p>Build compliant and engaging web-based training in minutes, with AI-assisted drafting and publishing.</p>
        </div>
      </aside>
    </form>
  );
}
