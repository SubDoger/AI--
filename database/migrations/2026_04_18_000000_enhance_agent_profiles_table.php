<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_profiles', function (Blueprint $table) {
            $table->string('category', 50)->nullable()->after('role_label');
            $table->json('tags')->nullable()->after('category');
            $table->text('capabilities')->nullable()->after('description');
            $table->text('welcome_message')->nullable()->after('capabilities');
            $table->json('suggested_prompts')->nullable()->after('welcome_message');
        });
    }

    public function down(): void
    {
        Schema::table('agent_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'category',
                'tags',
                'capabilities',
                'welcome_message',
                'suggested_prompts',
            ]);
        });
    }
};
