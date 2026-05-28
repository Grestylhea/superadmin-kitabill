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
        Schema::create('hotspot_profile_extras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('router_id')->constrained()->onDelete('cascade');
            $table->string('profile_name'); // Nama profile di Mikrotik
            $table->string('expired_mode')->nullable()->default('Remove'); // Remove, Notice, Remove & Record, Notice & Record
            $table->string('validity')->nullable(); // Format: 8h, 30d, 7d
            $table->decimal('price', 10, 2)->nullable()->default(0); // Harga beli
            $table->decimal('selling_price', 10, 2)->nullable()->default(0); // Harga jual
            $table->boolean('lock_user')->nullable()->default(false); // Lock user (hanya 1 device)
            $table->timestamps();
            
            $table->unique(['router_id', 'profile_name']); // Satu profile per router
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hotspot_profile_extras');
    }
};

