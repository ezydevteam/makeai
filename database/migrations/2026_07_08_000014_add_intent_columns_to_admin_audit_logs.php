<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enrich admin_audit_logs so entries carry INTENT, not just a raw "METHOD path"
 * string. The route name (e.g. admin.plans.update) is stable across URL changes
 * and maps cleanly to a human label, and target_type/target_id let us answer
 * "who last changed THIS record?". Legacy rows keep working — every new column
 * is nullable and the old `action` column is untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_audit_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('admin_audit_logs', 'route_name')) {
                $table->string('route_name')->nullable()->after('action');
            }
            if (! Schema::hasColumn('admin_audit_logs', 'method')) {
                $table->string('method', 10)->nullable()->after('route_name');
            }
            if (! Schema::hasColumn('admin_audit_logs', 'target_type')) {
                $table->string('target_type')->nullable()->after('method');
            }
            if (! Schema::hasColumn('admin_audit_logs', 'target_id')) {
                $table->string('target_id')->nullable()->after('target_type');
            }
        });

        Schema::table('admin_audit_logs', function (Blueprint $table) {
            if (! $this->indexExists('admin_audit_logs', 'admin_audit_logs_route_name_index')) {
                $table->index('route_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('admin_audit_logs', function (Blueprint $table) {
            if ($this->indexExists('admin_audit_logs', 'admin_audit_logs_route_name_index')) {
                $table->dropIndex('admin_audit_logs_route_name_index');
            }

            foreach (['route_name', 'method', 'target_type', 'target_id'] as $column) {
                if (Schema::hasColumn('admin_audit_logs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        try {
            return collect(Schema::getIndexes($table))
                ->contains(fn (array $i) => ($i['name'] ?? null) === $index);
        } catch (\Throwable) {
            return false;
        }
    }
};
