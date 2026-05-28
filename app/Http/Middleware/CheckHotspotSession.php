<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class CheckHotspotSession
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Check if router is selected in session
        if (!Session::has('hotspot_router_id')) {
            return redirect()->route('hotspot.select')
                ->with('warning', 'Silakan pilih router terlebih dahulu.');
        }
        
        // Verify router still exists and is active
        $routerId = Session::get('hotspot_router_id');
        $router = \App\Models\Router::find($routerId);
        
        if (!$router || !$router->is_active) {
            Session::forget('hotspot_router_id');
            return redirect()->route('hotspot.select')
                ->with('error', 'Router tidak ditemukan atau tidak aktif. Silakan pilih router lagi.');
        }
        
        return $next($request);
    }
}

