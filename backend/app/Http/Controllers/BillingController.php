<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Subscription;
use App\Services\PayPalService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BillingController extends Controller
{
    public function __construct(private PayPalService $paypal) {}

    // ─── Plans ────────────────────────────────────────────────────────────────

    public function plans()
    {
        return response()->json([
            'plans' => Plan::all(),
        ]);
    }

    // ─── Current subscription ─────────────────────────────────────────────────

    public function current(Request $request)
    {
        $subscription = $this->activeSubscription($request->user()->id);

        return response()->json([
            'subscription' => $subscription ? $this->formatSubscription($subscription) : null,
        ]);
    }

    // ─── Initiate checkout ────────────────────────────────────────────────────

    public function checkout(Request $request)
    {
        $data = $request->validate([
            'plan_id' => 'required|string|exists:plans,id',
        ]);

        $plan    = Plan::findOrFail($data['plan_id']);
        $user    = $request->user();
        $frontendUrl = config('app.frontend_url', config('app.url'));

        $returnUrl = $frontendUrl . '/billing/success';
        $cancelUrl = $frontendUrl . '/billing/cancel';

        if ($plan->isOneTime()) {
            $result = $this->paypal->createOrder($plan->id, $returnUrl, $cancelUrl);

            // Create a pending subscription record
            Subscription::create([
                'id'                    => 'sub_' . Str::uuid(),
                'user_id'               => $user->id,
                'plan_id'               => $plan->id,
                'status'                => 'pending',
                'paypal_order_id'       => $result['order_id'],
                'current_period_start'  => now(),
                'current_period_end'    => null, // one-time, no expiry
            ]);

            return response()->json(['approval_url' => $result['approval_url']]);
        }

        // Subscription plan
        if (!$plan->paypal_plan_id) {
            return response()->json(['message' => 'This plan is not yet available for purchase.'], 422);
        }

        $result = $this->paypal->createSubscription($plan->paypal_plan_id, $returnUrl, $cancelUrl);

        Subscription::create([
            'id'                      => 'sub_' . Str::uuid(),
            'user_id'                 => $user->id,
            'plan_id'                 => $plan->id,
            'status'                  => 'pending',
            'paypal_subscription_id'  => $result['subscription_id'],
        ]);

        return response()->json(['approval_url' => $result['approval_url']]);
    }

    // ─── Capture one-time order (called after PayPal redirect) ────────────────

    public function captureOrder(Request $request)
    {
        $data = $request->validate([
            'order_id' => 'required|string',
        ]);

        $subscription = Subscription::where('paypal_order_id', $data['order_id'])
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $capture = $this->paypal->captureOrder($data['order_id']);

        if (($capture['status'] ?? '') !== 'COMPLETED') {
            return response()->json(['message' => 'Payment not completed.'], 422);
        }

        $subscription->update([
            'status'               => 'active',
            'current_period_start' => now(),
            'current_period_end'   => null,
        ]);

        return response()->json(['subscription' => $this->formatSubscription($subscription->fresh())]);
    }

    // ─── Cancel subscription ──────────────────────────────────────────────────

    public function cancel(Request $request)
    {
        $subscription = $this->activeSubscription($request->user()->id);

        if (!$subscription) {
            return response()->json(['message' => 'No active subscription.'], 404);
        }

        if ($subscription->paypal_subscription_id) {
            $this->paypal->cancelSubscription($subscription->paypal_subscription_id);
        }

        $subscription->update([
            'status'       => 'cancelled',
            'cancelled_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function activeSubscription(int $userId): ?Subscription
    {
        return Subscription::where('user_id', $userId)
            ->where('status', 'active')
            ->with('plan')
            ->latest()
            ->first();
    }

    private function formatSubscription(Subscription $sub): array
    {
        return [
            'id'                 => $sub->id,
            'plan'               => [
                'id'             => $sub->plan->id,
                'name'           => $sub->plan->name,
                'type'           => $sub->plan->type,
                'module_limit'   => $sub->plan->module_limit,
                'seat_limit'     => $sub->plan->seat_limit,
                'billing_period' => $sub->plan->billing_period,
                'rollover'       => $sub->plan->rollover,
            ],
            'status'             => $sub->status,
            'modules_used'       => $sub->modules_used,
            'modules_available'  => $sub->modulesAvailable(),
            'seats_used'         => $sub->seatsUsed(),
            'seats_available'    => $sub->seatsAvailable(),
            'current_period_end' => $sub->current_period_end?->toIso8601String(),
            'cancelled_at'       => $sub->cancelled_at?->toIso8601String(),
        ];
    }
}
