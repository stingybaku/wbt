<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Project;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BillingController extends Controller
{
    public function __construct(private StripeService $stripe) {}

    // ─── Plans ────────────────────────────────────────────────────────────────

    public function plans()
    {
        return response()->json([
            'plans' => Plan::where('is_active', true)->get(),
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

        $plan        = Plan::findOrFail($data['plan_id']);
        $user        = $request->user();
        $frontendUrl = config('app.frontend_url', config('app.url'));

        if (!$plan->isOneTime()) {
            return response()->json(['message' => 'Subscription plans are not yet available.'], 422);
        }

        $result = $this->stripe->createCheckoutSession(
            $plan->name,
            $plan->price_cents,
            $frontendUrl . '/billing/success?session_id={CHECKOUT_SESSION_ID}',
            $frontendUrl . '/billing/cancel',
            $user->email
        );

        Subscription::create([
            'id'                   => 'sub_' . Str::uuid(),
            'user_id'              => $user->id,
            'plan_id'              => $plan->id,
            'status'               => 'pending',
            'stripe_session_id'    => $result['session_id'],
            'current_period_start' => now(),
            'current_period_end'   => null,
        ]);

        return response()->json(['checkout_url' => $result['checkout_url']]);
    }

    // ─── Transaction history ──────────────────────────────────────────────────

    public function transactions(Request $request)
    {
        $transactions = Transaction::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($t) => [
                'id'               => $t->id,
                'plan_name'        => $t->plan_name,
                'plan_type'        => $t->plan_type,
                'amount_formatted' => $t->amountFormatted(),
                'amount_cents'     => $t->amount_cents,
                'status'           => $t->status,
                'description'      => $t->description,
                'payment_reference' => $t->payment_reference,
                'created_at'       => $t->created_at->toIso8601String(),
            ]);

        return response()->json(['transactions' => $transactions]);
    }

    // ─── Invoice download ─────────────────────────────────────────────────────

    public function invoice(Request $request, string $id)
    {
        $transaction = Transaction::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->with('user')
            ->firstOrFail();

        $invoiceNumber = strtoupper(substr($transaction->id, 0, 8));

        $pdf = app('dompdf.wrapper');
        $pdf->loadView('invoices.invoice', compact('transaction', 'invoiceNumber'));
        $pdf->setPaper('A4');

        return $pdf->download("invoice-{$invoiceNumber}.pdf");
    }

    // ─── Cancel subscription ──────────────────────────────────────────────────

    public function cancel(Request $request)
    {
        $subscription = $this->activeSubscription($request->user()->id);

        if (!$subscription) {
            return response()->json(['message' => 'No active subscription.'], 404);
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
        $data = [
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

        if ($sub->plan->isOneTime()) {
            $seatLimit = $sub->plan->seat_limit;

            $projects = Project::where('user_id', $sub->user_id)
                ->whereHas('module', fn ($q) => $q->where('status', 'generated'))
                ->with(['module:id,project_id', 'invites' => fn ($q) => $q->whereNull('revoked_at')->where('expires_at', '>', now())])
                ->orderBy('created_at')
                ->get()
                ->map(fn ($p) => [
                    'id'          => $p->id,
                    'title'       => $p->title,
                    'seats_used'  => $p->invites->count(),
                    'seats_limit' => $seatLimit,
                ])
                ->values()
                ->toArray();

            $totalSlots = $sub->modulesAvailable() + $sub->modules_used;
            $emptySlots = max(0, $totalSlots - count($projects));
            for ($i = 0; $i < $emptySlots; $i++) {
                $projects[] = ['id' => null, 'title' => null, 'seats_used' => 0, 'seats_limit' => $seatLimit];
            }

            $data['slots'] = $projects;
        }

        return $data;
    }
}
