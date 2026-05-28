<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Tanggal custom untuk isolir customer (opsional)
            // Jika diisi, customer akan diisolir pada tanggal ini
            // Terlepas dari due date invoice
            $table->dateTime('custom_isolir_date')->nullable()->after('next_billing_date');
            
            // Flag untuk menandakan isolir sudah dijalankan
            $table->boolean('custom_isolir_executed')->default(false)->after('custom_isolir_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['custom_isolir_date', 'custom_isolir_executed']);
        });
    }
};
