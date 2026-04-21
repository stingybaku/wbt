<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayPalService
{
    private string $clientId;
    private string $secret;
    private string $baseUrl;

    public function __construct()
    {
        $this->clientId = config('paypal.client_id');
        $this->secret   = config('paypal.secret');
        $this->baseUrl  = config('paypal.base_url');
    }

    // ─── Auth ─────────────────────────────────────────────────────────────────

    private function accessToken(): string
    {
        $response = Http::withBasicAuth($this->clientId, $this->secret)
            ->asForm()
            ->post("{$this->baseUrl}/v1/oauth2/token", [
                'grant_type' => 'client_credentials',
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException('PayPal auth failed: ' . $response->body());
        }

        return $response->json('access_token');
    }

    private function client(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withToken($this->accessToken())
            ->acceptJson()
            ->asJson();
    }

    // ─── One-time orders ──────────────────────────────────────────────────────

    /**
     * Create a PayPal order for a one-time purchase.
     * Returns the order ID and approval URL to redirect the user to.
     */
    public function createOrder(string $planId, string $returnUrl, string $cancelUrl): array
    {
        $response = $this->client()->post("{$this->baseUrl}/v2/checkout/orders", [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'reference_id' => $planId,
                    'description'  => 'WBT Forge — One-time plan',
                    'amount'       => [
                        'currency_code' => 'USD',
                        'value'         => '39.00',
                    ],
                ],
            ],
            'application_context' => [
                'return_url'          => $returnUrl,
                'cancel_url'          => $cancelUrl,
                'brand_name'          => 'WBT Forge',
                'user_action'         => 'PAY_NOW',
                'shipping_preference' => 'NO_SHIPPING',
            ],
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('PayPal createOrder failed: ' . $response->body());
        }

        $data        = $response->json();
        $approvalUrl = collect($data['links'])->firstWhere('rel', 'approve')['href'] ?? null;

        return [
            'order_id'    => $data['id'],
            'approval_url' => $approvalUrl,
        ];
    }

    /**
     * Capture a completed PayPal order (called after user approves).
     */
    public function captureOrder(string $orderId): array
    {
        // PayPal's capture endpoint requires Content-Type: application/json with a valid body.
        // withBody('{}') sends an explicit empty JSON object, bypassing Guzzle's serialization.
        $response = Http::withToken($this->accessToken())
            ->withBody('{}', 'application/json')
            ->acceptJson()
            ->post("{$this->baseUrl}/v2/checkout/orders/{$orderId}/capture");

        if (!$response->successful()) {
            throw new \RuntimeException('PayPal captureOrder failed: ' . $response->body());
        }

        return $response->json();
    }

    // ─── Subscriptions ────────────────────────────────────────────────────────

    /**
     * Create a PayPal subscription for a recurring plan.
     * Returns the subscription ID and approval URL.
     */
    public function createSubscription(string $paypalPlanId, string $returnUrl, string $cancelUrl): array
    {
        $response = $this->client()->post("{$this->baseUrl}/v1/billing/subscriptions", [
            'plan_id' => $paypalPlanId,
            'application_context' => [
                'return_url'          => $returnUrl,
                'cancel_url'          => $cancelUrl,
                'brand_name'          => 'WBT Forge',
                'user_action'         => 'SUBSCRIBE_NOW',
                'shipping_preference' => 'NO_SHIPPING',
            ],
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('PayPal createSubscription failed: ' . $response->body());
        }

        $data        = $response->json();
        $approvalUrl = collect($data['links'])->firstWhere('rel', 'approve')['href'] ?? null;

        return [
            'subscription_id' => $data['id'],
            'approval_url'    => $approvalUrl,
        ];
    }

    /**
     * Fetch a subscription's current status from PayPal.
     */
    public function getSubscription(string $subscriptionId): array
    {
        $response = $this->client()->get("{$this->baseUrl}/v1/billing/subscriptions/{$subscriptionId}");

        if (!$response->successful()) {
            throw new \RuntimeException('PayPal getSubscription failed: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Cancel a subscription in PayPal.
     */
    public function cancelSubscription(string $subscriptionId, string $reason = 'User requested cancellation'): void
    {
        $response = $this->client()->post(
            "{$this->baseUrl}/v1/billing/subscriptions/{$subscriptionId}/cancel",
            ['reason' => $reason]
        );

        if ($response->status() !== 204 && !$response->successful()) {
            throw new \RuntimeException('PayPal cancelSubscription failed: ' . $response->body());
        }
    }

    // ─── Webhook verification ─────────────────────────────────────────────────

    /**
     * Verify a webhook event signature.
     */
    public function verifyWebhook(array $headers, string $body): bool
    {
        $webhookId = config('paypal.webhook_id');
        if (!$webhookId) return true; // skip verification if not configured (local dev)

        try {
            $response = $this->client()->post("{$this->baseUrl}/v1/notifications/verify-webhook-signature", [
                'auth_algo'         => $headers['PAYPAL-AUTH-ALGO'] ?? '',
                'cert_url'          => $headers['PAYPAL-CERT-URL'] ?? '',
                'transmission_id'   => $headers['PAYPAL-TRANSMISSION-ID'] ?? '',
                'transmission_sig'  => $headers['PAYPAL-TRANSMISSION-SIG'] ?? '',
                'transmission_time' => $headers['PAYPAL-TRANSMISSION-TIME'] ?? '',
                'webhook_id'        => $webhookId,
                'webhook_event'     => json_decode($body, true),
            ]);

            return $response->json('verification_status') === 'SUCCESS';
        } catch (\Throwable $e) {
            Log::error('PayPal webhook verification failed: ' . $e->getMessage());
            return false;
        }
    }
}
