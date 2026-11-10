<?php

namespace App\Livewire\Admin;

use App\Models\Payment;
use App\Models\Question;
use App\Models\QuestionReport;
use App\Models\StudentVerification;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;

#[Title('Dashboard')]
class Dashboard extends AdminPage
{
    public string $search = '';

    public function render()
    {
        return view('livewire.admin.dashboard', [
            'kpis' => $this->kpis(),
            'weeks' => $this->subscriptionsByWeek(),
            'attention' => $this->attention(),
            'languages' => $this->languages(),
            'hardest' => $this->hardest(),
            'results' => $this->searchResults(),
        ]);
    }

    private function kpis(): array
    {
        $students = User::query()->students()->where('is_guest', false);
        $total = (clone $students)->count();
        $newThisMonth = (clone $students)->where('created_at', '>=', now()->startOfMonth())->count();

        $activeNow = Subscription::query()->current()->distinct()->count('user_id');
        $activeMonthAgo = Subscription::query()->where('status', 'active')
            ->where('starts_at', '<=', now()->subMonth())->where('ends_at', '>', now()->subMonth())->distinct()->count('user_id');
        $subDelta = $activeMonthAgo ? round(($activeNow - $activeMonthAgo) / $activeMonthAgo * 100, 1) : null;

        $revenue = Payment::query()->where('status', 'approved')->where('paid_at', '>=', now()->startOfMonth());
        $revenueTotal = (float) (clone $revenue)->sum('amount');
        $topGateway = (clone $revenue)->groupBy('gateway')->selectRaw('gateway, SUM(amount) s')->orderByDesc('s')->first();

        $answeredToday = (int) DB::table('daily_activities')->where('date', today()->toDateString())->sum('questions_answered');
        $peak = DB::table('quiz_answers')->where('answered_at', '>=', today())
            ->selectRaw((DB::getDriverName() === 'sqlite' ? "CAST(strftime('%H', answered_at) AS INTEGER)" : 'HOUR(answered_at)').' h, COUNT(*) n')
            ->groupBy('h')->orderByDesc('n')->first();

        return [
            ['Total students', number_format($total), '+'.number_format($newThisMonth).' this month', true],
            ['Active subscribers', number_format($activeNow), $subDelta !== null ? ($subDelta >= 0 ? '+' : '').$subDelta.'% vs last month' : 'First month', false],
            ['Revenue this month', 'ETB '.$this->short($revenueTotal), $topGateway && $revenueTotal > 0 ? round($topGateway->s / $revenueTotal * 100).'% via '.(Payment::GATEWAYS[$topGateway->gateway] ?? $topGateway->gateway) : 'No payments yet', false],
            ['Questions answered today', number_format($answeredToday), $peak ? 'Peak at '.date('g A', mktime((int) $peak->h)) : 'No activity yet', false],
        ];
    }

    private function short(float $n): string
    {
        return match (true) {
            $n >= 1_000_000 => round($n / 1_000_000, 1).'M',
            $n >= 1_000 => round($n / 1_000).'K',
            default => number_format($n),
        };
    }

    private function subscriptionsByWeek(): array
    {
        $start = now()->startOfWeek()->subWeeks(11);
        $rows = Subscription::query()->where('source', 'payment')->where('created_at', '>=', $start)->get(['created_at'])
            ->groupBy(fn ($s) => $s->created_at->copy()->startOfWeek()->toDateString())->map->count();

        return collect(range(0, 11))->map(function ($i) use ($start, $rows) {
            $wk = $start->copy()->addWeeks($i);

            return ['label' => $wk->format('M j'), 'value' => (int) ($rows[$wk->toDateString()] ?? 0)];
        })->all();
    }

    private function attention(): array
    {
        $oldest = Payment::query()->where('status', 'pending')->where('gateway', 'bank_transfer')->oldest()->first();
        $reports = QuestionReport::query()->where('status', 'open');
        $review = Question::query()->where('status', 'in_review');

        return [
            [
                'Payment receipts to approve',
                $oldest ? 'Oldest waiting '.$oldest->created_at->diffForHumans(null, true) : 'All caught up',
                Payment::query()->where('status', 'pending')->where('gateway', 'bank_transfer')->count(),
                route('admin.payments'), true,
            ],
            [
                'Reported questions',
                (clone $reports)->where('reason', 'wrong_key')->count().' marked “wrong answer key”',
                (clone $reports)->count(),
                route('admin.reports'), false,
            ],
            [
                'Drafts awaiting review',
                'From '.(clone $review)->distinct()->count('author_id').' content editors',
                (clone $review)->count(),
                route('admin.questions', ['status' => 'in_review']), false,
            ],
            [
                'Student ID verifications',
                'For discounted plans',
                StudentVerification::query()->where('status', 'pending')->count(),
                route('admin.reports', ['tab' => 'ids']), false,
            ],
        ];
    }

    private function languages(): array
    {
        $published = Question::query()->published();
        $total = (clone $published)->count();
        $out = [];
        foreach (\App\Models\Language::cached() as $l) {
            $label = $l->native_name.($l->native_name !== $l->name ? ' '.$l->name : '').($l->is_active ? '' : ' (draft)');
            $out[] = [$label, $l->is_default ? $total : (clone $published)->where('translation_status->'.$l->code, 'published')->count()];
        }

        return array_map(fn ($r) => [$r[0], $r[1], $total ? round($r[1] / $total * 100) : 0], $out);
    }

    private function hardest()
    {
        $rows = DB::table('quiz_answers')->where('answered_at', '>=', now()->subWeek())->whereNotNull('selected_option')
            ->groupBy('question_id')->havingRaw('COUNT(*) >= 10')
            ->selectRaw('question_id, AVG(is_correct) * 100 pct')->orderBy('pct')->limit(4)->get();
        $questions = Question::query()->with(['department', 'subcategory', 'paper'])->whereIn('id', $rows->pluck('question_id'))->get()->keyBy('id');

        return $rows->map(fn ($r) => [
            'question' => $questions[$r->question_id] ?? null,
            'pct' => (int) round($r->pct),
        ])->filter(fn ($r) => $r['question']);
    }

    private function searchResults(): array
    {
        $s = trim($this->search);
        if (mb_strlen($s) < 2) {
            return [];
        }
        $like = '%'.$s.'%';
        $out = [];
        foreach (User::query()->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('phone', 'like', $like)->orWhere('email', 'like', $like))->limit(5)->get() as $u) {
            $out[] = ['Student', $u->name ?: $u->phone, $u->phone, route('admin.users', ['search' => $u->phone ?: $u->email])];
        }
        foreach (Question::query()->where(fn ($q) => $q->where('code', 'like', $like)->orWhere('stem', 'like', $like))->limit(5)->get() as $q) {
            $out[] = ['Question', $q->code, mb_strimwidth(strip_tags($q->getTranslation('stem', 'en')), 0, 80, '…'), route('admin.questions', ['selected' => $q->id])];
        }
        foreach (Payment::query()->with('user')->where(fn ($q) => $q->where('reference', 'like', $like)->orWhere('tx_ref', 'like', $like))->limit(5)->get() as $p) {
            $out[] = ['Receipt', $p->reference ?: $p->tx_ref, $p->user?->name, route('admin.payments', ['selected' => $p->id])];
        }

        return $out;
    }
}
