import { FormEvent, useEffect, useMemo, useRef, useState } from 'react';
import { fetchFrontendTexts } from '../lib/api';
import { createTextResolver, type FrontendTextMap } from '../lib/text';

type LoginFormProps = {
  loading: boolean;
  onSubmit: (email: string, password: string) => Promise<void>;
};

export function LoginForm({ loading, onSubmit }: LoginFormProps) {
  const routeKey = 'auth.login';
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [frontendTexts, setFrontendTexts] = useState<FrontendTextMap | null>(null);
  const hasLoggedFetchError = useRef(false);

  useEffect(() => {
    fetchFrontendTexts(routeKey)
      .then((response) => {
        setFrontendTexts(response.texts);
      })
      .catch((error: unknown) => {
        if (!hasLoggedFetchError.current) {
          console.error('Failed to fetch frontend texts for auth.login', error);
          hasLoggedFetchError.current = true;
        }
      });
  }, []);

  const t = useMemo(() => createTextResolver(frontendTexts), [frontendTexts]);

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    await onSubmit(email, password);
  }

  return (
    <form className="card" onSubmit={handleSubmit}>
      <h1>{t('auth.title', 'WBT Generator')}</h1>
      <p className="muted">{t('auth.subtitle', 'Sign in to manage projects.')}</p>
      <label>
        {t('auth.email_label', 'Email')}
        <input
          type="email"
          value={email}
          onChange={(event) => setEmail(event.target.value)}
          required
          autoComplete="email"
        />
      </label>
      <label>
        {t('auth.password_label', 'Password')}
        <input
          type="password"
          value={password}
          onChange={(event) => setPassword(event.target.value)}
          required
          autoComplete="current-password"
        />
      </label>
      <button type="submit" disabled={loading}>
        {loading ? t('auth.signing_in_button', 'Signing in...') : t('auth.submit_button', 'Sign in')}
      </button>
    </form>
  );
}
