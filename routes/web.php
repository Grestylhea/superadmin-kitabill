<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\WhatsAppGatewayController;

/*
|--------------------------------------------------------------------------
| Superadmin Application Routes
|--------------------------------------------------------------------------
|
| This application is dedicated for superadmin panel only.
| Domain: panelsuperadmin.kitabill.site
| WhatsApp Gateway: Port 3001
|
*/

Route::get('/', function () {
    return redirect()->route('login');
})->name('home');

// Redirect /superadmin untuk backward compatibility (jika ada yang akses)
Route::get('/superadmin', function () {
    if (auth()->check() && auth()->user()->is_super_admin) {
        return redirect()->route('superadmin.dashboard');
    }
    return redirect()->route('login');
});

// Redirect /superadmin/* ke path tanpa prefix
Route::prefix('superadmin')->group(function () {
    Route::get('/{any}', function ($any) {
        if (auth()->check() && auth()->user()->is_super_admin) {
            return redirect('/' . $any);
        }
        return redirect()->route('login');
    })->where('any', '.*');
});

// Authentication routes (from auth.php)
require __DIR__ . '/auth.php';

// ✅ SSO route untuk superadmin (dari central login di kitabill.site)
Route::get('/auth/sso', [\App\Http\Controllers\Auth\SsoController::class, 'verify'])
    ->name('sso.verify');

// Superadmin routes - only accessible by superadmin
// ✅ Route names pakai prefix 'superadmin.' tapi URL paths TIDAK pakai prefix '/superadmin/'
Route::name('superadmin.')->middleware(['auth', 'is_super_admin'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [\App\Http\Controllers\SuperAdmin\DashboardController::class, 'index'])->name('dashboard');
    
    // Global Monitoring
    Route::get('/monitoring', [\App\Http\Controllers\SuperAdmin\GlobalMonitoringController::class, 'index'])->name('monitoring.index');
    
    // Tenant Management
    Route::resource('tenants', \App\Http\Controllers\SuperAdmin\TenantController::class);
    Route::post('/tenants/{tenant}/activate', [\App\Http\Controllers\SuperAdmin\TenantController::class, 'activate'])->name('tenants.activate');
    Route::post('/tenants/{tenant}/suspend', [\App\Http\Controllers\SuperAdmin\TenantController::class, 'suspend'])->name('tenants.suspend');
    Route::post('/tenants/{tenant}/extend-trial', [\App\Http\Controllers\SuperAdmin\TenantController::class, 'extendTrial'])->name('tenants.extend-trial');
    Route::post('/tenants/bulk-destroy', [\App\Http\Controllers\SuperAdmin\TenantController::class, 'bulkDestroy'])->name('tenants.bulk-destroy');
    Route::post('/tenants/bulk-destroy-expired', [\App\Http\Controllers\SuperAdmin\TenantController::class, 'bulkDestroyExpired'])->name('tenants.bulk-destroy-expired');
    Route::post('/tenants/{tenant}/referral/rate', [\App\Http\Controllers\SuperAdmin\TenantController::class, 'updateReferralRate'])->name('tenants.referral.update-rate');
    Route::post('/tenants/{tenant}/referral/toggle', [\App\Http\Controllers\SuperAdmin\TenantController::class, 'toggleReferral'])->name('tenants.referral.toggle');
    Route::get('/tenant/{tenant}/access', [SuperAdminController::class, 'accessTenant'])->name('tenant.access');
    
    // ✅ Tenant ACS Management
    Route::post('/tenants/{tenant}/acs/enable', [\App\Http\Controllers\SuperAdmin\TenantAcsController::class, 'enable'])->name('tenants.acs.enable');
    Route::post('/tenants/{tenant}/acs/disable', [\App\Http\Controllers\SuperAdmin\TenantAcsController::class, 'disable'])->name('tenants.acs.disable');
    Route::post('/tenants/{tenant}/acs/regenerate-key', [\App\Http\Controllers\SuperAdmin\TenantAcsController::class, 'regenerateKey'])->name('tenants.acs.regenerate-key');
    Route::post('/tenants/acs/bulk-enable', [\App\Http\Controllers\SuperAdmin\TenantAcsController::class, 'bulkEnable'])->name('tenants.acs.bulk-enable');
    Route::post('/tenants/acs/bulk-disable', [\App\Http\Controllers\SuperAdmin\TenantAcsController::class, 'bulkDisable'])->name('tenants.acs.bulk-disable');
    
    // ✅ Tenant Payment Gateway Management
    Route::get('/tenants/{tenant}/gateways/tripay', [\App\Http\Controllers\SuperAdmin\TenantTripayGatewayController::class, 'show'])->name('tenants.gateways.tripay.show');
    Route::put('/tenants/{tenant}/gateways/tripay', [\App\Http\Controllers\SuperAdmin\TenantTripayGatewayController::class, 'update'])->name('tenants.gateways.tripay.update');
    Route::post('/tenants/{tenant}/gateways/tripay/test', [\App\Http\Controllers\SuperAdmin\TenantTripayGatewayController::class, 'test'])->name('tenants.gateways.tripay.test');

    
    // Revenue Report
    Route::get('/revenue', [SuperAdminController::class, 'revenue'])->name('revenue');
    
    // Subscriptions (redirect to subscription plans)
    Route::get('/subscriptions', [\App\Http\Controllers\SuperAdmin\SubscriptionPlanController::class, 'index'])->name('subscriptions');
    
    // Subscription Plans
    Route::resource('subscription-plans', \App\Http\Controllers\SuperAdmin\SubscriptionPlanController::class);
    
    // Email Templates
    Route::get('/email-templates', [\App\Http\Controllers\SuperAdmin\EmailTemplateController::class, 'index'])->name('email-templates.index');
    Route::get('/email-templates/{emailTemplate}/edit', [\App\Http\Controllers\SuperAdmin\EmailTemplateController::class, 'edit'])->name('email-templates.edit');
    Route::put('/email-templates/{emailTemplate}', [\App\Http\Controllers\SuperAdmin\EmailTemplateController::class, 'update'])->name('email-templates.update');
    
    // Analytics (placeholder - to be implemented)
    Route::get('/analytics', function () {
        return \Inertia\Inertia::render('SuperAdmin/Analytics');
    })->name('analytics');
    
    // Logs
    Route::get('/logs', [\App\Http\Controllers\SuperAdmin\ActivityLogController::class, 'index'])->name('logs');
    
    // Backups
    Route::get('/backups', [\App\Http\Controllers\SuperAdmin\BackupController::class, 'index'])->name('backups.index');
    Route::get('/backups/{filename}/download', [\App\Http\Controllers\SuperAdmin\BackupController::class, 'download'])->name('backups.download');

    // Withdrawals (Referral System)
    Route::get('/withdrawals', [\App\Http\Controllers\SuperAdmin\WithdrawalController::class, 'index'])->name('withdrawals.index');
    Route::post('/withdrawals/{withdrawal}/status', [\App\Http\Controllers\SuperAdmin\WithdrawalController::class, 'updateStatus'])->name('withdrawals.update-status');
    
    // Support (placeholder - to be implemented)
    Route::get('/support', function () {
        return \Inertia\Inertia::render('SuperAdmin/Support');
    })->name('support');
    
    // Settings
    Route::get('/settings', [\App\Http\Controllers\SuperAdmin\SettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings/payment', [\App\Http\Controllers\SuperAdmin\SettingsController::class, 'updatePaymentSettings'])->name('settings.payment.update');
    Route::post('/settings/test-connection', [\App\Http\Controllers\SuperAdmin\SettingsController::class, 'testConnection'])->name('settings.test-connection');
    Route::post('/settings/whatsapp', [\App\Http\Controllers\SuperAdmin\SettingsController::class, 'updateWhatsAppSettings'])->name('settings.whatsapp.update');
    Route::post('/settings/test-whatsapp', [\App\Http\Controllers\SuperAdmin\SettingsController::class, 'testWhatsAppConnection'])->name('settings.test-whatsapp');
    Route::post('/settings/email', [\App\Http\Controllers\SuperAdmin\SettingsController::class, 'updateEmailSettings'])->name('settings.email.update');
    Route::post('/settings/referral', [\App\Http\Controllers\SuperAdmin\SettingsController::class, 'updateReferralSettings'])->name('settings.referral.update');
    Route::post('/settings', [SettingController::class, 'update'])->name('settings.update');
    Route::post('/settings/wa-test', [SettingController::class, 'testWhatsApp'])->name('settings.wa.test');
    Route::post('/settings/whatsapp-test', [SettingController::class, 'testWhatsApp'])->name('settings.whatsapp.test');

    // Bulk Messages
    Route::post('bulk-messages/{id}/send-batch', [\App\Http\Controllers\SuperAdmin\SuperAdminBulkMessageController::class, 'sendBatch'])
        ->name('bulk-messages.send-batch');
    Route::resource('bulk-messages', \App\Http\Controllers\SuperAdmin\SuperAdminBulkMessageController::class)
        ->names('bulk-messages');

    // WhatsApp Gateway Routes - Superadmin menggunakan port 3001
    Route::get('/wa-gateway/status', [WhatsAppGatewayController::class, 'status'])
        ->name('wa-gateway.status');
    Route::get('/wa-gateway/qr', [WhatsAppGatewayController::class, 'qr'])
        ->name('wa-gateway.qr');
    Route::post('/wa-gateway/send-test', [WhatsAppGatewayController::class, 'sendTest'])
        ->name('wa-gateway.send-test');
    Route::post('/wa-gateway/reconnect', [WhatsAppGatewayController::class, 'reconnect'])
        ->name('wa-gateway.reconnect');
    Route::post('/wa-gateway/reset-session', [WhatsAppGatewayController::class, 'resetSession'])
        ->name('wa-gateway.reset-session');
    
    // WhatsApp Gateway Monitoring Dashboard (Multi-Tenant)
    Route::get('/wa-gateways', [\App\Http\Controllers\SuperAdmin\WhatsAppGatewayController::class, 'index'])
        ->name('wa-gateways.index');
    Route::get('/wa-gateways/status', [\App\Http\Controllers\SuperAdmin\WhatsAppGatewayController::class, 'status'])
        ->name('wa-gateways.status');
    Route::get('/wa-gateways/metrics', [\App\Http\Controllers\SuperAdmin\WhatsAppGatewayController::class, 'metrics'])
        ->name('wa-gateways.metrics');
    Route::get('/wa-gateways/{tenant}/qr', [\App\Http\Controllers\SuperAdmin\WhatsAppGatewayController::class, 'getQr'])
        ->name('wa-gateways.qr');
    Route::post('/wa-gateways/{tenant}/reconnect', [\App\Http\Controllers\SuperAdmin\WhatsAppGatewayController::class, 'reconnect'])
        ->name('wa-gateways.reconnect');
    Route::post('/wa-gateways/{tenant}/reset-session', [\App\Http\Controllers\SuperAdmin\WhatsAppGatewayController::class, 'resetSession'])
        ->name('wa-gateways.reset-session');
    Route::post('/wa-gateways/{tenant}/restart', [\App\Http\Controllers\SuperAdmin\WhatsAppGatewayController::class, 'restartService'])
        ->name('wa-gateways.restart');
    Route::get('/wa-gateways/{tenant}/logs', [\App\Http\Controllers\SuperAdmin\WhatsAppGatewayController::class, 'getLogs'])
        ->name('wa-gateways.logs');
    
    // Profile
    Route::get('/profile', function () {
        return \Inertia\Inertia::render('Profile/Edit', [
            'user' => auth()->user(),
        ]);
    })->name('profile.edit');
    Route::patch('/profile', [\App\Http\Controllers\ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [\App\Http\Controllers\ProfileController::class, 'updatePassword'])->name('profile.password.update');
});
