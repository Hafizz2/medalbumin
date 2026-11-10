<?php

namespace App\Console\Commands;

use App\Models\Language;
use App\Services\UiTranslationService;
use Illuminate\Console\Command;

/**
 * Loads app interface text from a JSON file, e.g. the mobile app's English source:
 *   php artisan medalbumin:ui-import en ../mobile/src/i18n/en.json --prune
 */
class ImportUiStrings extends Command
{
    protected $signature = 'medalbumin:ui-import {locale} {path} {--prune : For English, remove keys that are no longer in the file}';

    protected $description = 'Import app interface text (JSON) for a language';

    public function handle(UiTranslationService $ui): int
    {
        $locale = $this->argument('locale');
        if (! in_array($locale, Language::contentCodes(), true)) {
            $this->error("Language {$locale} is not configured. Add it in Admin → Languages first.");

            return self::FAILURE;
        }
        $data = json_decode((string) @file_get_contents($this->argument('path')), true);
        if (! is_array($data)) {
            $this->error('Could not read a JSON object from '.$this->argument('path'));

            return self::FAILURE;
        }

        $result = $ui->import($locale, $data, null, (bool) $this->option('prune'));
        $this->info("{$result['saved']} saved, {$result['removed']} removed, ".count($result['skipped']).' skipped.');
        foreach (array_slice($result['skipped'], 0, 20, true) as $key => $why) {
            $this->line("  {$key}: {$why}");
        }

        return self::SUCCESS;
    }
}
