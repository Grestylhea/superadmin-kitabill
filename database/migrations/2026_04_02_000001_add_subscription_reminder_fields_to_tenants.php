<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Anti-spam flags for subscription reminder WA messages
            $table->timestamp('subscription_reminder_h7_sent_at')->nullable()->after('subscription_expires_at');
            $table->timestamp('subscription_reminder_h3_sent_at')->nullable()->after('subscription_reminder_h7_sent_at');
            $table->timestamp('subscription_reminder_h1_sent_at')->nullable()->after('subscription_reminder_h3_sent_at');
            $table->timestamp('subscription_suspended_notified_at')->nullable()->after('subscription_reminder_h1_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'subscription_reminder_h7_sent_at',
                'subscription_reminder_h3_sent_at',
                'subscription_reminder_h1_sent_at',
                'subscription_suspended_notified_at',
            ]);
        });
    }
};
