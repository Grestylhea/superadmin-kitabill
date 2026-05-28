<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\Setting;

class WhatsAppService
{
    protected $baseUrl;
    protected $isEnabled;

    protected $apiKey;
    protected $sessionId;

    public function __construct()
    {
        // ✅ Gunakan WAKita (wa.kitabill.site) sesuai instruksi user
        $this->baseUrl = env('WAKITA_BASE_URL', 'https://wa.kitabill.site');
        $this->apiKey  = env('WAKITA_SUPERADMIN_API_KEY', 'wk_915fabcd930f16d66b9865fd2b4555b76b085d1c8fd4b8b34590212a5d9f4bd4');
        $this->sessionId = env('WAKITA_SUPERADMIN_SESSION_ID', '1');
        try {
            if (\Schema::hasTable('settings')) {
                $enabled = Setting::where('key', 'whatsapp_enabled')->value('value');
                
                // ✅ Jika setting belum ada, buat default dengan value '1'
                if ($enabled === null) {
                    Setting::firstOrCreate(
                        ['key' => 'whatsapp_enabled'],
                        ['value' => '1']
                    );
                    $enabled = '1';
                    Log::info('WhatsApp enabled setting created with default value: 1');
                }
                
                $this->isEnabled = $enabled === '1';
            } else {
                $this->isEnabled = false;
            }
        } catch (\Exception $e) {
            Log::warning('Failed to check WhatsApp enabled setting: ' . $e->getMessage());
            // ✅ Default ke false jika ada error
            $this->isEnabled = false;
        }
    }

    /**
     * Kirim pesan WhatsApp
     */
    public function sendMessage($phoneNumber, $message)
    {
        // ✅ Validasi: Pastikan nomor telepon tidak kosong
        if (empty($phoneNumber)) {
            Log::warning('WhatsApp sendMessage: phone number is empty');
            $this->logMessage('failed', 'invalid_phone', 'superadmin');
            return ['ok' => false, 'error_type' => 'invalid_phone', 'error_message' => 'Phone number is empty'];
        }
        
        if (!$this->isEnabled) {
            Log::info('WhatsApp disabled, message not sent', [
                'phone' => $phoneNumber,
                'formatted_phone' => $this->formatPhone($phoneNumber)
            ]);
            // Optional: log if disabled? Task says "After send attempt", but being thorough:
            // $this->logMessage('failed', 'disabled', 'superadmin'); 
            return ['ok' => false, 'error_type' => 'disabled', 'error_message' => 'WhatsApp is disabled in settings'];
        }
        
        // ✅ Cek apakah WhatsApp Gateway tersedia (tapi jangan block jika tidak available)
        // ✅ Biarkan sendMessage() tetap mencoba kirim, karena gateway mungkin sementara down
        $gatewayAvailable = $this->isAvailable();
        if (!$gatewayAvailable) {
            Log::warning('WhatsApp Gateway check failed, but will attempt to send message anyway', [
                'phone' => $phoneNumber,
                'formatted_phone' => $this->formatPhone($phoneNumber),
                'note' => 'Message will be attempted even if gateway check failed'
            ]);
            // ✅ Jangan return, lanjut coba kirim
        }

        // ✅ ATOMIC LOCK: Prevent parallel sends for the same session
        $session = 'superadmin'; // Default session
        $lockKey = "wa_lock:session:{$session}";
        $lock = Cache::lock($lockKey, 60);

        try {
            // For Bulk/Cron: Non-blocking (fail fast with 'locked')
            // For others: You could use ->block(5) if needed, 
            // but for simplicity and to match request requirements, we follow the get() strategy.
            if (!$lock->get()) {
                $this->logMessage('locked', null, $session);
                return [
                    'ok' => false, 
                    'error_type' => 'locked', 
                    'error_message' => 'Sender session is busy (locked)'
                ];
            }

            try {
                // Format nomor HP (62xxx) - mendukung 08xxx dan 628xxx
                $phone = $this->formatPhone($phoneNumber);
                
                // ✅ Validasi: Pastikan format phone tidak kosong setelah formatting
                if (empty($phone)) {
                    Log::error('WhatsApp sendMessage: phone number is empty after formatting', [
                        'original_phone' => $phoneNumber
                    ]);
                    return ['ok' => false, 'error_type' => 'invalid_phone', 'error_message' => 'Phone number is empty after formatting'];
                }
                
                Log::info('Sending WhatsApp message', [
                    'original_phone' => $phoneNumber,
                    'formatted_phone' => $phone,
                    'message_length' => strlen($message),
                    'gateway_url' => $this->baseUrl . '/api/send',
                    'session' => $session
                ]);

                // ✅ Endpoint yang benar adalah /api/send dengan API key
                // ✅ Format request sesuai dengan gateway: gunakan 'number' dan 'session'
                // ✅ Kirim via WAKita GET api/sendWA (Sesuai App/Services/WhatsAppGatewayService.php)
                $response = Http::timeout(60)
                    ->get($this->baseUrl . '/api/sendWA', [
                        'to'         => $phone,
                        'msg'        => $message,
                        'secret'     => $this->apiKey,
                        'session_id' => $this->sessionId,
                    ]);

                if ($response->successful()) {
                    Log::info('✅ WhatsApp sent successfully', [
                        'original_phone' => $phoneNumber,
                        'formatted_phone' => $phone,
                        'response' => $response->json()
                    ]);
                    $this->logMessage('success', null, $session);
                    return ['ok' => true];
                }

                $errorData = $response->json();
                $errorMessage = $errorData['error'] ?? $response->body();
                $errorType = 'api_error';

                // Detect disconnection or restriction
                $errorLower = strtolower($errorMessage);
                if (str_contains($errorLower, 'disconnected') || 
                    str_contains($errorLower, 'not connected') ||
                    str_contains($errorLower, 'forbidden') ||
                    str_contains($errorLower, 'restricted') ||
                    str_contains($errorLower, 'suspended')) {
                    $errorType = 'disconnected';
                }

                Log::warning('❌ WhatsApp send failed', [
                    'original_phone' => $phoneNumber,
                    'formatted_phone' => $phone,
                    'status_code' => $response->status(),
                    'error_type' => $errorType,
                    'error' => $errorMessage
                ]);

                $this->logMessage('failed', $errorType, $session);
                return [
                    'ok' => false,
                    'error_type' => $errorType,
                    'error_message' => $errorMessage
                ];

            } catch (\Exception $e) {
                $errorMessage = $e->getMessage();
                $errorType = 'exception';

                // Handle connection timeouts or refusals as potential disconnections or gateway being down
                if (str_contains(strtolower($errorMessage), 'timed out') || str_contains(strtolower($errorMessage), 'connection refused')) {
                    $errorType = 'gateway_down';
                }

                Log::error('❌ WhatsApp sendMessage exception', [
                    'phone' => $phoneNumber,
                    'error' => $errorMessage
                ]);
                
                $this->logMessage('failed', $errorType, $session);
                return [
                    'ok' => false,
                    'error_type' => $errorType,
                    'error_message' => $errorMessage
                ];
            }
        } finally {
            $lock->release();
        }
    }

    /**
     * Record message log for observability
     */
    private function logMessage(string $status, ?string $errorType, string $session): void
    {
        try {
            // Get tenant ID if available (from context)
            $tenantId = null;
            if (function_exists('tenant') && tenant()) {
                $tenantId = tenant()->id;
            }

            DB::table('wa_message_logs')->insert([
                'tenant_id' => $tenantId,
                'sender_session' => $session,
                'status' => $status,
                'error_type' => $errorType,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to record WA message log: ' . $e->getMessage());
        }
    }

    /**
     * Kirim notifikasi custom isolir (untuk isolir berdasarkan jadwal custom)
     * Menggunakan template wa_expired_template dari settings
     */
    public function sendCustomIsolirNotification($customer, $isolirDate)
    {
        // ✅ Validasi: Pastikan customer memiliki nomor telepon
        if (empty($customer->phone)) {
            Log::warning("Cannot send custom isolir notification: customer phone is empty", [
                'customer_code' => $customer->customer_code,
                'customer_id' => $customer->id,
                'isolir_date' => $isolirDate->format('Y-m-d H:i')
            ]);
            return false;
        }
        
        // ✅ Gunakan template expired dari settings (sama seperti expired notification)
        $template = Setting::where('key', 'wa_expired_template')->value('value');
        
        // ✅ Jika template tidak ada di settings, gunakan default
        if (!$template) {
            // Template default untuk custom isolir
            $template = "🔴 *LAYANAN DIBLOKIR*\n\n"
                . "Halo {customer_name},\n\n"
                . "Layanan internet Anda telah *DIBLOKIR* sesuai jadwal yang ditentukan.\n\n"
                . "📅 Tanggal Isolir: {isolir_date}\n"
                . "📦 Paket: {package_name}\n"
                . "👤 Customer Code: {customer_code}\n\n"
                . "Silakan hubungi customer service untuk informasi lebih lanjut.\n\n"
                . "Terima kasih.";
        }

        // ✅ Reload customer untuk memastikan data terbaru
        $customer->refresh();
        
        // ✅ Gunakan method replacePlaceholders yang sudah ada, dengan tambahan isolir_date
        $replacements = [
            '{customer_name}' => $customer->name,
            '{customer_code}' => $customer->customer_code,
            '{package_name}' => $customer->package->name ?? 'N/A',
            '{username}' => $customer->customer_mikrotik_username ?? $customer->customer_code,
            '{phone}' => $customer->phone ?? '-',
            '{isolir_date}' => $isolirDate->translatedFormat('d F Y H:i'),
        ];

        // ✅ Untuk invoice-related placeholders, gunakan 'N/A' karena tidak relevan untuk custom isolir
        $replacements['{invoice_number}'] = 'N/A';
        $replacements['{total}'] = 'N/A';
        $replacements['{due_date}'] = 'N/A';
        $replacements['{issue_date}'] = 'N/A';
        $replacements['{period}'] = 'N/A';

        $message = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $template
        );
        
        Log::info("Sending custom isolir notification via WhatsApp", [
            'customer_code' => $customer->customer_code,
            'customer_name' => $customer->name,
            'phone' => $customer->phone,
            'formatted_phone' => $this->formatPhone($customer->phone),
            'isolir_date' => $isolirDate->format('Y-m-d H:i'),
            'template_from_settings' => !empty(Setting::where('key', 'wa_expired_template')->value('value'))
        ]);
        
        return $this->sendMessage($customer->phone, $message)['ok'] ?? false;
    }

    /**
     * Kirim notifikasi expired
     */
    public function sendExpiredNotification($customer, $invoice)
    {
        // ✅ Validasi: Pastikan customer memiliki nomor telepon
        if (empty($customer->phone)) {
            Log::warning("Cannot send expired notification: customer phone is empty", [
                'customer_code' => $customer->customer_code,
                'customer_id' => $customer->id,
                'invoice_number' => $invoice->invoice_number ?? 'N/A'
            ]);
            return false;
        }
        
        $template = Setting::where('key', 'wa_expired_template')->value('value');
        
        if (!$template) {
            // Template default
            $template = "🔴 *LAYANAN DIBLOKIR*\n\n"
                . "Halo {customer_name},\n\n"
                . "Layanan internet Anda telah *DIBLOKIR* karena tagihan belum dibayar.\n\n"
                . "📄 Invoice: {invoice_number}\n"
                . "💰 Total: Rp {total}\n"
                . "📅 Jatuh Tempo: {due_date}\n"
                . "📦 Paket: {package_name}\n\n"
                . "Segera lakukan pembayaran untuk mengaktifkan kembali layanan.\n\n"
                . "Terima kasih.";
        }

        // ✅ Reload customer untuk memastikan data terbaru
        $customer->refresh();
        
        $message = $this->replacePlaceholders($template, $customer, $invoice);
        
        Log::info("Sending expired notification via WhatsApp", [
            'customer_code' => $customer->customer_code,
            'customer_name' => $customer->name,
            'phone' => $customer->phone,
            'formatted_phone' => $this->formatPhone($customer->phone),
            'invoice_number' => $invoice->invoice_number ?? 'N/A'
        ]);
        
        return $this->sendMessage($customer->phone, $message)['ok'] ?? false;
    }

    /**
     * Kirim notifikasi aktivasi kembali
     */
    public function sendReactivationNotification($customer)
    {
        $template = Setting::where('key', 'wa_reactivation_template')->value('value');
        
        if (!$template) {
            // Template default
            $template = "✅ *LAYANAN DIAKTIFKAN*\n\n"
                . "Halo {customer_name},\n\n"
                . "Pembayaran Anda telah kami terima! 🎉\n\n"
                . "Layanan internet Anda telah *DIAKTIFKAN* kembali.\n\n"
                . "📦 Paket: {package_name}\n"
                . "🌐 Status: AKTIF\n\n"
                . "Terima kasih atas pembayaran Anda!";
        }

        $message = str_replace(
            ['{customer_name}', '{package_name}'],
            [$customer->name, $customer->package->name ?? 'N/A'],
            $template
        );

        return $this->sendMessage($customer->phone, $message)['ok'] ?? false;
    }

    /**
     * Kirim reminder H-7 (7 hari sebelum jatuh tempo)
     */
    public function sendReminderH7($invoice)
    {
        // ✅ Validasi: Pastikan customer memiliki nomor telepon
        if (empty($invoice->customer->phone)) {
            Log::warning("Cannot send reminder H-7: customer phone is empty", [
                'customer_code' => $invoice->customer->customer_code,
                'customer_id' => $invoice->customer->id,
                'invoice_number' => $invoice->invoice_number
            ]);
            return false;
        }
        
        $template = Setting::where('key', 'wa_reminder_h7')->value('value');
        
        if (!$template) {
            $template = "🔔 *PENGINGAT TAGIHAN*\n\n"
                . "Halo {customer_name},\n\n"
                . "Tagihan Anda akan jatuh tempo dalam *7 hari*:\n\n"
                . "📄 Invoice: {invoice_number}\n"
                . "💰 Total: Rp {total}\n"
                . "📅 Jatuh Tempo: {due_date}\n"
                . "📦 Paket: {package_name}\n\n"
                . "Mohon segera lakukan pembayaran.\n\n"
                . "Terima kasih!";
        }

        // ✅ Reload customer untuk memastikan data terbaru
        $invoice->customer->refresh();
        
        $message = $this->replacePlaceholders($template, $invoice->customer, $invoice);
        
        Log::info("Sending reminder H-7 via WhatsApp", [
            'customer_code' => $invoice->customer->customer_code,
            'customer_name' => $invoice->customer->name,
            'phone' => $invoice->customer->phone,
            'formatted_phone' => $this->formatPhone($invoice->customer->phone),
            'invoice_number' => $invoice->invoice_number,
            'due_date' => $invoice->due_date
        ]);
        
        return $this->sendMessage($invoice->customer->phone, $message)['ok'] ?? false;
    }

    /**
     * Kirim reminder H-3 (3 hari sebelum jatuh tempo)
     */
    public function sendReminderH3($invoice)
    {
        // ✅ Validasi: Pastikan customer memiliki nomor telepon
        if (empty($invoice->customer->phone)) {
            Log::warning("Cannot send reminder H-3: customer phone is empty", [
                'customer_code' => $invoice->customer->customer_code,
                'customer_id' => $invoice->customer->id,
                'invoice_number' => $invoice->invoice_number
            ]);
            return false;
        }
        
        $template = Setting::where('key', 'wa_reminder_h3')->value('value');
        
        if (!$template) {
            $template = "⚠️ *PERINGATAN TAGIHAN*\n\n"
                . "Halo {customer_name},\n\n"
                . "Tagihan Anda akan jatuh tempo dalam *3 hari*:\n\n"
                . "📄 Invoice: {invoice_number}\n"
                . "💰 Total: Rp {total}\n"
                . "📅 Jatuh Tempo: {due_date}\n"
                . "📦 Paket: {package_name}\n\n"
                . "⚠️ Segera lakukan pembayaran!\n\n"
                . "Terima kasih!";
        }

        // ✅ Reload customer untuk memastikan data terbaru
        $invoice->customer->refresh();
        
        $message = $this->replacePlaceholders($template, $invoice->customer, $invoice);
        
        Log::info("Sending reminder H-3 via WhatsApp", [
            'customer_code' => $invoice->customer->customer_code,
            'customer_name' => $invoice->customer->name,
            'phone' => $invoice->customer->phone,
            'formatted_phone' => $this->formatPhone($invoice->customer->phone),
            'invoice_number' => $invoice->invoice_number,
            'due_date' => $invoice->due_date
        ]);
        
        return $this->sendMessage($invoice->customer->phone, $message)['ok'] ?? false;
    }

    /**
     * Kirim reminder H-1 (1 hari sebelum jatuh tempo)
     */
    public function sendReminderH1($invoice)
    {
        // ✅ Validasi: Pastikan customer memiliki nomor telepon
        if (empty($invoice->customer->phone)) {
            Log::warning("Cannot send reminder H-1: customer phone is empty", [
                'customer_code' => $invoice->customer->customer_code,
                'customer_id' => $invoice->customer->id,
                'invoice_number' => $invoice->invoice_number
            ]);
            return false;
        }
        
        $template = Setting::where('key', 'wa_reminder_h1')->value('value');
        
        if (!$template) {
            $template = "🚨 *PERINGATAN TERAKHIR*\n\n"
                . "Halo {customer_name},\n\n"
                . "Tagihan Anda akan jatuh tempo *BESOK*:\n\n"
                . "📄 Invoice: {invoice_number}\n"
                . "💰 Total: Rp {total}\n"
                . "📅 Jatuh Tempo: {due_date}\n"
                . "📦 Paket: {package_name}\n\n"
                . "🚨 Segera lakukan pembayaran sebelum layanan diblokir!\n\n"
                . "Terima kasih!";
        }

        // ✅ Reload customer untuk memastikan data terbaru
        $invoice->customer->refresh();
        
        $message = $this->replacePlaceholders($template, $invoice->customer, $invoice);
        
        Log::info("Sending reminder H-1 via WhatsApp", [
            'customer_code' => $invoice->customer->customer_code,
            'customer_name' => $invoice->customer->name,
            'phone' => $invoice->customer->phone,
            'formatted_phone' => $this->formatPhone($invoice->customer->phone),
            'invoice_number' => $invoice->invoice_number,
            'due_date' => $invoice->due_date
        ]);
        
        return $this->sendMessage($invoice->customer->phone, $message)['ok'] ?? false;
    }

    /**
     * Kirim welcome message (customer baru registrasi)
     */
    public function sendWelcomeMessage($customer)
    {
        // ✅ Validasi: Pastikan customer memiliki nomor telepon
        if (empty($customer->phone)) {
            Log::warning("Cannot send welcome message: customer phone is empty", [
                'customer_code' => $customer->customer_code,
                'customer_id' => $customer->id
            ]);
            return false;
        }
        
        $template = Setting::where('key', 'wa_welcome_message')->value('value');
        
        if (!$template) {
            $template = "👋 *SELAMAT DATANG*\n\n"
                . "Halo {customer_name},\n\n"
                . "Terima kasih telah bergabung dengan kami!\n\n"
                . "📦 Paket: {package_name}\n"
                . "👤 Username: {username}\n"
                . "🔑 Password: {password}\n"
                . "📞 Kontak: {phone}\n\n"
                . "Jika ada pertanyaan, jangan ragu untuk menghubungi kami.\n\n"
                . "Selamat menikmati layanan internet kami!";
        }

        // ✅ Reload customer untuk memastikan data terbaru (termasuk package)
        $customer->refresh();
        
        $message = str_replace(
            ['{customer_name}', '{package_name}', '{username}', '{password}', '{phone}', '{customer_code}'],
            [
                $customer->name, 
                $customer->package->name ?? 'N/A',
                $customer->customer_mikrotik_username ?? $customer->customer_code,
                $customer->customer_mikrotik_password ?? '-',
                $customer->phone ?? '-',
                $customer->customer_code
            ],
            $template
        );

        Log::info("Sending welcome WhatsApp message", [
            'customer_code' => $customer->customer_code,
            'customer_name' => $customer->name,
            'phone' => $customer->phone,
            'formatted_phone' => $this->formatPhone($customer->phone),
            'has_template' => !empty($template),
            'message_length' => strlen($message)
        ]);

        return $this->sendMessage($customer->phone, $message)['ok'] ?? false;
    }

    /**
     * Kirim invoice notification
     */
    public function sendInvoiceNotification($invoice)
    {
        $template = Setting::where('key', 'wa_invoice_notification')->value('value');
        
        if (!$template) {
            $template = "📄 *TAGIHAN BARU*\n\n"
                . "Halo {customer_name},\n\n"
                . "Tagihan bulanan Anda telah dibuat:\n\n"
                . "📄 Invoice: {invoice_number}\n"
                . "💰 Total: Rp {total}\n"
                . "📅 Jatuh Tempo: {due_date}\n"
                . "📦 Paket: {package_name}\n"
                . "📅 Periode: {period}\n\n"
                . "Mohon segera lakukan pembayaran sebelum jatuh tempo.\n\n"
                . "Terima kasih!";
        }

        $message = $this->replacePlaceholders($template, $invoice->customer, $invoice);
        return $this->sendMessage($invoice->customer->phone, $message)['ok'] ?? false;
    }

    /**
     * Replace placeholder di template
     */
    protected function replacePlaceholders($template, $customer, $invoice = null)
    {
        $replacements = [
            '{customer_name}' => $customer->name,
            '{customer_code}' => $customer->customer_code,
            '{package_name}' => $customer->package->name ?? 'N/A',
            '{username}' => $customer->customer_mikrotik_username ?? $customer->customer_code,
            '{phone}' => $customer->phone ?? '-',
        ];

        if ($invoice) {
            $replacements['{invoice_number}'] = $invoice->invoice_number;
            $replacements['{total}'] = number_format($invoice->total, 0, ',', '.');
            $replacements['{due_date}'] = \Carbon\Carbon::parse($invoice->due_date)->translatedFormat('d F Y');
            $replacements['{issue_date}'] = \Carbon\Carbon::parse($invoice->issue_date)->translatedFormat('d F Y');
            $replacements['{period}'] = $invoice->period ?? '-';
        }

        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $template
        );
    }

    /**
     * Format nomor HP (mendukung 08xxx dan 628xxx)
     * Input bisa: 081234567890, 6281234567890, +6281234567890, 81234567890
     * Output: 6281234567890
     */
    protected function formatPhone($phone)
    {
        if (empty($phone)) {
            return '';
        }
        
        // Simpan original untuk logging
        $original = $phone;
        
        // Hapus semua karakter non-angka (spasi, dash, plus, dll)
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Jika kosong setelah cleaning, return empty
        if (empty($phone)) {
            Log::warning("Phone number is empty after cleaning", ['original' => $original]);
            return '';
        }
        
        // ✅ Validasi: Tolak nomor telepon yang tidak valid (contoh: 0000000000, 1111111111, dll)
        // Cek apakah semua digit sama (tidak valid)
        if (strlen($phone) > 0 && count(array_unique(str_split($phone))) === 1) {
            Log::warning("Phone number appears to be invalid (all digits are the same)", [
                'original' => $original,
                'cleaned' => $phone
            ]);
            return '';
        }
        
        // ✅ Validasi: Nomor telepon minimal 10 digit (untuk format Indonesia)
        if (strlen($phone) < 10) {
            Log::warning("Phone number too short", [
                'original' => $original,
                'cleaned' => $phone,
                'length' => strlen($phone)
            ]);
            return '';
        }
        
        // ✅ Handle format 08xxx (contoh: 081234567890)
        if (substr($phone, 0, 1) === '0' && strlen($phone) >= 10) {
            // Hapus leading 0, tambahkan 62
            $phone = '62' . substr($phone, 1);
            Log::info("Phone formatted from 0xxx to 62xxx", [
                'original' => $original,
                'formatted' => $phone
            ]);
            return $phone;
        }
        
        // ✅ Handle format 628xxx (contoh: 6281234567890)
        if (substr($phone, 0, 2) === '62' && strlen($phone) >= 11) {
            // Sudah dalam format 62xxx, return as is
            Log::info("Phone already in 62xxx format", [
                'original' => $original,
                'formatted' => $phone
            ]);
            return $phone;
        }
        
        // ✅ Handle format 8xxx (contoh: 81234567890 - tanpa 0 dan tanpa 62)
        if (substr($phone, 0, 1) === '8' && strlen($phone) >= 10) {
            // Tambahkan 62 di depan
            $phone = '62' . $phone;
            Log::info("Phone formatted from 8xxx to 62xxx", [
                'original' => $original,
                'formatted' => $phone
            ]);
            return $phone;
        }
        
        // ✅ Handle format yang sudah ada 62 tapi mungkin ada karakter lain
        if (strlen($phone) >= 10) {
            // Jika panjangnya >= 10 dan tidak dimulai dengan 0 atau 62, coba tambahkan 62
            if (substr($phone, 0, 2) !== '62' && substr($phone, 0, 1) !== '0') {
                $phone = '62' . $phone;
            }
            Log::info("Phone formatted (fallback)", [
                'original' => $original,
                'formatted' => $phone
            ]);
            return $phone;
        }
        
        // Jika tidak memenuhi kriteria, log warning
        Log::warning("Phone number format tidak valid", [
            'original' => $original,
            'cleaned' => $phone,
            'length' => strlen($phone)
        ]);
        
        return $phone;
    }

    /**
     * Cek apakah WhatsApp Gateway tersedia
     */
    public function isAvailable()
    {
        if (!$this->isEnabled) {
            Log::info('WhatsApp is disabled in settings', [
                'whatsapp_enabled' => $this->isEnabled
            ]);
            return false;
        }

        try {
            $response = Http::timeout(5)->get($this->baseUrl . '/api/status');
            
            if (!$response->successful()) {
                Log::warning('WhatsApp Gateway status check failed', [
                    'base_url' => $this->baseUrl,
                    'status_code' => $response->status(),
                    'response' => $response->body()
                ]);
                return false;
            }
            
            $responseData = $response->json();
            
            // ✅ Cek beberapa field untuk memastikan gateway connected
            // Response bisa punya 'isConnected' atau 'connected'
            $isConnected = false;
            $gatewayStatus = $responseData['status'] ?? '';

            if (isset($responseData['isConnected'])) {
                $isConnected = $responseData['isConnected'] === true;
            } elseif (isset($responseData['connected'])) {
                $isConnected = $responseData['connected'] === true;
            } elseif (isset($responseData['success']) && $responseData['success'] === true) {
                // Jika ada success: true, anggap connected
                $isConnected = true;
            }
            
            // ✅ FIX: Treat 'authenticated' as connected
            if ($isConnected || $gatewayStatus === 'authenticated') {
                $isConnected = true;
            }
            
            if (!$isConnected) {
                Log::warning('WhatsApp Gateway not connected', [
                    'base_url' => $this->baseUrl,
                    'status_code' => $response->status(),
                    'response' => $responseData
                ]);
            } else {
                Log::info('WhatsApp Gateway is available and connected', [
                    'base_url' => $this->baseUrl,
                    'response' => $responseData
                ]);
            }
            
            return $isConnected;
        } catch (\Exception $e) {
            Log::warning('WhatsApp Gateway check failed', [
                'base_url' => $this->baseUrl,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    /**
     * Kirim welcome message untuk Tenant Baru (Superadmin)
     */
    public function sendTenantWelcomeMessage($tenant, $data = [])
    {
        // ✅ Validasi: Pastikan tenant memiliki nomor telepon
        if (empty($tenant->phone)) {
            Log::warning("Cannot send tenant welcome message: phone is empty", [
                'tenant_id' => $tenant->id,
                'name' => $tenant->name
            ]);
            return false;
        }

        // Ambil template dari settings (optional)
        $isTrial = $data['is_trial'] ?? false;
        $templateKey = $isTrial ? 'admin_wa_welcome_trial' : 'admin_wa_welcome_paid';
        $template = Setting::where('key', $templateKey)->value('value');

        if (!$template) {
            if ($isTrial) {
                $template = "✅ *AKUN BILLING AKTIF (TRIAL)*\n\n"
                    . "Halo {name},\n\n"
                    . "Selamat datang di Superadmin Kitabill!\n"
                    . "Akun Anda telah berhasil dibuat.\n\n"
                    . "🏢 Tenant: {name}\n"
                    . "🌐 Domain: {domain}\n"
                    . "⏳ Masa Trial: s/d {trial_end}\n\n"
                    . "Silakan login untuk mulai mengelola pelanggan Anda.\n"
                    . "🔗 {login_url}\n\n"
                    . "Butuh bantuan? Balas pesan ini.";
            } else {
                $template = "🧾 *PENDAFTARAN BERHASIL*\n\n"
                    . "Halo {name},\n\n"
                    . "Terima kasih telah mendaftar di Superadmin Kitabill.\n"
                    . "Mohon selesaikan pembayaran untuk mengaktifkan layanan.\n\n"
                    . "📄 Invoice: {invoice_number}\n"
                    . "💰 Total: Rp {amount}\n"
                    . "📅 Jatuh Tempo: {due_date}\n\n"
                    . "🏢 Tenant: {name}\n"
                    . "🌐 Domain: {domain}\n\n"
                    . "Segera lakukan pembayaran agar akun otomatis aktif.\n\n"
                    . "Terima kasih!";
            }
        }

        // Construct Login URL
        $protocol = config('app.env') === 'local' ? 'http://' : 'https://';
        $loginUrl = $protocol . $tenant->subdomain . '.' . config('app.domain', 'kitabill.site') . '/login';

        // Replace Placeholders
        $replacements = [
            '{name}' => $tenant->name,
            '{domain}' => $tenant->subdomain . '.' . config('app.domain', 'kitabill.site'),
            '{trial_end}' => $data['trial_end'] ?? '-',
            '{login_url}' => $loginUrl,
            '{invoice_number}' => $data['invoice_number'] ?? '-',
            '{amount}' => number_format($data['amount'] ?? 0, 0, ',', '.'),
            '{due_date}' => $data['due_date'] ?? '-',
        ];

        $message = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $template
        );

        Log::info("[TENANT_WELCOME_WA] Sending welcome message", [
            'tenant_id' => $tenant->id,
            'phone' => $tenant->phone,
            'is_trial' => $isTrial
        ]);

        return $this->sendMessage($tenant->phone, $message)['ok'] ?? false;
    }
    /**
     * Get random gap for rate limiting (30-60 seconds)
     */
    public static function getRandomGap(): int
    {
        return random_int(30, 60);
    }

    // ============================================================
    // TENANT SUBSCRIPTION REMINDER METHODS (SuperAdmin)
    // ============================================================

    /**
     * Kirim reminder H-7 ke tenant (7 hari sebelum subscription expired)
     */
    public function sendTenantSubscriptionReminderH7($tenant, $expiresAt): bool
    {
        if (empty($tenant->phone)) {
            Log::warning('[TENANT_REMINDER_H7] Phone empty, skipping', ['tenant_id' => $tenant->id]);
            return false;
        }

        $template = Setting::where('key', 'wa_tenant_reminder_h7')->value('value');

        if (!$template) {
            $template = "⏰ *PENGINGAT SUBSCRIPTION*\n\n"
                . "Halo {tenant_name},\n\n"
                . "Subscription Kitabill Anda akan *berakhir dalam 7 hari*:\n\n"
                . "📦 Plan: {plan}\n"
                . "📅 Expired: {expires_at}\n"
                . "🌐 Domain: {subdomain}\n\n"
                . "Segera lakukan perpanjangan agar layanan tidak terganggu.\n\n"
                . "Login ke panel Anda untuk melakukan renewal:\n"
                . "🔗 https://{subdomain}/subscription\n\n"
                . "Terima kasih!";
        }

        $message = $this->replaceTenantPlaceholders($template, $tenant, $expiresAt);

        Log::info('[TENANT_REMINDER_H7] Sending', ['tenant_id' => $tenant->id, 'phone' => $tenant->phone]);
        return $this->sendMessage($tenant->phone, $message)['ok'] ?? false;
    }

    /**
     * Kirim reminder H-3 ke tenant (3 hari sebelum subscription expired)
     */
    public function sendTenantSubscriptionReminderH3($tenant, $expiresAt): bool
    {
        if (empty($tenant->phone)) {
            Log::warning('[TENANT_REMINDER_H3] Phone empty, skipping', ['tenant_id' => $tenant->id]);
            return false;
        }

        $template = Setting::where('key', 'wa_tenant_reminder_h3')->value('value');

        if (!$template) {
            $template = "⚠️ *PERINGATAN SUBSCRIPTION*\n\n"
                . "Halo {tenant_name},\n\n"
                . "Subscription Kitabill Anda akan *berakhir dalam 3 hari*:\n\n"
                . "📦 Plan: {plan}\n"
                . "📅 Expired: {expires_at}\n"
                . "🌐 Domain: {subdomain}\n\n"
                . "⚠️ Jika tidak diperpanjang, akun akan *otomatis disuspend*.\n\n"
                . "Segera renewal sekarang:\n"
                . "🔗 https://{subdomain}/subscription\n\n"
                . "Terima kasih!";
        }

        $message = $this->replaceTenantPlaceholders($template, $tenant, $expiresAt);

        Log::info('[TENANT_REMINDER_H3] Sending', ['tenant_id' => $tenant->id, 'phone' => $tenant->phone]);
        return $this->sendMessage($tenant->phone, $message)['ok'] ?? false;
    }

    /**
     * Kirim reminder H-1 ke tenant (1 hari sebelum subscription expired)
     */
    public function sendTenantSubscriptionReminderH1($tenant, $expiresAt): bool
    {
        if (empty($tenant->phone)) {
            Log::warning('[TENANT_REMINDER_H1] Phone empty, skipping', ['tenant_id' => $tenant->id]);
            return false;
        }

        $template = Setting::where('key', 'wa_tenant_reminder_h1')->value('value');

        if (!$template) {
            $template = "🚨 *PERINGATAN TERAKHIR*\n\n"
                . "Halo {tenant_name},\n\n"
                . "Subscription Kitabill Anda akan *berakhir BESOK*:\n\n"
                . "📦 Plan: {plan}\n"
                . "📅 Expired: {expires_at}\n"
                . "🌐 Domain: {subdomain}\n\n"
                . "🚨 Akun akan *DISUSPEND OTOMATIS* jika tidak diperpanjang.\n\n"
                . "Lakukan renewal sekarang:\n"
                . "🔗 https://{subdomain}/subscription\n\n"
                . "Butuh bantuan? Hubungi support kami.";
        }

        $message = $this->replaceTenantPlaceholders($template, $tenant, $expiresAt);

        Log::info('[TENANT_REMINDER_H1] Sending', ['tenant_id' => $tenant->id, 'phone' => $tenant->phone]);
        return $this->sendMessage($tenant->phone, $message)['ok'] ?? false;
    }

    /**
     * Kirim notifikasi ke tenant bahwa akun telah disuspend (subscription expired)
     */
    public function sendTenantSuspendedNotification($tenant): bool
    {
        if (empty($tenant->phone)) {
            Log::warning('[TENANT_SUSPENDED] Phone empty, skipping', ['tenant_id' => $tenant->id]);
            return false;
        }

        $template = Setting::where('key', 'wa_tenant_suspended')->value('value');

        if (!$template) {
            $template = "🔴 *AKUN DISUSPEND*\n\n"
                . "Halo {tenant_name},\n\n"
                . "Akun Kitabill Anda telah *DISUSPEND* karena subscription sudah berakhir.\n\n"
                . "📦 Plan: {plan}\n"
                . "🌐 Domain: {subdomain}\n\n"
                . "Untuk mengaktifkan kembali akun Anda, silakan hubungi admin atau lakukan pembayaran perpanjangan.\n\n"
                . "Terima kasih.";
        }

        $message = $this->replaceTenantPlaceholders($template, $tenant);

        Log::info('[TENANT_SUSPENDED] Sending suspended notification', ['tenant_id' => $tenant->id, 'phone' => $tenant->phone]);
        return $this->sendMessage($tenant->phone, $message)['ok'] ?? false;
    }

    /**
     * Helper: Replace placeholders di template subscription tenant
     */
    protected function replaceTenantPlaceholders(string $template, $tenant, $expiresAt = null): string
    {
        $domain = $tenant->subdomain . '.' . config('app.domain', 'kitabill.site');

        $replacements = [
            '{tenant_name}' => $tenant->name,
            '{subdomain}'   => $domain,
            '{plan}'        => ucfirst($tenant->subscription_plan ?? 'Standard'),
            '{expires_at}'  => $expiresAt
                ? \Carbon\Carbon::parse($expiresAt)->translatedFormat('d F Y')
                : '-',
            '{days_remaining}' => $expiresAt
                ? max(0, (int) now()->diffInDays(\Carbon\Carbon::parse($expiresAt), false))
                : '-',
        ];

        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $template
        );
    }
}

