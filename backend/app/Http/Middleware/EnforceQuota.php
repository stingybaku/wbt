<?php

namespace App\Http\Middleware;

use App\Models\Subscription;
use Closure;
use Illuminate\Http\Request;

class EnforceQuota
{
    /**
     * Quota types:
     *   'module'  — check modules_available > 0
     *   'seat'    — check seats_available > 0
     */
    public function handle(Request $request, Closure $next, string $type = 'module')
    {
        $user         = $request->user();
        $subscription = Subscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->with('plan')
            ->latest()
            ->first();

        if (!$subscription) {
            return response()->json([
                'message' => 'No active subscription. Please purchase a plan to continue.',
                'code'    => 'no_subscription',
            ], 402);
        }

        if ($type === 'module' && $subscription->modulesAvailable() <= 0) {
            return response()->json([
                'message' => 'Module limit reached. Upgrade your plan or wait for the next billing cycle.',
                'code'    => 'module_limit_reached',
            ], 402);
        }

        if ($type === 'seat' && $subscription->seatsAvailable() <= 0) {
            return response()->json([
                'message' => 'Seat limit reached. Upgrade your plan to invite more learners.',
                'code'    => 'seat_limit_reached',
            ], 402);
        }

        return $next($request);
    }
}
