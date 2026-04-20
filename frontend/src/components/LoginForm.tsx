import { FormEvent, useEffect, useMemo, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
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
    <form className="card login-card" onSubmit={handleSubmit}>
      <section className="login-form-panel">
        <p className="login-kicker">{t('auth.welcome_kicker', 'WELCOME TO')}</p>
        <h1 className="login-brand">{t('auth.title', 'INFINITY')}</h1>
        <p className="muted login-subtitle">{t('auth.subtitle', 'Log in to get updates on the things that interest you.')}</p>

        <label className="login-field">
          <span className="sr-only">{t('auth.email_label', 'Email')}</span>
          <input
            type="email"
            value={email}
            onChange={(event) => setEmail(event.target.value)}
            required
            autoComplete="email"
            placeholder={t('auth.email_label', 'Email')}
          />
        </label>
        <label className="login-field">
          <span className="sr-only">{t('auth.password_label', 'Password')}</span>
          <input
            type="password"
            value={password}
            onChange={(event) => setPassword(event.target.value)}
            required
            autoComplete="current-password"
            placeholder={t('auth.password_label', 'Password')}
          />
        </label>
        <button className="login-submit" type="submit" disabled={loading}>
          {loading ? t('auth.signing_in_button', 'SIGNING IN...') : t('auth.submit_button', 'SIGN IN')}
        </button>

        <p className="login-meta">
          {t('auth.signup_prompt', 'Do not have an account?')} <Link to="/register">{t('auth.signup_cta', 'Sign Up Now')}</Link>
        </p>
      </section>

      <aside className="login-promo" aria-hidden="true">
        <div className="login-promo-content">
          <p className="login-promo-brand">{t('auth.promo_brand', 'INFINITY')}</p>
          <p>
            {t(
              'auth.promo_body',
              'Build compliant and engaging web-based training in minutes, with AI-assisted drafting and publishing.'
            )}
          </p>
        </div>
      </aside>
    </form>
  );
}
