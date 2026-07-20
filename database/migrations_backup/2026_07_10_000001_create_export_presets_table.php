<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('export_presets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('admins')->cascadeOnDelete();
            $table->string('name');
            $table->string('dataset');           // Dataset key, e.g. 'users'
            $table->string('format')->default('xlsx');
            $table->json('filters')->nullable();  // status/plan/provider/gateway/tool/date
            $table->json('columns')->nullable();  // selected column keys; null = all
            $table->timestamps();

            $table->index(['admin_id', 'dataset']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('export_presets');
    }
};
