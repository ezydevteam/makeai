<?php

namespace Addons\FakerAi\Generators;

use Addons\FakerAi\Models\FakerBatch;
use App\Models\Testimonial;

/**
 * Fake customer testimonials for the homepage / testimonials section.
 */
class TestimonialsGenerator extends AbstractGenerator
{
    public function type(): string
    {
        return 'testimonials';
    }

    public function label(): string
    {
        return 'Testimonials';
    }

    public function group(): string
    {
        return 'Testimonials';
    }

    /** Allowed testimonial sources — the subset of the DB enum that reads as a real review origin. */
    private const SOURCES = ['manual', 'google', 'trustpilot'];

    public function optionFields(): array
    {
        return [
            [
                'key' => 'source',
                'label' => 'Source',
                'type' => 'select',
                'options' => [
                    ['value' => 'manual', 'label' => 'Manual'],
                    ['value' => 'google', 'label' => 'Google'],
                    ['value' => 'trustpilot', 'label' => 'Trustpilot'],
                ],
                'default' => 'manual',
            ],
            $this->ratingOptionField(),
        ];
    }

    public function generate(FakerBatch $batch): int
    {
        $source = $this->resolveSource($batch);
        $band = $this->ratingBand($batch);

        $items = $this->content->generate(
            noun: 'customer testimonial',
            fields: [
                'name' => 'the full name of the person giving the testimonial',
                'role' => 'their job title, e.g. "Founder" or "Content Lead"',
                'company' => 'their company or brand name',
                'content' => 'a 1-3 sentence testimonial praising the product, specific and believable',
                'rating' => $this->ratingFieldHint($band),
            ],
            count: $batch->requested_count,
            style: $this->style($batch),
            batch: $batch,
        );

        $created = 0;
        foreach ($items as $item) {
            $content = trim((string) ($item['content'] ?? ''));
            $name = trim((string) ($item['name'] ?? ''));
            if ($content === '' || $name === '') {
                continue;
            }

            $date = $this->backdate();
            $testimonial = new Testimonial([
                'name' => mb_substr($name, 0, 120),
                'role' => $this->clean($item['role'] ?? null, 120),
                'company' => $this->clean($item['company'] ?? null, 120),
                'content' => $content,
                'rating' => $this->pickRating($item['rating'] ?? null, $band),
                'is_active' => true,
                // A minority featured, so the "featured" strip has something without every row
                // claiming the spotlight.
                'is_featured' => random_int(1, 100) <= 20,
                // Admin-chosen source (manual|google|trustpilot). Fakes are tracked by the batch
                // ledger, not by the source tag, so any valid enum value is fine here.
                'source' => $source,
                'sort_order' => 0,
            ]);
            $testimonial->created_at = $date;
            $testimonial->updated_at = $date;
            $testimonial->save();

            $batch->recordInsert($testimonial);
            $created++;
        }

        return $created;
    }

    private function resolveSource(FakerBatch $batch): string
    {
        $source = $batch->options['source'] ?? 'manual';

        return in_array($source, self::SOURCES, true) ? $source : 'manual';
    }

    private function clean(mixed $value, int $limit): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $limit);
    }
}
