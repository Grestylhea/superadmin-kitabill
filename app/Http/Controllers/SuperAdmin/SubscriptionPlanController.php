<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class SubscriptionPlanController extends Controller
{
    public function index(Request $request)
    {
        $query = SubscriptionPlan::query();

        // Search
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'ilike', '%' . $request->search . '%')
                  ->orWhere('slug', 'ilike', '%' . $request->search . '%')
                  ->orWhere('description', 'ilike', '%' . $request->search . '%');
            });
        }

        // Filter by status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        // Sort
        $sortField = $request->sort_by ?? 'sort_order';
        $sortDirection = $request->sort_dir ?? 'asc';
        $query->orderBy($sortField, $sortDirection);

        $plans = $query->paginate(15)->through(function ($plan) {
            return [
                'id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
                'description' => $plan->description,
                'price_monthly' => $plan->price_monthly,
                'price_yearly' => $plan->price_yearly,
                'setup_fee' => $plan->setup_fee,
                'trial_days' => $plan->trial_days,
                'is_trial_plan' => $plan->isTrialPlan(),
                'max_customers' => $plan->max_customers,
                'max_users' => $plan->max_users,
                'max_routers' => $plan->max_routers,
                'is_active' => $plan->is_active,
                'is_public' => $plan->is_public,
                'is_featured' => $plan->is_featured,
                'acs_enabled' => $plan->acs_enabled,
                'sort_order' => $plan->sort_order,
                'created_at' => $plan->created_at,
            ];
        });

        return Inertia::render('SuperAdmin/SubscriptionPlans/Index', [
            'plans' => $plans,
            'filters' => [
                'search' => $request->search,
                'is_active' => $request->is_active,
                'sort_by' => $sortField,
                'sort_dir' => $sortDirection,
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('SuperAdmin/SubscriptionPlans/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price_monthly' => 'required|numeric|min:0',
            'price_yearly' => 'required|numeric|min:0',
            'setup_fee' => 'nullable|numeric|min:0',
            'trial_days' => 'nullable|integer|min:0|max:365',
            'max_customers' => 'nullable|integer|min:1',
            'max_users' => 'nullable|integer|min:1',
            'max_routers' => 'nullable|integer|min:1',
            'whatsapp_integration' => 'boolean',
            'payment_gateway' => 'boolean',
            'priority_support' => 'boolean',
            'white_label' => 'boolean',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
            'is_featured' => 'boolean',
            'acs_enabled' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        // Ensure slug is unique
        $originalSlug = $validated['slug'];
        $count = 1;
        while (SubscriptionPlan::where('slug', $validated['slug'])->exists()) {
            $validated['slug'] = $originalSlug . '-' . $count;
            $count++;
        }

        SubscriptionPlan::create($validated);

        return redirect()->route('superadmin.subscription-plans.index')
            ->with('success', 'Subscription plan created successfully!');
    }

    public function show(SubscriptionPlan $subscriptionPlan)
    {
        return Inertia::render('SuperAdmin/SubscriptionPlans/Show', [
            'plan' => [
                'id' => $subscriptionPlan->id,
                'name' => $subscriptionPlan->name,
                'slug' => $subscriptionPlan->slug,
                'description' => $subscriptionPlan->description,
                'price_monthly' => $subscriptionPlan->price_monthly,
                'price_yearly' => $subscriptionPlan->price_yearly,
                'setup_fee' => $subscriptionPlan->setup_fee,
                'trial_days' => $subscriptionPlan->trial_days,
                'is_trial_plan' => $subscriptionPlan->isTrialPlan(),
                'max_customers' => $subscriptionPlan->max_customers,
                'max_users' => $subscriptionPlan->max_users,
                'max_routers' => $subscriptionPlan->max_routers,
                'whatsapp_integration' => $subscriptionPlan->whatsapp_integration,
                'payment_gateway' => $subscriptionPlan->payment_gateway,
                'priority_support' => $subscriptionPlan->priority_support,
                'white_label' => $subscriptionPlan->white_label,
                'is_active' => $subscriptionPlan->is_active,
                'is_public' => $subscriptionPlan->is_public,
                'is_featured' => $subscriptionPlan->is_featured,
                'acs_enabled' => $subscriptionPlan->acs_enabled,
                'sort_order' => $subscriptionPlan->sort_order,
                'created_at' => $subscriptionPlan->created_at,
                'updated_at' => $subscriptionPlan->updated_at,
            ],
        ]);
    }

    public function edit(SubscriptionPlan $subscriptionPlan)
    {
        return Inertia::render('SuperAdmin/SubscriptionPlans/Edit', [
            'plan' => [
                'id' => $subscriptionPlan->id,
                'name' => $subscriptionPlan->name,
                'slug' => $subscriptionPlan->slug,
                'description' => $subscriptionPlan->description,
                'price_monthly' => $subscriptionPlan->price_monthly,
                'price_yearly' => $subscriptionPlan->price_yearly,
                'setup_fee' => $subscriptionPlan->setup_fee,
                'trial_days' => $subscriptionPlan->trial_days,
                'max_customers' => $subscriptionPlan->max_customers,
                'max_users' => $subscriptionPlan->max_users,
                'max_routers' => $subscriptionPlan->max_routers,
                'whatsapp_integration' => $subscriptionPlan->whatsapp_integration,
                'payment_gateway' => $subscriptionPlan->payment_gateway,
                'priority_support' => $subscriptionPlan->priority_support,
                'white_label' => $subscriptionPlan->white_label,
                'is_active' => $subscriptionPlan->is_active,
                'is_public' => $subscriptionPlan->is_public,
                'is_featured' => $subscriptionPlan->is_featured,
                'acs_enabled' => $subscriptionPlan->acs_enabled,
                'sort_order' => $subscriptionPlan->sort_order,
            ],
        ]);
    }

    public function update(Request $request, SubscriptionPlan $subscriptionPlan)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price_monthly' => 'required|numeric|min:0',
            'price_yearly' => 'required|numeric|min:0',
            'setup_fee' => 'nullable|numeric|min:0',
            'trial_days' => 'nullable|integer|min:0|max:365',
            'max_customers' => 'nullable|integer|min:1',
            'max_users' => 'nullable|integer|min:1',
            'max_routers' => 'nullable|integer|min:1',
            'whatsapp_integration' => 'boolean',
            'payment_gateway' => 'boolean',
            'priority_support' => 'boolean',
            'white_label' => 'boolean',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
            'is_featured' => 'boolean',
            'acs_enabled' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        // Update slug if name changed
        if ($validated['name'] !== $subscriptionPlan->name) {
            $newSlug = Str::slug($validated['name']);
            if ($newSlug !== $subscriptionPlan->slug) {
                $originalSlug = $newSlug;
                $count = 1;
                while (SubscriptionPlan::where('slug', $newSlug)->where('id', '!=', $subscriptionPlan->id)->exists()) {
                    $newSlug = $originalSlug . '-' . $count;
                    $count++;
                }
                $validated['slug'] = $newSlug;
            }
        }

        $subscriptionPlan->update($validated);

        return redirect()->route('superadmin.subscription-plans.index')
            ->with('success', 'Subscription plan updated successfully!');
    }

    public function destroy(SubscriptionPlan $subscriptionPlan)
    {
        // Check if plan is used by any tenant
        $tenantCount = \App\Models\Tenant::where('subscription_plan', $subscriptionPlan->slug)->count();
        
        if ($tenantCount > 0) {
            return back()->with('error', "Cannot delete plan. It is used by {$tenantCount} tenant(s).");
        }

        $subscriptionPlan->delete();

        return redirect()->route('superadmin.subscription-plans.index')
            ->with('success', 'Subscription plan deleted successfully!');
    }
}
