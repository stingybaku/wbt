<?php

namespace App\Http\Controllers;

use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StripeWebhookController extends Controller
{
    public function __construct(private StripeService $stripe) {}

    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature', '');

        try {
            $event = $this->stripe->verifyWebhook($payload, $signature);
        } catch (\Exception $e) {
            Log::warning('Stripe webhook signature verification failed: '.$e->getMessage());

            return response()->json(['message' => 'Invalid signature.'], 400);
        }

        Log::info('Stripe webhook received: '.$event->type);

        if ($event->type === 'checkout.session.completed') {
            $this->handleCheckoutCompleted($event->data->object);
        }

        return response()->json(['ok' => true]);
    }

    private function handleCheckoutCompleted(object $session): void
    {
        app(\App\Services\CheckoutFulfillment::class)->fulfill($session);
    }
}
