<?php

namespace Tests\Unit;

use App\Services\AI\ToolCatalogCacheService;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use Tests\TestCase;

/**
 * Guard against the class of bug where a dead column is dropped from ai_tools
 * but ToolCatalogCacheService still names it in an explicit SELECT list — which
 * fails at runtime with "Unknown column ... in field list" (see avg_latency_ms).
 *
 * Every column the service asks the database for MUST exist on the table.
 */
class ToolCatalogColumnsTest extends TestCase
{
    public function test_selected_columns_exist_on_ai_tools(): void
    {
        $existing = Schema::getColumnListing('ai_tools');
        $this->assertNotEmpty($existing, 'ai_tools table should exist in the test schema.');

        $service = new ToolCatalogCacheService();
        $class = new ReflectionClass($service);

        foreach (['toolListColumns', 'toolDetailColumns'] as $method) {
            $reflected = $class->getMethod($method);
            $reflected->setAccessible(true);

            /** @var array<int, string> $selected */
            $selected = $reflected->invoke($service);
            $this->assertNotEmpty($selected, "{$method}() must return at least one column.");

            foreach ($selected as $column) {
                $this->assertContains(
                    $column,
                    $existing,
                    "ToolCatalogCacheService::{$method}() selects '{$column}', which does not exist on the ai_tools table.",
                );
            }
        }
    }
}
