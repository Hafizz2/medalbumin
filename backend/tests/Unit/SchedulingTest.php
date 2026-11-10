<?php

namespace Tests\Unit;

use App\Services\FlashcardScheduler;
use App\Services\MatchPredictor;
use App\Services\OtpService;
use PHPUnit\Framework\TestCase;

class SchedulingTest extends TestCase
{
    public function test_new_card_steps_match_the_design(): void
    {
        $s = new FlashcardScheduler;
        $this->assertSame(1, $s->next(null, 1)['interval_minutes']);
        $this->assertSame(8, $s->next(null, 2)['interval_minutes']);
        $this->assertSame(1440, $s->next(null, 3)['interval_minutes']);
        $this->assertSame(5760, $s->next(null, 4)['interval_minutes']);
    }

    public function test_intervals_grow_and_lapse_resets(): void
    {
        $s = new FlashcardScheduler;
        $state = (object) $s->next(null, 3);
        $second = (object) $s->next($state, 3);
        $this->assertGreaterThan($state->interval_minutes, $second->interval_minutes);
        $lapse = $s->next($second, 1);
        $this->assertSame(0, $lapse['repetitions']);
        $this->assertSame(1, $lapse['lapses']);
    }

    public function test_match_chance_curve(): void
    {
        $this->assertSame(50, MatchPredictor::chance(74, 74));
        $this->assertGreaterThanOrEqual(70, MatchPredictor::chance(77.7, 74));
        $this->assertSame('reach', MatchPredictor::tier(MatchPredictor::chance(70, 80)));
        $this->assertSame(0, MatchPredictor::overallChance([]));
        $this->assertSame(91, MatchPredictor::overallChance([70, 70]));
    }

    public function test_phone_normalisation(): void
    {
        $this->assertSame('+251912345678', OtpService::normalizePhone('0912345678'));
        $this->assertSame('+251912345678', OtpService::normalizePhone('+251 91 234 5678'));
        $this->assertSame('+251712345678', OtpService::normalizePhone('712345678'));
        $this->assertNull(OtpService::normalizePhone('0812345678'));
        $this->assertNull(OtpService::normalizePhone('12345'));
    }
}
