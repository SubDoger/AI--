<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_profile_knowledge_base', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('knowledge_base_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['agent_profile_id', 'knowledge_base_id'], 'agent_profile_knowledge_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_profile_knowledge_base');
    }
};
