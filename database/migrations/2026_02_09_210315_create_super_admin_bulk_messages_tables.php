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
        Schema::create('super_admin_bulk_messages', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->text('message_body');
            $table->jsonb('filters_json')->nullable();
            $table->enum('status', ['pending', 'processing', 'done', 'failed'])->default('pending');
            $table->integer('total_targets')->default(0);
            $table->integer('sent_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->integer('pending_count')->default(0);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('super_admin_bulk_message_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('super_admin_bulk_message_id')->constrained('super_admin_bulk_messages')->onDelete('cascade');
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->string('phone');
            $table->string('tenant_name_snapshot')->nullable();
            $table->enum('status', ['pending', 'sent', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('super_admin_bulk_message_recipients');
        Schema::dropIfExists('super_admin_bulk_messages');
    }
};
