<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create unified categories table
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('icon', 100)->nullable();
            $table->string('color', 20)->nullable();
            $table->string('type')->default('ai_tool'); // ai_tool, blog, general
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->boolean('requires_pro')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('tools_count')->default(0);
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->timestamps();

            $table->index(['type', 'is_active']);
        });

        // 2. Drop all FKs on ai_templates (including the one pointing to ai_tool_categories)
        //    Must happen BEFORE dropping ai_tool_categories
        if (Schema::hasTable('ai_templates')) {
            $fks = $this->listForeignKeys('ai_templates');
            foreach ($fks as $fk) {
                Schema::table('ai_templates', fn (Blueprint $t) => $t->dropForeign($fk));
            }
        }

        // 3. Migrate existing ai_tool_categories data, then drop the old table
        if (Schema::hasTable('ai_tool_categories')) {
            $rows = DB::table('ai_tool_categories')->get();
            foreach ($rows as $row) {
                DB::table('categories')->insert([
                    'id' => $row->id,
                    'name' => $row->name,
                    'slug' => $row->slug,
                    'description' => $row->description ?? null,
                    'icon' => $row->icon ?? null,
                    'color' => $row->color ?? null,
                    'type' => 'ai_tool',
                    'is_active' => $row->is_active ?? true,
                    'is_system' => true,
                    'requires_pro' => $row->requires_pro ?? false,
                    'sort_order' => $row->sort_order ?? 0,
                    'tools_count' => $row->tools_count ?? 0,
                    'created_at' => $row->created_at ?? now(),
                    'updated_at' => $row->updated_at ?? now(),
                ]);
            }
            Schema::dropIfExists('ai_tool_categories');
        }

        // 4. Rename ai_templates → ai_tools
        if (Schema::hasTable('ai_templates') && ! Schema::hasTable('ai_tools')) {
            Schema::rename('ai_templates', 'ai_tools');
        }

        // 5. Modify ai_tools structure
        Schema::table('ai_tools', function (Blueprint $table) {
            // Add new columns
            if (! Schema::hasColumn('ai_tools', 'ulid')) {
                $table->char('ulid', 26)->nullable()->unique()->after('id');
            }
            if (! Schema::hasColumn('ai_tools', 'is_system')) {
                $table->boolean('is_system')->default(true)->after('sort_order');
            }
            if (! Schema::hasColumn('ai_tools', 'avg_latency_ms')) {
                $table->unsignedInteger('avg_latency_ms')->nullable()->after('avg_output_tokens');
            }

            // Drop dead columns
            $dropCols = ['prompt', 'default_model', 'max_tokens', 'is_premium', 'category'];
            foreach ($dropCols as $col) {
                if (Schema::hasColumn('ai_tools', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        // 6. Add FK from ai_tools.category_id → categories.id
        Schema::table('ai_tools', function (Blueprint $table) {
            $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
        });

        // 7. Populate ulid for existing rows
        DB::table('ai_tools')->whereNull('ulid')->eachById(function ($row) {
            DB::table('ai_tools')->where('id', $row->id)->update([
                'ulid' => (string) \Illuminate\Support\Str::ulid(),
            ]);
        }, 100, 'id');

        // 8. Rename template_slug → tool_slug in tool_reviews
        if (Schema::hasTable('tool_reviews') && Schema::hasColumn('tool_reviews', 'template_slug')) {
            Schema::table('tool_reviews', function (Blueprint $table) {
                $table->renameColumn('template_slug', 'tool_slug');
            });
        }

        // 9. Update polymorphic commentable_type for comments
        if (Schema::hasTable('comments')) {
            DB::table('comments')
                ->where('commentable_type', 'App\\Models\\AiTemplate')
                ->update(['commentable_type' => 'App\\Models\\AiTool']);
        }

        // 10. Update polymorphic favoriteable_type for favorites
        if (Schema::hasTable('favorites')) {
            DB::table('favorites')
                ->where('favoriteable_type', 'App\\Models\\AiTemplate')
                ->update(['favoriteable_type' => 'App\\Models\\AiTool']);
        }
    }

    public function down(): void
    {
        // Not reversible in a meaningful way — this is a forward-only refactor
    }

    private function listForeignKeys(string $table): array
    {
        $database = DB::getDatabaseName();
        $results = DB::select("
            SELECT CONSTRAINT_NAME
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_TYPE = 'FOREIGN KEY'
        ", [$database, $table]);

        return array_map(fn ($r) => $r->CONSTRAINT_NAME, $results);
    }
};
