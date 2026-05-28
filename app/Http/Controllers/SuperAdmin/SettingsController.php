<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingsController extends Controller
{
    /**
     * Show settings page
     */
    public function index()
    {
        $paymentSettings = SystemSetting::where('group', 'payment')
            ->get()
            ->mapWithKeys(function ($setting) {
                $value = $setting->value;
                
                // Decrypt encrypted values for display (mask sensitive data)
                if ($setting->type === 'encrypted' && !empty($value)) {
                    try {
                        $decrypted = \Illuminate\Support\Facades\Crypt::decryptString($value);
                        // Mask the value for security (show only last 4 chars)
                        $value = strlen($decrypted) > 4 
                            ? str_repeat('*', strlen($decrypted) - 4) . substr($decrypted, -4) 
                            : str_repeat('*', strlen($decrypted));
                    } catch (\Exception $e) {
                        $value = $setting->value;
                    }
                }
                
                return [
                    $setting->key => [
                        'value' => $value,
                        'type' => $setting->type,
                        'description' => $setting->description,
                    ]
                ];
            });

        $whatsappSettings = SystemSetting::where('group', 'whatsapp')
            ->get()
            ->mapWithKeys(function ($setting) {
                $value = $setting->value;
                
                // Decrypt encrypted values for display (mask sensitive data)
                if ($setting->type === 'encrypted' && !empty($value)) {
                    try {
                        $decrypted = \Illuminate\Support\Facades\Crypt::decryptString($value);
                        // Mask the value for security (show only last 4 chars)
                        $value = strlen($decrypted) > 4 
                            ? str_repeat('*', strlen($decrypted) - 4) . substr($decrypted, -4) 
                            : str_repeat('*', strlen($decrypted));
                    } catch (\Exception $e) {
                        $value = $setting->value;
                    }
                }
                
                return [
                    $setting->key => [
                        'value' => $value,
                        'type' => $setting->type,
                        'description' => $setting->description,
                    ]
                ];
            });

        $emailSettings = SystemSetting::where('group', 'email')
            ->get()
            ->mapWithKeys(function ($setting) {
                $value = $setting->value;
                
                // Decrypt encrypted values for display (mask sensitive data)
                if ($setting->type === 'encrypted' && !empty($value)) {
                    try {
                        $decrypted = \Illuminate\Support\Facades\Crypt::decryptString($value);
                        // Mask the value for security (show only last 4 chars)
                        $value = strlen($decrypted) > 4 
                            ? str_repeat('*', strlen($decrypted) - 4) . substr($decrypted, -4) 
                            : str_repeat('*', strlen($decrypted));
                    } catch (\Exception $e) {
                        $value = $setting->value;
                    }
                }
                
                return [
                    $setting->key => [
                        'value' => $value,
                        'type' => $setting->type,
                        'description' => $setting->description,
                    ]
                ];
            });

        $referralSettings = SystemSetting::where('group', 'referral')
            ->get()
            ->mapWithKeys(function ($setting) {
                return [
                    $setting->key => [
                        'value' => $setting->value,
                        'type' => $setting->type,
                        'description' => $setting->description,
                    ]
                ];
            });

        return Inertia::render('SuperAdmin/Settings', [
            'paymentSettings' => $paymentSettings,
            'whatsappSettings' => $whatsappSettings,
            'emailSettings' => $emailSettings,
            'referralSettings' => $referralSettings,
            'activeGateway' => system_setting('subscription_payment_gateway', 'xendit'),
            'webhookUrl' => config('app.url', 'https://kitabill.site'),
        ]);
    }

    /**
     * Update payment gateway settings
     */
    public function updatePaymentSettings(Request $request)
    {
        $validated = $request->validate([
            'subscription_payment_gateway' => 'required|in:xendit,midtrans,duitku,tripay',
            'xendit_subscription_api_key' => 'nullable|string',
            'xendit_subscription_webhook_token' => 'nullable|string',
            'midtrans_subscription_server_key' => 'nullable|string',
            'midtrans_subscription_client_key' => 'nullable|string',
            'duitku_subscription_merchant_code' => 'nullable|string',
            'duitku_subscription_api_key' => 'nullable|string',
            'tripay_merchant_code' => 'nullable|string',
            'tripay_api_key' => 'nullable|string',
            'tripay_private_key' => 'nullable|string',
            'tripay_mode' => 'nullable|in:sandbox,production',
            'tripay_enabled' => 'nullable|boolean',
            'subscription_setup_fee' => 'nullable|numeric|min:0',
            'enable_trial_period' => 'nullable|boolean',
            'trial_period_days' => 'nullable|integer|min:1|max:90',
        ]);

        // Update each setting
        foreach ($validated as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $existingSetting = SystemSetting::where('key', $key)->first();
            
            if (!$existingSetting) {
                // Create new setting if not exists
                $type = 'string';
                if (str_contains($key, 'api_key') || str_contains($key, 'server_key') || str_contains($key, 'client_key') || str_contains($key, 'merchant_code') || str_contains($key, 'webhook_token')) {
                    $type = 'encrypted';
                } elseif ($key === 'enable_trial_period') {
                    $type = 'boolean';
                }
                
                SystemSetting::create([
                    'key' => $key,
                    'value' => $type === 'encrypted' ? \Illuminate\Support\Facades\Crypt::encryptString($value) : $value,
                    'type' => $type,
                    'group' => 'payment',
                ]);
            } else {
                // Update existing setting
                if ($existingSetting->type === 'encrypted') {
                    // Only update if value is not masked (doesn't start with ***) and not empty
                    // Masked value biasanya panjang dan dimulai dengan ***
                    if (!empty($value) && !str_starts_with($value, '***')) {
                        SystemSetting::set($key, $value, 'encrypted');
                    }
                } else {
                    SystemSetting::set($key, $value, $existingSetting->type);
                }
            }
        }

        // Clear settings cache
        SystemSetting::clearCache();

        return redirect()->route('superadmin.settings.index')
            ->with('success', 'Payment gateway settings updated successfully!');
    }

    /**
     * Update WhatsApp settings
     */
    public function updateWhatsAppSettings(Request $request)
    {
        $validated = $request->validate([
            'whatsapp_provider' => 'required|in:custom,fonnte,wablas,twilio',
            'whatsapp_api_url' => 'required|url',
            'whatsapp_api_token' => 'required|string',
            'whatsapp_phone_number' => 'required|string',
            'whatsapp_otp_template' => 'nullable|string',
        ]);

        // Update each setting
        foreach ($validated as $key => $value) {
            if ($value === null) {
                continue;
            }

            $existingSetting = SystemSetting::where('key', $key)->first();
            
            if (!$existingSetting) {
                // Create new setting if not exists
                $type = 'string';
                if ($key === 'whatsapp_api_token') {
                    $type = 'encrypted';
                } elseif ($key === 'whatsapp_otp_template') {
                    $type = 'text';
                }
                
                SystemSetting::create([
                    'key' => $key,
                    'value' => $type === 'encrypted' ? \Illuminate\Support\Facades\Crypt::encryptString($value) : $value,
                    'type' => $type,
                    'group' => 'whatsapp',
                    'description' => ucwords(str_replace('_', ' ', str_replace('whatsapp_', '', $key))),
                ]);
            } else {
                // Update existing setting
                if ($existingSetting->type === 'encrypted') {
                    // Only update if value is not masked (doesn't start with ***)
                    if (!str_starts_with($value, '***')) {
                        SystemSetting::set($key, $value, 'encrypted');
                    }
                } else {
                    SystemSetting::set($key, $value, $existingSetting->type);
                }
            }
        }

        // Clear settings cache
        SystemSetting::clearCache();

        return redirect()->route('superadmin.settings.index')
            ->with('success', 'WhatsApp settings updated successfully!');
    }

    /**
     * Update email settings
     */
    public function updateEmailSettings(Request $request)
    {
        $validated = $request->validate([
            'mail_mailer' => 'required|in:smtp,sendmail,mailgun,ses,postmark,log',
            'mail_host' => 'nullable|string|max:255',
            'mail_port' => 'nullable|integer|min:1|max:65535',
            'mail_username' => 'nullable|string|max:255',
            'mail_password' => 'nullable|string',
            'mail_encryption' => 'nullable|in:tls,ssl',
            'mail_from_address' => 'required|email|max:255',
            'mail_from_name' => 'required|string|max:255',
        ]);

        // Update each setting
        foreach ($validated as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $existingSetting = SystemSetting::where('key', $key)->first();
            
            if (!$existingSetting) {
                // Create new setting if not exists
                $type = 'string';
                if ($key === 'mail_password') {
                    $type = 'encrypted';
                } elseif (in_array($key, ['mail_port'])) {
                    $type = 'integer';
                }
                
                SystemSetting::create([
                    'key' => $key,
                    'value' => $type === 'encrypted' ? \Illuminate\Support\Facades\Crypt::encryptString($value) : $value,
                    'type' => $type,
                    'group' => 'email',
                    'description' => ucwords(str_replace('_', ' ', str_replace('mail_', '', $key))),
                ]);
            } else {
                // Update existing setting
                if ($existingSetting->type === 'encrypted') {
                    // Only update if value is not masked (doesn't start with ***)
                    if (!str_starts_with($value, '***')) {
                        SystemSetting::set($key, $value, 'encrypted');
                    }
                } else {
                    SystemSetting::set($key, $value, $existingSetting->type);
                }
            }
        }

        // Update Laravel mail config dynamically
        $this->updateMailConfig();

        // Clear settings cache
        SystemSetting::clearCache();

        return redirect()->route('superadmin.settings.index')
            ->with('success', 'Email settings updated successfully!');
    }

    /**
     * Update Laravel mail configuration from system settings
     */
    private function updateMailConfig()
    {
        $mailer = system_setting('mail_mailer', config('mail.default'));
        $host = system_setting('mail_host', config('mail.mailers.smtp.host'));
        $port = system_setting('mail_port', config('mail.mailers.smtp.port'));
        $username = system_setting('mail_username', config('mail.mailers.smtp.username'));
        $password = system_setting('mail_password');
        $encryption = system_setting('mail_encryption', config('mail.mailers.smtp.encryption'));
        $fromAddress = system_setting('mail_from_address', config('mail.from.address'));
        $fromName = system_setting('mail_from_name', config('mail.from.name'));

        // Decrypt password if encrypted
        if ($password) {
            try {
                $password = \Illuminate\Support\Facades\Crypt::decryptString($password);
            } catch (\Exception $e) {
                // Password might not be encrypted yet
            }
        }

        // Update config
        config([
            'mail.default' => $mailer,
            'mail.mailers.smtp.host' => $host,
            'mail.mailers.smtp.port' => $port,
            'mail.mailers.smtp.username' => $username,
            'mail.mailers.smtp.password' => $password,
            'mail.mailers.smtp.encryption' => $encryption,
            'mail.from.address' => $fromAddress,
            'mail.from.name' => $fromName,
        ]);
    }

    /**
     * Test WhatsApp connection
     */
    public function testWhatsAppConnection(Request $request)
    {
        $provider = system_setting('whatsapp_provider', 'custom');
        $apiUrl = system_setting('whatsapp_api_url') ?: config('services.wa_gateway.url') ?: env('WA_GATEWAY_URL');
        $apiToken = system_setting('whatsapp_api_token') ?: config('services.wa_gateway.key') ?: env('WA_GATEWAY_TOKEN');
        $phoneNumber = system_setting('whatsapp_phone_number') ?: config('services.wa_gateway.public') ?: env('WA_GATEWAY_PUBLIC');

        if (!$apiUrl || !$apiToken) {
            return response()->json(['success' => false, 'message' => 'WhatsApp credentials not configured']);
        }

        try {
            $testNumber = $request->input('test_number', $phoneNumber);
            $testMessage = "Test message from KitaBill System. Time: " . now()->format('Y-m-d H:i:s');

            if ($provider === 'custom') {
                // Custom gateway (self-hosted)
                $response = \Http::withHeaders([
                    'Authorization' => 'Bearer ' . $apiToken,
                    'Content-Type' => 'application/json',
                ])->post($apiUrl . '/send', [
                    'to' => $testNumber,
                    'message' => $testMessage,
                    'sender_id' => $phoneNumber,
                ]);

                if ($response->successful()) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Test message sent successfully to custom gateway!',
                        'data' => $response->json()
                    ]);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send test message: ' . $response->body()
                ]);
            } elseif ($provider === 'fonnte') {
                $response = \Http::withHeaders([
                    'Authorization' => $apiToken,
                ])->post($apiUrl . '/send', [
                    'target' => $testNumber,
                    'message' => $testMessage,
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    return response()->json([
                        'success' => true,
                        'message' => 'Test message sent successfully!',
                        'data' => $data
                    ]);
                }
            } elseif ($provider === 'wablas') {
                $response = \Http::withHeaders([
                    'Authorization' => $apiToken,
                ])->post($apiUrl . '/api/send-message', [
                    'phone' => $testNumber,
                    'message' => $testMessage,
                ]);

                if ($response->successful()) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Test message sent successfully!',
                        'data' => $response->json()
                    ]);
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to send test message: ' . $response->body()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Test payment gateway connection
     */
    public function testConnection(Request $request)
    {
        $gateway = $request->input('gateway');
        
        try {
            switch ($gateway) {
                case 'xendit':
                    return $this->testXenditConnection();
                case 'midtrans':
                    return $this->testMidtransConnection();
                case 'duitku':
                    return $this->testDuitkuConnection();
                case 'tripay':
                    return $this->testTripayConnection();
                default:
                    return response()->json(['success' => false, 'message' => 'Invalid gateway']);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Test Xendit connection
     */
    private function testXenditConnection()
    {
        $apiKey = system_setting('xendit_subscription_api_key');
        
        if (!$apiKey) {
            return response()->json(['success' => false, 'message' => 'API Key not configured']);
        }

        $response = \Http::withBasicAuth($apiKey, '')
            ->get('https://api.xendit.co/balance');

        if ($response->successful()) {
            return response()->json([
                'success' => true,
                'message' => 'Connection successful!',
                'data' => $response->json()
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Connection failed: ' . $response->body()
        ]);
    }

    /**
     * Test Midtrans connection
     */
    private function testMidtransConnection()
    {
        $serverKey = system_setting('midtrans_subscription_server_key');
        
        if (!$serverKey) {
            return response()->json(['success' => false, 'message' => 'Server Key not configured']);
        }

        // Test with Midtrans ping endpoint
        $response = \Http::withHeaders([
            'Authorization' => 'Basic ' . base64_encode($serverKey . ':'),
            'Content-Type' => 'application/json',
        ])->get('https://api.midtrans.com/v2/');

        if ($response->successful()) {
            return response()->json([
                'success' => true,
                'message' => 'Connection successful!'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Connection failed: Invalid credentials'
        ]);
    }

    /**
     * Test Duitku connection
     */
    private function testDuitkuConnection()
    {
        $merchantCode = system_setting('duitku_subscription_merchant_code');
        $apiKey = system_setting('duitku_subscription_api_key');
        
        if (!$merchantCode || !$apiKey) {
            return response()->json(['success' => false, 'message' => 'Credentials not configured']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Credentials configured (Duitku test requires actual transaction)'
        ]);
    }

    /**
     * Test Tripay Connection
     */
    private function testTripayConnection()
    {
        $apiKey = system_setting('tripay_api_key');
        $mode = system_setting('tripay_mode', 'sandbox');

        if (!$apiKey) {
            return response()->json(['success' => false, 'message' => 'Tripay API Key not set']);
        }

        $baseUrl = $mode === 'production'
            ? 'https://tripay.co.id/api/merchant/payment-channel'
            : 'https://tripay.co.id/api-sandbox/merchant/payment-channel';

        try {
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
            ])->get($baseUrl);

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Connection successful! (Channels fetched)',
                    'data' => $response->json()
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Tripay Error: ' . ($response->json()['message'] ?? $response->body())
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Update referral settings
     */
    public function updateReferralSettings(Request $request)
    {
        $validated = $request->validate([
            'referral_system_enabled' => 'nullable|boolean',
            'global_referral_commission_rate' => 'required|numeric|min:0|max:100',
            'referral_min_withdrawal_amount' => 'required|numeric|min:0',
        ]);

        foreach ($validated as $key => $value) {
            $existingSetting = SystemSetting::where('key', $key)->first();
            
            if (!$existingSetting) {
                SystemSetting::create([
                    'key' => $key,
                    'value' => $value,
                    'type' => is_bool($value) ? 'boolean' : (is_numeric($value) ? 'integer' : 'string'),
                    'group' => 'referral',
                    'description' => ucwords(str_replace('_', ' ', str_replace('referral_', '', $key))),
                ]);
            } else {
                SystemSetting::set($key, $value, $existingSetting->type);
            }
        }

        SystemSetting::clearCache();

        return redirect()->route('superadmin.settings.index')
            ->with('success', 'Referral settings updated successfully!');
    }
}


