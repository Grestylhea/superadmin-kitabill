<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - FULL MIKHMON FEATURES
     */
    public function up(): void
    {
        // Hotspot Users Table
        Schema::create('hotspot_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('router_id')->constrained('routers')->onDelete('cascade');
            $table->string('server')->default('all');
            $table->string('username')->unique();
            $table->string('password');
            $table->string('profile');
            $table->string('comment')->nullable();
            $table->boolean('disabled')->default(false);
            
            // Limits
            $table->integer('limit_uptime')->nullable(); // seconds
            $table->bigInteger('limit_bytes_in')->nullable();
            $table->bigInteger('limit_bytes_out')->nullable();
            $table->bigInteger('limit_bytes_total')->nullable();
            
            // Usage tracking
            $table->bigInteger('bytes_in')->default(0);
            $table->bigInteger('bytes_out')->default(0);
            $table->bigInteger('bytes_total')->default(0);
            $table->integer('uptime')->default(0); // seconds
            $table->integer('packets_in')->default(0);
            $table->integer('packets_out')->default(0);
            
            // Voucher info
            $table->string('voucher_code')->nullable()->unique();
            $table->string('batch_id')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            
            // Dates
            $table->timestamp('last_seen')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['router_id', 'server']);
            $table->index('profile');
            $table->index('voucher_code');
            $table->index('batch_id');
            $table->index('disabled');
        });
        
        // Hotspot Profiles Table
        Schema::create('hotspot_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('router_id')->constrained('routers')->onDelete('cascade');
            $table->string('name');
            $table->string('rate_limit')->nullable();
            $table->string('session_timeout')->nullable();
            $table->string('idle_timeout')->nullable();
            $table->integer('shared_users')->default(1);
            $table->string('address_pool')->nullable();
            $table->string('transparent_proxy')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->string('validity')->nullable(); // 1d, 2h, 30m, etc
            $table->timestamps();
            
            $table->unique(['router_id', 'name']);
        });
        
        // Hotspot Active Sessions (cache table)
        Schema::create('hotspot_active_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('router_id')->constrained('routers')->onDelete('cascade');
            $table->string('username');
            $table->string('address')->nullable();
            $table->string('mac_address')->nullable();
            $table->string('server')->nullable();
            $table->integer('uptime')->default(0);
            $table->bigInteger('bytes_in')->default(0);
            $table->bigInteger('bytes_out')->default(0);
            $table->timestamp('login_at')->nullable();
            $table->timestamp('synced_at')->useCurrent();
            
            $table->index(['router_id', 'username']);
        });
        
        // Hotspot Hosts (cache table)
        Schema::create('hotspot_hosts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('router_id')->constrained('routers')->onDelete('cascade');
            $table->string('mac_address');
            $table->string('address')->nullable();
            $table->string('server')->nullable();
            $table->integer('uptime')->default(0);
            $table->bigInteger('bytes_in')->default(0);
            $table->bigInteger('bytes_out')->default(0);
            $table->timestamp('synced_at')->useCurrent();
            
            $table->index(['router_id', 'mac_address']);
        });
        
        // Hotspot IP Bindings Table
        Schema::create('hotspot_ip_bindings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('router_id')->constrained('routers')->onDelete('cascade');
            $table->string('mac_address');
            $table->string('address');
            $table->string('to_address')->nullable();
            $table->string('server')->nullable();
            $table->string('type')->default('regular');
            $table->string('comment')->nullable();
            $table->boolean('disabled')->default(false);
            $table->timestamps();
            
            $table->index(['router_id', 'mac_address']);
        });
        
        // Hotspot Cookies Table
        Schema::create('hotspot_cookies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('router_id')->constrained('routers')->onDelete('cascade');
            $table->string('mac_address');
            $table->string('domain')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('synced_at')->useCurrent();
            
            $table->index(['router_id', 'mac_address']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hotspot_cookies');
        Schema::dropIfExists('hotspot_ip_bindings');
        Schema::dropIfExists('hotspot_hosts');
        Schema::dropIfExists('hotspot_active_sessions');
        Schema::dropIfExists('hotspot_profiles');
        Schema::dropIfExists('hotspot_users');
    }
};

