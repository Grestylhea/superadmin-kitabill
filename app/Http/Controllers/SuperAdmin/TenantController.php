<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use App\Models\Setting;
use App\Models\WhatsAppGatewayStatus;
use App\Models\SubscriptionPlan;

class TenantController extends Controller
{
    public function index(Request $request)
    {
        $query = Tenant::query();

        // Search
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'ilike', '%' . $request->search . '%')
                  ->orWhere('subdomain', 'ilike', '%' . $request->search . '%')
                  ->orWhere('email', 'ilike', '%' . $request->search . '%');
            });
        }

        // Filter by status
        if ($request->status) {
            if ($request->status === 'expired') {
                $query->where(function ($q) {
                    $q->where(function ($q2) {
                        $q2->where('status', 'trial')
                           ->whereNotNull('trial_ends_at')
                           ->where('trial_ends_at', '<', now());
                    })->orWhere(function ($q2) {
                        $q2->whereIn('status', ['active', 'suspended'])
                           ->whereNotNull('subscription_expires_at')
                           ->where('subscription_expires_at', '<', now());
                    });
                });
            } else {
                $query->where('status', $request->status);
                // Filter actual records so active/trial/suspended aren't secretly expired
                if ($request->status === 'trial') {
                    $query->where(function ($q) {
                        $q->whereNull('trial_ends_at')->orWhere('trial_ends_at', '>=', now());
                    });
                } elseif (in_array($request->status, ['active', 'suspended'])) {
                    $query->where(function ($q) {
                        $q->whereNull('subscription_expires_at')->orWhere('subscription_expires_at', '>=', now());
                    });
                }
            }
        }

        // Filter by plan
        if ($request->plan) {
            $query->where('subscription_plan', strtolower($request->plan));
        }

        // Sort
        $sortField = $request->sort_by ?? 'id';
        $sortDirection = $request->sort_dir ?? 'desc';
        $query->orderBy($sortField, $sortDirection);

        $tenants = $query->paginate(15)->through(function ($tenant) {
            $userCount = User::withoutGlobalScope(\App\Scopes\TenantScope::class)
                ->where('tenant_id', $tenant->id)
                ->count();

            // Evaluate true active/expired status based on dates
            $actualStatus = $tenant->status;
            if ($tenant->status === 'trial' && $tenant->trial_ends_at && \Carbon\Carbon::parse($tenant->trial_ends_at)->isPast()) {
                $actualStatus = 'expired';
            } elseif (in_array($tenant->status, ['active', 'suspended']) && $tenant->subscription_expires_at && \Carbon\Carbon::parse($tenant->subscription_expires_at)->isPast()) {
                $actualStatus = 'expired';
            }

            return [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'subdomain' => $tenant->subdomain,
                'email' => $tenant->email,
                'phone' => $tenant->phone,
                'subscription_plan' => $tenant->subscription_plan,
                'status' => $actualStatus,
                'original_status' => $tenant->status,
                'is_active' => $tenant->is_active, // ✅ Add is_active status
                'trial_ends_at' => $tenant->trial_ends_at,
                'subscription_expires_at' => $tenant->subscription_expires_at,
                'user_count' => $userCount,
                'username' => $tenant->username, // ✅ Add username
                'acs_enabled' => $tenant->acs_enabled, // ✅ Add ACS status
                'acs_tenant_id' => $tenant->acs_tenant_id,
                'created_at' => $tenant->created_at,
            ];
        });

        $plans = SubscriptionPlan::active()
            ->orderBy('price_monthly', 'asc')
            ->get();

        return Inertia::render('SuperAdmin/Tenants/Index', [
            'tenants' => $tenants,
            'plans' => $plans,
            'filters' => [
                'search' => $request->search,
                'status' => $request->status,
                'plan' => $request->plan,
                'sort_by' => $sortField,
                'sort_dir' => $sortDirection,
            ],
        ]);
    }

    public function show(Tenant $tenant)
    {
        $users = User::withoutGlobalScope(\App\Scopes\TenantScope::class)
            ->where('tenant_id', $tenant->id)
            ->get();

        $recentPayments = \App\Models\Payment::where('tenant_id', $tenant->id)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $totalCommissions = \App\Models\ReferralCommission::where('referral_tenant_id', $tenant->id)->sum('amount');
        $referredCount = Tenant::where('referrer_id', $tenant->id)->count();

        return Inertia::render('SuperAdmin/Tenants/Show', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'subdomain' => $tenant->subdomain,
                'email' => $tenant->email,
                'phone' => $tenant->phone,
                'address' => $tenant->address,
                'subscription_plan' => $tenant->subscription_plan,
                'status' => $tenant->status,
                'trial_ends_at' => $tenant->trial_ends_at,
                'subscription_expires_at' => $tenant->subscription_expires_at,
                'username' => $tenant->username,
                'referral_code' => $tenant->referral_code,
                'referral_balance' => $tenant->referral_balance,
                'referral_commission_rate' => $tenant->referral_commission_rate,
                'referrer' => $tenant->referrer ? [
                    'id' => $tenant->referrer->id,
                    'name' => $tenant->referrer->name,
                    'subdomain' => $tenant->referrer->subdomain,
                ] : null,
                'total_commissions' => $totalCommissions,
                'referred_tenants_count' => $referredCount,
                'referral_system_enabled' => (bool) $tenant->referral_system_enabled,
                'created_at' => $tenant->created_at,
                'updated_at' => $tenant->updated_at,
                'acs_enabled' => $tenant->acs_enabled,
                'acs_api_key' => $tenant->acs_api_key,
                'acs_tenant_id' => $tenant->acs_tenant_id,
            ],
            'users' => $users,
            'recentPayments' => $recentPayments,
        ]);
    }

    public function create()
    {
        $plans = SubscriptionPlan::active()
            ->orderBy('price_monthly', 'asc')
            ->get();

        return Inertia::render('SuperAdmin/Tenants/Create', [
            'plans' => $plans
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'subdomain' => 'required|string|max:50|unique:tenants,subdomain|regex:/^[a-z0-9-]+$/',
            'email' => 'required|email|unique:tenants,email',
            'phone' => 'required|string|max:20', // ✅ Required, not nullable
            'address' => 'nullable|string',
            'subscription_plan' => 'required|string|max:50',
            'status' => 'required|in:trial,active,suspended',
            'trial_days' => 'nullable|integer|min:0',
            'username' => 'required|string|max:255|alpha_dash|unique:tenants,username', // ✅ Add username validation
            'timezone' => 'nullable|string|in:Asia/Jakarta,Asia/Makassar,Asia/Jayapura', // ✅ Add timezone
            'admin_name' => 'required|string|max:255',
            'admin_email' => 'required|email|unique:users,email',
            'admin_password' => 'required|string|min:8',
        ]);

        // Generate database name from subdomain
        $subdomain = Str::lower($validated['subdomain']);
        $dbName = 'tenant_' . preg_replace('/[^a-z0-9_]/', '_', $subdomain);

        try {
            DB::beginTransaction();

            // 1. Create tenant record
            $tenant = Tenant::create([
                'name' => $validated['name'],
                'subdomain' => $subdomain,
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'address' => $validated['address'] ?? null,
                'username' => $validated['username'],
                'database' => $dbName,
                'subscription_plan' => strtolower($validated['subscription_plan']),
                'status' => $validated['status'],
                'is_active' => true, // Superadmin created tenants are active by default
                'trial_ends_at' => $validated['status'] === 'trial' && isset($validated['trial_days']) 
                    ? now()->addDays($validated['trial_days']) 
                    : null,
            ]);

            // 2. Create database
            $this->createTenantDatabase($dbName);

            // 3. Run migrations on tenant database
            $this->runTenantMigrations($dbName);

            // 4. Create admin user in central database
            $user = User::withoutGlobalScope(\App\Scopes\TenantScope::class)->create([
                'tenant_id' => $tenant->id,
                'name' => $validated['admin_name'],
                'username' => $validated['username'],
                'email' => $validated['admin_email'],
                'phone' => $validated['phone'],
                'password' => Hash::make($validated['admin_password']),
                'email_verified_at' => now(),
                'is_super_admin' => false,
                'status' => 'active',
            ]);

            // Assign admin role
            try {
                $user->assignRole('admin');
            } catch (\Exception $e) {
                Log::warning('Failed to assign admin role: ' . $e->getMessage());
            }

            // 5. Seed default settings
            $this->seedTenantSettings($tenant, $validated);

            DB::commit();

            // Dispatch Registered Event (Sends WhatsApp)
            event(new \App\Events\TenantRegistered($tenant));

            return redirect()->route('superadmin.tenants.index')
                ->with('success', 'Tenant created and provisioned successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Tenant creation failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return back()->with('error', 'Gagal membuat tenant: ' . $e->getMessage());
        }
    }

    private function createTenantDatabase($dbName)
    {
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");

        if ($driver === 'pgsql') {
            $dbExists = DB::select("SELECT 1 FROM pg_database WHERE datname = ?", [$dbName]);
            if (empty($dbExists)) {
                DB::statement("CREATE DATABASE \"{$dbName}\"");
                Log::info('Tenant database created: ' . $dbName);
            }
        } else {
            DB::statement("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
            Log::info('Tenant database created: ' . $dbName);
        }
    }

    private function runTenantMigrations($dbName)
    {
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");

        config(['database.connections.tenant' => [
            'driver' => $driver,
            'host' => config("database.connections.{$connection}.host"),
            'port' => config("database.connections.{$connection}.port"),
            'database' => $dbName,
            'username' => config("database.connections.{$connection}.username"),
            'password' => config("database.connections.{$connection}.password"),
            'charset' => config("database.connections.{$connection}.charset", 'utf8mb4'),
            'collation' => config("database.connections.{$connection}.collation", 'utf8mb4_unicode_ci'),
            'prefix' => '',
        ]]);

        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--force' => true,
        ]);
        Log::info('Migrations completed for: ' . $dbName);
    }

    private function seedTenantSettings($tenant, $validated)
    {
        $settings = [
            'company_name' => $tenant->name,
            'company_email' => $tenant->email,
            'company_phone' => $tenant->phone,
            'company_whatsapp' => $tenant->phone,
            'app_timezone' => $validated['timezone'] ?? 'Asia/Jakarta',
        ];

        foreach ($settings as $key => $value) {
            Setting::updateOrCreate(
                ['tenant_id' => $tenant->id, 'key' => $key],
                ['value' => $value]
            );
        }

        // Initialize WhatsApp Gateway status
        try {
            WhatsAppGatewayStatus::updateOrCreate(
                ['tenant_id' => $tenant->id],
                ['status' => 'DISCONNECTED', 'last_checked_at' => now()]
            );
        } catch (\Exception $e) {
            Log::warning('Failed to initialize WhatsApp status: ' . $e->getMessage());
        }
    }

    public function edit(Tenant $tenant)
    {
        $plans = SubscriptionPlan::active()
            ->orderBy('price_monthly', 'asc')
            ->get();

        return Inertia::render('SuperAdmin/Tenants/Edit', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'subdomain' => $tenant->subdomain,
                'email' => $tenant->email,
                'phone' => $tenant->phone,
                'address' => $tenant->address,
                'subscription_plan' => $tenant->subscription_plan,
                'status' => $tenant->status,
                'username' => $tenant->username, // ✅ Add username
                'trial_ends_at' => $tenant->trial_ends_at,
                'subscription_expires_at' => $tenant->subscription_expires_at,
            ],
            'plans' => $plans
        ]);
    }

    public function update(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|alpha_dash|unique:tenants,username,' . $tenant->id,
            'email' => 'required|email|unique:tenants,email,' . $tenant->id,
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'subscription_plan' => 'required|string|max:50',
            'status' => 'required|in:trial,active,suspended,expired',
            'trial_ends_at' => 'nullable|date',
            'subscription_expires_at' => 'nullable|date',
        ]);

        if (isset($validated['subscription_plan'])) {
            $validated['subscription_plan'] = strtolower($validated['subscription_plan']);
        }

        // ✅ Reset reminder flags jika subscription_expires_at diperbarui (renewal)
        $oldExpiresAt = $tenant->subscription_expires_at?->format('Y-m-d');
        $newExpiresAt = isset($validated['subscription_expires_at']) 
            ? \Carbon\Carbon::parse($validated['subscription_expires_at'])->format('Y-m-d') 
            : null;

        if ($newExpiresAt && $newExpiresAt !== $oldExpiresAt) {
            $validated['subscription_reminder_h7_sent_at'] = null;
            $validated['subscription_reminder_h3_sent_at'] = null;
            $validated['subscription_reminder_h1_sent_at'] = null;
            $validated['subscription_suspended_notified_at'] = null;
            \Illuminate\Support\Facades\Log::info("[TENANT_RENEWAL] Resetting reminder flags for Tenant #{$tenant->id} due to expiry date change.", [
                'old' => $oldExpiresAt,
                'new' => $newExpiresAt
            ]);
        }
        
        $tenant->update($validated);

        // ✅ SYNC: Update associated Admin User in central database
        // We find the user by tenant_id and original email/username
        try {
            $user = User::withoutGlobalScope(\App\Scopes\TenantScope::class)
                ->where('tenant_id', $tenant->id)
                ->where(function($q) use ($tenant) {
                    $q->where('email', $tenant->email)
                      ->orWhere('username', $tenant->username);
                })
                ->first();

            if ($user) {
                $user->update([
                    'phone' => $tenant->phone,
                    'email' => $tenant->email,
                    'username' => $tenant->username, // Update if changed
                ]);
                Log::info("Synced Tenant #{$tenant->id} data to User #{$user->id}");
            }
        } catch (\Exception $e) {
            Log::warning("Failed to sync Tenant #{$tenant->id} data to User: " . $e->getMessage());
        }

        return redirect()->route('superadmin.tenants.index')
            ->with('success', 'Tenant updated successfully!');
    }

    public function destroy(Tenant $tenant)
    {
        if ($tenant->id === 1 || $tenant->is_system) {
            return back()->with('error', 'Cannot delete system/root tenant!');
        }

        $summary = [];
        try {
            $this->performTenantDeletion($tenant, $summary);
            return redirect()->route('superadmin.tenants.index')
                ->with('success', 'Tenant deleted successfully!')
                ->with('deletion_summary', $summary);

        } catch (\Exception $e) {
            \Log::error("Tenant deletion failed for ID {$tenant->id}: " . $e->getMessage());
            return back()->with('error', 'Deletion failed: ' . $e->getMessage());
        }
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:tenants,id'
        ]);

        $ids = $validated['ids'];
        $successCount = 0;
        $errors = [];
        $totalSummary = [];

        foreach ($ids as $id) {
            if ($id == 1) continue; // Skip system tenant

            $tenant = Tenant::find($id);
            if (!$tenant || $tenant->is_system) continue;

            try {
                $summary = ["Tenant: {$tenant->name}"];
                $this->performTenantDeletion($tenant, $summary);
                $successCount++;
                $totalSummary = array_merge($totalSummary, $summary);
            } catch (\Exception $e) {
                $errors[] = "Failed to delete tenant #{$id}: " . $e->getMessage();
            }
        }

        if ($successCount > 0) {
            $message = "Successfully deleted {$successCount} tenants.";
            if (count($errors) > 0) {
                $message .= " However, " . count($errors) . " failures occurred.";
            }
            return redirect()->route('superadmin.tenants.index')
                ->with('success', $message)
                ->with('deletion_summary', $totalSummary);
        }

        return back()->with('error', 'Failed to delete selected tenants: ' . implode(', ', $errors));
    }

    public function bulkDestroyExpired(Request $request)
    {
        $expiredTenants = Tenant::where(function($q) {
            $q->where(function($q2) {
                $q2->where('status', 'trial')
                   ->whereNotNull('trial_ends_at')
                   ->where('trial_ends_at', '<', now());
            })->orWhere(function($q2) {
                $q2->whereIn('status', ['active', 'suspended'])
                   ->whereNotNull('subscription_expires_at')
                   ->where('subscription_expires_at', '<', now());
            });
        })->get();

        $successCount = 0;
        $errors = [];
        $totalSummary = [];

        foreach ($expiredTenants as $tenant) {
            if ($tenant->id == 1 || $tenant->is_system) continue;

            try {
                $summary = ["Tenant: {$tenant->name}"];
                $this->performTenantDeletion($tenant, $summary);
                $successCount++;
                $totalSummary = array_merge($totalSummary, $summary);
            } catch (\Exception $e) {
                $errors[] = "Failed to delete tenant #{$tenant->id}: " . $e->getMessage();
            }
        }

        if ($successCount > 0) {
            $message = "Successfully deleted {$successCount} expired tenants.";
            if (count($errors) > 0) {
                $message .= " Namun ada " . count($errors) . " kegagalan parsial.";
            }
            return redirect()->route('superadmin.tenants.index')
                ->with('success', $message)
                ->with('deletion_summary', $totalSummary);
        }

        if (count($errors) > 0) {
            return back()->with('error', 'Gagal menghapus tenant kedaluwarsa: ' . implode(', ', $errors));
        }

        return back()->with('success', 'Tidak ada tenant expired terbaru untuk dihapus.');
    }

    protected function performTenantDeletion(Tenant $tenant, &$summary)
    {
        $tenantId = $tenant->id;
        $subdomain = $tenant->subdomain;

        // PHASE A: Mark for Deletion
        $tenant->update([
            'deletion_status' => 'deleting',
            'deletion_requested_at' => now(),
            'deletion_requested_by' => auth()->id(),
            'is_active' => false,
            'status' => 'suspended'
        ]);
        $summary[] = "Tenant marked for deletion.";

        // Log activity
        \App\Models\ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'tenant.deleted',
            'model_type' => 'Tenant',
            'model_id' => $tenantId,
            'description' => "SuperAdmin " . auth()->user()->name . " deleted tenant: {$tenant->name} ({$subdomain})",
            'properties' => [
                'tenant_id' => $tenantId,
                'subdomain' => $subdomain,
                'name' => $tenant->name
            ],
            'ip_address' => request()->ip()
        ]);
        $summary[] = "Audit log recorded.";

        // PHASE B: Operational & Filesystem Cleanup
        $this->cleanupOperationalResources($tenant, $summary);

        // PHASE B.2: Cloud/WAKita Cleanup
        $this->deleteWakitaAccount($tenant, $summary);

        // PHASE C: Database Cleanup
        $this->cleanupDatabaseRecords($tenantId, $summary);

        // Final Step: Remove Tenant Row
        $tenant->delete();
        $summary[] = "Tenant record removed from database.";
    }

    protected function cleanupOperationalResources(Tenant $tenant, &$summary)
    {
        $tenantId = $tenant->id;
        $serviceName = "whatsapp-gateway-tenant@{$tenantId}.service";
        $sessionDir = "/opt/whatsapp-gateway-tenant-{$tenantId}";

        // 1. Safe Systemctl Stop/Disable
        try {
            // Check if unit exists
            $check = shell_exec("systemctl list-unit-files $serviceName");
            if (str_contains($check, $serviceName)) {
                shell_exec("sudo systemctl stop $serviceName 2>/dev/null");
                shell_exec("sudo systemctl disable $serviceName 2>/dev/null");
                $summary[] = "3. WhatsApp service stopped and disabled.";
            } else {
                $summary[] = "3. WhatsApp service unit not found (skipped).";
            }
        } catch (\Exception $e) {
            $summary[] = "3. WhatsApp service cleanup error (non-fatal): " . $e->getMessage();
        }

        // 2. Safe Filesystem Removal
        try {
            if (is_dir($sessionDir)) {
                shell_exec("sudo rm -rf " . escapeshellarg($sessionDir));
                $summary[] = "4. WhatsApp session directory removed.";
            } else {
                $summary[] = "4. WhatsApp session directory not found (skipped).";
            }
        } catch (\Exception $e) {
            $summary[] = "4. Filesystem removal error (non-fatal): " . $e->getMessage();
        }
    }

    protected function deleteWakitaAccount(Tenant $tenant, &$summary)
    {
        if (!$tenant->wakita_user_id) {
            $summary[] = "WAKita Cloud Account deletion skipped (No wakita_user_id attached).";
            return;
        }

        try {
            $wakitaBase    = rtrim(config('env.WAKITA_BASE_URL', env('WAKITA_BASE_URL', 'https://wa.kitabill.site')), '/');
            $adminUsername = config('env.WAKITA_SUPERADMIN_USERNAME', env('WAKITA_SUPERADMIN_USERNAME', 'admin'));
            $adminPassword = config('env.WAKITA_SUPERADMIN_PASSWORD', env('WAKITA_SUPERADMIN_PASSWORD', 'user123'));

            // 1. Admin Login
            $loginRes = \Illuminate\Support\Facades\Http::timeout(10)->post("{$wakitaBase}/api/auth/login", [
                'username' => $adminUsername,
                'password' => $adminPassword,
            ]);

            if (!$loginRes->successful()) {
                throw new \Exception("Superadmin login failed: " . $loginRes->body());
            }

            $jwt = $loginRes->json('token');

            // 2. Delete User
            $deleteRes = \Illuminate\Support\Facades\Http::timeout(10)->withToken($jwt)
                        ->delete("{$wakitaBase}/api/admin/users/{$tenant->wakita_user_id}");

            if ($deleteRes->successful()) {
                $summary[] = "WAKita Cloud Account (ID: {$tenant->wakita_user_id}) successfully deleted & deactivated.";
            } else {
                throw new \Exception("Delete API returned status " . $deleteRes->status() . ": " . $deleteRes->body());
            }
        } catch (\Exception $e) {
            \Log::error("Failed to delete WAKita account for Tenant #{$tenant->id}: " . $e->getMessage());
            $summary[] = "WAKita Cloud Account deletion failed (non-fatal): " . $e->getMessage();
        }
    }

    protected function cleanupDatabaseRecords($tenantId, &$summary)
    {
        // 1. Discover all tables with tenant_id column
        $tables = \DB::select("
            SELECT table_name 
            FROM information_schema.columns 
            WHERE column_name = 'tenant_id' 
            AND table_schema = 'public'
            AND table_name NOT IN ('tenants', 'activity_logs')
        ");

        \DB::beginTransaction();
        try {
            $totalDeleted = 0;
            foreach ($tables as $table) {
                $tableName = $table->table_name;
                
                // Special handling for users table to be safe
                if ($tableName === 'users') {
                    $count = \DB::table($tableName)
                        ->where('tenant_id', $tenantId)
                        ->where('is_super_admin', false) // Extra safety check
                        ->delete();
                } else {
                    $count = \DB::table($tableName)
                        ->where('tenant_id', $tenantId)
                        ->delete();
                }
                
                if ($count > 0) {
                    $totalDeleted += $count;
                }
            }
            
            // Handle activity_logs separately to avoid deleting the superadmin's log we just created
            $logCount = \DB::table('activity_logs')
                ->where('tenant_id', $tenantId)
                ->delete();
            
            $totalDeleted += $logCount;

            \DB::commit();
            $summary[] = "5. Database cleanup completed (Cleared " . count($tables) . " tables, total {$totalDeleted} records).";
        } catch (\Exception $e) {
            \DB::rollBack();
            throw $e;
        }
    }

    public function activate(Tenant $tenant)
    {
        $tenant->update([
            'status' => 'active',
            'is_active' => true, // ✅ Set is_active to true when activating
            // ✅ Reset suspension notif flag agar bisa dikirim ulang jika lapse lagi di masa depan
            'subscription_suspended_notified_at' => null,
        ]);

        return back()->with('success', 'Tenant activated successfully!');
    }

    public function suspend(Tenant $tenant)
    {
        $tenant->update([
            'status' => 'suspended',
            'is_active' => false, // ✅ Set is_active to false when suspending (blocks subdomain access)
        ]);

        return back()->with('success', 'Tenant suspended successfully! Subdomain access has been blocked.');
    }

    public function extendTrial(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'days' => 'required|integer|min:1|max:365',
        ]);

        $currentTrialEnds = $tenant->trial_ends_at ?? now();
        $newTrialEnds = \Carbon\Carbon::parse($currentTrialEnds)->addDays($validated['days']);

        $tenant->update([
            'trial_ends_at' => $newTrialEnds,
            'status' => 'trial',
            // ✅ Reset reminder flags agar pengingat trial periode baru bisa terkirim
            'subscription_reminder_h7_sent_at' => null,
            'subscription_reminder_h3_sent_at' => null,
            'subscription_reminder_h1_sent_at' => null,
            'subscription_suspended_notified_at' => null,
        ]);

        return back()->with('success', "Trial extended by {$validated['days']} days!");
    }

    public function updateReferralRate(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'rate' => 'required|numeric|min:0|max:100',
        ]);

        $tenant->update([
            'referral_commission_rate' => $validated['rate'],
        ]);

        return back()->with('success', 'Referral commission rate updated successfully!');
    }

    public function toggleReferral(Tenant $tenant)
    {
        $tenant->update([
            'referral_system_enabled' => !$tenant->referral_system_enabled,
        ]);

        return back()->with('success', 'Referral system status updated successfully!');
    }
}



