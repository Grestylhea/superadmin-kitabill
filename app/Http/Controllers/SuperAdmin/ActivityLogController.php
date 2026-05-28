<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $query = ActivityLog::with('user')->orderBy('created_at', 'desc');

        // Filter by Action
        if ($request->action) {
            $query->where('action', $request->action);
        }

        // Filter by Tenant ID
        if ($request->tenant_id) {
            $query->where('tenant_id', $request->tenant_id);
        }

        // Search (Description or User Email)
        if ($request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('description', 'ilike', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('email', 'ilike', "%{$search}%");
                  });
            });
        }

        $logs = $query->paginate(25)->withQueryString();

        return Inertia::render('SuperAdmin/Logs/Index', [
            'logs' => $logs,
            'filters' => [
                'action' => $request->action,
                'tenant_id' => $request->tenant_id,
                'search' => $request->search,
            ],
            // Predefined list of actions for filter dropdown
            'availableActions' => ActivityLog::distinct()->pluck('action')
        ]);
    }
}
