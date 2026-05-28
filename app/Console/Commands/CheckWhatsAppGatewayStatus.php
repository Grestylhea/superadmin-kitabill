<?php

namespace App\Console\Commands;

use App\Models\WhatsAppGatewayStatus;
use App\Services\WhatsAppGatewayService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class CheckWhatsAppGatewayStatus extends Command
{
    protected $signature = 'wa-gateway:check';
    protected $description = 'Check WhatsApp Gateway status and notify if disconnected';

    public function handle(WhatsAppGatewayService $gateway)
    {
        $status = $gateway->getStatus();

        $record = WhatsAppGatewayStatus::firstOrNew(['id' => 1]);
        $prevStatus = $record->status;

        $record->status          = $status['gateway_state'] ?? 'disconnected';
        $record->uptime          = $status['uptime'] ?? null;
        $record->last_checked_at = Carbon::now();

        $shouldNotify = false;

        // kalau dari CONNECTED -> DISCONNECTED, kirim notif
        if (strtoupper($prevStatus) === 'CONNECTED' && strtoupper($status['gateway_state'] ?? '') === 'DISCONNECTED') {
            $shouldNotify = true;
        }

        $record->save();

        if ($shouldNotify) {
            $this->notifyAdmin();
        }

        return Command::SUCCESS;
    }

    protected function notifyAdmin()
    {
        // contoh simple: kirim email
        $email = config('mail.admin_address', 'admin@isp.local');

        Mail::raw('WhatsApp Gateway DISCONNECTED. Segera cek dashboard.', function ($message) use ($email) {
            $message->to($email)->subject('WA Gateway DISCONNECTED');
        });
    }
}
