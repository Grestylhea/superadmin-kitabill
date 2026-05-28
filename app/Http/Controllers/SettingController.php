<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use App\Models\Setting;
use App\Services\WhatsAppGatewayService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use App\Models\WhatsAppTemplate;


class SettingController extends Controller
{
    public function index()
    {
        // Ambil semua settings sebagai key-value array
        $settings = Setting::pluck('value', 'key')->toArray();

        // pastikan ada template default jika tabel masih kosong untuk tenant ini
        $tenantId = tenant()?->id;
        $templateCount = WhatsAppTemplate::when($tenantId, function($query) use ($tenantId) {
            return $query->where('tenant_id', $tenantId);
        })->count();

        if ($templateCount === 0) {
            $defaults = [
                [
                    'name'    => 'Penagihan Invoice',
                    'content' =>
                        "Halo {customer_name},\n\n" .
                        "Ini adalah pengingat tagihan #{invoice_number} sebesar {amount} yang jatuh tempo pada {due_date}.\n" .
                        "Silakan melakukan pembayaran. Terima kasih.",
                ],
                [
                    'name'    => 'Konfirmasi Pembayaran',
                    'content' =>
                        "Halo {customer_name},\n\n" .
                        "Pembayaran untuk invoice #{invoice_number} sebesar {amount} telah kami terima pada {payment_date}.\n" .
                        "Terima kasih telah melakukan pembayaran.",
                ],
                [
                    'name'    => 'Info Registrasi Customer',
                    'content' =>
                        "Halo {customer_name},\n\n" .
                        "Akun internet Anda berhasil dibuat.\n" .
                        "Username: {username}\n" .
                        "Paket: {plan_name}\n" .
                        "Tanggal aktivasi: {activation_date}\n\n" .
                        "Silakan hubungi kami jika membutuhkan bantuan.",
                ],
            ];

            foreach ($defaults as $tpl) {
                $tpl['tenant_id'] = $tenantId;
                WhatsAppTemplate::firstOrCreate(
                    ['name' => $tpl['name'], 'tenant_id' => $tenantId],
                    $tpl
                );
            }
        }

        // Ambil semua template untuk ditampilkan di view (filter per tenant)
        $tenantId = tenant()?->id;
        $waTemplates = WhatsAppTemplate::when($tenantId, function($query) use ($tenantId) {
            return $query->where('tenant_id', $tenantId);
        })->orderBy('name')->get();

        // Daftar timezone yang tersedia
        $timezones = [
            'Asia/Jakarta' => 'WIB - Jakarta, Surabaya, Medan (UTC+7)',
            'Asia/Makassar' => 'WITA - Makassar, Bali, Manado (UTC+8)',
            'Asia/Jayapura' => 'WIT - Jayapura, Ambon, Sorong (UTC+9)',
            'UTC' => 'UTC - Coordinated Universal Time (UTC+0)',
        ];

        return view('settings.index', compact('settings', 'waTemplates', 'timezones'));
    }


    /**  Simpan System Settings */
    public function update(Request $request): RedirectResponse
    {
        // Validasi input
        $request->validate([
            'wa_number' => ['nullable', 'string'],
            'company_name' => ['nullable', 'string'],
            'company_email' => ['nullable', 'email'],
            'app_timezone' => ['nullable', 'string', 'in:Asia/Jakarta,Asia/Makassar,Asia/Jayapura,UTC'],
            
            // WhatsApp Templates
            'wa_expired_template' => ['nullable', 'string'],
            'wa_reminder_h7' => ['nullable', 'string'],
            'wa_reminder_h3' => ['nullable', 'string'],
            'wa_reminder_h1' => ['nullable', 'string'],
            'wa_invoice_notification' => ['nullable', 'string'],
            'wa_reactivation_template' => ['nullable', 'string'],
            'wa_welcome_message' => ['nullable', 'string'],
            
            // Payment Gateway - Xendit
            'xendit_secret_key' => ['nullable', 'string'],
            'xendit_mode' => ['nullable', 'in:sandbox,production'],
            
            // Payment Gateway - Tripay
            'tripay_api_key' => ['nullable', 'string'],
            'tripay_private_key' => ['nullable', 'string'],
            'tripay_merchant_code' => ['nullable', 'string'],
            'tripay_mode' => ['nullable', 'in:sandbox,production'],
            
            // Payment Gateway - Midtrans
            'midtrans_server_key' => ['nullable', 'string'],
            'midtrans_client_key' => ['nullable', 'string'],
            'midtrans_mode' => ['nullable', 'in:sandbox,production'],
            
            // Payment Gateway - Duitku
            'duitku_merchant_code' => ['nullable', 'string'],
            'duitku_api_key' => ['nullable', 'string'],
            'duitku_mode' => ['nullable', 'in:sandbox,production'],
            
            // Active Payment Gateway
            'active_payment_gateway' => ['nullable', 'string'],
        ]);

        // KUMPULKAN setting yang mau disimpan
        $fields = [
            'wa_number',
            'company_name',
            'company_email',
            'app_timezone',
            
            // WhatsApp Templates
            'wa_expired_template',
            'wa_reminder_h7',
            'wa_reminder_h3',
            'wa_reminder_h1',
            'wa_invoice_notification',
            'wa_reactivation_template',
            'wa_welcome_message',
            
            // Payment Gateways
            'xendit_secret_key',
            'xendit_mode',
            'tripay_api_key',
            'tripay_private_key',
            'tripay_merchant_code',
            'tripay_mode',
            'midtrans_server_key',
            'midtrans_client_key',
            'midtrans_mode',
            'duitku_merchant_code',
            'duitku_api_key',
            'duitku_mode',
            'active_payment_gateway',
        ];

        // Handle logo upload
        if ($request->hasFile('company_logo')) {
            // Delete old logo if exists
            $oldLogo = Setting::get('company_logo');
            if ($oldLogo && Storage::disk('public')->exists($oldLogo)) {
                Storage::disk('public')->delete($oldLogo);
            }
            
            // Store new logo
            $logoPath = $request->file('company_logo')->store('logos', 'public');
            Setting::set('company_logo', $logoPath);
        }

        foreach ($fields as $key) {
            $value = $request->input($key);
            if ($value !== null) {
                Setting::set($key, $value);
            }
        }

        return back()->with('success', 'Pengaturan berhasil disimpan.');
    }

    /**
     * Store WhatsApp Template
     */
    public function storeWhatsAppTemplate(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'content' => 'required|string',
        ]);

        // Tambahkan tenant_id saat create template
        $data = $request->only(['name', 'content']);
        $data['tenant_id'] = tenant()?->id;

        WhatsAppTemplate::create($data);

        return back()->with('success', 'Template WhatsApp berhasil ditambahkan.');
    }

    /**
     * Update WhatsApp Template
     */
    public function updateWhatsAppTemplate(Request $request, WhatsAppTemplate $template): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'content' => 'required|string',
        ]);

        // Verifikasi template milik tenant yang sama
        $tenantId = tenant()?->id;
        if ($tenantId && $template->tenant_id !== $tenantId) {
            return back()->with('error', 'Template tidak ditemukan atau tidak memiliki akses.');
        }

        $template->update($request->only(['name', 'content']));

        return back()->with('success', 'Template WhatsApp berhasil diupdate.');
    }

    /**
     * Delete WhatsApp Template
     */
    public function deleteWhatsAppTemplate(WhatsAppTemplate $template): RedirectResponse
    {
        // Verifikasi template milik tenant yang sama
        $tenantId = tenant()?->id;
        if ($tenantId && $template->tenant_id !== $tenantId) {
            return back()->with('error', 'Template tidak ditemukan atau tidak memiliki akses.');
        }

        $template->delete();

        return back()->with('success', 'Template WhatsApp berhasil dihapus.');
    }

    /**
     * Kirim pesan test WhatsApp.
     * Dipanggil dari tombol "Kirim Test" di System Settings.
     */
    public function testWhatsApp(Request $request)
    {
        // Terima input dari form biasa ATAU dari fetch JSON
        $phone   = $request->input('wa_test_number') ?? $request->input('phone');
        
        // Normalisasi nomor HP (Indonesia)
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($phone, '0')) {
            $phone = '62' . substr($phone, 1);
        } elseif (str_starts_with($phone, '8')) {
             $phone = '62' . $phone;
        }

        $message = $request->input('wa_test_message') ?? $request->input('message');

        $request->merge([
            'wa_test_number'  => $phone,
            'wa_test_message' => $message,
        ]);

        $validated = $request->validate([
            'wa_test_number'  => 'required|string',
            'wa_test_message' => 'required|string',
        ]);

        try {
            // Gunakan Service agar logic engine (Go vs Node) otomatis tertangani
            // Kita anggap test dari sini sebagai 'superadmin' atau tenant yang sedang login
            // Jika ingin test sebagai tenant spesifik, bisa masukkan tenantId
            $tenantId = tenant()?->id; // Don't default to superadmin yet, I want to see what it is
            
            \Log::info("DEBUG TEST WA: TenantID from helper: " . json_encode($tenantId));

            if (!$tenantId) $tenantId = 'superadmin';

            $service = new WhatsAppGatewayService($tenantId);
            
            \Log::info("DEBUG TEST WA: Service Config", [
                'tenant_id' => $service->getTenantId(),
                'session' => $service->getSession(),
                'base_url' => $service->getPublicBase(),
            ]);

            $result = $service->sendMessage($validated['wa_test_number'], $validated['wa_test_message']);

            \Log::info("DEBUG TEST WA: Result", $result);

            if ($request->expectsJson() || $request->isJson()) {
                return response()->json([
                    'success' => $result['success'],
                    'message' => $result['message']
                ], $result['success'] ? 200 : 500);
            }

            if ($result['success']) {
                return back()->with('success', $result['message']);
            }

            return back()->with('error', $result['message']);

        } catch (\Throwable $e) {
            \Log::error('WA TEST EXCEPTION', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            if ($request->expectsJson() || $request->isJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal mengirim pesan WhatsApp. Silakan coba lagi.',
                ], 500);
            }

            return back()->with(
                'error',
                'Gagal mengirim pesan WhatsApp. Silakan coba lagi.'
            );
        }
    }


    /**
     * Delete company logo
     */
    public function deleteLogo(Request $request)
    {
        try {
            $logoPath = Setting::get('company_logo');
            
            if ($logoPath && Storage::disk('public')->exists($logoPath)) {
                Storage::disk('public')->delete($logoPath);
            }
            
            Setting::set('company_logo', null);
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Logo berhasil dihapus.'
                ]);
            }
            
            return back()->with('success', 'Logo berhasil dihapus.');
        } catch (\Throwable $e) {
            \Log::error('DELETE LOGO ERROR', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal menghapus logo.'
                ], 500);
            }
            
            return back()->with('error', 'Gagal menghapus logo.');
        }
    }

}