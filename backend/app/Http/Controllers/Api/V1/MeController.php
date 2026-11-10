<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Language;
use App\Models\Referral;
use App\Models\Setting;
use App\Models\StudentVerification;
use App\Services\AccountDeletion;
use App\Services\AuthService;
use App\Services\ExpoPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Intervention\Image\Laravel\Facades\Image;

class MeController extends Controller
{
    public function show(Request $request): UserResource
    {
        return new UserResource($request->user()->load('university'));
    }

    public function update(Request $request): UserResource
    {
        $data = $request->validate([
            'name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'email' => ['sometimes', 'nullable', 'email', 'max:190', Rule::unique('users', 'email')->ignore($request->user()->id)],
            'gender' => ['sometimes', 'nullable', 'in:female,male'],
            'university_id' => ['sometimes', 'nullable', 'exists:universities,id'],
            'program' => ['sometimes', 'nullable', 'string', 'max:60'],
            'study_year' => ['sometimes', 'nullable', 'string', 'max:30'],
            'target_exam' => ['sometimes', 'nullable', 'in:exit,qualification,licensing,residency'],
            'exam_date' => ['sometimes', 'nullable', 'date', 'after:today'],
            'target_score' => ['sometimes', 'integer', 'between:40,100'],
            'app_locale' => ['sometimes', Rule::in(Language::activeCodes())],
            'content_locale' => ['sometimes', Rule::in(Language::activeCodes())],
            'dark_mode' => ['sometimes', 'boolean'],
            'daily_reminder' => ['sometimes', 'boolean'],
            'reminder_time' => ['sometimes', 'date_format:H:i'],
        ]);

        $request->user()->fill($data)->save();

        return new UserResource($request->user()->fresh('university'));
    }

    public function avatar(Request $request): UserResource
    {
        $request->validate(['avatar' => ['required', 'image', 'max:5120']]);
        $user = $request->user();

        $path = 'avatars/'.$user->id.'-'.now()->timestamp.'.jpg';
        Storage::disk('public')->put($path, (string) Image::read($request->file('avatar')->getRealPath())->cover(400, 400)->toJpeg(82));
        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }
        $user->forceFill(['avatar_path' => $path])->save();

        return new UserResource($user->fresh('university'));
    }

    /**
     * Permanently deletes the account and personal data (App Store 5.1.1(v) / Google Play requirement).
     */
    public function destroy(Request $request, AccountDeletion $deletion): JsonResponse
    {
        $deletion->delete($request->user());

        return response()->json(['message' => 'Your account has been deleted.']);
    }

    public function devices(Request $request): JsonResponse
    {
        $current = $request->user()->currentDeviceId();

        return response()->json([
            'limit' => app(AuthService::class)->maxDevices(),
            'devices' => $request->user()->devices()->active()->latest('last_seen_at')->get()->map(fn ($d) => [
                'id' => $d->id,
                'name' => $d->name,
                'platform' => $d->platform,
                'last_seen_at' => $d->last_seen_at?->toIso8601String(),
                'current' => $current === $d->id,
            ]),
        ]);
    }

    public function revokeDevice(Request $request, int $device): JsonResponse
    {
        $model = $request->user()->devices()->active()->findOrFail($device);
        app(AuthService::class)->revokeDevice($model);

        return response()->json(['message' => 'Device signed out.']);
    }

    public function pushToken(Request $request): JsonResponse
    {
        $data = $request->validate(['push_token' => ['nullable', 'string', 'max:255']]);
        $deviceId = $request->user()->currentDeviceId();
        abort_unless($deviceId, 422, 'Unknown device.');

        $token = $data['push_token'] ?? null;
        $request->user()->devices()->whereKey($deviceId)->update([
            'push_token' => ExpoPushService::isExpoToken($token) ? $token : null,
        ]);

        return response()->json(['message' => 'Saved.']);
    }

    public function referral(Request $request): JsonResponse
    {
        $user = $request->user();
        $rows = Referral::query()->where('referrer_id', $user->id);

        return response()->json([
            'code' => $user->referral_code,
            'share_text' => "Study for your medical exams with MedAlbumin. Use my code {$user->referral_code} — we both get 1 free month when you subscribe.",
            'reward_days' => (int) Setting::get('referral_reward_days', 30),
            'max_rewards' => (int) Setting::get('referral_max_rewards', 6),
            'joined' => (clone $rows)->count(),
            'rewarded' => (clone $rows)->where('status', 'rewarded')->count(),
        ]);
    }

    /** Student ID upload for discounted plans. */
    public function verifyStudent(Request $request): JsonResponse
    {
        $data = $request->validate([
            'student_id_number' => ['nullable', 'string', 'max:60'],
            'image' => ['required', 'image', 'max:8192'],
        ]);
        $user = $request->user();
        abort_if(StudentVerification::query()->where('user_id', $user->id)->where('status', 'pending')->exists(), 422, 'Your student ID is already waiting for review.');

        $path = 'student-ids/'.$user->id.'-'.now()->timestamp.'.jpg';
        Storage::disk('local')->put($path, (string) Image::read($request->file('image')->getRealPath())->scaleDown(1600, 1600)->toJpeg(80));

        StudentVerification::query()->create([
            'user_id' => $user->id,
            'student_id_number' => $data['student_id_number'] ?? null,
            'image_path' => $path,
        ]);

        return response()->json(['message' => 'Thanks! We will review your student ID within a day.'], 201);
    }

    public function verificationStatus(Request $request): JsonResponse
    {
        $v = StudentVerification::query()->where('user_id', $request->user()->id)->latest()->first();

        return response()->json(['status' => $v?->status, 'note' => $v?->note]);
    }
}
