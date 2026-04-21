<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_profile_collaborators', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('agent_profile_id');
            $table->unsignedBigInteger('collaborator_agent_profile_id');
            $table->timestamps();

            $table->foreign('agent_profile_id', 'apc_agent_fk')
                ->references('id')
                ->on('agent_profiles')
                ->cascadeOnDelete();
            $table->foreign('collaborator_agent_profile_id', 'apc_collab_fk')
                ->references('id')
                ->on('agent_profiles')
                ->cascadeOnDelete();
            $table->unique(
                ['agent_profile_id', 'collaborator_agent_profile_id'],
                'agent_profile_collaborator_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_profile_collaborators');
    }
};
