<?php

namespace App\Livewire\Admin;

use App\Models\MatchPrediction;
use App\Models\MatchResult;
use App\Models\Setting;
use App\Models\Specialty;
use App\Models\University;
use App\Services\MatchPredictor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\WithFileUploads;
use Spatie\SimpleExcel\SimpleExcelReader;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Throwable;

#[Title('Match data')]
class MatchData extends AdminPage
{
    use WithFileUploads;

    protected ?string $permission = 'edit content';

    public ?int $year = null;
    public ?int $specialty = null;
    public ?array $row = null;
    public array $rules = [];
    public $upload;
    public ?int $uploadYear = null;
    public ?array $uploadResult = null;

    public function mount(MatchPredictor $predictor): void
    {
        $this->year = (int) (MatchResult::query()->max('year') ?: now()->year);
        $this->specialty = Specialty::query()->active()->value('id');
        $r = $predictor->rules();
        $this->rules = [
            'female_bonus_pct' => $r['female_bonus_pct'],
            'managerial_bonus_pct' => $r['managerial_bonus_pct'],
            'minimum_cutoff' => $r['minimum_cutoff'],
            'max_specialties' => $r['max_specialties'],
            'max_universities' => $r['max_universities'],
            'years' => $r['years'],
            'audience' => $r['audience'],
            'timeline' => implode("\n", $r['timeline']),
            'timeline_step' => $r['timeline_step'],
        ];
        $this->uploadYear = $this->year;
    }

    public function editRow(?int $id = null): void
    {
        $m = $id ? MatchResult::query()->findOrFail($id) : null;
        $this->row = ['id' => $m?->id, 'university_id' => $m?->university_id, 'seats' => $m?->seats, 'applicants' => $m?->applicants, 'accepted' => $m?->accepted, 'cutoff' => $m?->cutoff];
    }

    public function saveRow(): void
    {
        $this->validate([
            'row.university_id' => ['required', 'exists:universities,id'],
            'row.seats' => ['required', 'integer', 'min:0', 'max:5000'],
            'row.applicants' => ['nullable', 'integer', 'min:0', 'max:50000'],
            'row.accepted' => ['nullable', 'integer', 'min:0', 'max:5000'],
            'row.cutoff' => ['required', 'numeric', 'between:0,100'],
        ], [], ['row.university_id' => 'university', 'row.cutoff' => 'cut-off']);
        MatchResult::query()->updateOrCreate(
            ['year' => $this->year, 'specialty_id' => $this->specialty, 'university_id' => $this->row['university_id']],
            ['seats' => $this->row['seats'], 'applicants' => $this->row['applicants'] ?: 0, 'accepted' => $this->row['accepted'] ?: 0, 'cutoff' => $this->row['cutoff']],
        );
        $this->row = null;
        $this->toast('Saved.');
    }

    public function deleteRow(int $id): void
    {
        MatchResult::query()->whereKey($id)->delete();
    }

    public function saveRules(): void
    {
        $this->validate([
            'rules.female_bonus_pct' => ['required', 'numeric', 'between:0,50'],
            'rules.managerial_bonus_pct' => ['required', 'numeric', 'between:0,50'],
            'rules.minimum_cutoff' => ['nullable', 'numeric', 'between:0,100'],
            'rules.max_specialties' => ['required', 'integer', 'between:1,10'],
            'rules.max_universities' => ['required', 'integer', 'between:1,10'],
            'rules.years' => ['required', 'integer', 'between:1,10'],
            'rules.audience' => ['required', 'in:final_year,all,premium'],
            'rules.timeline' => ['required', 'string', 'max:1000'],
            'rules.timeline_step' => ['required', 'integer', 'min:0'],
        ]);
        foreach (['female_bonus_pct', 'managerial_bonus_pct', 'minimum_cutoff', 'max_specialties', 'max_universities', 'years', 'audience', 'timeline_step'] as $k) {
            Setting::put('match.'.$k, is_numeric($this->rules[$k]) ? $this->rules[$k] + 0 : ($this->rules[$k] ?: null));
        }
        Setting::put('match.timeline', array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $this->rules['timeline'])))));
        activity()->causedBy(auth()->user())->log('Updated match predictor rules');
        $this->toast('Saved — predictions use the new rules now.');
    }

    public function downloadTemplate()
    {
        $path = storage_path('app/private/match-template.csv');
        @mkdir(dirname($path), 0775, true);
        $w = SimpleExcelWriter::create($path);
        $w->addRow(['specialty' => 'IM', 'university' => 'Addis Ababa University', 'seats' => 24, 'applicants' => 132, 'accepted' => 24, 'cutoff' => 78.5]);
        $w->close();

        return response()->download($path, 'medalbumin-match-template.csv');
    }

    /** Upload one year of placement results. Specialty = code or name, university = name or short name. */
    public function import(): void
    {
        $this->validate(['upload' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:10240'], 'uploadYear' => ['required', 'integer', 'between:2000,2100']]);
        $specs = Specialty::query()->get()->flatMap(fn ($s) => [Str::lower($s->code) => $s->id, Str::lower($s->getTranslation('name', 'en')) => $s->id]);
        $unis = University::query()->get()->flatMap(fn ($u) => [Str::lower($u->name) => $u->id, Str::lower((string) $u->short_name) => $u->id])->filter();

        $ok = 0;
        $errors = [];
        $ext = strtolower($this->upload->getClientOriginalExtension()) === 'xlsx' ? 'xlsx' : 'csv';
        DB::transaction(function () use ($specs, $unis, $ext, &$ok, &$errors) {
            foreach (SimpleExcelReader::create($this->upload->getRealPath(), $ext)->headersToSnakeCase()->getRows() as $i => $r) {
                try {
                    $s = $specs[Str::lower(trim((string) ($r['specialty'] ?? '')))] ?? throw new \RuntimeException('Unknown specialty “'.($r['specialty'] ?? '').'”');
                    $u = $unis[Str::lower(trim((string) ($r['university'] ?? '')))] ?? throw new \RuntimeException('Unknown university “'.($r['university'] ?? '').'”');
                    if (! is_numeric($r['cutoff'] ?? null)) {
                        throw new \RuntimeException('Cut-off must be a number');
                    }
                    MatchResult::query()->updateOrCreate(
                        ['year' => $this->uploadYear, 'specialty_id' => $s, 'university_id' => $u],
                        ['seats' => (int) ($r['seats'] ?? 0), 'applicants' => (int) ($r['applicants'] ?? 0), 'accepted' => (int) ($r['accepted'] ?? 0), 'cutoff' => (float) $r['cutoff']],
                    );
                    $ok++;
                } catch (Throwable $e) {
                    $errors[$i + 2] = $e->getMessage();
                }
            }
        });

        $this->uploadResult = ['ok' => $ok, 'errors' => $errors];
        $this->year = $this->uploadYear;
        $this->upload = null;
        activity()->causedBy(auth()->user())->log("Uploaded {$ok} match results for {$this->uploadYear}");
        $this->toast("{$ok} rows imported.");
    }

    public function render()
    {
        $years = MatchResult::query()->distinct()->orderByDesc('year')->pluck('year');
        $lastYear = $years->first();
        $predictionsMonth = MatchPrediction::query()->where('created_at', '>=', now()->startOfMonth())->count();

        return view('livewire.admin.match-data', [
            'years' => $years->isEmpty() ? collect([$this->year]) : $years,
            'specialties' => Specialty::query()->active()->get(),
            'rows' => MatchResult::query()->with('university')->where('year', $this->year)->where('specialty_id', $this->specialty)->orderByDesc('cutoff')->get(),
            'universities' => University::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'stats' => [
                'range' => $years->isEmpty() ? '—' : $years->min().'–'.$years->max(),
                'unis' => MatchResult::query()->distinct()->count('university_id'),
                'specs' => MatchResult::query()->distinct()->count('specialty_id'),
                'predictions' => $predictionsMonth,
            ],
            'lastYear' => $lastYear,
        ]);
    }
}
