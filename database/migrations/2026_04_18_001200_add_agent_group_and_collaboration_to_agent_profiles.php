<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_profiles', function (Blueprint $table) {
            $table->foreignId('agent_group_id')
                ->nullable()
                ->after('relay_config_id')
                ->constrained('agent_groups')
                ->nullOnDelete();
            $table->string('collaboration_mode', 50)->default('solo')->after('suggested_prompts');
        });
    }

    public function down(): void
    {
        Schema::table('agent_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('agent_group_id');
            $table->dropColumn('collaboration_mode');
        });
    }
};
