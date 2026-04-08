<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Services\PayPalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PayPalWebhookController extends Controller
{
    public function __construct(private PayPalService $paypal) {}

    public function handle(Request $request)
    {
        $body    = $request->getContent();
        $headers = [
            'PAYPAL-AUTH-ALGO'        => $request->header('PAYPAL-AUTH-ALGO'),
            'PAYPAL-CERT-URL'         => $request->header('PAYPAL-CERT-URL'),
            'PAYPAL-TRANSMISSION-ID'  => $request->header('PAYPAL-TRANSMISSION-ID'),
            'PAYPAL-TRANSMISSION-SIG' => $request->header('PAYPAL-TRANSMISSION-SIG'),
            'PAYPAL-TRANSMISSION-TIME'=> $request->header('PAYPAL-TRANSMISSION-TIME'),
        ];

        if (!$this->paypal->verifyWebhook($headers, $body)) {
            Log::warning('PayPal webhook signature verification failed.');
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $event     = $request->json()->all();
        $eventType = $event['event_type'] ?? '';
        $resource  = $event['resource'] ?? [];

        Log::info("PayPal webhook received: {$eventType}");

        match ($eventType) {
            'PAYMENT.CAPTURE.COMPLETED'       => $this->handleCaptureCompleted($resource),
            'BILLING.SUBSCRIPTION.ACTIVATED'  => $this->handleSubscriptionActivated($resource),
            'BILLING.SUBSCRIPTION.CANCELLED'  => $this->handleSubscriptionCancelled($resource),
            'BILLING.SUBSCRIPTION.EXPIRED'    => $this->handleSubscriptionCancelled($resource),
            'BILLING.SUBSCRIPTION.RENEWED'    => $this->handleSubscriptionRenewed($resource),
            default                           => null,
        };

        return response()->json(['ok' => true]);
    }

    // ─── Handlers ─────────────────────────────────────────────────────────────

    private function handleCaptureCompleted(array $resource): void
    {
        // One-time order captured via webhook (supplement to captureOrder endpoint)
        $orderId = $resource['supplementary_data']['related_ids']['order_id'] ?? null;
        if (!$orderId) return;

        $subscription = Subscription::where('paypal_order_id', $orderId)->first();
        if (!$subscription || $subscription->status === 'active') return;

        $subscription->update([
            'status'               => 'active',
            'current_period_start' => now(),
            'current_period_end'   => null,
        ]);
    }

    private function handleSubscriptionActivated(array $resource): void
    {
        $subscriptionId = $resource['id'] ?? null;
        if (!$subscriptionId) return;

        $subscription = Subscription::where('paypal_subscription_id', $subscriptionId)->first();
        if (!$subscription) return;

        $periodStart = isset($resource['billing_info']['last_payment']['time'])
            ? now()->parse($resource['billing_info']['last_payment']['time'])
            : now();

        $nextBilling = isset($resource['billing_info']['next_billing_time'])
            ? now()->parse($resource['billing_info']['next_billing_time'])
            : null;

        $subscription->update([
            'status'               => 'active',
            'current_period_start' => $periodStart,
            'current_period_end'   => $nextBilling,
        ]);
    }

    private function handleSubscriptionCancelled(array $resource): void
    {
        $subscriptionId = $resource['id'] ?? null;
        if (!$subscriptionId) return;

        $subscription = Subscription::where('paypal_subscription_id', $subscriptionId)
            ->where('status', 'active')
            ->first();
        if (!$subscription) return;

        $subscription->update([
            'status'       => 'cancelled',
            'cancelled_at' => now(),
        ]);
    }

    private function handleSubscriptionRenewed(array $resource): void
    {
        $subscriptionId = $resource['id'] ?? null;
        if (!$subscriptionId) return;

        $subscription = Subscription::where('paypal_subscription_id', $subscriptionId)->first();
        if (!$subscription) return;

        $nextBilling = isset($resource['billing_info']['next_billing_time'])
            ? now()->parse($resource['billing_info']['next_billing_time'])
            : null;

        // Roll over unused modules if the plan supports it
        $rolled = 0;
        if ($subscription->plan->rollover) {
            $rolled = $subscription->modulesAvailable();
        }

        $subscription->update([
            'status'               => 'active',
            'current_period_start' => now(),
            'current_period_end'   => $nextBilling,
            'modules_used'         => 0,
            'modules_rolled_over'  => $rolled,
        ]);
    }
}
