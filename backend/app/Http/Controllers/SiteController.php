<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Note;
use App\Models\Paper;
use App\Models\Plan;
use App\Models\Question;
use App\Models\University;
use App\Models\User;
use App\Models\Video;
use App\Services\AccountDeletion;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SiteController extends Controller
{
    public function home(): View
    {
        $data = Cache::remember('site.home', now()->addMinutes(15), fn () => [
            'questions' => Question::query()->published()->count(),
            'papers' => Paper::query()->published()->count(),
            'notes' => Note::query()->published()->count(),
            'videos' => Video::query()->published()->count(),
            'students' => User::query()->students()->where('is_guest', false)->count(),
            'universities' => University::query()->whereHas('users')->count(),
            'departments' => Department::query()->active()->withCount(['questions' => fn ($q) => $q->published()])->get()
                ->map(fn ($d) => ['name' => $d->name, 'icon' => $d->icon, 'bg' => $d->color_bg, 'ink' => $d->color_ink, 'questions' => $d->questions_count])->all(),
            'plans' => Plan::query()->active()->where('price', '>', 0)->get()
                ->map(fn ($p) => ['name' => $p->name, 'note' => $p->note ?: $p->durationLabel(), 'badge' => $p->badge, 'price' => (float) $p->price])->all(),
            'years' => Paper::query()->published()->whereNotNull('year')->selectRaw('MIN(year) a, MAX(year) b')->first()?->only(['a', 'b']),
        ]);

        return view('site.home', $data);
    }

    /** Public pricing page: plans from Admin → Payments, and what Free vs Premium includes. */
    public function pricing(): View
    {
        $data = Cache::remember('site.pricing.'.app()->getLocale(), now()->addMinutes(10), function () {
            $q = Question::query()->published();
            $papers = Paper::query()->published();

            return [
                'compare' => [
                    ['Exam questions with explanations', number_format((clone $q)->where('is_free', true)->count()), number_format((clone $q)->count())],
                    ['Past exam papers', number_format((clone $papers)->where('is_premium', false)->count()), number_format((clone $papers)->count())],
                    ['Official notes', number_format(Note::query()->published()->where('visibility', 'all')->count()), number_format(Note::query()->published()->count())],
                    ['Video lessons', number_format(Video::query()->published()->where('access', 'free')->count()), number_format(Video::query()->published()->count())],
                    ['Timed mock exams', 'Free questions', 'Unlimited'],
                    ['National grand mocks & leaderboard', 'Free mocks', 'All mocks'],
                    ['Flashcards with spaced repetition', true, true],
                    ['Progress tracking & study plan', true, true],
                    ['Residency match predictor', true, true],
                    ['Discussion & faculty answers', true, true],
                ],
                'freeQuestions' => (clone $q)->where('is_free', true)->count(),
            ];
        });

        return view('site.pricing', $data + [
            'plans' => Plan::query()->active()->where('price', '>', 0)->get(),
            'online' => (bool) config('services.chapa.secret_key'),
        ]);
    }

    public function privacy(): View
    {
        return view('site.privacy');
    }

    public function terms(): View
    {
        return view('site.terms');
    }

    public function deleteAccount(Request $request): View
    {
        return view('site.delete-account', ['phone' => $request->session()->get('delete.phone'), 'done' => session('deleted')]);
    }

    public function deleteAccountCode(Request $request, OtpService $otp): RedirectResponse
    {
        $request->validate(['phone' => ['required', 'string', 'max:20']]);
        $phone = OtpService::normalizePhone($request->input('phone'));
        if (! $phone || ! User::query()->where('phone', $phone)->exists()) {
            throw ValidationException::withMessages(['phone' => 'No MedAlbumin account uses this phone number.']);
        }
        $otp->send($phone, $request->ip());
        $request->session()->put('delete.phone', $phone);

        return back()->with('status', 'We sent a 6-digit code by SMS.');
    }

    public function deleteAccountConfirm(Request $request, OtpService $otp, AccountDeletion $deletion): RedirectResponse
    {
        $request->validate(['code' => ['required', 'digits:6'], 'confirm' => ['accepted']], ['confirm.accepted' => 'Tick the box to confirm.']);
        $phone = $request->session()->get('delete.phone') ?? throw ValidationException::withMessages(['code' => 'Start again by entering your phone number.']);

        $code = $otp->verify($phone, $request->input('code'));
        $user = User::query()->where('phone', $phone)->firstOrFail();
        $otp->consume($code);
        $deletion->delete($user);
        $request->session()->forget('delete.phone');

        return redirect()->route('legal.delete-account')->with('deleted', true);
    }

    public function sitemap(): Response
    {
        $urls = [route('home'), route('pricing'), route('legal.privacy'), route('legal.terms'), route('legal.delete-account')];
        $xml = '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($urls as $u) {
            $xml .= '<url><loc>'.e($u).'</loc><changefreq>weekly</changefreq></url>';
        }

        return response($xml.'</urlset>', 200, ['Content-Type' => 'application/xml']);
    }
}
