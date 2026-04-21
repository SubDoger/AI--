<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->foreignId('agent_profile_id')
                ->nullable()
                ->after('relay_config_id')
                ->constrained()
                ->nullOnDelete();

            $table->index(['user_id', 'agent_profile_id']);
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'agent_profile_id']);
            $table->dropConstrainedForeignId('agent_profile_id');
        });
    }
};
