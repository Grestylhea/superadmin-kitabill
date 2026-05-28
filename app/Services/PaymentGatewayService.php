<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymentGatewayService
{
    protected $activeGateway;
    protected $config = [];

    public function __construct()
    {
        $this->loadConfig();
    }

    /**
     * Load configuration dari database
     */
    protected function loadConfig()
    {
        $settings = Setting::pluck('value', 'key')->toArray();
        
        $this->activeGateway = $settings['active_payment_gateway'] ?? null;
        $this->config = $settings;
    }

    /**
     * Get active gateway
     */
    public function getActiveGateway(): ?string
    {
        return $this->activeGateway;
    }

    /**
     * Create payment berdasarkan gateway aktif
     * 
     * @param array $params ['amount', 'invoice_number', 'customer_name', 'customer_email', 'customer_phone']
     * @return array
     */
    public function createPayment(array $params): array
    {
        if (!$this->activeGateway) {
            return [
                'success' => false,
                'message' => 'Tidak ada payment gateway yang aktif. Silakan konfigurasi di Settings.'
            ];
        }

        $method = 'create' . ucfirst($this->activeGateway) . 'Payment';
        
        if (!method_exists($this, $method)) {
            return [
                'success' => false,
                'message' => "Gateway '{$this->activeGateway}' belum di-implement."
            ];
        }

        try {
            return $this->$method($params);
        } catch (\Throwable $e) {
            Log::error("Payment Gateway Error ({$this->activeGateway})", [
                'error' => $e->getMessage(),
                'params' => $params
            ]);

            return [
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Create Xendit Payment
     */
    protected function createXenditPayment(array $params): array
    {
        $secretKey = $this->config['xendit_secret_key'] ?? null;
        $mode = $this->config['xendit_mode'] ?? 'sandbox';

        if (!$secretKey) {
            return ['success' => false, 'message' => 'Xendit secret key belum dikonfigurasi'];
        }

        $baseUrl = $mode === 'production' 
            ? 'https://api.xendit.co' 
            : 'https://api.xendit.co';

        // Example: Create VA
        $response = Http::withBasicAuth($secretKey, '')
            ->post($baseUrl . '/callback_virtual_accounts', [
                'external_id' => $params['invoice_number'],
                'bank_code' => 'BNI', // atau sesuaikan
                'name' => $params['customer_name'],
                'expected_amount' => $params['amount'],
                'is_closed' => true,
                'expiration_date' => now()->addDays(3)->toIso8601String(),
            ]);

        if ($response->successful()) {
            $data = $response->json();
            return [
                'success' => true,
                'payment_url' => null,
                'account_number' => $data['account_number'] ?? null,
                'bank_code' => $data['bank_code'] ?? null,
                'amount' => $data['expected_amount'] ?? $params['amount'],
                'raw_response' => $data
            ];
        }

        return [
            'success' => false,
            'message' => 'Xendit Error: ' . ($response->json()['message'] ?? $response->body())
        ];
    }

    /**
     * Get Tripay Channels (For Connection Test)
     */
    public function getTripayChannels(): array
    {
        $apiKey = $this->config['tripay_api_key'] ?? null;
        $mode = $this->config['tripay_mode'] ?? 'sandbox';

        if (!$apiKey) {
            return ['success' => false, 'message' => 'API Key missing'];
        }

        $baseUrl = $mode === 'production'
            ? 'https://tripay.co.id/api/merchant/payment-channel'
            : 'https://tripay.co.id/api-sandbox/merchant/payment-channel';

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
            ])->get($baseUrl);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()['data'] ?? []
                ];
            }

            return [
                'success' => false, 
                'message' => 'Tripay Error: ' . ($response->json()['message'] ?? $response->body())
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Create Tripay Payment
     */
    protected function createTripayPayment(array $params): array
    {
        $apiKey = $this->config['tripay_api_key'] ?? null;
        $privateKey = $this->config['tripay_private_key'] ?? null;
        $merchantCode = $this->config['tripay_merchant_code'] ?? null;
        $mode = $this->config['tripay_mode'] ?? 'sandbox';

        if (!$apiKey || !$privateKey || !$merchantCode) {
            return ['success' => false, 'message' => 'Tripay credentials belum lengkap'];
        }

        $baseUrl = $mode === 'production'
            ? 'https://tripay.co.id/api/transaction/create'
            : 'https://tripay.co.id/api-sandbox/transaction/create';

        $method = $params['method'] ?? 'BRIVA'; // Default or passed param
        
        $data = [
            'method' => $method,
            'merchant_ref' => $params['invoice_number'],
            'amount' => $params['amount'],
            'customer_name' => $params['customer_name'],
            'customer_email' => $params['customer_email'] ?? 'noreply@kitabill.site',
            'customer_phone' => $params['customer_phone'] ?? '08123456789',
            'order_items' => [
                [
                    'sku' => 'INV-' . $params['invoice_number'],
                    'name' => 'Subscription Invoice ' . $params['invoice_number'],
                    'price' => $params['amount'],
                    'quantity' => 1,
                ]
            ],
            'callback_url' => url('/api/webhooks/tripay-superadmin'), 
            'return_url' => url('/superadmin/subscriptions'), // Redirect back to panel
            'expired_time' => (time() + (24 * 60 * 60)), // 24 hours
        ];

        $signature = hash_hmac('sha256', $merchantCode . $params['invoice_number'] . $params['amount'], $privateKey);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
        ])->post($baseUrl, array_merge($data, [
            'signature' => $signature
        ]));

        Log::info('[SUPERADMIN_TRIPAY_CREATE]', [
            'params' => $data,
            'response_status' => $response->status(),
            'response_body' => $response->json(),
        ]);

        if ($response->successful()) {
            $result = $response->json();
            return [
                'success' => true,
                'payment_url' => $result['data']['checkout_url'] ?? null,
                'reference' => $result['data']['reference'] ?? null,
                'amount' => $result['data']['amount'] ?? $params['amount'],
                'raw_response' => $result
            ];
        }

        return [
            'success' => false,
            'message' => 'Tripay Error: ' . ($response->json()['message'] ?? $response->body())
        ];
    }

    /**
     * Create Midtrans Payment
     */
    protected function createMidtransPayment(array $params): array
    {
        $serverKey = $this->config['midtrans_server_key'] ?? null;
        $mode = $this->config['midtrans_mode'] ?? 'sandbox';

        if (!$serverKey) {
            return ['success' => false, 'message' => 'Midtrans server key belum dikonfigurasi'];
        }

        $baseUrl = $mode === 'production'
            ? 'https://app.midtrans.com'
            : 'https://app.sandbox.midtrans.com';

        $response = Http::withBasicAuth($serverKey, '')
            ->post($baseUrl . '/snap/v1/transactions', [
                'transaction_details' => [
                    'order_id' => $params['invoice_number'],
                    'gross_amount' => $params['amount'],
                ],
                'customer_details' => [
                    'first_name' => $params['customer_name'],
                    'email' => $params['customer_email'] ?? '',
                    'phone' => $params['customer_phone'] ?? '',
                ],
            ]);

        if ($response->successful()) {
            $data = $response->json();
            return [
                'success' => true,
                'payment_url' => $data['redirect_url'] ?? null,
                'token' => $data['token'] ?? null,
                'amount' => $params['amount'],
                'raw_response' => $data
            ];
        }

        return [
            'success' => false,
            'message' => 'Midtrans Error: ' . ($response->json()['message'] ?? $response->body())
        ];
    }

    /**
     * Create Duitku Payment
     */
    protected function createDuitkuPayment(array $params): array
    {
        $merchantCode = $this->config['duitku_merchant_code'] ?? null;
        $apiKey = $this->config['duitku_api_key'] ?? null;
        $mode = $this->config['duitku_mode'] ?? 'sandbox';

        if (!$merchantCode || !$apiKey) {
            return ['success' => false, 'message' => 'Duitku credentials belum lengkap'];
        }

        $baseUrl = $mode === 'production'
            ? 'https://passport.duitku.com/webapi/api/merchant'
            : 'https://sandbox.duitku.com/webapi/api/merchant';

        $paymentMethod = 'SP'; // QRIS
        $merchantOrderId = $params['invoice_number'];
        $paymentAmount = $params['amount'];
        $signature = md5($merchantCode . $merchantOrderId . $paymentAmount . $apiKey);

        $response = Http::post($baseUrl . '/v2/inquiry', [
            'merchantCode' => $merchantCode,
            'paymentAmount' => $paymentAmount,
            'paymentMethod' => $paymentMethod,
            'merchantOrderId' => $merchantOrderId,
            'productDetails' => 'Invoice ' . $params['invoice_number'],
            'customerVaName' => $params['customer_name'],
            'email' => $params['customer_email'] ?? '',
            'phoneNumber' => $params['customer_phone'] ?? '',
            'signature' => $signature,
        ]);

        if ($response->successful()) {
            $data = $response->json();
            return [
                'success' => true,
                'payment_url' => $data['paymentUrl'] ?? null,
                'reference' => $data['reference'] ?? null,
                'qr_string' => $data['qrString'] ?? null,
                'amount' => $paymentAmount,
                'raw_response' => $data
            ];
        }

        return [
            'success' => false,
            'message' => 'Duitku Error: ' . ($response->json()['message'] ?? $response->body())
        ];
    }

    /**
     * Verify payment callback
     */
    public function verifyCallback(string $gateway, array $data): array
    {
        $method = 'verify' . ucfirst($gateway) . 'Callback';
        
        if (!method_exists($this, $method)) {
            return ['success' => false, 'message' => "Gateway '{$gateway}' callback not implemented."];
        }

        return $this->$method($data);
    }

    /**
     * Verify Xendit Callback
     */
    protected function verifyXenditCallback(array $data): array
    {
        // Xendit akan mengirim callback dengan status
        $status = $data['status'] ?? '';
        
        return [
            'success' => in_array($status, ['ACTIVE', 'COMPLETED', 'PAID']),
            'external_id' => $data['external_id'] ?? null,
            'amount' => $data['amount'] ?? null,
            'status' => $status,
            'payment_date' => $data['paid_at'] ?? now(),
        ];
    }

    /**
     * Verify Tripay Callback
     */
    protected function verifyTripayCallback(array $data): array
    {
        $status = $data['status'] ?? '';
        
        return [
            'success' => $status === 'PAID',
            'reference' => $data['reference'] ?? null,
            'merchant_ref' => $data['merchant_ref'] ?? null,
            'amount' => $data['amount'] ?? null,
            'status' => $status,
            'payment_date' => $data['paid_at'] ?? now(),
        ];
    }

    /**
     * Verify Midtrans Callback
     */
    protected function verifyMidtransCallback(array $data): array
    {
        $status = $data['transaction_status'] ?? '';
        
        return [
            'success' => in_array($status, ['capture', 'settlement']),
            'order_id' => $data['order_id'] ?? null,
            'amount' => $data['gross_amount'] ?? null,
            'status' => $status,
            'payment_date' => $data['transaction_time'] ?? now(),
        ];
    }

    /**
     * Verify Duitku Callback
     */
    protected function verifyDuitkuCallback(array $data): array
    {
        $status = $data['resultCode'] ?? '';
        
        return [
            'success' => $status === '00',
            'merchant_order_id' => $data['merchantOrderId'] ?? null,
            'reference' => $data['reference'] ?? null,
            'amount' => $data['amount'] ?? null,
            'status' => $status,
            'payment_date' => now(),
        ];
    }
}

