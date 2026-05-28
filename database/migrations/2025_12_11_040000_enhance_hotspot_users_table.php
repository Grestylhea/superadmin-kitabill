<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotspot_users', function (Blueprint $table) {
            // Add server column if not exists
            if (!Schema::hasColumn('hotspot_users', 'server')) {
                $table->string('server')->default('all')->after('router_id');
            }
            
            // Add batch tracking
            if (!Schema::hasColumn('hotspot_users', 'batch_id')) {
                $table->string('batch_id')->nullable()->after('voucher_code');
                $table->index('batch_id');
            }
            
            // Add price for billing
            if (!Schema::hasColumn('hotspot_users', 'price')) {
                $table->decimal('price', 10, 2)->nullable()->after('profile');
            }
            
            // Add MAC address binding
            if (!Schema::hasColumn('hotspot_users', 'mac_address')) {
                $table->string('mac_address')->nullable()->after('password');
            }
            
            // Add IP address
            if (!Schema::hasColumn('hotspot_users', 'ip_address')) {
                $table->string('ip_address')->nullable()->after('mac_address');
            }
            
            // Add sync status
            if (!Schema::hasColumn('hotspot_users', 'synced_at')) {
                $table->timestamp('synced_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('hotspot_users', function (Blueprint $table) {
            $table->dropColumn(['server', 'batch_id', 'price', 'mac_address', 'ip_address', 'synced_at']);
        });
    }
};

