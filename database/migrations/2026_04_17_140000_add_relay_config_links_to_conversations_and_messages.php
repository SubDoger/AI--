<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->foreignId('relay_config_id')
                ->nullable()
                ->after('user_id')
                ->constrained('relay_configs')
                ->nullOnDelete();

            $table->index(['user_id', 'relay_config_id']);
        });

        Schema::table('messages', function (Blueprint $table): void {
            $table->foreignId('relay_config_id')
                ->nullable()
                ->after('user_id')
                ->constrained('relay_configs')
                ->nullOnDelete();

            $table->index(['conversation_id', 'relay_config_id']);
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('relay_config_id');
            $table->dropIndex(['conversation_id', 'relay_config_id']);
        });

        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('relay_config_id');
            $table->dropIndex(['user_id', 'relay_config_id']);
        });
    }
};
