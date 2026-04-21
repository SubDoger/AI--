<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::query()->updateOrCreate([
            'email' => 'admin@grok.local',
        ], [
            'is_admin' => true,
            'name' => '系统管理员',
            'password' => 'admin123456',
        ]);

        User::query()->updateOrCreate([
            'email' => 'test@example.com',
        ], [
            'is_admin' => false,
            'name' => 'Test User',
            'password' => 'password123',
        ]);
    }
}
