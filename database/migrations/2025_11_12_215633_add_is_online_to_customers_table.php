<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (!Schema::hasColumn('customers', 'is_online')) {
                $table->boolean('is_online')
                      ->default(false)
                      ->after('status')
                      ->comment('Menandakan apakah user sedang online (true) atau offline (false)');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasColumn('customers', 'is_online')) {
                $table->dropColumn('is_online');
            }
        });
    }
};

