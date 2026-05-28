<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = Auth::user();

        // ✅ Jika superadmin, SELALU redirect ke panelsuperadmin.kitabill.site
        if ($user->is_super_admin) {
            $host = $request->getHost();
            // Jika sudah di panelsuperadmin.kitabill.site, redirect ke dashboard
            if (str_contains($host, 'panelsuperadmin')) {
                return redirect()->intended(route('superadmin.dashboard'));
            }
            // Jika login dari domain lain, redirect ke panelsuperadmin.kitabill.site
            return redirect()->away('https://panelsuperadmin.kitabill.site/dashboard');
        }

        // Jika user biasa login dari main domain (kitabill.site)
        // Redirect ke subdomain tenant mereka
        $host = $request->getHost();
        $mainDomains = ['kitabill.site', 'www.kitabill.site', 'localhost', '127.0.0.1'];
        
        if (in_array($host, $mainDomains)) {
            // User biasa harus ke subdomain tenant mereka
            if ($user->tenant_id) {
                $tenant = \DB::table('tenants')->where('id', $user->tenant_id)->first();
                if ($tenant) {
                    $subdomain = $tenant->subdomain;
                    $protocol = $request->secure() ? 'https' : 'http';
                    $port = $request->getPort();
                    
                    // Build URL ke subdomain tenant
                    if (in_array($host, ['localhost', '127.0.0.1'])) {
                        // Development: use main domain
                        $tenantUrl = "{$protocol}://{$host}:{$port}/dashboard";
                    } else {
                        // Production: redirect ke subdomain
                        $tenantUrl = "{$protocol}://{$subdomain}.kitabill.site/dashboard";
                    }
                    
                    return redirect()->away($tenantUrl);
                }
            }
        }

        // Default redirect
        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
