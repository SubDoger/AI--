<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('use_admin_relay_preset')
                ->default(false)
                ->after('adult_content_toggle_visible');
            $table->foreignId('assigned_admin_relay_config_id')
                ->nullable()
                ->after('use_admin_relay_preset')
                ->constrained('relay_configs')
                ->nullOnDelete();
            $table->unsignedInteger('assigned_admin_relay_key_index')
                ->nullable()
                ->after('assigned_admin_relay_config_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('assigned_admin_relay_config_id');
            $table->dropColumn([
                'assigned_admin_relay_key_index',
                'use_admin_relay_preset',
            ]);
        });
    }
};
