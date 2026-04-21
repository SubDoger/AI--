<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'site_name',
    'login_title',
    'login_description',
])]
class AppSetting extends Model
{
    public static function current(): self
    {
        return self::query()->firstOrCreate(
            ['id' => 1],
            [
                'site_name' => config('app.name', 'GrokPlatform'),
                'login_title' => '欢迎回来',
                'login_description' => '登录管理员后台后可继续管理智能体、知识库与中转配置。',
            ],
        );
    }
}
