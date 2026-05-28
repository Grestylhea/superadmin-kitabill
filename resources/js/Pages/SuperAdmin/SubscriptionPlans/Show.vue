<template>
    <SuperAdminLayout>
        <Head :title="`${plan.name} - Subscription Plan`" />
        
        <div class="container-fluid">
            <div class="page-header">
                <div>
                    <Link :href="route('superadmin.subscription-plans.index')" class="back-link">
                        <i class="bi bi-arrow-left"></i>
                        Back to Plans
                    </Link>
                    <h3 class="page-title">{{ plan.name }}</h3>
                    <p class="page-subtitle">{{ plan.description || 'Subscription plan details' }}</p>
                </div>
                <div class="header-actions">
                    <Link :href="route('superadmin.subscription-plans.edit', plan.id)" class="btn btn-primary">
                        <i class="bi bi-pencil"></i>
                        Edit Plan
                    </Link>
                </div>
            </div>

            <div class="row g-4">
                <!-- Main Info Card -->
                <div class="col-md-8">
                    <div class="info-card">
                        <div class="card-header">
                            <h5 class="card-title">
                                <i class="bi bi-info-circle"></i>
                                Plan Information
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="info-grid">
                                <div class="info-item">
                                    <label>Plan Name</label>
                                    <div class="value">{{ plan.name }}</div>
                                </div>
                                <div class="info-item">
                                    <label>Slug</label>
                                    <div class="value"><code>{{ plan.slug }}</code></div>
                                </div>
                                <div class="info-item">
                                    <label>Description</label>
                                    <div class="value">{{ plan.description || 'No description' }}</div>
                                </div>
                                <div class="info-item">
                                    <label>Status</label>
                                    <div class="value">
                                        <span :class="plan.is_active ? 'badge bg-success' : 'badge bg-secondary'">
                                            {{ plan.is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                        <span v-if="plan.is_featured" class="badge bg-warning text-dark ms-2">Featured</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="info-card mt-4">
                        <div class="card-header">
                            <h5 class="card-title">
                                <i class="bi bi-currency-dollar"></i>
                                Pricing
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="pricing-grid">
                                <div class="pricing-item">
                                    <label>Monthly Price</label>
                                    <div class="price-value">Rp {{ formatNumber(plan.price_monthly) }}</div>
                                </div>
                                <div class="pricing-item">
                                    <label>Yearly Price</label>
                                    <div class="price-value">Rp {{ formatNumber(plan.price_yearly) }}</div>
                                </div>
                                <div class="pricing-item">
                                    <label>Setup Fee</label>
                                    <div class="price-value">Rp {{ formatNumber(plan.setup_fee) }}</div>
                                </div>
                                <div class="pricing-item">
                                    <label>Trial Days</label>
                                    <div class="price-value">
                                        <span v-if="plan.trial_days > 0" class="badge bg-info">
                                            {{ plan.trial_days }} days
                                        </span>
                                        <span v-else class="text-muted">No trial</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="info-card mt-4">
                        <div class="card-header">
                            <h5 class="card-title">
                                <i class="bi bi-bar-chart"></i>
                                Limits
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="limits-grid">
                                <div class="limit-item">
                                    <i class="bi bi-people"></i>
                                    <div>
                                        <label>Max Customers</label>
                                        <div class="value">{{ plan.max_customers === 999999 ? 'Unlimited' : formatNumber(plan.max_customers) }}</div>
                                    </div>
                                </div>
                                <div class="limit-item">
                                    <i class="bi bi-person"></i>
                                    <div>
                                        <label>Max Users</label>
                                        <div class="value">{{ plan.max_users === 999999 ? 'Unlimited' : formatNumber(plan.max_users) }}</div>
                                    </div>
                                </div>
                                <div class="limit-item">
                                    <i class="bi bi-router"></i>
                                    <div>
                                        <label>Max Routers</label>
                                        <div class="value">{{ plan.max_routers === 999999 ? 'Unlimited' : formatNumber(plan.max_routers) }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="col-md-4">
                    <div class="info-card">
                        <div class="card-header">
                            <h5 class="card-title">
                                <i class="bi bi-star"></i>
                                Features
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="features-list">
                                <div class="feature-item" :class="{ active: plan.whatsapp_integration }">
                                    <i class="bi" :class="plan.whatsapp_integration ? 'bi-check-circle-fill text-success' : 'bi-x-circle text-muted'"></i>
                                    <span>WhatsApp Integration</span>
                                </div>
                                <div class="feature-item" :class="{ active: plan.payment_gateway }">
                                    <i class="bi" :class="plan.payment_gateway ? 'bi-check-circle-fill text-success' : 'bi-x-circle text-muted'"></i>
                                    <span>Payment Gateway</span>
                                </div>
                                <div class="feature-item" :class="{ active: plan.priority_support }">
                                    <i class="bi" :class="plan.priority_support ? 'bi-check-circle-fill text-success' : 'bi-x-circle text-muted'"></i>
                                    <span>Priority Support</span>
                                </div>
                                <div class="feature-item" :class="{ active: plan.white_label }">
                                    <i class="bi" :class="plan.white_label ? 'bi-check-circle-fill text-success' : 'bi-x-circle text-muted'"></i>
                                    <span>White Label</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="info-card mt-4">
                        <div class="card-header">
                            <h5 class="card-title">
                                <i class="bi bi-clock"></i>
                                Metadata
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="metadata-list">
                                <div class="metadata-item">
                                    <label>Sort Order</label>
                                    <div class="value">{{ plan.sort_order }}</div>
                                </div>
                                <div class="metadata-item">
                                    <label>Created At</label>
                                    <div class="value">{{ formatDate(plan.created_at) }}</div>
                                </div>
                                <div class="metadata-item" v-if="plan.updated_at">
                                    <label>Updated At</label>
                                    <div class="value">{{ formatDate(plan.updated_at) }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </SuperAdminLayout>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';

const props = defineProps({
    plan: Object,
});

const formatNumber = (num) => {
    return new Intl.NumberFormat('id-ID').format(num);
};

const formatDate = (date) => {
    if (!date) return 'N/A';
    return new Date(date).toLocaleDateString('id-ID', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};
</script>

<style scoped>
.page-header {
    margin-bottom: 32px;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.back-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #3B82F6;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    margin-bottom: 12px;
}

.back-link:hover {
    color: #2563EB;
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
}

:global(.dark) .page-subtitle {
    color: #94A3B8;
}

.header-actions {
    display: flex;
    gap: 12px;
}

.info-card {
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    overflow: hidden;
}

:global(.dark) .info-card {
    background: #1E293B;
}

.card-header {
    padding: 20px 24px;
    border-bottom: 1px solid #E2E8F0;
    background: #F8FAFC;
}

:global(.dark) .card-header {
    background: #0F172A;
    border-bottom-color: #334155;
}

.card-title {
    font-size: 18px;
    font-weight: 700;
    color: #1E293B;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 12px;
}

:global(.dark) .card-title {
    color: #F1F5F9;
}

.card-title i {
    color: #3B82F6;
}

.card-body {
    padding: 24px;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}

.info-item label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: #64748B;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
}

.info-item .value {
    font-size: 16px;
    font-weight: 600;
    color: #1E293B;
}

:global(.dark) .info-item .value {
    color: #F1F5F9;
}

.info-item code {
    background: #F1F5F9;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 14px;
    color: #3B82F6;
}

:global(.dark) .info-item code {
    background: #0F172A;
    color: #60A5FA;
}

.pricing-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}

.pricing-item label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: #64748B;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
}

.price-value {
    font-size: 20px;
    font-weight: 700;
    color: #3B82F6;
}

.limits-grid {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.limit-item {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px;
    background: #F8FAFC;
    border-radius: 8px;
}

:global(.dark) .limit-item {
    background: #0F172A;
}

.limit-item i {
    font-size: 24px;
    color: #3B82F6;
}

.limit-item label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: #64748B;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 4px;
}

.limit-item .value {
    font-size: 18px;
    font-weight: 700;
    color: #1E293B;
}

:global(.dark) .limit-item .value {
    color: #F1F5F9;
}

.features-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.feature-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    border-radius: 8px;
    transition: all 0.2s;
}

.feature-item.active {
    background: #EFF6FF;
}

:global(.dark) .feature-item.active {
    background: #1E3A5F;
}

.feature-item i {
    font-size: 20px;
}

.feature-item span {
    font-weight: 500;
    color: #1E293B;
}

:global(.dark) .feature-item span {
    color: #F1F5F9;
}

.metadata-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.metadata-item label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: #64748B;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 4px;
}

.metadata-item .value {
    font-size: 14px;
    font-weight: 500;
    color: #1E293B;
}

:global(.dark) .metadata-item .value {
    color: #F1F5F9;
}

.btn {
    padding: 12px 24px;
    border-radius: 8px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s ease;
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

@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
        gap: 16px;
    }

    .info-grid,
    .pricing-grid {
        grid-template-columns: 1fr;
    }
}
</style>







