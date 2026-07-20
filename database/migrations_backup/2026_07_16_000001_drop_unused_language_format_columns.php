<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Number separators are now fixed platform-wide ('.' / ',') and currency
     * position is a global General setting, so these per-language columns are
     * no longer read anywhere. date_format, time_format and number_system stay.
     */
    public function up(): void
    {
        Schema::table('languages', function (Blueprint $table) {
            foreach (['currency_position', 'thousands_separator', 'decimal_separator'] as $column) {
                if (Schema::hasColumn('languages', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('languages', function (Blueprint $table) {
            if (! Schema::hasColumn('languages', 'decimal_separator')) {
                $table->char('decimal_separator', 1)->default('.')->after('time_format');
            }
            if (! Schema::hasColumn('languages', 'thousands_separator')) {
                $table->char('thousands_separator', 1)->default(',')->after('decimal_separator');
            }
            if (! Schema::hasColumn('languages', 'currency_position')) {
                $table->string('currency_position', 20)->default('before')->after('number_system');
            }
        });
    }
};
