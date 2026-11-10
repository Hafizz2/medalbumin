<?php

namespace App\Services;

use App\Models\StudentVerification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Permanently deletes a student's personal data (App Store 5.1.1(v), Google Play data deletion).
 * Payment records are kept for accounting, no longer linked to a name or phone number.
 * Discussion posts stay readable as "Deleted user".
 */
class AccountDeletion
{
    public function delete(User $user): void
    {
        DB::transaction(function () use ($user) {
            if ($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }
            $user->tokens()->delete();
            $user->devices()->delete();
            $user->userNotes()->delete();
            $user->bookmarks()->detach();
            DB::table('flashcard_reviews')->where('user_id', $user->id)->delete();
            DB::table('match_choices')->where('user_id', $user->id)->delete();
            DB::table('match_profiles')->where('user_id', $user->id)->delete();
            DB::table('study_plans')->where('user_id', $user->id)->delete();
            DB::table('notifications')->where('notifiable_type', $user->getMorphClass())->where('notifiable_id', $user->id)->delete();
            StudentVerification::query()->where('user_id', $user->id)->get()->each(function ($v) {
                Storage::disk('local')->delete($v->image_path);
                $v->delete();
            });
            foreach ($user->payments()->whereNotNull('receipt_path')->get() as $p) {
                Storage::disk('local')->delete($p->receipt_path);
                $p->forceFill(['receipt_path' => null])->save();
            }

            $user->forceFill([
                'name' => 'Deleted user',
                'phone' => null,
                'email' => null,
                'google_id' => null,
                'apple_id' => null,
                'telegram_id' => null,
                'avatar_path' => null,
                'password' => null,
                'referral_code' => null,
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'status' => 'suspended',
            ])->save();
            $user->delete();
        });
    }
}
