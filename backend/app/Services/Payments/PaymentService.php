<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\PromoCode;
use App\Models\PromoRedemption;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Laravel\Facades\Image;
use Throwable;

class PaymentService
{
    public function __construct(
        private readonly ChapaGateway $chapa,
        private readonly SubscriptionService $subscriptions,
    ) {}

    /** Validates a promo code for a plan and returns the price breakdown. */
    public function quote(User $user, Plan $plan, ?string $code): array
    {
        $promo = null;
        $discount = 0.0;
        if ($code) {
            $promo = PromoCode::query()->where('code', strtoupper(trim($code)))->first();
            $why = $promo ? $promo->rejectionFor($user, $plan) : 'This promo code does not exist.';
            if ($why) {
                throw ValidationException::withMessages(['promo_code' => $why]);
            }
            $discount = $promo->discountFor($plan);
        }

        return [
            'promo' => $promo,
            'price' => (float) $plan->price,
            'discount' => $discount,
            'total' => max(0, round((float) $plan->price - $discount, 2)),
            'free_days' => $promo?->type === 'free_days' ? (int) $promo->value : null,
        ];
    }

    /**
     * Starts a payment. Returns the payment; for online gateways it carries checkout_url.
     */
    public function start(User $user, Plan $plan, string $gateway, ?string $promoCode, array $bank = []): Payment
    {
        $quote = $this->quote($user, $plan, $promoCode);

        // Free-days promo codes (e.g. "Free 7 days") activate straight away, no payment needed.
        if ($quote['free_days']) {
            return DB::transaction(function () use ($user, $plan, $quote) {
                $payment = $this->createPayment($user, $plan, 'promo', 0, (float) $plan->price, $quote['promo']);
                $payment->update(['status' => 'approved', 'paid_at' => now()]);
                PromoRedemption::query()->create(['promo_code_id' => $quote['promo']->id, 'user_id' => $user->id, 'payment_id' => $payment->id]);
                $quote['promo']->increment('used_count');
                $this->subscriptions->grant($user, $plan, 'promo', $payment, $quote['free_days']);

                return $payment;
            });
        }

        if ($quote['total'] <= 0) {
            $payment = $this->createPayment($user, $plan, 'promo', 0, $quote['discount'], $quote['promo']);
            $this->subscriptions->approvePayment($payment);

            return $payment->refresh();
        }

        if ($gateway === 'bank_transfer') {
            return $this->startBankTransfer($user, $plan, $quote, $bank);
        }

        if (! $this->chapa->isConfigured()) {
            throw ValidationException::withMessages(['gateway' => 'Online payment is not available yet. Please pay by bank transfer and upload the receipt.']);
        }

        $payment = $this->createPayment($user, $plan, $gateway, $quote['total'], $quote['discount'], $quote['promo']);
        try {
            $payment->update(['checkout_url' => $this->chapa->initialize($payment)]);
        } catch (Throwable $e) {
            Log::error($e->getMessage(), ['payment' => $payment->id]);
            $payment->update(['status' => 'failed']);
            throw ValidationException::withMessages(['gateway' => 'We could not reach the payment provider. Please try again in a moment.']);
        }

        return $payment;
    }

    private function startBankTransfer(User $user, Plan $plan, array $quote, array $bank): Payment
    {
        /** @var UploadedFile $file */
        $file = $bank['receipt'];
        $reference = strtoupper(trim((string) ($bank['reference'] ?? '')));

        if ($reference !== '' && Payment::query()->where('reference', $reference)->whereIn('status', ['pending', 'approved'])->exists()) {
            throw ValidationException::withMessages(['reference' => 'This transaction reference has already been submitted.']);
        }

        $payment = $this->createPayment($user, $plan, 'bank_transfer', $quote['total'], $quote['discount'], $quote['promo']);
        $payment->update([
            'reference' => $reference ?: null,
            'bank_name' => $bank['bank_name'] ?? null,
            'receipt_path' => $this->storeReceipt($file, $payment),
        ]);

        return $payment;
    }

    /** Receipts are private (local disk) and re-encoded to strip metadata and shrink size. */
    private function storeReceipt(UploadedFile $file, Payment $payment): string
    {
        $path = 'receipts/'.now()->format('Y/m').'/'.$payment->tx_ref;

        if ($file->getMimeType() === 'application/pdf') {
            return $file->storeAs(dirname($path), basename($path).'.pdf', 'local');
        }

        $image = Image::read($file->getRealPath())->scaleDown(1600, 1600)->toJpeg(80);
        \Illuminate\Support\Facades\Storage::disk('local')->put($path.'.jpg', (string) $image);

        return $path.'.jpg';
    }

    private function createPayment(User $user, Plan $plan, string $gateway, float $amount, float $discount, ?PromoCode $promo): Payment
    {
        return $user->payments()->create([
            'plan_id' => $plan->id,
            'gateway' => $gateway,
            'amount' => $amount,
            'discount' => $discount,
            'currency' => $plan->currency,
            'status' => 'pending',
            'tx_ref' => 'MNX-'.now()->format('ymd').'-'.Str::upper(Str::random(10)),
            'promo_code_id' => $promo?->id,
        ]);
    }

    /** Called from the webhook and the return URL. Approves only after Chapa confirms. */
    public function confirmOnline(Payment $payment): Payment
    {
        if ($payment->status !== 'pending' || ! in_array($payment->gateway, ['chapa', 'telebirr', 'cbe_birr'], true)) {
            return $payment;
        }

        $result = $this->chapa->verify($payment->tx_ref);
        $payment->update(['gateway_payload' => $result['raw'], 'reference' => $result['reference'] ?? $payment->reference]);

        if ($result['paid'] && $result['amount'] !== null && $result['amount'] + 0.01 >= (float) $payment->amount) {
            $this->subscriptions->approvePayment($payment);
        }

        return $payment->refresh();
    }
}
