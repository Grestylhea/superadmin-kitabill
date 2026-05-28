<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            // tanggal expired per bulan (1–31)
            $table->unsignedTinyInteger('custom_expire_day')
                ->nullable()
                ->after('billing_cycle');

            // jam expired
            $table->time('custom_expire_time')
                ->nullable()
                ->after('custom_expire_day');
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn(['custom_expire_day', 'custom_expire_time']);
        });
    }
};

