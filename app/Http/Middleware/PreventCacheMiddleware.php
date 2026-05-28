<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class PreventCacheMiddleware
{
    /**
     * Handle an incoming request.
     * Set no-cache headers untuk prevent browser caching error responses
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        
        // ✅ Prevent caching untuk semua response (khususnya error)
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        
        // ✅ Jika error response (500, 404, dll), tambah header khusus
        if ($response->getStatusCode() >= 400) {
            $response->headers->set('X-Cache-Control', 'no-cache');
            $response->headers->set('X-Error-No-Cache', 'true');
        }
        
        // ✅ Tambah versioning header untuk force reload jika ada update
        $response->headers->set('X-App-Version', config('app.version', '1.0.0'));
        
        return $response;
    }
}






