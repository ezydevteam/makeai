<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('chatbot_products') && !Schema::hasTable('chatbot_modes')) {
            Schema::rename('chatbot_products', 'chatbot_modes');
        }

        Schema::table('conversations', function (Blueprint $table) {
            if (Schema::hasColumn('conversations', 'product_slug') && !Schema::hasColumn('conversations', 'mode_slug')) {
                $table->renameColumn('product_slug', 'mode_slug');
            }
        });

        Schema::table('conversation_messages', function (Blueprint $table) {
            if (Schema::hasColumn('conversation_messages', 'product_switch') && !Schema::hasColumn('conversation_messages', 'mode_switch')) {
                $table->renameColumn('product_switch', 'mode_switch');
            }
        });
    }

    public function down(): void
    {
        Schema::table('conversation_messages', function (Blueprint $table) {
            if (Schema::hasColumn('conversation_messages', 'mode_switch') && !Schema::hasColumn('conversation_messages', 'product_switch')) {
                $table->renameColumn('mode_switch', 'product_switch');
            }
        });

        Schema::table('conversations', function (Blueprint $table) {
            if (Schema::hasColumn('conversations', 'mode_slug') && !Schema::hasColumn('conversations', 'product_slug')) {
                $table->renameColumn('mode_slug', 'product_slug');
            }
        });

        if (Schema::hasTable('chatbot_modes') && !Schema::hasTable('chatbot_products')) {
            Schema::rename('chatbot_modes', 'chatbot_products');
        }
    }
};
