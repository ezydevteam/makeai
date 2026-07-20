<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $indexes = [
        'ai_tools' => [
            'live_search_ai_tools_fulltext' => ['name', 'description'],
        ],
        'blog_posts' => [
            'live_search_blog_posts_fulltext' => ['title', 'excerpt', 'content'],
        ],
        'pages' => [
            'live_search_pages_fulltext' => ['title'],
        ],
        'users' => [
            'live_search_users_fulltext' => ['name', 'email'],
        ],
    ];

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        foreach ($this->indexes as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($indexes as $name => $columns) {
                if ($this->indexExists($table, $name)) {
                    continue;
                }

                $columnList = collect($columns)
                    ->map(fn (string $column): string => "`{$column}`")
                    ->implode(', ');

                DB::statement("ALTER TABLE `{$table}` ADD FULLTEXT INDEX `{$name}` ({$columnList})");
            }
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        foreach ($this->indexes as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach (array_keys($indexes) as $name) {
                if (! $this->indexExists($table, $name)) {
                    continue;
                }

                DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$name}`");
            }
        }
    }

    private function indexExists(string $table, string $name): bool
    {
        return DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('INDEX_NAME', $name)
            ->exists();
    }
};
