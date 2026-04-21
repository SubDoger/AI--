<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('relay_configs', function (Blueprint $table): void {
            $table->text('api_keys')->nullable()->after('api_key');
            $table->unsignedInteger('api_key_cursor')->default(0)->after('api_keys');
        });

        DB::table('relay_configs')
            ->select(['id', 'api_key'])
            ->orderBy('id')
            ->chunkById(100, function ($configs): void {
                foreach ($configs as $config) {
                    DB::table('relay_configs')
                        ->where('id', $config->id)
                        ->update([
                            'api_keys' => json_encode([$config->api_key], JSON_UNESCAPED_UNICODE),
                            'api_key_cursor' => 0,
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('relay_configs', function (Blueprint $table): void {
            $table->dropColumn(['api_keys', 'api_key_cursor']);
        });
    }
};
