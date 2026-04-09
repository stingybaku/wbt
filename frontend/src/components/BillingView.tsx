import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import {
  cancelSubscription,
  captureOrder,
  fetchBillingPlans,
  fetchCurrentSubscription,
  startCheckout,
} from '../lib/api';
import type { BillingSubscription, Plan } from '../lib/types';

// ─── Billing page ─────────────────────────────────────────────────────────────

export function BillingView() {
  const { token } = useAuth();

  const [plans, setPlans] = useState<Plan[]>([]);
  const [subscription, setSubscription] = useState<BillingSubscription | null | undefined>(undefined);
  const [loading, setLoading] = useState(true);
  const [checkoutLoading, setCheckoutLoading] = useState<string | null>(null);
  const [cancelLoading, setCancelLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!token) return;
    Promise.all([fetchBillingPlans(token), fetchCurrentSubscription(token)])
      .then(([plansRes, subRes]) => {
        setPlans(plansRes.plans);
        setSubscription(subRes.subscription);
      })
      .catch((e) => setError(e.message))
      .finally(() => setLoading(false));
  }, [token]);

  async function handleCheckout(planId: string) {
    if (!token) return;
    setCheckoutLoading(planId);
    setError(null);
    try {
      const res = await startCheckout(token, planId);
      window.location.href = res.approval_url;
    } catch (e: unknown) {
      setError(e instanceof Error ? e.message : 'Checkout failed');
      setCheckoutLoading(null);
    }
  }

  async function handleCancel() {
    if (!token || !subscription) return;
    if (!confirm('Are you sure you want to cancel your subscription?')) return;
    setCancelLoading(true);
    setError(null);
    try {
      await cancelSubscription(token);
      const res = await fetchCurrentSubscription(token);
      setSubscription(res.subscription);
    } catch (e: unknown) {
      setError(e instanceof Error ? e.message : 'Cancel failed');
    } finally {
      setCancelLoading(false);
    }
  }

  if (loading) {
    return (
      <div className="billing-page">
        <p className="billing-loading">Loading billing info…</p>
      </div>
    );
  }

  return (
    <div className="billing-page">
      <h1 className="billing-title">Billing &amp; Plans</h1>

      {error && <div className="error-banner">{error}</div>}

      {/* Current subscription */}
      {subscription && (
        <div className="billing-current card">
          <div className="billing-current-header">
            <div>
              <h2 className="billing-current-plan">{subscription.plan.name}</h2>
              <span className={`billing-status-pill billing-status--${subscription.status}`}>
                {subscription.status}
              </span>
            </div>
            {subscription.plan.type === 'subscription' && subscription.status === 'active' && (
              <button
                type="button"
                className="ghost"
                onClick={handleCancel}
                disabled={cancelLoading}
              >
                {cancelLoading ? 'Cancelling…' : 'Cancel subscription'}
              </button>
            )}
          </div>

          <div className="billing-quota-grid">
            <QuotaBar
              label="Modules"
              used={subscription.modules_used}
              available={subscription.modules_available}
              limit={subscription.plan.module_limit}
            />
            <QuotaBar
              label="Seats"
              used={subscription.seats_used}
              available={subscription.seats_available}
              limit={subscription.plan.seat_limit}
            />
          </div>

          {subscription.current_period_end && (
            <p className="billing-renews">
              {subscription.status === 'cancelled' ? 'Access until' : 'Renews'}:{' '}
              <strong>{new Date(subscription.current_period_end).toLocaleDateString()}</strong>
            </p>
          )}
        </div>
      )}

      {/* Plan cards */}
      <h2 className="billing-plans-heading">
        {subscription ? 'Available Plans' : 'Choose a Plan'}
      </h2>

      <div className="billing-plans-grid">
        {plans.map((plan) => (
          <PlanCard
            key={plan.id}
            plan={plan}
            isCurrent={
              plan.type === 'subscription' &&
              subscription?.plan.id === plan.id &&
              subscription?.status === 'active'
            }
            isOwned={
              plan.type === 'one_time' &&
              subscription?.plan.id === plan.id &&
              subscription?.status === 'active'
            }
            loading={checkoutLoading === plan.id}
            onSelect={() => handleCheckout(plan.id)}
          />
        ))}
      </div>
    </div>
  );
}

// ─── Plan card ────────────────────────────────────────────────────────────────

function PlanCard({
  plan,
  isCurrent,
  isOwned,
  loading,
  onSelect,
}: {
  plan: Plan;
  isCurrent: boolean;
  isOwned: boolean;
  loading: boolean;
  onSelect: () => void;
}) {
  const priceLabel = plan.price_cents === 0 ? 'Free' : `$${(plan.price_cents / 100).toFixed(0)}`;
  const periodLabel = plan.type === 'one_time'
    ? 'one-time'
    : plan.billing_period === 'annual'
      ? '/ mo · billed annually'
      : '/ mo';

  return (
    <div className={`plan-card card${isCurrent ? ' plan-card--current' : ''}`}>
      {isCurrent && <span className="plan-card-badge">Current plan</span>}

      <h3 className="plan-card-name">{plan.name}</h3>

      <div className="plan-card-price">
        <span className="plan-card-amount">{priceLabel}</span>
        <span className="plan-card-period">{periodLabel}</span>
      </div>

      <ul className="plan-card-features">
        <li>
          <FeatureIcon /> {plan.module_limit} {plan.module_limit === 1 ? 'module' : 'modules'}
          {plan.type === 'subscription' ? ' / cycle' : ''}
        </li>
        <li>
          <FeatureIcon /> {plan.seat_limit} {plan.seat_limit === 1 ? 'seat' : 'seats'}
        </li>
        {plan.rollover && (
          <li>
            <FeatureIcon /> Unused modules roll over
          </li>
        )}
        {plan.billing_period === 'annual' && (
          <li>
            <FeatureIcon /> Annual billing
          </li>
        )}
      </ul>

      <button
        type="button"
        onClick={onSelect}
        disabled={isCurrent || loading}
        className={isCurrent ? 'ghost' : undefined}
      >
        {loading ? 'Redirecting…' : isCurrent ? 'Current plan' : isOwned ? 'Buy again' : 'Get started'}
      </button>
    </div>
  );
}

// ─── Quota bar ────────────────────────────────────────────────────────────────

function QuotaBar({
  label,
  used,
  available,
  limit,
}: {
  label: string;
  used: number;
  available: number;
  limit: number;
}) {
  const total = used + available;
  const pct = total === 0 ? 0 : Math.round((used / total) * 100);
  const isWarning = pct >= 80;

  return (
    <div className="quota-bar">
      <div className="quota-bar-labels">
        <span className="quota-bar-label">{label}</span>
        <span className="quota-bar-count">
          {used} / {limit} used
        </span>
      </div>
      <div className="quota-bar-track">
        <div
          className={`quota-bar-fill${isWarning ? ' quota-bar-fill--warn' : ''}`}
          style={{ width: `${pct}%` }}
        />
      </div>
    </div>
  );
}

function FeatureIcon() {
  return (
    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden>
      <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z" />
    </svg>
  );
}

// ─── Post-checkout pages ──────────────────────────────────────────────────────

export function BillingSuccessPage() {
  const { token } = useAuth();
  const navigate = useNavigate();
  const [status, setStatus] = useState<'loading' | 'ok' | 'error'>('loading');
  const [message, setMessage] = useState('');

  useEffect(() => {
    if (!token) return;

    const params = new URLSearchParams(window.location.search);
    const orderId = params.get('token'); // PayPal one-time order token
    const subscriptionId = params.get('subscription_id'); // PayPal subscription

    if (orderId) {
      // One-time purchase — capture the order
      captureOrder(token, orderId)
        .then(() => { setStatus('ok'); setMessage('Payment confirmed! Your plan is now active.'); })
        .catch((e: Error) => { setStatus('error'); setMessage(e.message); });
    } else if (subscriptionId) {
      // Recurring — PayPal will confirm via webhook; just show success
      setStatus('ok');
      setMessage('Subscription activated! Your plan is now active.');
    } else {
      setStatus('ok');
      setMessage('Payment received. Your plan is being activated.');
    }
  }, [token]);

  return (
    <div className="billing-result-page">
      {status === 'loading' && <p>Confirming your payment…</p>}
      {status === 'ok' && (
        <>
          <div className="billing-result-icon billing-result-icon--success">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="currentColor">
              <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z" />
            </svg>
          </div>
          <h1>You're all set!</h1>
          <p>{message}</p>
          <button type="button" onClick={() => navigate('/billing')}>View my plan</button>
          <button type="button" className="ghost" onClick={() => navigate('/projects')}>Go to projects</button>
        </>
      )}
      {status === 'error' && (
        <>
          <h1>Something went wrong</h1>
          <p>{message}</p>
          <button type="button" onClick={() => navigate('/billing')}>Back to billing</button>
        </>
      )}
    </div>
  );
}

export function BillingCancelPage() {
  const navigate = useNavigate();
  return (
    <div className="billing-result-page">
      <div className="billing-result-icon billing-result-icon--cancel">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="currentColor">
          <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12 19 6.41z" />
        </svg>
      </div>
      <h1>Payment cancelled</h1>
      <p>You have not been charged. You can try again whenever you're ready.</p>
      <button type="button" onClick={() => navigate('/billing')}>View plans</button>
      <button type="button" className="ghost" onClick={() => navigate('/projects')}>Back to projects</button>
    </div>
  );
}
