<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Customer;
use App\Services\MikrotikService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /**
     * Handle Xendit Payment Callback
     */
    public function handleXenditPayment(Request $request)
    {
        // Verify callback token
        $callbackToken = $request->header('X-CALLBACK-TOKEN');

        if ($callbackToken !== config('xendit.callback_token')) {
            Log::warning('Invalid Xendit callback token');
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Log webhook payload
        Log::info('Xendit Webhook Received', $request->all());

        try {
            // Get payment status
            $status = $request->input('status');
            $externalId = $request->input('external_id');

            // Find invoice
            $invoice = Invoice::where('invoice_number', $externalId)->first();

            if (!$invoice) {
                Log::error('Invoice not found: ' . $externalId);
                return response()->json(['error' => 'Invoice not found'], 404);
            }

            // Handle payment status
            if ($status === 'PAID' || $status === 'SUCCEEDED') {
                $this->markInvoiceAsPaid($invoice, $request->all());
                $this->activateCustomer($invoice->customer);
            }

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            Log::error('Xendit webhook error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Mark invoice as paid
     */
    private function markInvoiceAsPaid(Invoice $invoice, array $paymentData)
    {
        $invoice->update([
            'status' => 'paid',
            'paid_at' => now(),
            'payment_reference' => $paymentData['id'] ?? null,
            'payment_details' => array_merge(
                $invoice->payment_details ?? [],
                ['xendit_callback' => $paymentData]
            ),
        ]);

        Log::info("Invoice {$invoice->invoice_number} marked as paid");
    }

    /**
     * Auto-activate customer after payment
     */
    private function activateCustomer(Customer $customer)
    {
        // Skip if already active
        if ($customer->status === 'active') {
            return;
        }

        // Update customer status
        $customer->update(['status' => 'active']);

        // Re-enable PPPoE user in MikroTik if suspended
        if (($customer->connection_type === 'pppoe_direct' || $customer->connection_type === 'pppoe_mikrotik')
            && $customer->router_id
            && isset($customer->connection_config['username'])) {

            try {
                $router = $customer->router;
                $mikrotik = new MikrotikService($router);
                $mikrotik->enablePPPoEUser($customer->connection_config['username']);

                Log::info("Customer {$customer->name} activated in MikroTik");
            } catch (\Exception $e) {
                Log::error("Failed to activate customer in MikroTik: " . $e->getMessage());
            }
        }

        Log::info("Customer {$customer->name} activated after payment");
    }
    /**
     * Handle Tripay Superadmin Webhook
     */
    public function handleTripaySuperadmin(Request $request)
    {
        $payload = $request->getContent();
        $privateKey = \App\Models\SystemSetting::get('tripay_private_key');
        
        $signature = hash_hmac('sha256', $payload, $privateKey);
        $headerSignature = $request->header('X-Callback-Signature');
        
        Log::info('[SUPERADMIN_TRIPAY_CALLBACK]', [
            'header_sig_short' => substr($headerSignature ?? '', 0, 8),
            'calc_sig_short' => substr($signature, 0, 8),
            'match' => hash_equals($signature, (string)$headerSignature) ? 'YES' : 'NO',
            'event' => $request->header('X-Callback-Event'),
            'merchant_ref' => $request->input('merchant_ref'),
        ]);

        if (!hash_equals($signature, (string)$headerSignature)) {
            return response()->json(['success' => false, 'message' => 'Invalid Signature'], 400);
        }

        if ($request->header('X-Callback-Event') !== 'payment_status') {
             return response()->json(['success' => false, 'message' => 'Invalid Event']);
        }

        $status = $request->input('status');
        
        if ($status === 'PAID') {
             $merchantRef = $request->input('merchant_ref');
             // Invoice number is typically the merchant_ref
             $invoice = Invoice::where('invoice_number', $merchantRef)->first();
             
             if ($invoice) {
                 $this->markInvoiceAsPaid($invoice, $request->all());
                 
                 // If invoice has customer, activate them
                 if ($invoice->customer) {
                     $this->activateCustomer($invoice->customer);
                 }
             } else {
                 Log::warning("Invoice not found for Tripay Merchant Ref: {$merchantRef}");
                 return response()->json(['success' => false, 'message' => 'Invoice not found'], 404);
             }
        }

        return response()->json(['success' => true]);
    }
}
