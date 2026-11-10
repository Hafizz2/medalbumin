<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Thrown when an account is already signed in on the maximum number of devices.
 * The app shows the list and lets the student sign one out (replace_device_id).
 */
class DeviceLimitException extends RuntimeException
{
    public function __construct(private readonly Collection $devices, private readonly int $limit)
    {
        parent::__construct("This account is already used on {$limit} devices.");
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage().' Sign out one of them to continue.',
            'code' => 'DEVICE_LIMIT',
            'limit' => $this->limit,
            'devices' => $this->devices->map(fn ($d) => [
                'id' => $d->id,
                'name' => $d->name,
                'platform' => $d->platform,
                'last_seen_at' => $d->last_seen_at?->toIso8601String(),
            ])->values(),
        ], 409);
    }
}
