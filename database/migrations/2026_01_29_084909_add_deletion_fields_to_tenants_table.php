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
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('deletion_status')->nullable();
            $table->timestamp('deletion_requested_at')->nullable();
            $table->unsignedBigInteger('deletion_requested_by')->nullable();
            $table->boolean('is_system')->default(false);
            
            $table->foreign('deletion_requested_by')->references('id')->on('users')->onDelete('set null');
        });

        // Set tenant ID 1 as system tenant
        \DB::table('tenants')->where('id', 1)->update(['is_system' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropForeign(['deletion_requested_by']);
            $table->dropColumn(['deletion_status', 'deletion_requested_at', 'deletion_requested_by', 'is_system']);
        });
    }
};
