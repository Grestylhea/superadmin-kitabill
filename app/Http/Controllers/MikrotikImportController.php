<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Router;
use App\Plugins\MikrotikImport\MikrotikImportService;
use App\Plugins\MikrotikSync\MikrotikSyncService;
use Illuminate\Support\Facades\Log;
use Exception;

class MikrotikImportController extends Controller
{
    public function index()
    {
        $routers = Router::all();
        return view('mikrotik.import', compact('routers'));
    }

    public function import(Request $request)
    {
        $request->validate([
            'type' => 'required|string',
            'router' => 'required|string',
        ]);

        $router = Router::where('name', $request->router)->first();
        if (!$router) return back()->withErrors(['Router tidak ditemukan.']);

        try {
            $importer = new MikrotikImportService(
                $router->ip_address,
                $router->username,
                $router->password,
                $router->api_port
            );
            $results = $importer->importToDatabase($request->type, $router);
            return view('mikrotik.import-start', compact('results'));
        } catch (Exception $e) {
            Log::error("Import gagal: " . $e->getMessage());
            return back()->withErrors(['Gagal menghubungi router: ' . $e->getMessage()]);
        }
    }

    public function sync(Request $request)
    {
        $router = Router::find($request->router_id);
        if (!$router) return back()->withErrors(['Router tidak ditemukan.']);

        try {
            $sync = new MikrotikSyncService(
                $router->ip_address,
                $router->username,
                $router->password,
                $router->api_port
            );
            $result = $sync->syncActiveSessions($router);
            return back()->with('success', "Sinkronisasi selesai: {$result['updated']} user diperbarui");
        } catch (Exception $e) {
            return back()->withErrors(['Gagal sinkronisasi: ' . $e->getMessage()]);
        }
    }
}
