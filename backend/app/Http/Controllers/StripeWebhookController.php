<?php

namespace App\Http\Controllers;

use App\Mail\PurchaseConfirmationMail;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class StripeWebhookController extends Controller
{
    public function __construct(private StripeService $stripe) {}

    public function handle(Request $request)
    {
        $payload   = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        try {
            $event = $this->stripe->verifyWebhook($payload, $signature);
        } catch (\Exception $e) {
            Log::warning('Stripe webhook signature verification failed: ' . $e->getMessage());
            return response()->json(['message' => 'Invalid signature.'], 400);
        }

        Log::info('Stripe webhook received: ' . $event->type);

        if ($event->type === 'checkout.session.completed') {
            $this->handleCheckoutCompleted($event->data->object);
        }

        return response()->json(['ok' => true]);
    }

    private function handleCheckoutCompleted(object $session): void
    {
        if ($session->payment_status !== 'paid') {
            return;
        }

        $subscription = Subscription::where('stripe_session_id', $session->id)
            ->with('plan', 'user')
            ->first();

        if (!$subscription) {
            Log::warning('Stripe webhook: no pending subscription for session ' . $session->id);
            return;
        }

        // Idempotent
        if ($subscription->status === 'active') {
            return;
        }

        $amountCents = (int) $session->amount_total;

        // Stack onto existing active one-time subscription if present
        $existing = Subscription::where('user_id', $subscription->user_id)
            ->where('status', 'active')
            ->whereHas('plan', fn ($q) => $q->where('type', 'one_time'))
            ->latest()
            ->first();

        if ($existing) {
            $existing->increment('bonus_modules');
            $existing->increment('bonus_seats', $subscription->plan->seat_limit);
            $subscription->update(['status' => 'captured']);

            $transaction = Transaction::create([
                'user_id'           => $subscription->user_id,
                'subscription_id'   => $existing->id,
                'plan_name'         => $subscription->plan->name,
                'plan_type'         => $subscription->plan->type,
                'amount_cents'      => $amountCents,
                'payment_reference' => $session->id,
                'description'       => 'Additional module purchase',
            ]);
            $transaction->load('user');
            Mail::to($subscription->user->email)->queue(new PurchaseConfirmationMail($transaction));
            return;
        }

        $subscription->update([
            'status'               => 'active',
            'current_period_start' => now(),
            'current_period_end'   => null,
        ]);

        $transaction = Transaction::create([
            'user_id'           => $subscription->user_id,
            'subscription_id'   => $subscription->id,
            'plan_name'         => $subscription->plan->name,
            'plan_type'         => $subscription->plan->type,
            'amount_cents'      => $amountCents,
            'payment_reference' => $session->id,
        ]);
        $transaction->load('user');
        Mail::to($subscription->user->email)->queue(new PurchaseConfirmationMail($transaction));
    }
}
