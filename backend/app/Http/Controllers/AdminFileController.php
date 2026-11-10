<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\StudentVerification;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Receipts and student IDs live on the private disk; staff view them through here. */
class AdminFileController extends Controller
{
    public function receipt(Payment $payment): StreamedResponse
    {
        abort_unless(auth()->user()->can('approve payments'), 403);
        abort_unless($payment->receipt_path && Storage::disk('local')->exists($payment->receipt_path), 404);

        return Storage::disk('local')->response($payment->receipt_path, headers: ['Cache-Control' => 'private, max-age=600']);
    }

    public function verification(StudentVerification $verification): StreamedResponse
    {
        abort_unless(auth()->user()->can('approve payments') || auth()->user()->can('manage users') || auth()->user()->can('edit content'), 403);
        abort_unless(Storage::disk('local')->exists($verification->image_path), 404);

        return Storage::disk('local')->response($verification->image_path, headers: ['Cache-Control' => 'private, max-age=600']);
    }
}
