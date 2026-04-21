<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('relay_config_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 100);
            $table->string('role_label', 100)->nullable();
            $table->string('avatar', 32)->default('AI');
            $table->string('model', 100);
            $table->string('starter_prompt', 500)->nullable();
            $table->text('description')->nullable();
            $table->text('system_prompt')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index(['user_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_profiles');
    }
};
