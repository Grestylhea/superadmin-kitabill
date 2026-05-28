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
        // 1. Message Logs (Lightweight, indexed, retention-ready)
        Schema::create('wa_message_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->string('sender_session')->index(); // e.g. 'superadmin', 'tenant_1'
            $table->enum('status', ['success', 'failed', 'locked'])->index();
            $table->string('error_type')->nullable();
            $table->timestamp('created_at')->nullable()->index();
            $table->timestamp('updated_at')->nullable();

            // Composite index for efficient daily dashboard queries
            $table->index(['tenant_id', 'created_at']);
        });

        // 2. Daily Metrics (Permanent aggregation)
        Schema::create('wa_daily_metrics', function (Blueprint $table) {
            $table->id();
            $table->date('date')->index();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->integer('success_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->integer('locked_count')->default(0);
            $table->integer('paused_count')->default(0); // For tracking circuit breaker hits
            $table->timestamps();

            $table->unique(['date', 'tenant_id'], 'wa_metrics_date_tenant_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wa_daily_metrics');
        Schema::dropIfExists('wa_message_logs');
    }
};
