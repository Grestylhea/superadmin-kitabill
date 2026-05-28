<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Models\SuperAdminBulkMessage;
use App\Models\SuperAdminBulkMessageRecipient;
use App\Services\WhatsAppService;
use App\Jobs\SendSuperAdminBulkMessageJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Carbon\Carbon;

class SuperAdminBulkMessageController extends Controller
{
    /**
     * Display a listing of bulk message campaigns.
     */
    public function index(Request $request)
    {
        $campaigns = SuperAdminBulkMessage::with('creator:id,name')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return Inertia::render('SuperAdmin/BulkMessages/Index', [
            'campaigns' => $campaigns,
        ]);
    }

    /**
     * Show the form for creating a new bulk message.
     */
    public function create()
    {
        // Get all active tenants
        $tenants = Tenant::orderBy('name')
            ->select('id', 'name', 'subdomain', 'status', 'subscription_plan')
            ->get();
            
        // Get plans for filtering
        $plans = Tenant::select('subscription_plan')
            ->distinct()
            ->whereNotNull('subscription_plan')
            ->orderBy('subscription_plan')
            ->pluck('subscription_plan');

        return Inertia::render('SuperAdmin/BulkMessages/Create', [
            'tenants' => $tenants,
            'plans' => $plans,
            'statuses' => ['trial', 'active', 'suspended', 'expired'],
            'placeholders' => [
                '{{tenant_name}}', 
                '{{username}}', 
                '{{email}}', 
                '{{subdomain}}', 
                '{{plan}}', 
                '{{status}}', 
                '{{trial_ends_at}}', 
                '{{subscription_expires_at}}'
            ]
        ]);
    }

    /**
     * Store a newly created bulk message in storage.
     */
    public function store(Request $request)
    {
        // Validation
        $validator = Validator::make($request->all(), [
            'title' => 'nullable|string|max:255',
            'message_body' => 'required|string',
            'target_type' => 'required|in:all,selected,filtered',
            'selected_tenant_ids' => 'required_if:target_type,selected|array',
            'filters' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            DB::beginTransaction();

            // Resolve Targets
            $query = Tenant::query();
            
            if ($request->target_type === 'selected') {
                $query->whereIn('id', $request->selected_tenant_ids);
            } elseif ($request->target_type === 'filtered') {
                $filters = $request->input('filters', []);
                
                if (!empty($filters['plans'])) {
                    $query->whereIn('subscription_plan', $filters['plans']);
                }
                
                if (!empty($filters['statuses'])) {
                    $query->whereIn('status', $filters['statuses']);
                }
            }
            
            // Only get tenants with phone numbers
            $query->whereNotNull('phone')->where('phone', '!=', '');
            
            $tenants = $query->get();

            if ($tenants->isEmpty()) {
                return redirect()->back()
                    ->with('error', 'No valid targets found with phone numbers.')
                    ->withInput();
            }

            // Create Campaign
            $bulkMessage = SuperAdminBulkMessage::create([
                'title' => $request->input('title') ?: 'Announcement - ' . now()->format('d/m/Y H:i'),
                'message_body' => $request->input('message_body'),
                'filters_json' => [
                    'type' => $request->target_type,
                    'selected_ids' => $request->selected_tenant_ids,
                    'filters' => $request->input('filters')
                ],
                'status' => 'processing',
                'total_targets' => $tenants->count(),
                'sent_count' => 0,
                'failed_count' => 0,
                'pending_count' => $tenants->count(),
                'created_by' => auth()->id(),
                'started_at' => now(),
            ]);

            // Create Recipients
            $recipients = [];
            foreach ($tenants as $tenant) {
                $recipients[] = [
                    'super_admin_bulk_message_id' => $bulkMessage->id,
                    'tenant_id' => $tenant->id,
                    'phone' => $tenant->phone,
                    'email' => $tenant->email, // Snapshot email
                    'tenant_name_snapshot' => $tenant->name,
                    'status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // Insert in chunks to avoid memory issues
            foreach (array_chunk($recipients, 500) as $chunk) {
                SuperAdminBulkMessageRecipient::insert($chunk);
            }

            // Dispatch Jobs (or process immediately for simplicity if job queue not set up)
            // For now, let's process immediately to ensure delivery without queue worker setup if needed, 
            // but ideally dispatch jobs. Let's assume queue worker is running or we'll process via command.
            // Dispatching jobs is safer.
            
            $recipientIds = SuperAdminBulkMessageRecipient::where('super_admin_bulk_message_id', $bulkMessage->id)
                ->pluck('id');

            $delay = random_int(5, 15); // Start with a small initial delay
            foreach ($recipientIds as $recipientId) {
                SendSuperAdminBulkMessageJob::dispatch($recipientId)
                    ->delay(now()->addSeconds($delay));
                
                // Increment delay by 30-60 seconds for each message
                $delay += WhatsAppService::getRandomGap();
            }

            DB::commit();

            return redirect()->route('superadmin.bulk-messages.show', $bulkMessage->id)
                ->with('success', "Bulk message campaign created! Sending to {$tenants->count()} tenants...");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create superadmin bulk message', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Failed to create campaign: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified bulk message campaign.
     */
    public function show($id)
    {
        $campaign = SuperAdminBulkMessage::with('creator:id,name')->findOrFail($id);
        
        $recipients = SuperAdminBulkMessageRecipient::where('super_admin_bulk_message_id', $id)
            ->with('tenant:id,name,subdomain')
            // Order by status to show pending first, then failed, then sent
            ->orderByRaw("CASE status 
                WHEN 'pending' THEN 1 
                WHEN 'failed' THEN 2 
                WHEN 'sent' THEN 3 
                ELSE 4 END")
            ->paginate(50);

        return Inertia::render('SuperAdmin/BulkMessages/Show', [
            'campaign' => $campaign,
            'recipients' => $recipients
        ]);
    }

    /**
     * Process a batch of recipients synchronously (Frontend Driven).
     */
    public function sendBatch(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'integer|min:1|max:20', // Process small batches (default 5)
        ]);
        
        $limit = $request->input('limit', 5);
        $campaign = SuperAdminBulkMessage::findOrFail($id);

        // Pre-check campaign status
        if ($campaign->status !== 'processing') {
            return response()->json([
                'processed' => 0,
                'remain' => $campaign->pending_count,
                'completed' => $campaign->status === 'done',
                'message' => 'Campaign is not in processing state (current: ' . $campaign->status . ')'
            ], 400);
        }

        // Check cooldown
        if ($campaign->paused_until && $campaign->paused_until->isFuture()) {
            return response()->json([
                'processed' => 0,
                'remain' => $campaign->pending_count,
                'completed' => false,
                'message' => 'Campaign is in cooldown until ' . $campaign->paused_until->toDateTimeString()
            ], 400);
        }
        
        // Fetch pending recipients
        $recipients = SuperAdminBulkMessageRecipient::with('tenant')
            ->where('super_admin_bulk_message_id', $id)
            ->where('status', 'pending')
            ->limit($limit)
            ->get();
            
        if ($recipients->isEmpty()) {
            return response()->json([
                'processed' => 0,
                'remain' => 0,
                'completed' => true,
                'message' => 'No pending recipients found.'
            ]);
        }
        
        $processedCount = 0;
        $activeJob = new SendSuperAdminBulkMessageJob(0); // Dummy for accessing helper methods if needed, or static
        $whatsappService = app(WhatsAppService::class);
        
        foreach ($recipients as $recipient) {
            try {
                // Logic duplication from Job for synchronous execution
                $tenant = $recipient->tenant;
                
                // Replace placeholders
                // Note: We need to recreate the replacePlaceholders logic here or make it static/public in Job
                // For simplicity/speed, I'll replicate it here quickly as a private helper or inline
                $messageBody = $this->replacePlaceholders($campaign->message_body, $tenant);
                
                // Send
                $result = $whatsappService->sendMessage($recipient->phone, $messageBody);
                
                if ($result['ok']) {
                    $recipient->update([
                        'status' => 'sent',
                        'sent_at' => now(),
                        'error_message' => null
                    ]);
                    $campaign->increment('sent_count');
                } else {
                    $errorType = $result['error_type'] ?? 'unknown';
                    $errorMessage = $result['error_message'] ?? 'Unknown error';

                    $recipient->update([
                        'status' => 'failed',
                        'error_message' => $errorMessage
                    ]);
                    $campaign->increment('failed_count');

                    // Circuit Breaker for manual batch
                    if ($errorType === 'disconnected' || $errorType === 'gateway_down') {
                        // Atomic update
                        SuperAdminBulkMessage::where('id', $campaign->id)
                            ->where('status', 'processing')
                            ->update([
                                'status' => 'paused',
                                'pause_reason' => $errorType,
                                'paused_until' => now()->addMinutes(30)
                            ]);
                        
                        // Stop processing current batch
                        break;
                    }

                    if ($errorType === 'locked') {
                        Log::info('SuperAdminBulkMessageController: Session locked during manual batch, stopping batch early', [
                            'campaign_id' => $campaign->id
                        ]);
                        // Suggest user to wait or just stop this sync batch
                        break;
                    }
                }
            } catch (\Exception $e) {
                $recipient->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage()
                ]);
                $campaign->increment('failed_count');
            }
            
            $campaign->decrement('pending_count');
            $processedCount++;
        }
        
        // Refresh campaign to check if done
        $campaign->refresh();
        $remain = $campaign->pending_count;
        
        if ($remain <= 0 && $campaign->status !== 'done') {
            $campaign->update([
                'status' => 'done',
                'finished_at' => now()
            ]);
        }
        
        return response()->json([
            'processed' => $processedCount,
            'remain' => $remain,
            'completed' => $remain <= 0,
            'recipients' => $recipients->pluck('id') // Return IDs to update UI if needed
        ]);
    }

    /**
     * Helper to replace placeholders (Duplicate of Job logic)
     */
    private function replacePlaceholders($message, $tenant)
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
