<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\Payments\ChapaGateway;
use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class PaymentWebhookController extends Controller
{
    public function __construct(private readonly PaymentService $payments, private readonly ChapaGateway $chapa) {}

    /** Chapa calls this after a payment. We always re-verify with Chapa's API before approving. */
    public function chapa(Request $request): JsonResponse
    {
        $signature = $request->header('x-chapa-signature') ?: $request->header('chapa-signature');
        if (config('services.chapa.webhook_secret') && ! $this->chapa->validWebhookSignature($request->getContent(), $signature)) {
            Log::warning('Chapa webhook with invalid signature', ['ip' => $request->ip()]);

            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $txRef = $request->input('tx_ref') ?? $request->input('trx_ref');
        $payment = $txRef ? Payment::query()->where('tx_ref', $txRef)->first() : null;
        if ($payment) {
            $this->payments->confirmOnline($payment);
        }

        return response()->json(['received' => true]);
    }

    /** Page the student lands on after Chapa checkout. */
    public function return(Request $request): View|RedirectResponse
    {
        $payment = Payment::query()->where('tx_ref', (string) $request->query('tx_ref'))->first();
        if ($payment && $request->user()?->id === $payment->user_id) {
            return redirect()->route('account.payment-returned', $payment);
        }
        if ($payment) {
            $payment = $this->payments->confirmOnline($payment);
        }

        return view('payments.return', ['payment' => $payment]);
    }
}
