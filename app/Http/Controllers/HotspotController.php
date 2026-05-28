<?php

namespace App\Http\Controllers;

use App\Models\Router;
use App\Models\HotspotUser;
use App\Models\HotspotProfile;
use App\Models\HotspotActiveSession;
use App\Models\HotspotHost;
use App\Models\HotspotIpBinding;
use App\Models\HotspotCookie;
use App\Models\Setting;
use App\Models\VoucherTemplate;
use App\Services\MikrotikService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Helpers\TimeLimitHelper;

class HotspotController extends Controller
{
    /**
     * Show router selection page (Landing Page)
     */
    public function selectRouter()
    {
        $routers = Router::where('is_active', true)->get();
        
        // If only one router, auto-select it
        if ($routers->count() === 1) {
            Session::put('hotspot_router_id', $routers->first()->id);
            return redirect()->route('hotspot.dashboard');
        }
        
        return view('hotspot.select-router', compact('routers'));
    }

    /**
     * Set selected router to session
     */
    public function setSession(Request $request)
    {
        try {
            // ✅ OPTIMASI: Clear cache saat ganti router untuk memastikan data fresh
            $oldRouterId = Session::get('hotspot_router_id');
            if ($oldRouterId) {
                // Clear cache untuk router lama - flush semua cache untuk router ini
                // Note: Cache akan otomatis expire, jadi tidak perlu clear manual
            }
            
            // Support both router_id and mode (all)
            if ($request->has('mode') && $request->mode === 'all') {
                // ALL ROUTER mode - use first active router for now
                $router = Router::where('is_active', true)->first();
                if ($router) {
                    Session::put('hotspot_router_id', $router->id);
                    Session::put('hotspot_router_mode', 'all');
                } else {
                    return response()->json(['success' => false, 'message' => 'Tidak ada router aktif'], 400);
                }
            } else {
                $request->validate([
                    'router_id' => 'required|exists:routers,id',
                ]);
                
                Session::put('hotspot_router_id', $request->router_id);
                Session::forget('hotspot_router_mode');
            }
            
            // ✅ Clear cache untuk router baru juga (tidak perlu, cache akan otomatis expire)
            
            // Return JSON if AJAX request
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => true]);
            }
            
            return redirect()->route('hotspot.dashboard');
        } catch (\Exception $e) {
            \Log::error("Error setting router session: " . $e->getMessage());
            
            // Return JSON if AJAX request
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Gagal mengganti router: ' . $e->getMessage()
                ], 500);
            }
            
            return back()->with('error', 'Gagal mengganti router: ' . $e->getMessage());
        }
    }

    /**
     * Dashboard - Main hotspot management page
     */
    public function index()
    {
        $router = $this->getSelectedRouter();
        
        if (!$router) {
            return redirect()->route('hotspot.select');
        }
        
        try {
            $service = new MikrotikService($router);
            
            // Get statistics
            $stats = [
                'total_users' => HotspotUser::where('router_id', $router->id)->count(),
                'active_users' => count($service->getHotspotActiveUsers()),
                'total_profiles' => HotspotProfile::where('router_id', $router->id)->count(),
                'disabled_users' => HotspotUser::where('router_id', $router->id)->where('disabled', true)->count(),
            ];
            
            return view('hotspot.dashboard', compact('router', 'stats'));
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal terhubung ke router: ' . $e->getMessage());
        }
    }

    /**
     * Dashboard - Main hotspot management page (for router-specific route)
     * @param Router|int $router
     */
    public function dashboard($router)
    {
        // Handle router parameter - can be ID or Router model
        if (is_numeric($router)) {
            $router = Router::findOrFail($router);
        } elseif (!$router instanceof Router) {
            $router = Router::findOrFail($router);
        }
        
        // Set router to session for consistency
        Session::put('hotspot_router_id', $router->id);
        
        try {
            $service = new MikrotikService($router);
            
            // Get statistics
            $stats = [
                'total_users' => HotspotUser::where('router_id', $router->id)->count(),
                'active_users' => count($service->getHotspotActiveUsers()),
                'total_profiles' => HotspotProfile::where('router_id', $router->id)->count(),
                'disabled_users' => HotspotUser::where('router_id', $router->id)->where('disabled', true)->count(),
            ];
            
            return view('hotspot.dashboard', compact('router', 'stats'));
        } catch (\Exception $e) {
            \Log::error("Error loading hotspot dashboard: " . $e->getMessage());
            return redirect()->route('hotspot.index')->with('error', 'Gagal terhubung ke router: ' . $e->getMessage());
        }
    }

    /**
     * List all hotspot users
     */
    public function users(Request $request)
    {
        $router = $this->getSelectedRouter();
        
        if (!$router) {
            return redirect()->route('hotspot.select');
        }
        
        try {
            $service = new MikrotikService($router);
            
            // Sync users from MikroTik to database
            if ($request->get('sync')) {
                $this->syncUsersFromMikrotik($router, $service);
                return redirect()->route('hotspot.users')->with('success', 'User berhasil disinkronkan dari MikroTik!');
            }
            
            // Get users from database with pagination
            $query = HotspotUser::where('router_id', $router->id);
            
            // Search filter
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('username', 'like', "%{$search}%")
                      ->orWhere('comment', 'like', "%{$search}%")
                      ->orWhere('profile', 'like', "%{$search}%");
                });
            }
            
            // Profile filter
            if ($request->filled('profile')) {
                $query->where('profile', $request->profile);
            }
            
            // Status filter
            if ($request->filled('disabled')) {
                $query->where('disabled', $request->disabled === 'true');
            }
            
            $users = $query->orderBy('created_at', 'desc')->paginate(50);
            $profiles = HotspotProfile::where('router_id', $router->id)->pluck('name');
            
            return view('hotspot.users.index', compact('router', 'users', 'profiles'));
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal mengambil data user: ' . $e->getMessage());
        }
    }

    /**
     * Show form to create new user
     */
    public function create()
    {
        $router = $this->getSelectedRouter();
        
        if (!$router) {
            return redirect()->route('hotspot.select');
        }
        
        try {
            $service = new MikrotikService($router);
            $profiles = $service->getHotspotProfiles();
            $servers = $service->getHotspotServers();
            
            $routers = Router::where('is_active', true)->get();
            return view('hotspot.users.create', compact('router', 'routers', 'profiles', 'servers'));
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal mengambil data: ' . $e->getMessage());
        }
    }

    /**
     * Store new hotspot user
     */
    public function store(Request $request)
    {
        $router = $this->getSelectedRouter();
        
        if (!$router) {
            return redirect()->route('hotspot.select');
        }
        
        $request->validate([
            'username' => 'required|string|max:255',
            'password' => 'required|string|max:255',
            'profile' => 'required|string|max:255',
            'server' => 'nullable|string|max:255',
            'comment' => 'nullable|string|max:500',
            'limit_uptime' => 'nullable|string',  // [wdhm] format
            'limit_bytes_total' => 'nullable|integer',
            'price' => 'nullable|numeric',
        ]);
        
        // Validate time limit format
        if ($request->limit_uptime && !TimeLimitHelper::validate($request->limit_uptime)) {
            return back()->withErrors(['limit_uptime' => 'Invalid time limit format. Use [wdhm] format. Example: 1d, 12h, 4w3d'])->withInput();
        }
        
        // Parse time limit to seconds
        $limitUptimeSeconds = $request->limit_uptime 
            ? TimeLimitHelper::parseToSeconds($request->limit_uptime)
            : null;
        
        // Avoid conflict with Request::server bag
        $server = $request->input('server', 'all');
        
        try {
            $service = new MikrotikService($router);
            
            // Create user in MikroTik
            $options = [
                'server' => $server,
                'comment' => $request->comment,
                'limit_uptime' => $limitUptimeSeconds,
                'limit_bytes_total' => $request->limit_bytes_total,
            ];
            
            $service->createHotspotUser(
                $request->username,
                $request->password,
                $request->profile,
                $options
            );
            
            // Save to database
            HotspotUser::create([
                'router_id' => $router->id,
                'server' => $server,
                'username' => $request->username,
                'password' => $request->password,
                'profile' => $request->profile,
                'comment' => $request->comment,
                'disabled' => false,
                'limit_uptime' => $limitUptimeSeconds,
                'limit_bytes_total' => $request->limit_bytes_total,
                'price' => $request->price,
                'voucher_code' => strtoupper(Str::random(8)),
                'synced_at' => now(),
            ]);
            
            return redirect()->route('hotspot.users')->with('success', 'User hotspot berhasil dibuat!');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Gagal membuat user: ' . $e->getMessage());
        }
    }

    /**
     * Show form to edit user
     */
    public function edit($id)
    {
        $router = $this->getSelectedRouter();
        
        if (!$router) {
            return redirect()->route('hotspot.select');
        }
        
        $user = HotspotUser::where('router_id', $router->id)->findOrFail($id);
        
        try {
            $service = new MikrotikService($router);
            $profiles = $service->getHotspotProfiles();
            $servers = $service->getHotspotServers();
            
            return view('hotspot.users.edit', compact('router', 'user', 'profiles', 'servers'));
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal mengambil data: ' . $e->getMessage());
        }
    }

    /**
     * Update hotspot user
     */
    public function update(Request $request, $id)
    {
        $router = $this->getSelectedRouter();
        
        if (!$router) {
            return redirect()->route('hotspot.select');
        }
        
        $user = HotspotUser::where('router_id', $router->id)->findOrFail($id);
        
        $request->validate([
            'password' => 'nullable|string|max:255',
            'profile' => 'required|string|max:255',
            'server' => 'nullable|string|max:255',
            'comment' => 'nullable|string|max:500',
            'limit_uptime' => 'nullable|string',  // Changed to string for [wdhm] format
            'limit_bytes_total' => 'nullable|integer',
            'price' => 'nullable|numeric',
            'disabled' => 'nullable|boolean',
        ]);
        
        try {
            $service = new MikrotikService($router);
            
            // Update in MikroTik
            $options = [
                'password' => $request->password,
                'server' => $request->server ?? 'all',
                'comment' => $request->comment,
                'limit_uptime' => $limitUptimeSeconds,
                'limit_bytes_total' => $request->limit_bytes_total,
                'disabled' => $request->boolean('disabled'),
            ];
            
            $service->updateHotspotUser($user->username, $request->profile, $options);
            
            // Update in database
            $user->update([
                'password' => $request->password ?? $user->password,
                'profile' => $request->profile,
                'server' => $request->server ?? 'all',
                'comment' => $request->comment,
                'limit_uptime' => $limitUptimeSeconds,
                'limit_bytes_total' => $request->limit_bytes_total,
                'price' => $request->price,
                'disabled' => $request->boolean('disabled'),
                'synced_at' => now(),
            ]);
            
            return redirect()->route('hotspot.users')->with('success', 'User berhasil diupdate!');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Gagal update user: ' . $e->getMessage());
        }
    }

    /**
     * Delete hotspot user
     */
    public function destroy($id)
    {
        $router = $this->getSelectedRouter();
        
        if (!$router) {
            return redirect()->route('hotspot.select');
        }
        
        $user = HotspotUser::where('router_id', $router->id)->findOrFail($id);
        
        try {
            $service = new MikrotikService($router);
            
            // Delete from MikroTik
            $service->deleteHotspotUser($user->username);
            
            // Delete from database
            $user->delete();
            
            return redirect()->route('hotspot.users')->with('success', 'User berhasil dihapus!');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal hapus user: ' . $e->getMessage());
        }
    }

    /**
     * Show generate vouchers form
     */
    public function generateForm()
    {
        $router = $this->getSelectedRouter();
        
        if (!$router) {
            return redirect()->route('hotspot.select');
        }
        
        try {
            $service = new MikrotikService($router);
            $profiles = $service->getHotspotProfiles();
            $servers = $service->getHotspotServers();
            
            $routers = Router::where('is_active', true)->get();
            return view('hotspot.users.generate', compact('router', 'routers', 'profiles', 'servers'));
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal mengambil data: ' . $e->getMessage());
        }
    }

    /**
     * Generate multiple vouchers
     */
    public function generateStore(Request $request)
    {
        $router = $this->getSelectedRouter();
        
        if (!$router) {
            return redirect()->route('hotspot.select');
        }
        
        $request->validate([
            'qty' => 'required|integer|min:1|max:1000',
            'profile' => 'required|string|max:255',
            'prefix' => 'nullable|string|max:50',
            'user_mode' => 'required|string|max:10',
            'name_length' => 'required|integer|min:3|max:8',
            'character' => 'required|string|max:50',
            'server' => 'nullable|string|max:255',
            'comment' => 'nullable|string|max:500',
            'limit_uptime' => 'nullable|string',  // [wdhm] format
            'limit_bytes_total' => 'nullable|integer',
            'price' => 'nullable|numeric',
        ]);
        
        // Validate time limit format
        if ($request->limit_uptime && !TimeLimitHelper::validate($request->limit_uptime)) {
            return back()->withErrors(['limit_uptime' => 'Invalid time limit format. Use [wdhm] format. Example: 1d, 12h, 4w3d'])->withInput();
        }
        
        // Parse time limit to seconds
        $limitUptimeSeconds = $request->limit_uptime 
            ? TimeLimitHelper::parseToSeconds($request->limit_uptime)
            : null;
        
        $server = $request->input('server', 'all');
        
        try {
            $service = new MikrotikService($router);
            
            $options = [
                'prefix' => $request->prefix ?? 'voucher',
                'password_length' => $request->password_length ?? 6,
                'user_mode' => $request->user_mode ?? 'up',
                'name_length' => $request->name_length ?? 4,
                'character' => $request->character ?? 'random-abcd',
                'server' => $server,
                // Comment will be auto-generated by service with date format (voucherMM-DD)
                'limit_uptime' => $limitUptimeSeconds,
                'limit_bytes_total' => $request->limit_bytes_total,
            ];
            
            $generated = $service->generateHotspotUsers($request->qty, $request->profile, $options);
            
            // Save to database
            $batchId = 'BATCH-' . strtoupper(Str::random(8));
            $firstComment = null;
            foreach ($generated as $user) {
                $comment = $user['comment'] ?? $request->comment ?? 'voucher' . date('m-d-Y');
                if (!$firstComment) {
                    $firstComment = $comment;
                }
                HotspotUser::create([
                    'router_id' => $router->id,
                    'server' => $user['server'],
                    'username' => $user['username'],
                    'password' => $user['password'],
                    'profile' => $user['profile'],
                    'comment' => $comment,
                    'disabled' => false,
                    'limit_uptime' => $limitUptimeSeconds,
                    'limit_bytes_total' => $request->limit_bytes_total,
                    'price' => $request->price,
                    'voucher_code' => strtoupper(Str::random(8)),
                    'batch_id' => $batchId,
                    'synced_at' => now(),
                ]);
            }
            
            return redirect()->route('hotspot.users', ['batch' => $batchId])
                ->with('success', count($generated) . ' voucher berhasil digenerate!')
                ->with('last_generate_comment', $firstComment);
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Gagal generate voucher: ' . $e->getMessage());
        }
    }

    /**
     * Show active sessions (real-time monitoring)
    /**
     * Show active sessions (real-time monitoring)
     */
    public function active(Request $request)
    {
        $router = $this->getSelectedRouter();
        
        if (!$router) {
            return redirect()->route('hotspot.select');
        }
        
        try {
            $service = new MikrotikService($router);
            
            // Get active users from MikroTik with 10 second cache
            $activeUsers = Cache::remember("hotspot_active_{$router->id}", 10, function () use ($service) {
                return $service->getHotspotActiveUsers();
            });
            
            // If AJAX request, return JSON
            if ($request->ajax()) {
                return response()->json(['active_users' => $activeUsers]);
            }
            
            return view('hotspot.active', compact('router', 'activeUsers'));
        } catch (\Exception $e) {
            if ($request->ajax()) {
                return response()->json(['error' => $e->getMessage()], 500);
            }
            return back()->with('error', 'Gagal mengambil data active sessions: ' . $e->getMessage());
        }
    }

    /**
     * Kick active user (disconnect)
     */
    public function kickUser(Request $request)
    {
        $router = $this->getSelectedRouter();
        
        if (!$router) {
            return response()->json(['error' => 'Router not selected'], 400);
        }
        
        try {
            $service = new MikrotikService($router);
            $service->removeActiveSession($request->session_id);
            
            return response()->json(['success' => true, 'message' => 'User berhasil di-kick!']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Show hotspot profiles
     */
    public function profiles()
    {
        $router = $this->getSelectedRouter();
        
        if (!$router) {
            return redirect()->route('hotspot.select');
        }
        
        try {
            $service = new MikrotikService($router);
            $profiles = $service->getHotspotProfiles();
            
            // Get detailed info for each profile (parse on-login script)
            $profilesWithDetails = [];
            foreach ($profiles as $profile) {
                $details = $service->getHotspotUserProfile($profile['id'], $router);
                if ($details) {
                    $profilesWithDetails[] = array_merge($profile, $details);
                } else {
                    $profilesWithDetails[] = $profile;
                }
            }
            
            return view('hotspot.profiles.index', compact('router', 'profiles', 'profilesWithDetails'));
        } catch (\Exception $e) {
            \Log::error("Error getting profiles: " . $e->getMessage());
            return back()->with('error', 'Gagal mengambil data profiles: ' . $e->getMessage());
        }
    }

    /**
     * Show create profile form
     */
    public function createProfile()
    {
        $router = $this->getSelectedRouter();
        
        if (!$router) {
            return redirect()->route('hotspot.select');
        }
        
        try {
            $service = new MikrotikService($router);
            $pools = $service->getIPPools();
            $queues = $service->getQueues();
            
            return view('hotspot.profiles.create', compact('router', 'pools', 'queues'));
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal mengambil data: ' . $e->getMessage());
        }
    }

    /**
     * Store new profile
     */
    public function storeProfile(Request $request)
    {
        $router = $this->getSelectedRouter();
        
        if (!$router) {
            return redirect()->route('hotspot.select');
        }
        
        $request->validate([
            'name' => 'required|string|max:255',
            'shared_users' => 'required|integer|min:1',
            'rate_limit' => 'nullable|string|max:255',
            'expired_mode' => 'required|in:0,rem,ntf,remc,ntfc',
            'validity' => 'nullable|string|max:50',
            'price' => 'nullable|numeric|min:0',
            'selling_price' => 'nullable|numeric|min:0',
            'address_pool' => 'nullable|string|max:255',
            'parent_queue' => 'nullable|string|max:255',
            'lock_user' => 'required|in:Enable,Disable',
        ]);
        
        try {
            $service = new MikrotikService($router);
            
            $data = [
                'name' => $request->name,
                'shared_users' => $request->shared_users,
                'rate_limit' => $request->rate_limit ?? '',
                'expired_mode' => $request->expired_mode,
                'validity' => $request->validity ?? '',
                'price' => $request->price ?? '0',
                'selling_price' => $request->selling_price ?? '0',
                'address_pool' => $request->address_pool ?? 'none',
                'parent_queue' => $request->parent_queue ?? 'none',
                'lock_user' => $request->lock_user,
            ];
            
            $profileId = $service->createHotspotUserProfile($data);
            
            return redirect()->route('hotspot.profiles.edit', ['id' => $profileId])
                ->with('success', 'Profile berhasil dibuat!');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Gagal membuat profile: ' . $e->getMessage());
        }
    }

    /**
     * Show edit profile form
     */
    public function editProfile($id)
    {
        $router = $this->getSelectedRouter();
        
        if (!$router) {
            return redirect()->route('hotspot.select');
        }
        
        try {
            $service = new MikrotikService($router);
            $profile = $service->getHotspotUserProfile($id, $router);
            
            if (!$profile) {
                return back()->with('error', 'Profile tidak ditemukan');
            }
            
            $pools = $service->getIPPools();
            $queues = $service->getQueues();
            
            return view('hotspot.profiles.edit', compact('router', 'profile', 'pools', 'queues'));
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal mengambil data profile: ' . $e->getMessage());
        }
    }

    /**
     * Update profile
     */
    public function updateProfile(Request $request, $id)
    {
        $router = $this->getSelectedRouter();
        
        if (!$router) {
            return redirect()->route('hotspot.select');
        }
        
        $request->validate([
            'name' => 'required|string|max:255',
            'shared_users' => 'required|integer|min:1',
            'rate_limit' => 'nullable|string|max:255',
            'expired_mode' => 'required|in:0,rem,ntf,remc,ntfc',
            'validity' => 'nullable|string|max:50',
            'price' => 'nullable|numeric|min:0',
            'selling_price' => 'nullable|numeric|min:0',
            'address_pool' => 'nullable|string|max:255',
            'parent_queue' => 'nullable|string|max:255',
            'lock_user' => 'required|in:Enable,Disable',
        ]);
        
        try {
            $service = new MikrotikService($router);
            
            $data = [
                'name' => $request->name,
                'shared_users' => $request->shared_users,
                'rate_limit' => $request->rate_limit ?? '',
                'expired_mode' => $request->expired_mode,
                'validity' => $request->validity ?? '',
                'price' => $request->price ?? '0',
                'selling_price' => $request->selling_price ?? '0',
                'address_pool' => $request->address_pool ?? 'none',
                'parent_queue' => $request->parent_queue ?? 'none',
                'lock_user' => $request->lock_user,
            ];
            
            $service->updateHotspotUserProfileFull($id, $data);
            
            return redirect()->route('hotspot.profiles.edit', ['id' => $id])
                ->with('success', 'Profile berhasil diupdate!');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Gagal mengupdate profile: ' . $e->getMessage());
        }
    }

    /**
     * Delete profile
     */
    public function deleteProfile($id)
    {
        $router = $this->getSelectedRouter();
        
        if (!$router) {
            return redirect()->route('hotspot.select');
        }
        
        try {
            $service = new MikrotikService($router);
            
            // Get profile name first
            $profile = $service->getHotspotUserProfile($id, $router);
            if (!$profile) {
                return back()->with('error', 'Profile tidak ditemukan');
            }
            
            // Remove scheduler if exists
            $scheduler = $service->getScheduler($profile['name'], $router);
            if ($scheduler && isset($scheduler['.id'])) {
                $query = new \RouterOS\Query('/system/scheduler/remove');
                $query->equal('.id', $scheduler['.id']);
                $service->getClient()->query($query)->read();
            }
            
            // Remove profile
            $query = new \RouterOS\Query('/ip/hotspot/user/profile/remove');
            $query->equal('.id', $id);
            $service->getClient()->query($query)->read();
            
            return redirect()->route('hotspot.profiles')
                ->with('success', 'Profile berhasil dihapus!');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal menghapus profile: ' . $e->getMessage());
        }
    }

    /**
     * Show hotspot hosts (connected devices)
     */
    public function hosts()
    {
        $router = $this->getSelectedRouter();
        
        if (!$router) {
            return redirect()->route('hotspot.select');
        }
        
        try {
            $service = new MikrotikService($router);
            
            // Get hosts with 30 second cache
            $hosts = Cache::remember("hotspot_hosts_{$router->id}", 30, function () use ($service) {
                return $service->getHosts();
            });
            
            return view('hotspot.hosts', compact('router', 'hosts'));
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal mengambil data hosts: ' . $e->getMessage());
        }
    }

    /**
     * Show IP bindings
     */
    public function bindings()
    {
        $router = $this->getSelectedRouter();
        
        if (!$router) {
            return redirect()->route('hotspot.select');
        }
        
        try {
            $service = new MikrotikService($router);
            $bindings = $service->getIPBindings();
            
            return view('hotspot.bindings', compact('router', 'bindings'));
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal mengambil data IP bindings: ' . $e->getMessage());
        }
    }

    /**
     * Add IP binding
     */
    public function addBinding(Request $request)
    {
        $router = $this->getSelectedRouter();
        
        if (!$router) {
            return redirect()->route('hotspot.select');
        }
        
        $request->validate([
            'mac_address' => 'required|string|max:17',
            'address' => 'required|ip',
            'type' => 'required|in:regular,blocked,bypassed',
            'server' => 'nullable|string|max:255',
            'comment' => 'nullable|string|max:500',
        ]);
        
        try {
            $service = new MikrotikService($router);
            
            $data = [
                'mac_address' => $request->mac_address,
                'address' => $request->address,
                'type' => $request->type,
                'server' => $request->server ?? 'all',
                'comment' => $request->comment,
            ];
            
            $service->addIPBinding($data);
            
            return redirect()->route('hotspot.bindings')->with('success', 'IP Binding berhasil ditambahkan!');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Gagal menambahkan IP binding: ' . $e->getMessage());
        }
    }

    /**
     * Remove IP binding
     */
    public function removeBinding($bindingId)
    {
        $router = $this->getSelectedRouter();
        
        if (!$router) {
            return redirect()->route('hotspot.select');
        }
        
        try {
            $service = new MikrotikService($router);
            $service->removeIPBinding($bindingId);
            
            return redirect()->route('hotspot.bindings')->with('success', 'IP Binding berhasil dihapus!');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal menghapus IP binding: ' . $e->getMessage());
        }
    }

    /**
     * Show cookies
     */
    public function cookies()
    {
        $router = $this->getSelectedRouter();
        
        if (!$router) {
            return redirect()->route('hotspot.select');
        }
        
        try {
            $service = new MikrotikService($router);
            
            // Get cookies with 5 minute cache
            $cookies = Cache::remember("hotspot_cookies_{$router->id}", 300, function () use ($service) {
                return $service->getCookies();
            });
            
            return view('hotspot.cookies', compact('router', 'cookies'));
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal mengambil data cookies: ' . $e->getMessage());
        }
    }

    /**
     * Remove cookie
     */
    public function removeCookie($cookieId)
    {
        $router = $this->getSelectedRouter();
        
        if (!$router) {
            return redirect()->route('hotspot.select');
        }
        
        try {
            $service = new MikrotikService($router);
            $service->removeCookie($cookieId);
            
            // Clear cache
            Cache::forget("hotspot_cookies_{$router->id}");
            
            return redirect()->route('hotspot.cookies')->with('success', 'Cookie berhasil dihapus!');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal menghapus cookie: ' . $e->getMessage());
        }
    }

    /**
     * Show user activity log
     */
    public function log(Request $request)
    {
        $router = $this->getSelectedRouter();
        
        if (!$router) {
            return redirect()->route('hotspot.select');
        }
        
        try {
            $service = new MikrotikService($router);
            
            $filters = [];
            if ($request->filled('topics')) {
                $filters['topics'] = explode(',', $request->topics);
            }
            
            $logs = $service->getUserLog($filters);
            
            return view('hotspot.log', compact('router', 'logs'));
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal mengambil log: ' . $e->getMessage());
        }
    }

    /**
     * Sync users from MikroTik to database
     */
    private function syncUsersFromMikrotik(Router $router, MikrotikService $service)
    {
        $mikrotikUsers = $service->getAllHotspotUsers();
        
        foreach ($mikrotikUsers as $userData) {
            HotspotUser::updateOrCreate(
                [
                    'router_id' => $router->id,
                    'username' => $userData['username'],
                ],
                [
                    'server' => $userData['server'] ?? 'all',
                    'password' => $userData['password'],
                    'profile' => $userData['profile'],
                    'comment' => $userData['comment'],
                    'disabled' => $userData['disabled'],
                    'limit_uptime' => $userData['limit_uptime'],
                    'limit_bytes_total' => $userData['limit_bytes_total'],
                    'synced_at' => now(),
                ]
            );
        }
    }

    /**
     * Get selected router from session
     */
    private function getSelectedRouter(): ?Router
    {
        $routerId = Session::get('hotspot_router_id');
        
        if (!$routerId) {
            return null;
        }
        
        return Router::find($routerId);
    }

    /**
     * Change selected router
     */
    public function changeRouter()
    {
        Session::forget('hotspot_router_id');
        return redirect()->route('hotspot.select');
    }

    /**
     * Show selling report
     */
    public function sellingReport(Request $request)
    {
        $router = $this->getSelectedRouter();
        
        if (!$router) {
            return redirect()->route('hotspot.select');
        }
        
        try {
            $service = new MikrotikService($router);
            
            // Get filter parameters
            $date = $request->get('idhr'); // Format: "dec/01/2025"
            $month = $request->get('idbl'); // Format: "dec2025"
            $prefix = $request->get('prefix');
            // ✅ OPTIMASI: Default limit lebih kecil (300) untuk performa lebih cepat
            // Jika ada filter date/month, tidak perlu limit (data sudah terfilter)
            $limit = null;
            if (!$date && !$month) {
                // Hanya limit jika tidak ada filter (untuk performa)
                $limit = $request->get('limit', 300); // Default 300 untuk performa lebih cepat
            }
            
            // Show loading message
            \Log::info("Loading selling report", [
                'router' => $router->name,
                'date' => $date,
                'month' => $month,
                'prefix' => $prefix,
                'limit' => $limit
            ]);
            
            // Get report data (with caching di MikrotikService)
            // Limit untuk performa lebih baik
            $reportData = $service->getSellingReport($date, $month, $prefix, (int)$limit);
            
            // Format month for display
            $monthDisplay = '';
            if ($month) {
                $monthName = substr($month, 0, 3);
                $year = substr($month, 3);
                $monthDisplay = ucfirst($monthName) . ' ' . $year;
            } elseif ($date) {
                $monthDisplay = $date;
            } else {
                $monthDisplay = 'All';
            }
            
            return view('hotspot.report.selling', compact('router', 'reportData', 'date', 'month', 'prefix', 'monthDisplay'));
        } catch (\Exception $e) {
            \Log::error("Error loading selling report: " . $e->getMessage(), [
                'router' => $router->name ?? 'unknown',
                'trace' => $e->getTraceAsString()
            ]);
            return back()->with('error', 'Gagal mengambil data laporan: ' . $e->getMessage());
        }
    }

    /**
     * Delete selling report
     */
    public function deleteSellingReport(Request $request)
    {
        $router = $this->getSelectedRouter();
        
        if (!$router) {
            return redirect()->route('hotspot.select');
        }
        
        $request->validate([
            'date' => 'nullable|string',
            'month' => 'nullable|string',
        ]);
        
        try {
            $service = new MikrotikService($router);
            
            $date = $request->date;
            $month = $request->month;
            
            $service->deleteSellingReport($date, $month);
            
            // Redirect back dengan parameter yang sama
            $redirectUrl = route('hotspot.report.selling');
            if ($date) {
                $redirectUrl .= '?idhr=' . urlencode($date);
            } elseif ($month) {
                $redirectUrl .= '?idbl=' . urlencode($month);
            }
            
            return redirect($redirectUrl)->with('success', 'Data laporan berhasil dihapus!');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal menghapus data laporan: ' . $e->getMessage());
        }
    }

    /**
     * Export selling report to CSV
     */
    public function exportSellingReport(Request $request)
    {
        $router = $this->getSelectedRouter();
        
        if (!$router) {
            return redirect()->route('hotspot.select');
        }
        
        try {
            $service = new MikrotikService($router);
            
            $date = $request->get('idhr');
            $month = $request->get('idbl');
            $prefix = $request->get('prefix');
            $limit = $request->get('limit', 10000); // Limit untuk export
            
            $reportData = $service->getSellingReport($date, $month, $prefix, (int)$limit);
            
            $filename = 'report-' . ($month ?: ($date ?: 'all')) . ($prefix ? '-prefix-' . $prefix : '') . '.csv';
            
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ];
            
            $callback = function() use ($reportData) {
                $file = fopen('php://output', 'w');
                
                // BOM untuk Excel UTF-8
                fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
                
                // Header
                fputcsv($file, ['No', 'Date', 'Time', 'Username', 'Profile', 'Comment', 'Price']);
                
                // Data
                $no = 1;
                foreach ($reportData['reports'] as $report) {
                    fputcsv($file, [
                        $no++,
                        $report['date'],
                        $report['time'],
                        $report['username'],
                        $report['profile'],
                        $report['comment'],
                        $report['price'],
                    ]);
                }
                
                // Total
                fputcsv($file, ['', '', '', '', '', 'Total', $reportData['total']]);
                
                fclose($file);
            };
            
            return response()->stream($callback, 200, $headers);
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal export CSV: ' . $e->getMessage());
        }
    }

    /**
     * Print voucher(s) with template
     */
    public function printVoucher(Request $request, $router)
    {
        try {
            $router = Router::findOrFail($router);
            
            // Get parameters
            $users = $request->get('users', []); // Array of user IDs or usernames
            $comment = $request->get('comment'); // Comment filter (for batch vouchers)
            $qr = $request->get('qr', 'no'); // QR code: yes/no
            $small = $request->get('small', 'no'); // Small size: yes/no
            
            // Get users to print
            $hotspotUsers = collect();
            if (!empty($users)) {
                // Get by IDs or usernames
                if (is_array($users)) {
                    $hotspotUsers = HotspotUser::where('router_id', $router->id)
                        ->where(function($query) use ($users) {
                            $query->whereIn('id', array_filter($users, 'is_numeric'))
                                  ->orWhereIn('username', $users);
                        })
                        ->get();
                }
            } elseif ($comment) {
                // Get by comment (for batch vouchers from generate)
                $hotspotUsers = HotspotUser::where('router_id', $router->id)
                    ->where('comment', 'like', "%{$comment}%")
                    ->get();
            } else {
                // If no users selected and no comment, return error
                if ($request->expectsJson()) {
                    return response()->json(['error' => 'Pilih user atau comment untuk dicetak!'], 400);
                }
                return back()->with('error', 'Pilih user atau comment untuk dicetak!');
            }

            if ($hotspotUsers->isEmpty()) {
                if ($request->expectsJson()) {
                    return response()->json(['error' => 'Tidak ada user yang ditemukan!'], 404);
                }
                return back()->with('error', 'Tidak ada user yang ditemukan!');
            }

            // Get logo from settings
            $logoUrl = $this->getCompanyLogo();
            
            // Get router DNS name
            $dnsname = $router->host ?? 'hotspot.local';
            try {
                $service = new MikrotikService($router);
                $servers = $service->getHotspotServers();
                if (!empty($servers) && isset($servers[0]['dns-name'])) {
                    $dnsname = $servers[0]['dns-name'];
                }
            } catch (\Exception $e) {
                \Log::debug("Could not get DNS name from MikroTik: " . $e->getMessage());
            }
            
            // Get hotspot name from settings
            $hotspotname = Setting::get('company_name', $router->name ?? 'Hotspot');
            
            // Get template from database or external URL
            $template = $this->getVoucherTemplate($router->id, $small == 'yes' ? 'small' : 'default');
            
            return view('hotspot.voucher.print', compact(
                'hotspotUsers',
                'logoUrl',
                'dnsname',
                'hotspotname',
                'qr',
                'small',
                'router',
                'template'
            ));
        } catch (\Exception $e) {
            \Log::error("Error printing voucher: " . $e->getMessage());
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Gagal mencetak voucher: ' . $e->getMessage()], 500);
            }
            return back()->with('error', 'Gagal mencetak voucher: ' . $e->getMessage());
        }
    }

    /**
     * Get voucher template from database or external URL
     * This uses the same logic as VoucherTemplateController to ensure consistency
     */
    private function getVoucherTemplate($routerId, $templateType = 'default')
    {
        // Use same logic as VoucherTemplateController::getVoucherTemplateContent()
        // Priority 1: Get from database (template yang sudah diedit via web)
        // IMPORTANT: Always get fresh from database (no cache) to ensure latest changes are used
        try {
            // Try to get by name first (default, small, etc)
            // IMPORTANT: Always get FRESH from database (no cache) to ensure latest changes are used
            $template = VoucherTemplate::where('name', $templateType)->first();
            if ($template) {
                // Force refresh to get latest data
                $template->refresh();
                if (!empty(trim($template->html_content ?? ''))) {
                    \Log::info("PrintVoucher: Using template from database: name={$templateType}, id={$template->id}, content_length=" . strlen($template->html_content) . ", has_testing=" . (strpos($template->html_content, 'TESTING') !== false ? 'YES' : 'NO'));
                    return $template->html_content;
                }
            }
            
            // If not found by name, get default template
            $template = VoucherTemplate::where('is_default', true)->first();
            if ($template) {
                $template->refresh();
                if (!empty(trim($template->html_content ?? ''))) {
                    \Log::info("PrintVoucher: Using default template from database: id={$template->id}, content_length=" . strlen($template->html_content) . ", has_testing=" . (strpos($template->html_content, 'TESTING') !== false ? 'YES' : 'NO'));
                    return $template->html_content;
                }
            }
        } catch (\Exception $e) {
            \Log::debug("Failed to get template from database: " . $e->getMessage());
        }
        
        // Priority 2: Try to fetch from external URL (ONLY if no template in database)
        // IMPORTANT: Do NOT override database template with external URL
        // Only use external URL if database is empty
        $externalUrl = "https://raw.kitabill.site/hotspot/{$routerId}/template";
        try {
            $client = new \GuzzleHttp\Client(['timeout' => 5, 'verify' => false]);
            $response = $client->get($externalUrl);
            if ($response->getStatusCode() === 200) {
                $content = (string) $response->getBody();
                if (strpos($content, '<!DOCTYPE') === false && strpos($content, 'Redirecting') === false) {
                    if (strpos($content, '<table') !== false || strpos($content, '<?=') !== false || strpos($content, '<?php') !== false) {
                        // Only save to database if template doesn't exist yet (don't override edited template)
                        try {
                            $existing = VoucherTemplate::where('name', $templateType)->first();
                            if (!$existing || empty(trim($existing->html_content ?? ''))) {
                                VoucherTemplate::updateOrCreate(
                                    ['name' => $templateType],
                                    ['html_content' => $content, 'is_default' => $templateType == 'default']
                                );
                                \Log::info("PrintVoucher: Loaded from external URL and saved (no existing template)");
                                return $content;
                            } else {
                                \Log::info("PrintVoucher: Skipped external URL - template exists in database, using database template");
                                // Return database template instead
                                $existing->refresh();
                                return $existing->html_content;
                            }
                        } catch (\Exception $e) {
                            \Log::error("Error saving external template: " . $e->getMessage());
                        }
                        return $content;
                    }
                }
            }
        } catch (\Exception $e) {
            \Log::debug("Failed to fetch template from external URL: " . $e->getMessage());
        }
        
        // Priority 3: Create default template from mikhmon and save to database
        try {
            $mikhmonPath = base_path('../html/mikhmon/voucher/template.php');
            if ($templateType == 'small' && file_exists(base_path('../html/mikhmon/voucher/template-small.php'))) {
                $mikhmonPath = base_path('../html/mikhmon/voucher/template-small.php');
            }
            
            if (file_exists($mikhmonPath)) {
                $content = file_get_contents($mikhmonPath);
                // Save to database
                VoucherTemplate::updateOrCreate(
                    ['name' => $templateType],
                    ['html_content' => $content, 'is_default' => $templateType == 'default']
                );
                return $content;
            }
        } catch (\Exception $e) {
            \Log::debug("Failed to load default template: " . $e->getMessage());
        }
        
        // Fallback: return null to use blade template
        return null;
    }


    /**
     * Get company logo URL from settings
     */
    private function getCompanyLogo()
    {
        $logoPath = Setting::get('company_logo');
        if ($logoPath && \Storage::disk('public')->exists($logoPath)) {
            return \Storage::disk('public')->url($logoPath);
        }
        // Fallback to default logo
        return asset('img/logo.png');
    }
}

