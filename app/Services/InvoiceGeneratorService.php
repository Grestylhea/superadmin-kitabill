<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class InvoiceGeneratorService
{
    protected $whatsapp;

    public function __construct(WhatsAppService $whatsapp)
    {
        $this->whatsapp = $whatsapp;
    }
    /**
     * Generate invoice untuk semua active customers yang jatuh tempo hari ini
     */
    public function generateMonthlyInvoices()
    {
        $today = now();
        
        $customers = Customer::where('status', 'active')
            ->whereNotNull('package_id')
            ->with('package')
            ->get();

        $generated = 0;
        $skipped = 0;
        $errors = [];

        foreach ($customers as $customer) {
            try {
                $package = $customer->package;
                
                if (!$package) {
                    $skipped++;
                    continue;
                }

                // ✅ Cek apakah hari ini adalah tanggal billing customer
                $shouldGenerate = $this->shouldGenerateInvoiceToday($customer, $package, $today);
                
                if (!$shouldGenerate) {
                    $skipped++;
                    continue;
                }

                // ✅ Cek apakah sudah ada invoice bulan ini
                $existingInvoice = Invoice::where('customer_id', $customer->id)
                    ->whereMonth('issue_date', $today->month)
                    ->whereYear('issue_date', $today->year)
                    ->first();

                if ($existingInvoice) {
                    $skipped++;
                    continue;
                }

                // Generate invoice
                $invoice = $this->generateInvoiceForCustomer($customer);
                $generated++;

                Log::info("✅ Invoice generated for {$customer->name} ({$customer->customer_code})");

            } catch (\Exception $e) {
                $errors[] = [
                    'customer' => $customer->name,
                    'error' => $e->getMessage()
                ];
                Log::error("❌ Failed to generate invoice for {$customer->name}: " . $e->getMessage());
            }
        }

        return [
            'generated' => $generated,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * ✅ Cek apakah invoice harus di-generate hari ini
     */
    protected function shouldGenerateInvoiceToday(Customer $customer, $package, Carbon $today): bool
    {
        // Jika package punya custom expire day, gunakan itu
        if ($package->custom_expire_day) {
            // Generate invoice di tanggal yang ditentukan package
            return $today->day == $package->custom_expire_day;
        }

        // Jika tidak ada custom_expire_day, gunakan next_billing_date customer
        if ($customer->next_billing_date) {
            return $customer->next_billing_date->isSameDay($today);
        }

        // Fallback: generate invoice tanggal 1 setiap bulan
        return $today->day == 1;
    }

    /**
     * Generate invoice untuk customer tertentu
     */
    public function generateInvoiceForCustomer(Customer $customer)
    {
        $package = $customer->package;

        if (!$package) {
            throw new \Exception("Customer tidak memiliki package");
        }

        // ✅ Issue date = sekarang
        $issueDate = now();

        // ✅ Due date = next billing date + grace period
        // Jika ada custom_expire_day, gunakan itu
        $dueDate = $this->calculateDueDate($customer, $package, $issueDate);

        // Prepare items
        $items = [
            [
                'description' => $package->name . ' - ' . $issueDate->translatedFormat('F Y'),
                'qty' => 1,
                'price' => $package->price,
                'amount' => $package->price,
            ]
        ];

        // Create invoice
        $invoice = Invoice::create([
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'issue_date' => $issueDate,
            'due_date' => $dueDate,
            'period' => $issueDate->translatedFormat('F Y'),
            'subtotal' => $package->price,
            'tax_percentage' => 0,
            'tax' => 0,
            'discount' => 0,
            'late_fee' => 0,
            'total' => $package->price,
            'status' => 'unpaid',
            'items' => $items,
            'notes' => 'Invoice tagihan bulanan - Auto generated',
        ]);

        // ✅ Update customer next billing date
        $this->updateNextBillingDate($customer, $package);

        // 📱 Send WhatsApp invoice notification
        try {
            $this->whatsapp->sendInvoiceNotification($invoice);
            Log::info("Invoice notification sent via WhatsApp", [
                'customer' => $customer->customer_code,
                'invoice' => $invoice->invoice_number
            ]);
        } catch (\Exception $e) {
            Log::warning("Failed to send invoice notification: " . $e->getMessage());
        }

        return $invoice;
    }

    /**
     * ✅ Hitung due date berdasarkan package settings
     */
    protected function calculateDueDate(Customer $customer, $package, Carbon $issueDate): Carbon
    {
        $gracePeriod = $package->grace_period ?? 3; // Default 3 hari

        // Jika package punya custom_expire_day
        if ($package->custom_expire_day) {
            $dueDate = Carbon::createFromDate(
                $issueDate->year,
                $issueDate->month,
                $package->custom_expire_day
            );

            // Jika custom_expire_time ada, set jam nya
            if ($package->custom_expire_time) {
                $time = Carbon::parse($package->custom_expire_time);
                $dueDate->setTime($time->hour, $time->minute, $time->second);
            }

            // Tambah grace period
            $dueDate->addDays($gracePeriod);

            return $dueDate;
        }

        // Fallback: issue date + grace period
        return $issueDate->copy()->addDays($gracePeriod);
    }

    /**
     * ✅ Update next billing date customer
     */
    protected function updateNextBillingDate(Customer $customer, $package)
    {
        $nextBillingDate = null;

        // Jika package punya custom_expire_day
        if ($package->custom_expire_day) {
            $nextBillingDate = now()->addMonth()->startOfMonth()->addDays($package->custom_expire_day - 1);
        } else {
            // Default: next month, same day
            $nextBillingDate = now()->addMonth();
        }

        $customer->update([
            'next_billing_date' => $nextBillingDate
        ]);
    }

    /**
     * Generate invoice manual untuk customer (dari UI)
     */
    public function generateManualInvoice(Customer $customer, array $data = [])
    {
        $package = $customer->package;

        if (!$package) {
            throw new \Exception("Customer tidak memiliki package");
        }

        $issueDate = $data['issue_date'] ?? now();
        $dueDate = $data['due_date'] ?? $this->calculateDueDate($customer, $package, Carbon::parse($issueDate));

        $items = $data['items'] ?? [
            [
                'description' => $package->name . ' - ' . Carbon::parse($issueDate)->translatedFormat('F Y'),
                'qty' => 1,
                'price' => $package->price,
                'amount' => $package->price,
            ]
        ];

        $subtotal = $data['subtotal'] ?? $package->price;
        $tax = $data['tax'] ?? 0;
        $discount = $data['discount'] ?? 0;
        $late_fee = $data['late_fee'] ?? 0;
        $total = $subtotal + $tax + $late_fee - $discount;

        $invoice = Invoice::create([
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'issue_date' => $issueDate,
            'due_date' => $dueDate,
            'period' => Carbon::parse($issueDate)->translatedFormat('F Y'),
            'subtotal' => $subtotal,
            'tax_percentage' => $data['tax_percentage'] ?? 0,
            'tax' => $tax,
            'discount' => $discount,
            'late_fee' => $late_fee,
            'total' => $total,
            'status' => 'unpaid',
            'items' => $items,
            'notes' => $data['notes'] ?? 'Manual invoice',
        ]);

        return $invoice;
    }
}
