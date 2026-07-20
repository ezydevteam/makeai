<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Reference factory.
 *
 * MakeAI ships no factories of its own — a new installation is populated from
 * database/data/data.sql by the installation wizard, not by generated records.
 * This one is included as a working starting point for creating test accounts,
 * and as a template for factories of your own.
 *
 * Usage (tinker, or a seeder):
 *
 *   User::factory()->create(['email' => 'test@example.com']);
 *   User::factory(25)->create();
 *   User::factory()->unverified()->create();
 *
 * Every generated user gets the password "password". Never run this against a
 * production database.
 *
 * ── Why no fake() calls ──────────────────────────────────────────────────────
 *
 * Laravel's factories normally use fake() for names and emails. That helper comes
 * from fakerphp/faker, which is a DEVELOPMENT dependency: this package is built
 * with `composer install --no-dev`, so vendor/fakerphp is not here and any call to
 * fake() would fail with `Class "Faker\Factory" not found`.
 *
 * The values below are therefore generated with plain PHP so this factory runs on
 * a stock installation. If you want realistic-looking data instead, install the
 * package first — `composer require --dev fakerphp/faker` — and then fake() will
 * work here as it does in any Laravel project.
 *
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The model this factory builds.
     *
     * @var class-string<User>
     */
    protected $model = User::class;

    /**
     * The password hash shared by every generated user, hashed once because
     * bcrypt is deliberately slow and the value never differs.
     */
    protected static ?string $password = null;

    /**
     * Incremented per generated user so a batch cannot collide on the unique
     * email column. Str::random() alone is random, not guaranteed distinct.
     */
    protected static int $sequence = 0;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $n = ++static::$sequence;

        return [
            'name' => 'Test User ' . $n,
            'email' => 'user' . $n . '-' . Str::lower(Str::random(6)) . '@example.com',
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
