<?php

namespace App\Services;

use App\Mail\PurchaseConfirmationMail;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class CheckoutFulfillment
{
    public function fulfill(object $session): void
    {
        if (($session->payment_status ?? null) !== 'paid') {
            return;
        }

        DB::transaction(function () use ($session) {
            $pending = Subscription::where('stripe_session_id', $session->id)->first();
            // A retry must remain possible if checkout creation has not committed yet.
            abort_unless($pending, 503, 'Checkout is not ready for fulfillment.');
            // Serialize all purchases for an account, including simultaneous first purchases.
            User::whereKey($pending->user_id)->lockForUpdate()->firstOrFail();
            $pending = Subscription::whereKey($pending->id)->with('plan', 'user')->lockForUpdate()->firstOrFail();

            if (DB::table('payment_fulfillments')->where('session_id', $session->id)->exists()) {
                return;
            }
            if ($pending->status !== 'pending') {
                return;
            }

            DB::table('payment_fulfillments')->insert([
                'session_id' => $session->id,
                'fulfilled_at' => now(),
            ]);
            $existing = Subscription::where('user_id', $pending->user_id)->where('status', 'active')
                ->whereHas('plan', fn ($q) => $q->where('type', 'one_time'))
                ->latest()->lockForUpdate()->first();
            if ($existing) {
                $existing->increment('bonus_modules', $pending->plan->module_limit);
                $existing->increment('bonus_seats', $pending->plan->seat_limit);
                $pending->update(['status' => 'captured']);
            } else {
                $pending->update(['status' => 'active', 'current_period_start' => now(), 'current_period_end' => null]);
            }
            $transaction = Transaction::create([
                'user_id' => $pending->user_id,
                'subscription_id' => $existing?->id ?? $pending->id,
                'plan_name' => $pending->plan->name,
                'plan_type' => $pending->plan->type,
                'amount_cents' => (int) $session->amount_total,
                'currency' => strtoupper($session->currency ?? 'usd'),
                'payment_reference' => $session->id,
                'description' => $existing ? 'Additional module purchase' : null,
            ]);
            // Database queue insert participates in this transaction. Delivery happens
            // after commit and can be retried independently of credit fulfillment.
            Mail::to($pending->user->email)->queue(
                (new PurchaseConfirmationMail($transaction))->onConnection('database')->beforeCommit()
            );
        }, 3);
    }
}
