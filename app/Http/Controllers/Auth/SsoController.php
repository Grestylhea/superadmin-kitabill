<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SsoToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SsoController extends Controller
{
    /**
     * Handle SSO token verification and login for superadmin
     */
    public function verify(Request $request)
    {
        $token = $request->query('token');
        $remember = $request->query('remember', false);

        if (!$token) {
            Log::warning('SSO (Superadmin): Missing token', [
                'ip' => $request->ip(),
                'url' => $request->fullUrl(),
            ]);

            return redirect()->route('login')
                ->withErrors(['error' => 'Invalid SSO request. Please login again.']);
        }

        // ✅ Verify token - Query langsung dari database main (shared database - pgsql)
        $ssoToken = \Illuminate\Support\Facades\DB::table('sso_tokens')
            ->where('token', $token)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        if (!$ssoToken) {
            Log::warning('SSO (Superadmin): Invalid or expired token', [
                'token' => substr($token, 0, 10) . '...',
                'ip' => $request->ip(),
            ]);

            return redirect()->route('login')
                ->withErrors(['error' => 'SSO token is invalid or expired. Please login again.']);
        }

        // ✅ Untuk superadmin, tenant_id = null (skip tenant verification)
        // Verify user is superadmin
        $user = \Illuminate\Support\Facades\DB::table('users')
            ->where('id', $ssoToken->user_id)
            ->where('is_super_admin', true)
            ->first();

        if (!$user) {
            Log::warning('SSO (Superadmin): User is not superadmin', [
                'user_id' => $ssoToken->user_id,
            ]);

            return redirect()->route('login')
                ->withErrors(['error' => 'Invalid SSO token for superadmin.']);
        }

        // ✅ Mark token as used
        \Illuminate\Support\Facades\DB::table('sso_tokens')
            ->where('id', $ssoToken->id)
            ->update(['used_at' => now()]);

        // ✅ Login the user (superadmin) - Load user model dari database main
        $userModel = \App\Models\User::find($user->id);

        if (!$userModel) {
            return redirect()->route('login')
                ->withErrors(['error' => 'User not found.']);
        }

        Auth::login($userModel, $remember);
        $request->session()->regenerate();

        Log::info('SSO (Superadmin): Successful login', [
            'user_id' => $userModel->id,
            'email' => $userModel->email,
        ]);

        // Redirect to dashboard
        return redirect()->intended(route('superadmin.dashboard'));
    }
}

