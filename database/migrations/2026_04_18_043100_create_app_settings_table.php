<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('site_name', 120)->default('GrokPlatform');
            $table->string('login_title', 120)->default('欢迎回来');
            $table->string('login_description', 255)->default('登录管理员后台后可继续管理智能体、知识库与中转配置。');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
