<?php

namespace App\Http\Resources;

use App\Models\Language;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A question as the student sees it. The answer key and explanation are only
 * included when `reveal()` was called (after answering in practice mode,
 * in review, or for timed sessions once finished).
 *
 * @mixin Question
 */
class QuestionResource extends JsonResource
{
    private bool $reveal = false;

    private ?array $optionOrder = null;

    private ?array $userState = null;

    public function reveal(bool $reveal = true): static
    {
        $this->reveal = $reveal;

        return $this;
    }

    public function withOptionOrder(?array $order): static
    {
        $this->optionOrder = $order;

        return $this;
    }

    /** @param array{bookmarked?:bool, selected?:?string} $state */
    public function withUserState(array $state): static
    {
        $this->userState = $state;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $loc = $this->displayLocale(app()->getLocale());
        $options = $this->options->keyBy('key');
        $keys = $this->optionOrder ?: $options->keys()->all();
        $totalPicks = max(1, $options->sum('pick_count'));

        return [
            'id' => $this->id,
            'code' => $this->code,
            'locale' => $loc,
            'available_locales' => array_values(array_filter(Language::activeCodes(), fn ($l) => $this->translationState($l) === 'ok')),
            'department' => $this->department ? ['id' => $this->department->id, 'name' => $this->department->name, 'short_name' => $this->department->short_name] : null,
            'subcategory' => $this->subcategory ? ['id' => $this->subcategory->id, 'name' => $this->subcategory->name] : null,
            'breadcrumb' => $this->breadcrumb(),
            'source' => $this->sourceLabel(),
            'difficulty' => $this->difficulty,
            'stem' => $this->getTranslation('stem', $loc),
            'image' => $this->image_path ? ['url' => $this->imageUrl(), 'caption' => $this->image_caption, 'type' => $this->image_type] : null,
            'options' => collect($keys)->filter(fn ($k) => $options->has($k))->values()->map(fn ($k, $i) => [
                'key' => $k,
                // Shown letter follows the (possibly shuffled) order; `key` is what gets submitted.
                'label' => chr(65 + $i),
                'text' => $options[$k]->getTranslation('text', $loc),
                'pick_pct' => $this->reveal ? (int) round($options[$k]->pick_count / $totalPicks * 100) : null,
            ]),
            'comments_count' => $this->comments_count,
            'bookmarked' => $this->userState['bookmarked'] ?? null,
            'selected' => $this->userState['selected'] ?? null,
            $this->mergeWhen($this->reveal, fn () => [
                'correct_option' => $this->correct_option,
                'explanation' => $this->getTranslation('explanation', $loc),
                'explanations' => collect(Language::activeCodes())
                    ->filter(fn ($l) => $this->translationState($l) === 'ok')
                    ->mapWithKeys(fn ($l) => [$l => $this->getTranslation('explanation', $l, false)])
                    ->filter(),
                'correct_pct' => $this->correctPct(),
            ]),
        ];
    }
}
