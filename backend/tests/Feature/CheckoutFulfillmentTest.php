<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CheckoutFulfillment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\Fixtures;
use Tests\TestCase;

class CheckoutFulfillmentTest extends TestCase
{
    use Fixtures, RefreshDatabase;

    public function test_additional_purchase_replays_do_not_grant_credits_or_duplicate_mail(): void
    {
        $user = User::factory()->create();
        $active = $this->subscription($user);
        $purchase = $this->subscription($user, ['status' => 'pending', 'stripe_session_id' => 'cs_paid']);
        $session = (object) ['id' => 'cs_paid', 'payment_status' => 'paid', 'amount_total' => 3900];
        $service = app(CheckoutFulfillment::class);
        $service->fulfill($session);
        $service->fulfill($session);
        $this->assertSame(1, $active->fresh()->bonus_modules);
        $this->assertSame('captured', $purchase->fresh()->status);
        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseCount('payment_fulfillments', 1);
        $this->assertDatabaseCount('jobs', 1);
        $active->update(['status' => 'cancelled']);
        $service->fulfill($session);
        $this->assertSame('cancelled', $active->fresh()->status);
        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_queue_failure_rolls_back_fulfillment_for_safe_retry(): void
    {
        $user = User::factory()->create();
        $pending = $this->subscription($user, ['status' => 'pending', 'stripe_session_id' => 'cs_retry']);
        $mail = \Mockery::mock();
        Mail::shouldReceive('to')->once()->andReturn($mail);
        $mail->shouldReceive('queue')->once()->andThrow(new \RuntimeException('queue unavailable'));
        try {
            app(CheckoutFulfillment::class)->fulfill((object) ['id' => 'cs_retry', 'payment_status' => 'paid', 'amount_total' => 3900]);
            $this->fail('Expected rollback');
        } catch (\RuntimeException $e) {
            $this->assertSame('queue unavailable', $e->getMessage());
        }
        $this->assertSame('pending', $pending->fresh()->status);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('payment_fulfillments', 0);
    }

    public function test_webhook_rejects_missing_or_invalid_signatures(): void
    {
        config(['services.stripe.secret' => 'sk_test_placeholder', 'services.stripe.webhook_secret' => 'whsec_test']);
        $this->postJson('/api/webhooks/stripe', [])->assertStatus(400);
        $this->postJson('/api/webhooks/stripe', [], ['Stripe-Signature' => 'invalid'])->assertStatus(400);
    }

    public function test_signed_first_purchase_is_fulfilled_once_even_after_cancellation(): void
    {
        config(['services.stripe.secret' => 'sk_test_placeholder', 'services.stripe.webhook_secret' => 'whsec_test']);
        $user = User::factory()->create();
        $purchase = $this->subscription($user, ['status' => 'pending', 'stripe_session_id' => 'cs_first']);
        $payload = json_encode(['id' => 'evt_first', 'object' => 'event', 'type' => 'checkout.session.completed',
            'data' => ['object' => ['id' => 'cs_first', 'object' => 'checkout.session',
                'payment_status' => 'paid', 'amount_total' => 3900, 'currency' => 'usd']]]);
        $timestamp = time();
        $signature = 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_test');
        $send = fn () => $this->call('POST', '/api/webhooks/stripe', [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $signature], $payload)->assertOk();
        $send();
        $this->assertSame('active', $purchase->fresh()->status);
        $purchase->update(['status' => 'cancelled']);
        $send();
        $this->assertSame('cancelled', $purchase->fresh()->status);
        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseCount('jobs', 1);
    }
}
