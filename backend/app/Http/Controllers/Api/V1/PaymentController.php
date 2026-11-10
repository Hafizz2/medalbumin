<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Plan status for the app. Plans are bought on the website (Account page), so the
 * apps contain no purchase flow — this keeps them within App Store / Google Play rules.
 */
class PaymentController extends Controller
{
    public function subscription(Request $request, SubscriptionService $subs): JsonResponse
    {
        $user = $request->user();
        $current = $user->activeSubscription()->with('plan')->first();

        return response()->json([
            'active' => (bool) $current,
            'plan' => $current?->plan?->name,
            'until' => $subs->premiumUntil($user)?->toIso8601String(),
            'pending_payment' => $user->payments()->where('status', 'pending')->where('gateway', 'bank_transfer')->exists(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $request->user()->payments()->with('plan')->latest()->limit(30)->get()->map(fn ($p) => [
                'id' => $p->id,
                'plan' => $p->plan?->name,
                'gateway' => $p->gateway,
                'amount' => (float) $p->amount,
                'currency' => $p->currency,
                'status' => $p->status,
                'reject_reason' => $p->reject_reason,
                'created_at' => $p->created_at->toIso8601String(),
            ]),
        ]);
    }
}
