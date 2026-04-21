<?php

namespace App\Services;

use Stripe\StripeClient;

class StripeService
{
    private StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('services.stripe.secret'));
    }

    public function createCheckoutSession(
        string $planName,
        int $amountCents,
        string $successUrl,
        string $cancelUrl,
        string $customerEmail
    ): array {
        $session = $this->stripe->checkout->sessions->create([
            'mode'                => 'payment',
            'customer_email'      => $customerEmail,
            'line_items'          => [[
                'price_data' => [
                    'currency'     => 'usd',
                    'unit_amount'  => $amountCents,
                    'product_data' => ['name' => $planName],
                ],
                'quantity' => 1,
            ]],
            'success_url'         => $successUrl,
            'cancel_url'          => $cancelUrl,
            'payment_intent_data' => ['description' => $planName],
        ]);

        return [
            'session_id'   => $session->id,
            'checkout_url' => $session->url,
        ];
    }

    public function verifyWebhook(string $payload, string $signature): object
    {
        return \Stripe\Webhook::constructEvent(
            $payload,
            $signature,
            config('services.stripe.webhook_secret')
        );
    }

    public function retrieveSession(string $sessionId): object
    {
        return $this->stripe->checkout->sessions->retrieve($sessionId);
    }
}
