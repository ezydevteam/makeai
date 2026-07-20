<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tool_chain_runs', function (Blueprint $table) {
            // Present in the create-table migration, but absent on databases that were
            // migrated before it was added there — and nothing ever wrote it anyway.
            if (! Schema::hasColumn('tool_chain_runs', 'total_credits')) {
                $table->decimal('total_credits', 12, 4)->default(0)->after('total_tokens');
            }

            if (! Schema::hasColumn('tool_chain_runs', 'input')) {
                // The text the chain was run with — steps template it as {{input}},
                // and the history needs it to show what produced an output.
                $table->text('input')->nullable()->after('status');
            }

            if (! Schema::hasColumn('tool_chain_runs', 'error')) {
                $table->text('error')->nullable()->after('total_tokens');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tool_chain_runs', function (Blueprint $table) {
            $table->dropColumn(['input', 'error']);
        });
    }
};
