import { FormEvent, useState } from 'react';

type LoginFormProps = {
  loading: boolean;
  onSubmit: (email: string, password: string) => Promise<void>;
};

export function LoginForm({ loading, onSubmit }: LoginFormProps) {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    await onSubmit(email, password);
  }

  return (
    <form className="card" onSubmit={handleSubmit}>
      <h1>WBT Generator</h1>
      <p className="muted">Sign in to manage projects.</p>
      <label>
        Email
        <input
          type="email"
          value={email}
          onChange={(event) => setEmail(event.target.value)}
          required
          autoComplete="email"
        />
      </label>
      <label>
        Password
        <input
          type="password"
          value={password}
          onChange={(event) => setPassword(event.target.value)}
          required
          autoComplete="current-password"
        />
      </label>
      <button type="submit" disabled={loading}>
        {loading ? 'Signing in...' : 'Sign in'}
      </button>
    </form>
  );
}
