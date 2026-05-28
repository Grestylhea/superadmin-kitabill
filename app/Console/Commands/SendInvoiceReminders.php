<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Invoice;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SendInvoiceReminders extends Command
{
    protected $signature = 'invoices:send-reminders';
    protected $description = 'Send WhatsApp reminders H-7, H-3, H-1 for unpaid invoices';

    protected $whatsapp;

    public function __construct(WhatsAppService $whatsapp)
    {
        parent::__construct();
        $this->whatsapp = $whatsapp;
    }

    public function handle()
    {
        $this->info('🔔 Starting invoice reminder process...');
        $today = Carbon::today();

        // Get all unpaid invoices
        $invoices = Invoice::with(['customer', 'package'])
            ->where('status', 'unpaid')
            ->whereHas('customer', function($q) {
                $q->where('status', 'active');
            })
            ->get();

        $sentH7 = 0;
        $sentH3 = 0;
        $sentH1 = 0;

        foreach ($invoices as $invoice) {
            // ✅ Validasi: Pastikan customer memiliki nomor telepon
            if (empty($invoice->customer->phone)) {
                Log::warning("Skipping reminder: customer phone is empty", [
                    'customer_code' => $invoice->customer->customer_code,
                    'invoice_number' => $invoice->invoice_number
                ]);
                continue;
            }
            
            $dueDate = Carbon::parse($invoice->due_date);
            $daysUntilDue = $today->diffInDays($dueDate, false);

            // Skip if already overdue
            if ($daysUntilDue < 0) {
                continue;
            }

            // H-7: Send reminder 7 days before due date
            if ($daysUntilDue == 7) {
                if ($this->whatsapp->sendReminderH7($invoice)) {
                    $sentH7++;
                    $this->info("✅ H-7 sent to {$invoice->customer->name} ({$invoice->invoice_number})");
                    Log::info("Reminder H-7 sent", [
                        'customer' => $invoice->customer->customer_code,
                        'invoice' => $invoice->invoice_number,
                        'phone' => $invoice->customer->phone
                    ]);
                } else {
                    $this->warn("⚠️ H-7 failed for {$invoice->customer->name} ({$invoice->invoice_number})");
                }
            }

            // H-3: Send reminder 3 days before due date
            if ($daysUntilDue == 3) {
                if ($this->whatsapp->sendReminderH3($invoice)) {
                    $sentH3++;
                    $this->info("✅ H-3 sent to {$invoice->customer->name} ({$invoice->invoice_number})");
                    Log::info("Reminder H-3 sent", [
                        'customer' => $invoice->customer->customer_code,
                        'invoice' => $invoice->invoice_number,
                        'phone' => $invoice->customer->phone
                    ]);
                } else {
                    $this->warn("⚠️ H-3 failed for {$invoice->customer->name} ({$invoice->invoice_number})");
                }
            }

            // H-1: Send reminder 1 day before due date
            if ($daysUntilDue == 1) {
                if ($this->whatsapp->sendReminderH1($invoice)) {
                    $sentH1++;
                    $this->info("✅ H-1 sent to {$invoice->customer->name} ({$invoice->invoice_number})");
                    Log::info("Reminder H-1 sent", [
                        'customer' => $invoice->customer->customer_code,
                        'invoice' => $invoice->invoice_number,
                        'phone' => $invoice->customer->phone
                    ]);
                } else {
                    $this->warn("⚠️ H-1 failed for {$invoice->customer->name} ({$invoice->invoice_number})");
                }
            }
        }

        // Summary
        $this->newLine();
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("📊 REMINDER SUMMARY:");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("H-7 Reminders: {$sentH7} sent");
        $this->info("H-3 Reminders: {$sentH3} sent");
        $this->info("H-1 Reminders: {$sentH1} sent");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        Log::info('Invoice reminders sent', [
            'h7' => $sentH7,
            'h3' => $sentH3,
            'h1' => $sentH1
        ]);

        return 0;
    }
}
