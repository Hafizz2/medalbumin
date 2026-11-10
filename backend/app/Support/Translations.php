<?php

namespace App\Support;

use App\Models\Language;

class Translations
{
    /** Trims values, drops empty ones and anything that is not a configured language. */
    public static function clean(?array $values): array
    {
        $codes = array_flip(Language::contentCodes());
        $out = [];
        foreach ((array) $values as $code => $text) {
            $text = is_string($text) ? trim($text) : $text;
            if (isset($codes[$code]) && filled($text)) {
                $out[$code] = $text;
            }
        }

        return $out;
    }

    /** Validation rules for a translatable field: English required, others optional. */
    public static function rules(string $field, int $max = 190, bool $required = true): array
    {
        return [
            $field => ['array'],
            $field.'.en' => [$required ? 'required' : 'nullable', 'string', 'max:'.$max],
            $field.'.*' => ['nullable', 'string', 'max:'.$max],
        ];
    }
}
