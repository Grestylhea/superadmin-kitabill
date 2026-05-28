<template>
    <SuperAdminLayout>
        <Head title="Subscription Plans" />
        
        <div class="container-fluid">
            <div class="page-header">
                <div>
                    <h3 class="page-title">Subscription Plans</h3>
                    <p class="page-subtitle">Manage subscription plans for tenant registration</p>
                </div>
                <Link :href="route('superadmin.subscription-plans.create')" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i>
                    Add New Plan
                </Link>
            </div>

            <div class="plans-card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="plans-table">
                            <thead>
                                <tr>
                                    <th>Plan Name</th>
                                    <th>Pricing</th>
                                    <th>Trial Period</th>
                                    <th>Limits</th>
                                    <th>Status</th>
                                    <th>Public</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="plan in plans.data" :key="plan.id" class="plan-row">
                                    <td class="plan-name-cell">
                                        <div class="plan-name">{{ plan.name }}</div>
                                        <div class="plan-description">{{ plan.description || 'No description' }}</div>
                                    </td>
                                    <td class="pricing-cell">
                                        <div class="pricing-item">
                                            <span class="pricing-label">Monthly:</span>
                                            <span class="pricing-value">Rp {{ formatNumber(plan.price_monthly) }}</span>
                                        </div>
                                        <div class="pricing-item">
                                            <span class="pricing-label">Yearly:</span>
                                            <span class="pricing-value">Rp {{ formatNumber(plan.price_yearly) }}</span>
                                        </div>
                                        <div v-if="plan.setup_fee > 0" class="pricing-item setup-fee">
                                            <span class="pricing-label">Setup:</span>
                                            <span class="pricing-value">Rp {{ formatNumber(plan.setup_fee) }}</span>
                                        </div>
                                    </td>
                                    <td class="trial-cell">
                                        <span v-if="plan.trial_days > 0" class="badge badge-trial">
                                            <i class="bi bi-clock"></i>
                                            {{ plan.trial_days }} days
                                        </span>
                                        <span v-else class="badge badge-no-trial">No trial</span>
                                        <div v-if="plan.is_trial_plan" class="badge badge-free-trial mt-1">
                                            <i class="bi bi-gift"></i>
                                            Free Trial
                                        </div>
                                    </td>
                                    <td class="limits-cell">
                                        <div class="limit-item">
                                            <i class="bi bi-people"></i>
                                            <span>Customers: <strong>{{ plan.max_customers === 999999 ? '∞' : formatNumber(plan.max_customers) }}</strong></span>
                                        </div>
                                        <div class="limit-item">
                                            <i class="bi bi-person"></i>
                                            <span>Users: <strong>{{ plan.max_users === 999999 ? '∞' : formatNumber(plan.max_users) }}</strong></span>
                                        </div>
                                        <div class="limit-item">
                                            <i class="bi bi-router"></i>
                                            <span>Routers: <strong>{{ plan.max_routers === 999999 ? '∞' : formatNumber(plan.max_routers) }}</strong></span>
                                        </div>
                                        <div class="limit-item">
                                            <i class="bi bi-hdd-network"></i>
                                            <span>ACS: <strong>{{ plan.acs_enabled ? 'Enabled' : 'Disabled' }}</strong></span>
                                        </div>
                                    </td>
                                    <td class="status-cell">
                                        <span :class="plan.is_active ? 'badge badge-active' : 'badge badge-inactive'">
                                            {{ plan.is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                        <span v-if="plan.is_featured" class="badge badge-featured mt-1">
                                            <i class="bi bi-star-fill"></i>
                                            Featured
                                        </span>
                                    </td>
                                    <td class="status-cell">
                                        <span :class="plan.is_public ? 'badge badge-public' : 'badge badge-private'">
                                            <i :class="plan.is_public ? 'bi bi-eye' : 'bi bi-eye-slash-fill'"></i>
                                            {{ plan.is_public ? 'Public' : 'Hidden' }}
                                        </span>
                                    </td>
                                    <td class="actions-cell">
                                        <div class="action-buttons">
                                            <Link :href="route('superadmin.subscription-plans.show', plan.id)" class="btn-action btn-action-view" title="View Details">
                                                <i class="bi bi-eye"></i>
                                            </Link>
                                            <Link :href="route('superadmin.subscription-plans.edit', plan.id)" class="btn-action btn-action-edit" title="Edit Plan">
                                                <i class="bi bi-pencil"></i>
                                            </Link>
                                            <button @click="deletePlan(plan.id)" class="btn-action btn-action-delete" title="Delete Plan">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="plans.data.length === 0">
                                    <td colspan="6" class="text-center py-5">
                                        <i class="bi bi-inbox" style="font-size: 48px; color: #94A3B8; margin-bottom: 16px;"></i>
                                        <div style="color: #64748B; font-size: 16px;">No subscription plans found.</div>
                                        <Link :href="route('superadmin.subscription-plans.create')" class="btn btn-primary mt-3">
                                            <i class="bi bi-plus-circle"></i>
                                            Create First Plan
                                        </Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="plans.last_page > 1" class="pagination-wrapper">
                        <nav>
                            <ul class="pagination">
                                <li class="page-item" :class="{ disabled: !plans.prev_page_url }">
                                    <Link :href="plans.prev_page_url" class="page-link">
                                        <i class="bi bi-chevron-left"></i>
                                        Previous
                                    </Link>
                                </li>
                                <li class="page-item" :class="{ disabled: !plans.next_page_url }">
                                    <Link :href="plans.next_page_url" class="page-link">
                                        Next
                                        <i class="bi bi-chevron-right"></i>
                                    </Link>
                                </li>
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </SuperAdminLayout>
</template>

<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';

const props = defineProps({
    plans: Object,
    filters: Object,
});

const formatNumber = (num) => {
    return new Intl.NumberFormat('id-ID').format(num);
};

const deletePlan = (id) => {
    if (confirm('Are you sure you want to delete this subscription plan? This action cannot be undone.')) {
        router.delete(route('superadmin.subscription-plans.destroy', id), {
            onError: (errors) => {
                if (errors.error) {
                    alert(errors.error);
                }
            }
        });
    }
};
</script>

<style scoped>
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 32px;
}

.page-title {
    font-size: 28px;
    font-weight: 700;
    color: #1E293B;
    margin-bottom: 4px;
}

:global(.dark) .page-title {
    color: #F1F5F9;
}

.page-subtitle {
    color: #64748B;
    margin: 0;
    font-size: 14px;
}

:global(.dark) .page-subtitle {
    color: #94A3B8;
}

.btn {
    padding: 12px 24px;
    border-radius: 8px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s ease;
    text-decoration: none;
}

.btn-primary {
    background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%);
    border: none;
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(59, 130, 246, 0.3);
}

.plans-card {
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    overflow: hidden;
}

:global(.dark) .plans-card {
    background: #1E293B;
}

.card-body {
    padding: 0;
}

.table-responsive {
    overflow-x: auto;
}

.plans-table {
    width: 100%;
    border-collapse: collapse;
}

.plans-table thead {
    background: #F8FAFC;
}

:global(.dark) .plans-table thead {
    background: #0F172A;
}

.plans-table th {
    padding: 16px 20px;
    text-align: left;
    font-weight: 600;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #64748B;
    border-bottom: 2px solid #E2E8F0;
}

:global(.dark) .plans-table th {
    color: #94A3B8;
    border-bottom-color: #334155;
}

.plan-row {
    border-bottom: 1px solid #E2E8F0;
    transition: background 0.2s;
}

:global(.dark) .plan-row {
    border-bottom-color: #334155;
}

.plan-row:hover {
    background: #F8FAFC;
}

:global(.dark) .plan-row:hover {
    background: #0F172A;
}

.plans-table td {
    padding: 20px;
    vertical-align: top;
}

.plan-name-cell {
    min-width: 200px;
}

.plan-name {
    font-size: 16px;
    font-weight: 700;
    color: #1E293B;
    margin-bottom: 4px;
}

:global(.dark) .plan-name {
    color: #F1F5F9;
}

.plan-description {
    font-size: 13px;
    color: #64748B;
    line-height: 1.4;
}

:global(.dark) .plan-description {
    color: #94A3B8;
}

.pricing-cell {
    min-width: 180px;
}

.pricing-item {
    margin-bottom: 8px;
    font-size: 14px;
}

.pricing-item:last-child {
    margin-bottom: 0;
}

.pricing-label {
    color: #64748B;
    font-weight: 500;
    margin-right: 8px;
}

:global(.dark) .pricing-label {
    color: #94A3B8;
}

.pricing-value {
    color: #1E293B;
    font-weight: 600;
}

:global(.dark) .pricing-value {
    color: #F1F5F9;
}

.setup-fee {
    font-size: 12px;
    color: #64748B;
}

.trial-cell {
    min-width: 120px;
}

.badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
}

.badge-trial {
    background: #DBEAFE;
    color: #1E40AF;
}

:global(.dark) .badge-trial {
    background: #1E3A5F;
    color: #93C5FD;
}

.badge-no-trial {
    background: #F3F4F6;
    color: #6B7280;
}

:global(.dark) .badge-no-trial {
    background: #374151;
    color: #9CA3AF;
}

.badge-free-trial {
    background: #D1FAE5;
    color: #065F46;
}

:global(.dark) .badge-free-trial {
    background: #064E3B;
    color: #6EE7B7;
}

.badge-active {
    background: #D1FAE5;
    color: #065F46;
}

:global(.dark) .badge-active {
    background: #064E3B;
    color: #6EE7B7;
}

.badge-inactive {
    background: #F3F4F6;
    color: #6B7280;
}

:global(.dark) .badge-inactive {
    background: #374151;
    color: #9CA3AF;
}

.badge-featured {
    background: #FEF3C7;
    color: #92400E;
}

:global(.dark) .badge-featured {
    background: #78350F;
    color: #FCD34D;
}

.badge-public {
    background: #E0F2FE;
    color: #0369A1;
}

:global(.dark) .badge-public {
    background: #0C4A6E;
    color: #7DD3FC;
}

.badge-private {
    background: #FFEDD5;
    color: #9A3412;
}

:global(.dark) .badge-private {
    background: #7C2D12;
    color: #FDBA74;
}

.limits-cell {
    min-width: 180px;
}

.limit-item {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 6px;
    font-size: 13px;
    color: #64748B;
}

:global(.dark) .limit-item {
    color: #94A3B8;
}

.limit-item i {
    color: #3B82F6;
    font-size: 14px;
}

.limit-item strong {
    color: #1E293B;
    font-weight: 600;
}

:global(.dark) .limit-item strong {
    color: #F1F5F9;
}

.status-cell {
    min-width: 120px;
}

.actions-cell {
    min-width: 120px;
}

.action-buttons {
    display: flex;
    gap: 8px;
}

.btn-action {
    width: 36px;
    height: 36px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
    font-size: 14px;
}

.btn-action-view {
    background: #DBEAFE;
    color: #1E40AF;
}

.btn-action-view:hover {
    background: #BFDBFE;
    transform: translateY(-2px);
}

.btn-action-edit {
    background: #FEF3C7;
    color: #92400E;
}

.btn-action-edit:hover {
    background: #FDE68A;
    transform: translateY(-2px);
}

.btn-action-delete {
    background: #FEE2E2;
    color: #991B1B;
}

.btn-action-delete:hover {
    background: #FECACA;
    transform: translateY(-2px);
}

.pagination-wrapper {
    padding: 24px;
    border-top: 1px solid #E2E8F0;
    display: flex;
    justify-content: center;
}

:global(.dark) .pagination-wrapper {
    border-top-color: #334155;
}

.pagination {
    display: flex;
    gap: 8px;
    list-style: none;
    margin: 0;
    padding: 0;
}

.page-item {
    margin: 0;
}

.page-item.disabled .page-link {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
}

.page-link {
    padding: 10px 16px;
    border-radius: 8px;
    background: white;
    border: 1px solid #E2E8F0;
    color: #3B82F6;
    text-decoration: none;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s;
}

:global(.dark) .page-link {
    background: #1E293B;
    border-color: #334155;
    color: #60A5FA;
}

.page-link:hover:not(.disabled) {
    background: #F8FAFC;
    border-color: #3B82F6;
    transform: translateY(-2px);
}

:global(.dark) .page-link:hover:not(.disabled) {
    background: #0F172A;
}

@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
        gap: 16px;
    }

    .plans-table {
        font-size: 14px;
    }

    .plans-table th,
    .plans-table td {
        padding: 12px;
    }
}
</style>

