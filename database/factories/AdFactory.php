<?php

namespace Database\Factories;

use App\Models\Ad;
use Illuminate\Database\Eloquent\Factories\Factory;

class AdFactory extends Factory
{
    protected $model = Ad::class;

    public function definition(): array
    {
        $type = $this->faker->randomElement(['image_link', 'custom_html', 'adsense']);

        return [
            'title' => $this->faker->sentence(3),
            'type' => $type,
            'zone' => $this->faker->randomElement(array_keys(config('ads.zones'))),
            'custom_html' => $type === 'custom_html' ? '<div>Sponsored content</div>' : null,
            'adsense_client' => $type === 'adsense' ? 'ca-pub-' . $this->faker->randomNumber(9) : null,
            'adsense_slot' => $type === 'adsense' ? (string) $this->faker->randomNumber(9) : null,
            'adsense_format' => $type === 'adsense' ? 'auto' : null,
            'image_url' => $type === 'image_link' ? 'https://via.placeholder.com/300x250' : null,
            'link_url' => $type === 'image_link' ? $this->faker->url() : null,
            'link_target' => '_blank',
            'show_to' => $this->faker->randomElement(['all', 'guests', 'logged_in', 'free_users', 'paid_users']),
            'is_active' => true,
            'start_at' => null,
            'end_at' => null,
            'impressions' => $this->faker->numberBetween(0, 10000),
            'clicks' => $this->faker->numberBetween(0, 500),
            'sort_order' => 0,
        ];
    }

    public function imageLink(): static
    {
        return $this->state(fn () => [
            'type' => 'image_link',
            'custom_html' => null,
            'adsense_client' => null,
            'adsense_slot' => null,
            'adsense_format' => null,
        ]);
    }

    public function customHtml(): static
    {
        return $this->state(fn () => [
            'type' => 'custom_html',
            'custom_html' => '<div>Sponsored content</div>',
            'adsense_client' => null,
            'adsense_slot' => null,
            'adsense_format' => null,
            'image_url' => null,
            'link_url' => null,
        ]);
    }

    public function adsense(): static
    {
        return $this->state(fn () => [
            'type' => 'adsense',
            'custom_html' => null,
            'image_url' => null,
            'link_url' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function scheduled(): static
    {
        return $this->state(fn () => [
            'start_at' => now()->subDay(),
            'end_at' => now()->addDays(30),
        ]);
    }
}
