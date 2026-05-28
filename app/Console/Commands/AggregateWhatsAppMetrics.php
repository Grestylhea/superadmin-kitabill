<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AggregateWhatsAppMetrics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wa:aggregate-daily {date? : The date to aggregate (YYYY-MM-DD), defaults to yesterday}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Aggregate WhatsApp message logs into daily metrics and cleanup old logs';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dateStr = $this->argument('date') ?: Carbon::yesterday()->toDateString();
        $date = Carbon::parse($dateStr);
        $start = $date->copy()->startOfDay();
        $end = $date->copy()->endOfDay();

        $this->info("Aggregating WA metrics for: {$dateStr}");

        // 1. Fetch aggregations grouped by tenant
        $results = DB::table('wa_message_logs')
            ->select('tenant_id')
            ->selectRaw("SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success_count")
            ->selectRaw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count")
            ->selectRaw("SUM(CASE WHEN status = 'locked' THEN 1 ELSE 0 END) as locked_count")
            // We can also count 'paused' if we had a status for it, 
            // but currently we use circuit breaker which might be tracked via error_type.
            ->selectRaw("SUM(CASE WHEN error_type IN ('disconnected', 'gateway_down') THEN 1 ELSE 0 END) as paused_count")
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('tenant_id')
            ->get();

        if ($results->isEmpty()) {
            $this->warn("No logs found for {$dateStr}");
        }

        foreach ($results as $row) {
            DB::table('wa_daily_metrics')->updateOrInsert(
                ['date' => $dateStr, 'tenant_id' => $row->tenant_id],
                [
                    'success_count' => $row->success_count,
                    'failed_count' => $row->failed_count,
                    'locked_count' => $row->locked_count,
                    'paused_count' => $row->paused_count,
                    'updated_at' => now(),
                    'created_at' => now(), // only used if inserting
                ]
            );
        }

        $this->info("Aggregation complete for " . $results->count() . " groups.");

        // 2. Cleanup old logs (Retention: 30 days)
        $retentionDays = 30;
        $cutoff = now()->subDays($retentionDays);
        
        $deletedCount = DB::table('wa_message_logs')
            ->where('created_at', '<', $cutoff)
            ->delete();

        if ($deletedCount > 0) {
            $this->info("Cleanup: Deleted {$deletedCount} old message logs (older than {$retentionDays} days).");
        } else {
            $this->info("Cleanup: No old logs to delete.");
        }

        return Command::SUCCESS;
    }
}
