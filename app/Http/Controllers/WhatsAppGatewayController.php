<?php

namespace App\Http\Controllers;

use App\Services\WhatsAppGatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppGatewayController extends Controller
{
    /**
     * Ambil status koneksi dari WA Gateway (WAKita).
     * GET /wa-gateway/status
     */
    public function status(): JsonResponse
    {
        try {
            $service = new WhatsAppGatewayService('superadmin');
            $statusData = $service->getStatus(); // Returns ['gateway_state' => ..., 'phone_number' => ...]

            // Map WAKita status to Legacy fields expected by Frontend
            $connected = $statusData['gateway_state'] === 'connected' || $statusData['gateway_state'] === 'authenticated';
            $state = strtoupper($statusData['gateway_state']);
            
            return response()->json([
                'success' => true,
                'connected' => $connected,
                'status' => $state,
                'state' => $state,
                'phoneNumber' => $statusData['phone_number'],
                'uptime' => $statusData['uptime'], // WAKita currently null, but that's fine
                'session' => $statusData['session'],
                'engine' => 'wakita'
            ]);

        } catch (\Throwable $e) {
            Log::error('WA Gateway status exception', [
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'connected' => false,
                'status' => 'ERROR',
                'error'   => 'WhatsApp Gateway error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Ambil QR code dari WA Gateway (WAKita).
     * GET /wa-gateway/qr
     */
    public function qr(): JsonResponse
    {
        try {
            $service = new WhatsAppGatewayService('superadmin');
            $statusData = $service->getStatus();

            if ($statusData['gateway_state'] === 'connected') {
                return response()->json([
                    'success' => true,
                    'connected' => true,
                    'message' => 'WhatsApp sudah terhubung',
                    'status' => 'CONNECTED',
                ]);
            }

            $qrCode = $service->getQrCodeUrl(); // Returns Base64 Data URI or null

            if ($qrCode) {
                return response()->json([
                    'success' => true,
                    'qr' => $qrCode, // Frontend might use 'qr' or 'dataUrl'
                    'dataUrl' => $qrCode,
                    'status' => 'qr_ready',
                    'message' => 'Scan QR Code ini',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'QR code belum siap / tidak tersedia. Pastikan session initialized.',
                'status' => 'initializing',
            ], 404);

        } catch (\Throwable $e) {
            Log::error('WA Gateway QR exception', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil QR code: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reconnect WhatsApp Gateway.
     * POST /wa-gateway/reconnect
     */
    public function reconnect(): JsonResponse
    {
        try {
            $service = new WhatsAppGatewayService('superadmin');
            $success = $service->reconnect();

            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Reconnect logic triggered (WAKita Session Connect).',
                    'session' => 'superadmin',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Gagal melakukan reconnect.',
            ], 500);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal melakukan reconnect: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Hard reset session superadmin.
     * POST /wa-gateway/reset-session
     */
    public function resetSession(): JsonResponse
    {
        try {
            $service = new WhatsAppGatewayService('superadmin');
            $success = $service->logout();

            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Session superadmin berhasil di-reset. Silakan scan QR baru.',
                ]);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal reset session.',
            ], 500);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Reset session gagal: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send test message via WhatsApp Gateway.
     * POST /wa-gateway/send-test
     */
    public function sendTest(Request $request): JsonResponse
    {
        $request->validate([
            'phone'   => 'required|string',
            'message' => 'required|string',
        ]);

        try {
            $service = new WhatsAppGatewayService('superadmin');
            $result = $service->sendMessage($request->input('phone'), $request->input('message'));

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Pesan berhasil dikirim',
                    'data' => $result['data'] ?? [],
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 500);

        } catch (\Throwable $e) {
            Log::error('WA Gateway send exception', [
                'error' => $e->getMessage(),
                'phone' => substr($request->input('phone'), 0, 5) . '***',
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengirim pesan WhatsApp: ' . $e->getMessage(),
            ], 500);
        }
    }
}
