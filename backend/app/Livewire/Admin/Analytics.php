<?php

namespace App\Livewire\Admin;

use App\Models\Payment;
use App\Models\Question;
use App\Models\User;
use App\Services\AnalyticsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Spatie\SimpleExcel\SimpleExcelWriter;

#[Title('Analytics')]
class Analytics extends AdminPage
{
    protected ?string $permission = 'view analytics';

    public string $quality = 'all';

    public function recompute(AnalyticsService $analytics): void
    {
        $n = $analytics->refreshQuestionQuality();
        $this->toast("Quality updated for {$n} questions.");
    }

    public function exportUsers()
    {
        $path = storage_path('app/private/exports/students-'.now()->format('Ymd-His').'.csv');
        @mkdir(dirname($path), 0775, true);
        $writer = SimpleExcelWriter::create($path);
        User::query()->students()->where('is_guest', false)->with('university')->chunk(500, function ($users) use ($writer) {
            foreach ($users as $u) {
                $writer->addRow([
                    'ID' => $u->id, 'Name' => $u->name, 'Phone' => $u->phone, 'Email' => $u->email, 'University' => $u->university?->name,
                    'Program' => $u->program, 'Year' => $u->study_year, 'Target exam' => $u->target_exam, 'Status' => $u->status,
                    'Joined' => $u->created_at->toDateString(), 'Last active' => $u->last_active_at?->toDateTimeString(),
                ]);
            }
        });
        $writer->close();

        return response()->download($path)->deleteFileAfterSend();
    }

    public function exportPayments()
    {
        return (new Payments)->export();
    }

    public function monthlyReport(AnalyticsService $analytics)
    {
        $from = now()->subMonthNoOverflow()->startOfMonth();
        $to = $from->copy()->endOfMonth();
        $payments = Payment::query()->where('status', 'approved')->whereBetween('paid_at', [$from, $to]);

        $data = [
            'from' => $from,
            'to' => $to,
            'newStudents' => User::query()->students()->where('is_guest', false)->whereBetween('created_at', [$from, $to])->count(),
            'revenue' => (float) (clone $payments)->sum('amount'),
            'paymentsCount' => (clone $payments)->count(),
            'byGateway' => (clone $payments)->groupBy('gateway')->selectRaw('gateway, SUM(amount) s, COUNT(*) n')->get(),
            'answered' => (int) DB::table('daily_activities')->whereBetween('date', [$from, $to])->sum('questions_answered'),
            'activeStudents' => DB::table('daily_activities')->whereBetween('date', [$from, $to])->distinct()->count('user_id'),
            'kpis' => $analytics->kpis(),
            'funnel' => $analytics->funnel(),
            'flagged' => Question::query()->whereIn('quality_flag', ['ambiguous', 'too_hard'])->orderByDesc('attempts_count')->limit(10)->get(),
        ];

        return response()->streamDownload(
            fn () => print(Pdf::loadView('pdf.monthly-report', $data)->output()),
            'medalbumin-report-'.$from->format('Y-m').'.pdf',
        );
    }

    public function render(AnalyticsService $analytics)
    {
        return view('livewire.admin.analytics', [
            'kpis' => $analytics->kpis(),
            'dau' => $analytics->dau(),
            'funnel' => $analytics->funnel(),
            'rows' => $analytics->qualityRows($this->quality),
        ]);
    }
}
