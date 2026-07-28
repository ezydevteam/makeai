<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\System\SystemController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Timestamps sent to the admin panel have to carry their zone.
 *
 * They are stored with now()->toDateTimeString() — "2026-07-28 15:39:00", no offset —
 * and JavaScript's new Date() reads a string in that shape as BROWSER-local. So the UTC
 * wall clock reached the screen unconverted: a site six hours ahead of UTC saw 3:39 PM
 * for something that happened at 9:39 PM. useDateFormat then rebased an instant that was
 * already wrong, which is why having the timezone plumbing in place did not help.
 *
 * ISO 8601 carries the offset, so the browser parses the right instant and the site-zone
 * rebasing lands where it should.
 */
class AdminTimestampInstantTest extends TestCase
{
    use RefreshDatabase;

    private function asInstant(?string $stored): ?string
    {
        return (new \ReflectionMethod(SystemController::class, 'asInstant'))
            ->invoke(app(SystemController::class), $stored);
    }

    public function test_a_stored_wall_clock_becomes_an_instant_with_an_offset(): void
    {
        $result = $this->asInstant('2026-07-28 15:39:00');

        $this->assertNotNull($result);
        $this->assertMatchesRegularExpression('/^2026-07-28T15:39:00(\+00:00|Z)$/', $result,
            'the stored value is a UTC wall clock and must be emitted as that same instant');
    }

    /**
     * The digits must not move. Only the offset is added — reinterpreting the stored
     * value in another zone would shift every timestamp in the panel.
     */
    public function test_the_instant_is_not_shifted_only_labelled(): void
    {
        $parsed = \Illuminate\Support\Carbon::parse($this->asInstant('2026-07-28 15:39:00'));

        $this->assertSame('2026-07-28 15:39:00', $parsed->utc()->toDateTimeString());
    }

    /** An already-ISO value keeps its own offset rather than being re-stamped as UTC. */
    public function test_an_iso_value_keeps_its_own_offset(): void
    {
        $parsed = \Illuminate\Support\Carbon::parse($this->asInstant('2026-07-28T15:39:00+06:00'));

        $this->assertSame('2026-07-28 09:39:00', $parsed->utc()->toDateTimeString());
    }

    /** Never run, never rolled back: null must stay null rather than becoming "now". */
    public function test_blank_values_stay_null(): void
    {
        $this->assertNull($this->asInstant(null));
        $this->assertNull($this->asInstant(''));
    }

    /** A corrupt setting must not take the System pages down. */
    public function test_an_unparseable_value_is_returned_as_is(): void
    {
        $this->assertSame('not a date at all', $this->asInstant('not a date at all'));
    }
}
