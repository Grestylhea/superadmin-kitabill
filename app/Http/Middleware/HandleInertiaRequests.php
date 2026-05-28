<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Handle an incoming request.
     * Override untuk tambah no-cache headers
     */
    public function handle(Request $request, \Closure $next)
    {
        $response = parent::handle($request, $next);
        
        // ✅ Prevent caching untuk Inertia responses
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        
        return $response;
    }

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'ziggy' => fn() => [
                ...(new \Tighten\Ziggy\Ziggy)->toArray(),
            ],
            'kitabill' => [
                'name' => 'KITABILL',
                'tagline' => 'Billing System Terpercaya',
                'copyright' => [
                    'year' => date('Y'),
                    'company' => 'KITABILL',
                    'text' => '© ' . date('Y') . ' KITABILL. All rights reserved.',
                    'poweredBy' => 'Powered by KITABILL Billing System',
                ],
            ],
            'flash' => [
                'success' => fn() => $request->session()->get('success'),
                'error' => fn() => $request->session()->get('error'),
                'message' => fn() => $request->session()->get('message'),
            ],
        ];
    }
}
