<?php

namespace Addons\FakerAi\Generators;

use Addons\FakerAi\Models\FakerBatch;

/**
 * Fake member accounts. Flagged (@faker.local + preferences) so they're identifiable and never
 * emailed; rollback force-deletes them.
 */
class UsersGenerator extends AbstractGenerator
{
    public function type(): string
    {
        return 'users';
    }

    public function label(): string
    {
        return 'Users';
    }

    public function group(): string
    {
        return 'Users';
    }

    public function generate(FakerBatch $batch): int
    {
        $items = $this->content->generate(
            noun: 'website member profile',
            fields: [
                'name' => 'a realistic full name for one person',
                'profession' => 'their job title or profession, e.g. "Marketing Manager"',
                'country' => 'the English name of their country',
            ],
            count: $batch->requested_count,
            style: $this->style($batch),
            batch: $batch,
        );

        $created = 0;
        foreach ($items as $item) {
            $date = $this->backdate();
            $this->createFakeUser($batch, (string) ($item['name'] ?? ''), $date, [
                'profession' => $this->clean($item['profession'] ?? null, 120),
                'country' => $this->clean($item['country'] ?? null, 100),
            ]);
            $created++;
        }

        return $created;
    }

    private function clean(mixed $value, int $limit): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $limit);
    }
}
