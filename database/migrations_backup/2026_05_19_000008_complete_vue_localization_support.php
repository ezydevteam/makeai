<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('languages', function (Blueprint $table) {
            $table->string('date_format', 50)->default('MMM D, YYYY')->after('is_active');
            $table->string('time_format', 50)->default('h:mm A')->after('date_format');
            $table->char('decimal_separator', 1)->default('.')->after('time_format');
            $table->char('thousands_separator', 1)->default(',')->after('decimal_separator');
            $table->string('number_system', 20)->default('latn')->after('thousands_separator');
        });
    }

    public function down(): void
    {
        Schema::table('languages', function (Blueprint $table) {
            $table->dropColumn([
                'date_format',
                'time_format',
                'decimal_separator',
                'thousands_separator',
                'number_system',
            ]);
        });
    }
};
