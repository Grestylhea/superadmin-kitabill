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
        Schema::create('hotspot_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('router_id')->constrained('routers')->onDelete('cascade');
            $table->string('username')->unique();
            $table->string('password');
            $table->string('profile')->default('default');
            $table->string('comment')->nullable();
            $table->boolean('disabled')->default(false);
            $table->integer('limit_uptime')->nullable(); // in seconds
            $table->bigInteger('limit_bytes_in')->nullable(); // in bytes
            $table->bigInteger('limit_bytes_out')->nullable(); // in bytes
            $table->integer('limit_bytes_total')->nullable(); // in bytes
            $table->timestamp('uptime_limit')->nullable();
            $table->timestamp('bytes_limit')->nullable();
            $table->string('voucher_code')->nullable()->unique(); // Untuk voucher
            $table->integer('voucher_quantity')->default(1); // Jumlah voucher yang di-generate
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('username');
            $table->index('voucher_code');
            $table->index('router_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hotspot_users');
    }
};
