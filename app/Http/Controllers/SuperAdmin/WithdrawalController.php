<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\WithdrawalRequest;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;

class WithdrawalController extends Controller
{
    public function index()
    {
        $requests = WithdrawalRequest::with('tenant:id,name,subdomain')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return Inertia::render('SuperAdmin/Withdrawals/Index', [
            'requests' => $requests,
        ]);
    }

    public function updateStatus(Request $request, WithdrawalRequest $withdrawal)
    {
        $request->validate([
            'status' => 'required|in:approved,rejected,paid',
            'admin_notes' => 'nullable|string',
            'proof_file' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ]);

        $oldStatus = $withdrawal->status;
        $newStatus = $request->status;

        \DB::transaction(function () use ($withdrawal, $request, $oldStatus, $newStatus) {
            $data = [
                'status' => $newStatus,
                'admin_notes' => $request->admin_notes,
            ];

            if ($request->hasFile('proof_file')) {
                $path = $request->file('proof_file')->store('withdrawal-proofs', 'public');
                $data['proof_file'] = $path;
            }

            $withdrawal->update($data);

            // If marked as paid, deduct balance from tenant
            if ($newStatus === 'paid' && $oldStatus !== 'paid') {
                $tenant = $withdrawal->tenant;
                $tenant->decrement('referral_balance', $withdrawal->amount);
                
                \Log::info('[WITHDRAWAL] Mark as paid and balance deducted', [
                    'tenant_id' => $tenant->id,
                    'amount' => $withdrawal->amount,
                    'withdrawal_id' => $withdrawal->id,
                ]);
            }
            
            // If was paid and moved to something else (correction), increment back balance
            if ($oldStatus === 'paid' && $newStatus !== 'paid') {
                $tenant = $withdrawal->tenant;
                $tenant->increment('referral_balance', $withdrawal->amount);
            }
        });

        return redirect()->back()->with('success', 'Withdrawal status updated successfully.');
    }
}
