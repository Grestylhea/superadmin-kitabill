<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Models\Router;
use App\Models\Customer;
use App\Services\MikrotikService;
use RouterOS\Query;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PackageController extends Controller
{
    public function __construct()
    {
        // pakai cara middleware lama (non-static)
        $this->middleware('can:view_packages')->only(['index', 'show']);
        $this->middleware('can:create_package')->only(['create', 'store']);
        $this->middleware('can:edit_package')->only(['edit', 'update']);
        $this->middleware('can:delete_package')->only(['destroy']);
    }

    public function index(Request $request)
    {
        $query = Package::withCount('customers');

        // Filter by status
        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        // Filter by billing cycle
        if ($request->filled('billing_cycle')) {
            $query->where('billing_cycle', $request->billing_cycle);
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%");
        }

        // Filter by connection type (available_for)
        if ($request->filled('connection_type')) {
            $connectionType = $request->connection_type;
            // Filter packages yang memiliki connection type ini di available_for array
            $query->whereJsonContains('available_for', $connectionType);
        }

        // ✅ Filter by router
        if ($request->filled('router_id')) {
            $routerId = $request->router_id;
            $query->whereHas('routers', function($q) use ($routerId) {
                $q->where('routers.id', $routerId);
            });
        }

        $packages = $query->latest()->paginate(10)->appends($request->query());

        // Get Isolir Profile Settings
        $isolirSettings = [
            'profile_name' => setting('isolir_profile_name', 'PROFIL-ISOLIR'),
            'download_speed' => setting('isolir_download_speed', 1),
            'upload_speed' => setting('isolir_upload_speed', 1),
            'description' => setting('isolir_description', 'Profile untuk customer suspended'),
        ];

        // Get routers for filter dropdown
        $routers = Router::where('is_active', true)->get();

        return view('packages.index', compact('packages', 'isolirSettings', 'routers'));
    }

    public function create()
    {
        $routers = Router::where('is_active', true)->get();
        return view('packages.create', compact('routers'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'             => 'required|string|max:255',
            'description'      => 'nullable|string',
            'download_speed'   => 'required|numeric|min:0',
            'upload_speed'     => 'required|numeric|min:0',
            'price'            => 'required|numeric|min:0',
            'has_fup'          => 'nullable|boolean',
            'fup_quota'        => 'nullable|numeric|min:0',
            'fup_speed'        => 'nullable|numeric|min:0',
            'billing_cycle'    => 'required|string',
            'grace_period'     => 'required|integer|min:0',
            'burst_limit'      => 'nullable|numeric|min:0',
            'priority'         => 'required|integer|min:1|max:10',
            'connection_limit' => 'nullable|integer|min:0',
            'available_for'    => 'nullable|array',
            'available_for.*'  => 'string',
            'is_active'        => 'nullable|boolean',

            'custom_expire_day'  => 'nullable|integer|min:1|max:31',
            'custom_expire_time' => 'nullable|date_format:H:i',
            
            'router_id'        => 'required|exists:routers,id',
            'connection_type'  => 'required|in:pppoe,hotspot',
        ]);

        $package = new Package();

        $package->name             = $validated['name'];
        $package->description      = $validated['description'] ?? null;
        $package->download_speed   = $validated['download_speed'];
        $package->upload_speed     = $validated['upload_speed'];
        $package->price            = $validated['price'];
        $package->has_fup          = $request->boolean('has_fup');
        $package->fup_quota        = $validated['fup_quota'] ?? null;
        $package->fup_speed        = $validated['fup_speed'] ?? null;
        $package->billing_cycle    = $validated['billing_cycle'];
        $package->grace_period     = $validated['grace_period'];
        $package->burst_limit      = $validated['burst_limit'] ?? null;
        $package->priority         = $validated['priority'];
        $package->connection_limit = $validated['connection_limit'] ?? null;
        $package->available_for    = $validated['available_for'] ?? [];
        $package->is_active        = $request->boolean('is_active');

        $package->custom_expire_day  = $validated['custom_expire_day'] ?? null;
        $package->custom_expire_time = isset($validated['custom_expire_time'])
            ? $validated['custom_expire_time'] . ':00'
            : null;

        $package->save();

        // Simpan router dan connection type ke pivot table
        $router = Router::findOrFail($validated['router_id']);
        $package->routers()->sync([
            $router->id => ['connection_type' => $validated['connection_type']]
        ]);

        // === Sync ke Mikrotik (PPP Profile atau Hotspot Profile) ===
        // Sync ke router yang dipilih sesuai connection type
        $this->syncPackageToSelectedRouter($package, $router, $validated['connection_type']);

        return redirect()
            ->route('packages.index')
            ->with('success', 'Paket berhasil dibuat.');
    }

    public function show(Package $package)
    {
        $package->loadCount('customers');
        $package->load('routers'); // Load routers dengan pivot connection_type
        return view('packages.show', compact('package'));
    }

    /**
     * API: Get package info (router and connection_type) for AJAX
     */
    public function getPackageInfo(Package $package)
    {
        $package->load('routers');
        
        $router = $package->routers->first();
        
        // Format custom_expire_time untuk ditampilkan (H:i)
        $customExpireTime = $package->custom_expire_time 
            ? \Carbon\Carbon::parse($package->custom_expire_time)->format('H:i')
            : null;
        
        return response()->json([
            'success' => true,
            'router_id' => $router ? $router->id : null,
            'router_name' => $router ? $router->name : null,
            'connection_type' => $router ? $router->pivot->connection_type : null,
            'connection_type_display' => $router ? ucfirst($router->pivot->connection_type) : null,
            'custom_expire_day' => $package->custom_expire_day,
            'custom_expire_time' => $customExpireTime,
        ]);
    }

    /**
     * API: Get packages filtered by router and connection_type for AJAX
     */
    public function getPackagesByRouterAndType(Request $request)
    {
        $routerId = $request->input('router_id');
        $connectionType = $request->input('connection_type'); // 'pppoe' or 'hotspot'

        $query = Package::where('is_active', true)
            ->with('routers');

        // Filter by router and connection_type if provided
        if ($routerId && $connectionType) {
            $query->whereHas('routers', function($q) use ($routerId, $connectionType) {
                $q->where('routers.id', $routerId)
                  ->where('package_router.connection_type', $connectionType);
            });
        } elseif ($routerId) {
            // Filter hanya by router
            $query->whereHas('routers', function($q) use ($routerId) {
                $q->where('routers.id', $routerId);
            });
        } elseif ($connectionType) {
            // Filter hanya by connection_type
            $query->whereHas('routers', function($q) use ($connectionType) {
                $q->where('package_router.connection_type', $connectionType);
            });
        }

        $packages = $query->get();

        $packagesData = $packages->map(function($package) {
            $router = $package->routers->first();
            return [
                'id' => $package->id,
                'name' => $package->name,
                'speed_label' => $package->getSpeedLabel(),
                'price' => $package->getFormattedPrice(),
                'router_id' => $router ? $router->id : null,
                'connection_type' => $router ? $router->pivot->connection_type : null,
            ];
        });

        return response()->json([
            'success' => true,
            'packages' => $packagesData,
        ]);
    }

    public function edit(Package $package)
    {
        $package->loadCount('customers');
        $package->load('routers'); // Load routers dengan pivot connection_type
        $routers = Router::where('is_active', true)->get();
        return view('packages.edit', compact('package', 'routers'));
    }

    public function update(Request $request, Package $package)
    {
        $validated = $request->validate([
            'name'             => 'required|string|max:255',
            'description'      => 'nullable|string',
            'download_speed'   => 'required|numeric|min:0',
            'upload_speed'     => 'required|numeric|min:0',
            'price'            => 'required|numeric|min:0',
            'has_fup'          => 'nullable|boolean',
            'fup_quota'        => 'nullable|numeric|min:0',
            'fup_speed'        => 'nullable|numeric|min:0',
            'billing_cycle'    => 'required|string',
            'grace_period'     => 'required|integer|min:0',
            'burst_limit'      => 'nullable|numeric|min:0',
            'priority'         => 'required|integer|min:1|max:10',
            'connection_limit' => 'nullable|integer|min:0',
            'available_for'    => 'nullable|array',
            'available_for.*'  => 'string',
            'is_active'        => 'nullable|boolean',

            'custom_expire_day'  => 'nullable|integer|min:1|max:31',
            'custom_expire_time' => 'nullable|date_format:H:i',
            
            'router_id'        => 'required|exists:routers,id',
            'connection_type'  => 'required|in:pppoe,hotspot',
        ]);

        // Simpan router lama untuk cek apakah berubah
        $package->load('routers');
        $oldRouter = $package->routers->first();
        $oldConnectionType = $oldRouter ? $oldRouter->pivot->connection_type : null;

        $package->name             = $validated['name'];
        $package->description      = $validated['description'] ?? null;
        $package->download_speed   = $validated['download_speed'];
        $package->upload_speed     = $validated['upload_speed'];
        $package->price            = $validated['price'];
        $package->has_fup          = $request->boolean('has_fup');
        $package->fup_quota        = $validated['fup_quota'] ?? null;
        $package->fup_speed        = $validated['fup_speed'] ?? null;
        $package->billing_cycle    = $validated['billing_cycle'];
        $package->grace_period     = $validated['grace_period'];
        $package->burst_limit      = $validated['burst_limit'] ?? null;
        $package->priority         = $validated['priority'];
        $package->connection_limit = $validated['connection_limit'] ?? null;
        $package->available_for    = $validated['available_for'] ?? [];
        $package->is_active        = $request->boolean('is_active');

        // Simpan nilai lama untuk cek apakah berubah
        $oldExpireDay = $package->custom_expire_day;
        $oldExpireTime = $package->custom_expire_time;
        
        $package->custom_expire_day  = $validated['custom_expire_day'] ?? null;
        $package->custom_expire_time = isset($validated['custom_expire_time'])
            ? $validated['custom_expire_time'] . ':00'
            : null;

        $package->save();

        // 🔄 Jika custom_expire_day atau custom_expire_time berubah, sync semua customer dengan paket ini
        $expireDayChanged = ($oldExpireDay != $package->custom_expire_day) || ($oldExpireTime != $package->custom_expire_time);
        $syncedCount = 0;
        
        if ($expireDayChanged && $package->custom_expire_day) {
            try {
                $customers = \App\Models\Customer::where('package_id', $package->id)->get();
                
                $today = now(); // Tanggal hari ini untuk acuan
            $expireDay = (int) $package->custom_expire_day; // Pastikan integer
            
            foreach ($customers as $customer) {
                    // ✅ LOGIKA: Gunakan tanggal HARI INI sebagai acuan
                    $nextBilling = $today->copy();
                    
                    // Jika tanggal expire sudah lewat di bulan ini → set ke bulan depan
                    if ($today->day > $expireDay) {
                        $nextBilling->addMonth();
                    }
                    // Jika belum lewat, tetap di bulan ini
                    
                    // Set ke custom_expire_day (pastikan integer)
                    $nextBilling->day($expireDay);
                    
                    // Set waktu dari paket atau default 23:59
                    if ($package->custom_expire_time) {
                        $time = \Carbon\Carbon::parse($package->custom_expire_time);
                        $nextBilling->setTime($time->hour, $time->minute);
                    } else {
                        $nextBilling->setTime(23, 59);
                    }
                    
                    $customer->next_billing_date = $nextBilling;
                    $customer->save();
                    $syncedCount++;
                }
                
                if ($syncedCount > 0) {
                    \Log::info("Auto-synced billing dates for {$syncedCount} customers after package update", [
                        'package_id' => $package->id,
                        'package_name' => $package->name,
                        'custom_expire_day' => $package->custom_expire_day
                    ]);
                }
            } catch (\Exception $e) {
                \Log::error("Failed to auto-sync billing dates after package update: " . $e->getMessage());
            }
        }

        // Handle router dan connection type changes
        $newRouter = Router::findOrFail($validated['router_id']);
        $newConnectionType = $validated['connection_type'];
        
        // Jika router atau connection type berubah, hapus dari router lama dan tambah ke router baru
        if ($oldRouter && ($oldRouter->id != $newRouter->id || $oldConnectionType != $newConnectionType)) {
            // Hapus profile dari router lama
            if ($oldRouter) {
                try {
                    $oldMikrotik = new MikrotikService($oldRouter);
                    $this->deletePackageProfileFromRouter($oldMikrotik, $package->name, $oldConnectionType);
                    Log::info("🗑️ Deleted package '{$package->name}' profile from old router: {$oldRouter->name} ({$oldConnectionType})");
                } catch (\Exception $e) {
                    Log::error("❌ Failed to delete package '{$package->name}' from old router {$oldRouter->name}: " . $e->getMessage());
                }
            }
        }
        
        // Update pivot table dengan router dan connection type baru
        $package->routers()->sync([
            $newRouter->id => ['connection_type' => $newConnectionType]
        ]);
        
        // Sync ke router baru
        $this->syncPackageToSelectedRouter($package, $newRouter, $newConnectionType);

        $successMessage = 'Paket berhasil diupdate.';
        if ($syncedCount > 0) {
            $successMessage .= " {$syncedCount} customer billing dates telah di-sync otomatis ke tanggal {$package->custom_expire_day}.";
        }
        
        return redirect()
            ->route('packages.index')
            ->with('success', $successMessage);
    }

    public function destroy(Package $package)
    {
        if ($package->customers()->count() > 0) {
            return back()->with('error', 'Tidak bisa menghapus paket yang masih digunakan customer!');
        }

        $package->delete();

        return redirect()
            ->route('packages.index')
            ->with('success', 'Paket berhasil dihapus!');
    }

    /**
     * Update Isolir Profile Settings
     */
    public function updateIsolirProfile(Request $request)
    {
        $validated = $request->validate([
            'isolir_profile_name' => 'required|string|max:255',
            'isolir_download_speed' => 'required|numeric|min:0',
            'isolir_upload_speed' => 'required|numeric|min:0',
            'isolir_description' => 'nullable|string|max:500',
        ]);

        // Save to settings table
        setting([
            'isolir_profile_name' => $validated['isolir_profile_name'],
            'isolir_download_speed' => $validated['isolir_download_speed'],
            'isolir_upload_speed' => $validated['isolir_upload_speed'],
            'isolir_description' => $validated['isolir_description'] ?? 'Profile untuk customer suspended',
        ]);

        // Sync to all Mikrotik routers
        $routers = Router::where('is_active', true)->get();
        $syncedCount = 0;
        $errors = [];

        foreach ($routers as $router) {
            try {
                $mikrotik = new MikrotikService($router);
                
                // Create/Update PROFIL-ISOLIR profile
                $mikrotik->createProfile(
                    $validated['isolir_profile_name'],
                    $validated['isolir_download_speed'],
                    $validated['isolir_upload_speed']
                );
                
                $syncedCount++;
                \Log::info("Isolir profile synced to router: {$router->name}");
                
            } catch (\Exception $e) {
                $errors[] = "Router {$router->name}: " . $e->getMessage();
                \Log::error("Failed to sync isolir profile to {$router->name}: " . $e->getMessage());
            }
        }

        if ($syncedCount > 0) {
            $message = "✅ Profil Isolir berhasil disimpan dan di-sync ke {$syncedCount} router!";
            if (count($errors) > 0) {
                $message .= " (Beberapa router gagal: " . implode(', ', $errors) . ")";
            }
            return redirect()->back()->with('success', $message);
        } else {
            return redirect()->back()->with('error', '❌ Gagal sync ke semua router: ' . implode(', ', $errors));
        }
    }

    /**
     * Sync Package Profile ke router yang dipilih sesuai connection type
     */
    private function syncPackageToSelectedRouter(Package $package, Router $router, string $connectionType): void
    {
        try {
            $mikrotik = new MikrotikService($router);
            
            if ($connectionType === 'pppoe') {
                // Sync sebagai PPP Profile
                $mikrotik->syncPackageProfile($package);
            } else if ($connectionType === 'hotspot') {
                // Sync sebagai Hotspot Profile
                $mikrotik->createHotspotProfile(
                    $package->name,
                    $package->download_speed,
                    $package->upload_speed
                );
            }
            
            Log::info("✅ Package '{$package->name}' synced to router: {$router->name} ({$connectionType})");
        } catch (\Exception $e) {
            Log::error("❌ Failed to sync package '{$package->name}' to router {$router->name}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Hapus package profile dari router
     */
    private function deletePackageProfileFromRouter(MikrotikService $mikrotik, string $profileName, string $connectionType): void
    {
        try {
            $client = $mikrotik->getClient();
            
            if ($connectionType === 'pppoe') {
                // Hapus PPP Profile
                $query = new Query('/ppp/profile/print');
                $query->where('name', $profileName);
                $profiles = $client->query($query)->read();
                
                if (!empty($profiles)) {
                    $query = new Query('/ppp/profile/remove');
                    $query->equal('.id', $profiles[0]['.id']);
                    $client->query($query)->read();
                }
            } else if ($connectionType === 'hotspot') {
                // Hapus Hotspot Profile
                $query = new Query('/ip/hotspot/user/profile/print');
                $query->where('name', $profileName);
                $profiles = $client->query($query)->read();
                
                if (!empty($profiles)) {
                    $query = new Query('/ip/hotspot/user/profile/remove');
                    $query->equal('.id', $profiles[0]['.id']);
                    $client->query($query)->read();
                }
            }
        } catch (\Exception $e) {
            Log::warning("Failed to delete profile '{$profileName}' from router: " . $e->getMessage());
            // Jangan throw exception, karena mungkin profile sudah tidak ada
        }
    }

    /**
     * Sync Package Profile ke semua router yang relevan (Sync 2 Arah)
     * Router yang relevan = router yang memiliki customer dengan package ini
     */
    private function syncPackageToAllRouters(Package $package): void
    {
        // Cari semua router yang memiliki customer dengan package ini
        $routerIds = Customer::where('package_id', $package->id)
            ->whereNotNull('router_id')
            ->distinct()
            ->pluck('router_id')
            ->toArray();

        // Jika tidak ada customer dengan package ini, sync ke semua router aktif
        if (empty($routerIds)) {
            $routers = Router::where('is_active', true)->get();
        } else {
            $routers = Router::whereIn('id', $routerIds)
                ->where('is_active', true)
                ->get();
        }

        $syncedCount = 0;
        $errors = [];

        foreach ($routers as $router) {
            try {
                $mikrotik = new MikrotikService($router);
                $mikrotik->syncPackageProfile($package);
                
                $syncedCount++;
                Log::info("✅ Package '{$package->name}' synced to router: {$router->name} ({$package->download_speed}M/{$package->upload_speed}M)");
                
            } catch (\Exception $e) {
                $errors[] = "Router {$router->name}: " . $e->getMessage();
                Log::error("❌ Failed to sync package '{$package->name}' to router {$router->name}: " . $e->getMessage());
            }
        }

        if ($syncedCount > 0) {
            Log::info("📤 Package '{$package->name}' synced to {$syncedCount} router(s)");
        }

        if (count($errors) > 0) {
            Log::warning("⚠️ Some routers failed to sync: " . implode(', ', $errors));
        }
    }
}
