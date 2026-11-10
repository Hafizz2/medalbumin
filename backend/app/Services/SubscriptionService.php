<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\PromoRedemption;
use App\Models\Referral;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\AppNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    /**
     * Marks a payment as approved and activates (or extends) the student's plan.
     * Safe to call twice — the second call is a no-op.
     */
    public function approvePayment(Payment $payment, ?User $reviewer = null): Subscription
    {
        return DB::transaction(function () use ($payment, $reviewer) {
            /** @var Payment $payment */
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            if ($payment->status === 'approved' && $payment->subscription) {
                return $payment->subscription;
            }

            $payment->update([
                'status' => 'approved',
                'paid_at' => $payment->paid_at ?? now(),
                'reviewed_by' => $reviewer?->id ?? $payment->reviewed_by,
                'reviewed_at' => $reviewer ? now() : $payment->reviewed_at,
                'reject_reason' => null,
            ]);

            if ($payment->promo_code_id) {
                PromoRedemption::query()->firstOrCreate(
                    ['promo_code_id' => $payment->promo_code_id, 'user_id' => $payment->user_id],
                    ['payment_id' => $payment->id],
                );
                $payment->promoCode?->increment('used_count');
            }

            $sub = $this->grant($payment->user, $payment->plan, 'payment', $payment);
            $this->rewardReferral($payment->user);

            $payment->user->notify(AppNotification::paymentApproved(
                $payment->plan->name,
                $sub->ends_at->format('j M Y'),
            ));

            return $sub;
        });
    }

    public function rejectPayment(Payment $payment, User $reviewer, string $reason): void
    {
        $payment->update([
            'status' => 'rejected',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'reject_reason' => $reason,
        ]);
        $payment->user->notify(AppNotification::paymentRejected($reason));
    }

    /**
     * Adds a subscription that starts when the current one ends, so paying early never loses days.
     */
    public function grant(User $user, ?Plan $plan, string $source, ?Payment $payment = null, ?int $days = null): Subscription
    {
        $current = $user->subscriptions()->current()->orderByDesc('ends_at')->first();
        $start = $current ? $current->ends_at->copy() : now();
        $end = $days !== null ? $start->copy()->addDays($days) : $plan->endsAtFrom($start);

        // A fixed-date plan (exit pack) bought after another plan still ends on its own date.
        if ($end->lessThanOrEqualTo($start)) {
            $start = now();
            $end = $plan->endsAtFrom(now());
        }

        return $user->subscriptions()->create([
            'plan_id' => $plan?->id,
            'payment_id' => $payment?->id,
            'source' => $source,
            'starts_at' => $start,
            'ends_at' => $end,
        ]);
    }

    /** "When an invited friend subscribes, both get 1 free month. Up to 6 rewards per student." */
    public function rewardReferral(User $user): void
    {
        $referral = Referral::query()->where('referred_id', $user->id)->where('status', 'joined')->first();
        if (! $referral) {
            return;
        }
        $days = (int) Setting::get('referral_reward_days', 30);
        $max = (int) Setting::get('referral_max_rewards', 6);
        $rewarded = Referral::query()->where('referrer_id', $referral->referrer_id)->where('status', 'rewarded')->count();

        $referral->update(['status' => 'rewarded', 'rewarded_at' => now()]);
        $this->grant($user, null, 'referral', days: $days);

        if ($rewarded < $max && $referral->referrer) {
            $this->grant($referral->referrer, null, 'referral', days: $days);
            $referral->referrer->notify(AppNotification::referralReward($user->shortName()));
        }
    }

    public function premiumUntil(User $user): ?Carbon
    {
        if (! $user->subscriptions()->current()->exists()) {
            return null;
        }

        // Includes back-to-back subscriptions that start after the current one ends.
        return Carbon::parse($user->subscriptions()->where('status', 'active')->where('ends_at', '>', now())->max('ends_at'));
    }
}
