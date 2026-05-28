<?php

namespace App\Http\Controllers;

use App\Models\Router;
use App\Models\ActivityLog;
use App\Services\MikrotikService;
use Illuminate\Http\Request;
use Exception;
use phpseclib3\Net\SSH2;
use phpseclib3\Net\SFTP;
use App\Plugins\Mikrotik\MikrotikSyncService;

class RouterController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:view_routers')->only(['index', 'show']);
        $this->middleware('can:create_router')->only(['create', 'store']);
        $this->middleware('can:edit_router')->only(['edit', 'update']);
        $this->middleware('can:delete_router')->only(['destroy']);
        $this->middleware('can:access_router')->only(['testConnection', 'pppoeUsers']);
        $this->middleware('can:reboot_router')->only(['reboot']);
    }


    public function index(Request $request)
    {
        $query = Router::withCount('customers');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $isActive = $request->status === 'active';
            $query->where('is_active', $isActive);
        }

        $routers = $query->latest()->paginate(15);

        return view('routers.index', compact('routers'));
    }

    public function create()
    {
        return view('routers.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'ip_address' => 'required|ip|unique:routers',
            'ssh_port' => 'required|integer|min:1|max:65535',
            'api_port' => 'required|integer|min:1|max:65535',
            'username' => 'required|string',
            'password' => 'required|string',
            'ros_version' => 'required|string',
            'address' => 'nullable|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'coverage_radius' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $router = Router::create($validated);

        ActivityLog::log(
            'created',
            'Router',
            $router->id,
            "Created new router: {$router->name} ({$router->ip_address})",
            ['ip_address' => $router->ip_address, 'ssh_port' => $router->ssh_port, 'api_port' => $router->api_port]
        );

        return redirect()->route('routers.index')->with('success', 'Router berhasil ditambahkan!');
    }

    public function show(Router $router)
    {
        $router->loadCount('customers');

        $routerInfo = ['online' => false];
        try {
            $mikrotik = new MikrotikService($router);
            $routerInfo = $mikrotik->getSystemInfo();
            $routerInfo['online'] = true;
        } catch (\Exception $e) {
            $routerInfo['error'] = $e->getMessage();
        }

        return view('routers.show', compact('router', 'routerInfo'));
    }

    public function edit(Router $router)
    {
        return view('routers.edit', compact('router'));
    }

    public function update(Request $request, Router $router)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'ip_address' => 'required|ip|unique:routers,ip_address,' . $router->id,
            'ssh_port' => 'required|integer|min:1|max:65535',
            'api_port' => 'required|integer|min:1|max:65535',
            'username' => 'required|string',
            'password' => 'required|string',
            'ros_version' => 'required|string',
            'address' => 'nullable|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'coverage_radius' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $router->update($validated);

        ActivityLog::log(
            'updated',
            'Router',
            $router->id,
            "Updated router: {$router->name}",
            ['ip_address' => $router->ip_address]
        );

        return redirect()->route('routers.index')->with('success', 'Router berhasil diupdate!');
    }

    public function destroy(Router $router)
    {
        $customerCount = $router->customers()->count();
        if ($customerCount > 0) {
            return back()->with('error', "Tidak dapat menghapus router! Masih ada {$customerCount} pelanggan terhubung.");
        }

        $routerName = $router->name;
        $routerIp = $router->ip_address;

        $router->delete();

        ActivityLog::log(
            'deleted',
            'Router',
            $router->id,
            "Deleted router: {$routerName} ({$routerIp})",
            ['name' => $routerName, 'ip' => $routerIp]
        );

        return redirect()->route('routers.index')->with('success', 'Router berhasil dihapus!');
    }

    public function testConnection(Router $router)
    {
        try {
            $mikrotikService = new MikrotikService($router);
            $result = $mikrotikService->testConnection();

            if ($result['success']) {
                $router->update(['last_seen' => now(), 'is_active' => true]);
                ActivityLog::log('connection_tested', 'Router', $router->id, "Connection successful: {$router->name}");
                return back()->with('success', 'Router is online!');
            } else {
                $router->update(['is_active' => false]);
                ActivityLog::log('connection_failed', 'Router', $router->id, "Connection failed: {$router->name}");
                return back()->with('error', 'Connection failed: ' . $result['message']);
            }
        } catch (Exception $e) {
            return back()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    public function reboot(Router $router)
    {
        try {
            $mikrotik = new MikrotikService($router);
            $mikrotik->rebootRouter();

            ActivityLog::log(
                'rebooted',
                'Router',
                $router->id,
                "Rebooted router: {$router->name}",
                ['initiated_by' => auth()->user()->name]
            );

            return back()->with('success', 'Router reboot command sent!');
        } catch (\Exception $e) {
            ActivityLog::log(
                'reboot_failed',
                'Router',
                $router->id,
                "Failed to reboot router: {$router->name}",
                ['error' => $e->getMessage()]
            );

            return back()->with('error', 'Failed to reboot: ' . $e->getMessage());
        }
    }

    public function pppoeUsers(Router $router)
    {
        try {
            $mikrotik = new MikrotikService($router);
            $secrets = $mikrotik->getPPPoESecrets($router);
            $activeSessions = $mikrotik->getActivePPPoESessions();

            return view('routers.pppoe-users', compact('router', 'secrets', 'activeSessions'));
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to get PPPoE users: ' . $e->getMessage());
        }
    }

    public function sshTerminal(Router $router)
    {
        return view('routers.ssh-terminal', compact('router'));
    }

    public function executeSSHCommand(Request $request, Router $router)
    {
        $request->validate(['command' => 'required|string']);
        try {
            $ssh = new SSH2($router->ip_address, $router->ssh_port ?? 22);

            if (!$ssh->login($router->username, $router->password)) {
                return response()->json(['success' => false, 'output' => 'Login failed!']);
            }

            $output = $ssh->exec($request->command);

            ActivityLog::log(
                'ssh_command',
                'Router',
                $router->id,
                "Executed SSH command on {$router->name}: {$request->command}",
                ['output' => substr($output, 0, 500)]
            );

            return response()->json(['success' => true, 'output' => $output]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'output' => 'Error: ' . $e->getMessage()]);
        }
    }

    // ✅ FINAL: Import PPPoE from Mikrotik and sync to customers
    public function importPppoe(Router $router)
    {
        try {
            $sync = new MikrotikSyncService(
                $router->ip_address,
                $router->api_user,
                $router->api_password,
                $router->api_port
            );

            $result = $sync->syncFromRouter($router);

            if ($result['success']) {
                return redirect()
                    ->route('routers.show', $router->id)
                    ->with('success', "✅ {$router->name}: {$result['message']}");
            }

            return redirect()
                ->route('routers.show', $router->id)
                ->with('error', "❌ {$router->name}: {$result['message']}");

        } catch (\Exception $e) {
            \Log::error("Import PPPoE gagal untuk router {$router->name}: " . $e->getMessage());
            return redirect()
                ->route('routers.show', $router->id)
                ->with('error', 'Import failed: ' . $e->getMessage());
        }
    }


    
        /**
     * Import Full (PPPoE + Hotspot Profiles)
     */
        /**
     * Import Full (PPPoE + Hotspot Profiles)
     */
    public function importMikrotik(\Illuminate\Http\Request $request, Router $router) {
        dd('DEBUG HIT: Controller Method Reached');
        \Log::info("???? IMPORT MIKROTIK CONTROLLER HIT for Router: " . $router->id);
        set_time_limit(600); 
        ini_set('memory_limit', '512M');
        
        try {
            $svc = new \App\Plugins\Mikrotik\MikrotikSyncService();
            
            // 1. PPPoE Users & Profiles
            $pppoe = $svc->syncFromRouter($router);
            $pppoeMsg = $pppoe['message'] ?? 'PPPoE Sync Done';

            // 2. Hotspot Profiles
            $hotspot = $svc->syncHotspotProfiles($router);
            $hotspotMsg = "Hotspot Profiles: {$hotspot['created']} New, {$hotspot['updated']} Upd";

            return response()->json([
                'success' => true,
                'message' => "Import Full Selesai.\n" . $pppoeMsg . "\n" . $hotspotMsg,
                'details' => [$pppoeMsg, $hotspotMsg],
                'results' => [
                    'pppoe' => $pppoe,
                    'hotspot' => $hotspot
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error("Import Full Failed: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => "Import Failed: " . $e->getMessage()
            ], 500);
        }
    }
}