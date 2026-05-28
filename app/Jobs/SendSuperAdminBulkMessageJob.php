<?php

namespace App\Jobs;

use App\Models\SuperAdminBulkMessage;
use App\Models\SuperAdminBulkMessageRecipient;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SendSuperAdminBulkMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 60; // 60 seconds timeout
    public $tries = 3; // Retry 3 times if failed
    public $backoff = [10, 30, 60]; // Backoff delay: 10s, 30s, 60s

    protected $recipientId;

    /**
     * Create a new job instance.
     *
     * @param int $recipientId ID_of_SuperAdminBulkMessageRecipient
     */
    public function __construct(int $recipientId)
    {
        $this->recipientId = $recipientId;
        $this->onQueue('superadmin_whatsapp');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $recipient = SuperAdminBulkMessageRecipient::with(['bulkMessage', 'tenant'])->find($this->recipientId);

        if (!$recipient || !$recipient->bulkMessage) {
            Log::error('SendSuperAdminBulkMessageJob: Recipient or Campaign not found', ['recipient_id' => $this->recipientId]);
            return;
        }

        $bulkMessage = $recipient->bulkMessage;
        $tenant = $recipient->tenant;

        // 1. Circuit Breaker awareness: At job start, load Campaign by id.
        // If campaign.status != 'processing', STOP immediately (no gateway call, no attempt).
        if ($bulkMessage->status !== 'processing') {
            Log::info('SendSuperAdminBulkMessageJob: Campaign not in processing state, skipping', [
                'recipient_id' => $this->recipientId,
                'status' => $bulkMessage->status
            ]);
            return;
        }

        // Check if there is a cooldown (paused_until)
        if ($bulkMessage->paused_until && $bulkMessage->paused_until->isFuture()) {
            Log::info('SendSuperAdminBulkMessageJob: Campaign is in cooldown, skipping', [
                'recipient_id' => $this->recipientId,
                'paused_until' => $bulkMessage->paused_until->toDateTimeString()
            ]);
            return;
        }

        // Skip if already sent (idempotency)
        if ($recipient->status === 'sent') {
             $this->checkCampaignComplete($bulkMessage);
             return;
        }

        try {
            // Use WhatsAppGatewayService correctly with 'superadmin' session
            $waGateway = new \App\Services\WhatsAppGatewayService('superadmin');
            
            // Replace Placeholders
            $message = $this->replacePlaceholders($bulkMessage->message_body, $tenant);

            // 2. Pre-check if number is registered on WhatsApp (Optimization for Go Gateway)
            if (!$waGateway->isRegistered($recipient->phone)) {
                $recipient->update([
                    'status' => 'failed',
                    'error_message' => 'WhatsApp ID is Not Registered',
                ]);

                $bulkMessage->increment('failed_count');
                $bulkMessage->decrement('pending_count'); 
                
                $this->checkCampaignComplete($bulkMessage);
                
                Log::info('SendSuperAdminBulkMessageJob: Skipped unregistered number', [
                    'recipient_id' => $this->recipientId,
                    'phone' => $recipient->phone
                ]);
                return;
            }

            // 3. Send via WhatsAppGatewayService (external WAKita server)
            $result = $waGateway->sendMessage($recipient->phone, $message);
            
            // Map the result from WhatsAppGatewayService to the expected format
            // WhatsAppGatewayService returns ['success' => bool, 'message' => string]
            $isOk = $result['success'] ?? false;

            if ($isOk) {
                $recipient->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                    'error_message' => null,
                ]);

                $bulkMessage->increment('sent_count');
                $bulkMessage->decrement('pending_count'); // Decrement pending
                
                Log::info('SendSuperAdminBulkMessageJob: Message sent', [
                    'recipient_id' => $this->recipientId,
                    'tenant' => $tenant->name ?? 'Unknown'
                ]);
            } else {
                // Failed to send 
                $errorMessage = $result['message'] ?? 'Unknown error';
                $errorType = 'api_error';
                
                // Identify lock or disconnect errors from string
                if (str_contains(strtolower($errorMessage), 'locked')) {
                    $errorType = 'locked';
                } elseif (str_contains(strtolower($errorMessage), 'disconnected') || str_contains(strtolower($errorMessage), 'unreachable')) {
                    $errorType = 'disconnected';
                }
                
                $recipient->update([
                    'status' => 'failed',
                    'error_message' => $errorMessage,
                ]);

                $bulkMessage->increment('failed_count');
                $bulkMessage->decrement('pending_count');
                
                Log::error('SendSuperAdminBulkMessageJob: Failed to send', [
                    'recipient_id' => $this->recipientId, 
                    'error_type' => $errorType,
                    'error' => $errorMessage
                ]);

                // 3.5. Lock contention handling:
                if ($errorType === 'locked') {
                    Log::info('SendSuperAdminBulkMessageJob: Session locked, releasing with delay', [
                        'recipient_id' => $this->recipientId
                    ]);
                    // Release back to queue with random delay (10-30s)
                    $this->release(random_int(10, 30));
                    return;
                }

                // 3. Circuit breaker (DISCONNECTED / RESTRICTED):
                // If sendMessage() fails with error_type='disconnected' or gateway_down:
                if ($errorType === 'disconnected' || $errorType === 'gateway_down') {
                    // Atomically update campaign from processing -> paused:
                    // Only the first job performs this transition
                    $updated = SuperAdminBulkMessage::where('id', $bulkMessage->id)
                        ->where('status', 'processing')
                        ->update([
                            'status' => 'paused',
                            'pause_reason' => $errorType,
                            'paused_until' => now()->addMinutes(30), // 30min cooldown
                        ]);
                    
                    if ($updated) {
                         Log::warning('SendSuperAdminBulkMessageJob: Circuit breaker triggered. Campaign paused due to ' . $errorType, [
                             'campaign_id' => $bulkMessage->id
                         ]);
                    }
                }
            }

            // Check if all done
            $this->checkCampaignComplete($bulkMessage);

        } catch (\Exception $e) {
            Log::error('SendSuperAdminBulkMessageJob: Exception occurred', [
                'recipient_id' => $this->recipientId,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    protected function checkCampaignComplete(SuperAdminBulkMessage $bulkMessage): void
    {
        $bulkMessage->refresh();
        
        // If pending_count is 0, we assume it's done. 
        // Note: This relies on accurate increment/decrement. 
        // Safer check: sent + failed >= total
        $processed = $bulkMessage->sent_count + $bulkMessage->failed_count;
        
        if ($bulkMessage->status !== 'done' && $processed >= $bulkMessage->total_targets) {
            $bulkMessage->update([
                'status' => 'done',
                'finished_at' => now(),
            ]);
            
            Log::info('SendSuperAdminBulkMessageJob: Campaign Finished', ['campaign_id' => $bulkMessage->id]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $recipient = SuperAdminBulkMessageRecipient::find($this->recipientId);
        
        if ($recipient) {
            $recipient->update([
                'status' => 'failed',
                'error_message' => 'Job failed permanently: ' . $exception->getMessage(),
            ]);

            if ($recipient->bulkMessage) {
                $recipient->bulkMessage->increment('failed_count');
                $recipient->bulkMessage->decrement('pending_count');
                $this->checkCampaignComplete($recipient->bulkMessage);
            }
        }
        
        Log::error('SendSuperAdminBulkMessageJob: Job failed permanently', [
            'recipient_id' => $this->recipientId,
            'error' => $exception->getMessage()
        ]);
    }

    protected function replacePlaceholders($message, $tenant)
    {
        if (!$tenant) return $message;

        $replacements = [
            '{{tenant_name}}' => $tenant->name,
            '{{username}}' => $tenant->username ?? $tenant->subdomain,
            '{{subdomain}}' => $tenant->subdomain,
            '{{email}}' => $tenant->email ?? '-',
            '{{phone}}' => $tenant->phone ?? '-',
            '{{plan}}' => $tenant->subscription_plan ?? 'N/A',
            '{{status}}' => $tenant->status ?? 'N/A',
            '{{trial_ends_at}}' => $tenant->trial_ends_at ? $tenant->trial_ends_at->format('d M Y') : '-',
            '{{subscription_expires_at}}' => $tenant->subscription_expires_at ? $tenant->subscription_expires_at->format('d M Y') : '-',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $message);
    }
}
