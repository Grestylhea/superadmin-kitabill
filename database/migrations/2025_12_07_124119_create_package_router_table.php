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
        Schema::create('package_router', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained()->onDelete('cascade');
            $table->foreignId('router_id')->constrained()->onDelete('cascade');
            $table->enum('connection_type', ['pppoe', 'hotspot'])->default('pppoe');
            $table->timestamps();
            
            // Unique constraint: satu package hanya bisa punya satu entry per router dan connection type
            $table->unique(['package_id', 'router_id', 'connection_type'], 'package_router_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('package_router');
    }
};
