<template>
    <SuperAdminLayout>
        <Head title="Edit Subscription Plan" />
        
        <div class="container-fluid">
            <div class="page-header">
                <div>
                    <Link :href="route('superadmin.subscription-plans.index')" class="back-link">
                        <i class="bi bi-arrow-left"></i>
                        Back to Plans
                    </Link>
                    <h3 class="page-title">Edit Subscription Plan</h3>
                    <p class="page-subtitle">Update subscription plan details</p>
                </div>
            </div>

            <div class="form-card">
                <form @submit.prevent="submit">
                    <!-- Basic Information -->
                    <div class="form-section">
                        <div class="section-header">
                            <h5 class="section-title">
                                <i class="bi bi-info-circle"></i>
                                Basic Information
                            </h5>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Plan Name *</label>
                                <input 
                                    type="text" 
                                    v-model="form.name" 
                                    class="form-control"
                                    :class="{ 'is-invalid': form.errors.name }"
                                    placeholder="e.g. Starter, Professional, Enterprise"
                                    required
                                >
                                <div v-if="form.errors.name" class="invalid-feedback">
                                    {{ form.errors.name }}
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Description</label>
                                <input 
                                    type="text" 
                                    v-model="form.description" 
                                    class="form-control"
                                    :class="{ 'is-invalid': form.errors.description }"
                                    placeholder="Brief description of the plan"
                                >
                                <div v-if="form.errors.description" class="invalid-feedback">
                                    {{ form.errors.description }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pricing -->
                    <div class="form-section">
                        <div class="section-header">
                            <h5 class="section-title">
                                <i class="bi bi-currency-dollar"></i>
                                Pricing
                            </h5>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Monthly Price (IDR) *</label>
                                <input 
                                    type="number" 
                                    v-model.number="form.price_monthly" 
                                    class="form-control"
                                    :class="{ 'is-invalid': form.errors.price_monthly }"
                                    min="0"
                                    step="1000"
                                    placeholder="0"
                                    required
                                >
                                <small class="text-muted">Set to 0 for free trial plans</small>
                                <div v-if="form.errors.price_monthly" class="invalid-feedback">
                                    {{ form.errors.price_monthly }}
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Yearly Price (IDR) *</label>
                                <input 
                                    type="number" 
                                    v-model.number="form.price_yearly" 
                                    class="form-control"
                                    :class="{ 'is-invalid': form.errors.price_yearly }"
                                    min="0"
                                    step="1000"
                                    placeholder="0"
                                    required
                                >
                                <small class="text-muted">Set to 0 for free trial plans</small>
                                <div v-if="form.errors.price_yearly" class="invalid-feedback">
                                    {{ form.errors.price_yearly }}
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Setup Fee (IDR)</label>
                                <input 
                                    type="number" 
                                    v-model.number="form.setup_fee" 
                                    class="form-control"
                                    :class="{ 'is-invalid': form.errors.setup_fee }"
                                    min="0"
                                    step="1000"
                                    placeholder="0"
                                >
                                <small class="text-muted">One-time setup fee</small>
                                <div v-if="form.errors.setup_fee" class="invalid-feedback">
                                    {{ form.errors.setup_fee }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Trial & Limits -->
                    <div class="form-section">
                        <div class="section-header">
                            <h5 class="section-title">
                                <i class="bi bi-clock"></i>
                                Trial Period & Limits
                            </h5>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Trial Days</label>
                                <input 
                                    type="number" 
                                    v-model.number="form.trial_days" 
                                    class="form-control"
                                    :class="{ 'is-invalid': form.errors.trial_days }"
                                    min="0"
                                    max="365"
                                    placeholder="14"
                                >
                                <small class="text-muted">Number of trial days (0 = no trial)</small>
                                <div v-if="form.errors.trial_days" class="invalid-feedback">
                                    {{ form.errors.trial_days }}
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Max Customers</label>
                                <input 
                                    type="number" 
                                    v-model.number="form.max_customers" 
                                    class="form-control"
                                    :class="{ 'is-invalid': form.errors.max_customers }"
                                    min="1"
                                    placeholder="100"
                                >
                                <small class="text-muted">Set 999999 for unlimited</small>
                                <div v-if="form.errors.max_customers" class="invalid-feedback">
                                    {{ form.errors.max_customers }}
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Max Users</label>
                                <input 
                                    type="number" 
                                    v-model.number="form.max_users" 
                                    class="form-control"
                                    :class="{ 'is-invalid': form.errors.max_users }"
                                    min="1"
                                    placeholder="10"
                                >
                                <small class="text-muted">Set 999999 for unlimited</small>
                                <div v-if="form.errors.max_users" class="invalid-feedback">
                                    {{ form.errors.max_users }}
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Max Routers</label>
                                <input 
                                    type="number" 
                                    v-model.number="form.max_routers" 
                                    class="form-control"
                                    :class="{ 'is-invalid': form.errors.max_routers }"
                                    min="1"
                                    placeholder="5"
                                >
                                <small class="text-muted">Set 999999 for unlimited</small>
                                <div v-if="form.errors.max_routers" class="invalid-feedback">
                                    {{ form.errors.max_routers }}
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Sort Order</label>
                                <input 
                                    type="number" 
                                    v-model.number="form.sort_order" 
                                    class="form-control"
                                    :class="{ 'is-invalid': form.errors.sort_order }"
                                    min="0"
                                    placeholder="0"
                                >
                                <small class="text-muted">Lower number appears first</small>
                                <div v-if="form.errors.sort_order" class="invalid-feedback">
                                    {{ form.errors.sort_order }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Features -->
                    <div class="form-section">
                        <div class="section-header">
                            <h5 class="section-title">
                                <i class="bi bi-star"></i>
                                Features & Status
                            </h5>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input 
                                        class="form-check-input" 
                                        type="checkbox" 
                                        v-model="form.whatsapp_integration"
                                        id="whatsapp">
                                    <label class="form-check-label" for="whatsapp">
                                        WhatsApp Integration
                                    </label>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input 
                                        class="form-check-input" 
                                        type="checkbox" 
                                        v-model="form.payment_gateway"
                                        id="payment">
                                    <label class="form-check-label" for="payment">
                                        Payment Gateway
                                    </label>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input 
                                        class="form-check-input" 
                                        type="checkbox" 
                                        v-model="form.priority_support"
                                        id="support">
                                    <label class="form-check-label" for="support">
                                        Priority Support
                                    </label>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input 
                                        class="form-check-input" 
                                        type="checkbox" 
                                        v-model="form.white_label"
                                        id="whitelabel">
                                    <label class="form-check-label" for="whitelabel">
                                        White Label
                                    </label>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input 
                                        class="form-check-input" 
                                        type="checkbox" 
                                        v-model="form.is_active"
                                        id="active">
                                    <label class="form-check-label" for="active">
                                        Active (visible to tenants)
                                    </label>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input 
                                        class="form-check-input" 
                                        type="checkbox" 
                                        v-model="form.is_featured"
                                        id="featured">
                                    <label class="form-check-label" for="featured">
                                        Featured Plan
                                    </label>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input 
                                        class="form-check-input" 
                                        type="checkbox" 
                                        v-model="form.is_public"
                                        id="public">
                                    <label class="form-check-label" for="public">
                                        Public (Tampilkan di Website)
                                    </label>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input 
                                        class="form-check-input" 
                                        type="checkbox" 
                                        v-model="form.acs_enabled"
                                        id="acs">
                                    <label class="form-check-label" for="acs">
                                        ACS (TR-069) Management
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="form-actions">
                        <Link :href="route('superadmin.subscription-plans.index')" class="btn btn-secondary">
                            Cancel
                        </Link>
                        <button type="submit" class="btn btn-primary" :disabled="form.processing">
                            <span v-if="form.processing">
                                <i class="bi bi-arrow-repeat spinner"></i> Updating...
                            </span>
                            <span v-else>
                                <i class="bi bi-check-circle"></i> Update Plan
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </SuperAdminLayout>
</template>

<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';

const props = defineProps({
    plan: Object,
});

const form = useForm({
    name: props.plan.name || '',
    description: props.plan.description || '',
    price_monthly: props.plan.price_monthly || 0,
    price_yearly: props.plan.price_yearly || 0,
    setup_fee: props.plan.setup_fee || 0,
    trial_days: props.plan.trial_days || 14,
    max_customers: props.plan.max_customers || 100,
    max_users: props.plan.max_users || 3,
    max_routers: props.plan.max_routers || 2,
    whatsapp_integration: props.plan.whatsapp_integration || false,
    payment_gateway: props.plan.payment_gateway || false,
    priority_support: props.plan.priority_support || false,
    white_label: props.plan.white_label || false,
    is_active: props.plan.is_active ?? true,
    is_public: props.plan.is_public ?? true,
    is_featured: props.plan.is_featured || false,
    acs_enabled: !!props.plan.acs_enabled,
    sort_order: props.plan.sort_order || 0,
});

const submit = () => {
    form.put(route('superadmin.subscription-plans.update', props.plan.id), {
        onSuccess: () => {
            // Redirect handled by controller
        },
    });
};
</script>

<style scoped>
/* Same styles as Create.vue */
.page-header {
    margin-bottom: 32px;
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

.form-card {
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    overflow: hidden;
}

:global(.dark) .form-card {
    background: #1E293B;
}

.form-section {
    padding: 32px;
    border-bottom: 1px solid #E2E8F0;
}

:global(.dark) .form-section {
    border-bottom-color: #334155;
}

.form-section:last-of-type {
    border-bottom: none;
}

.section-header {
    margin-bottom: 24px;
}

.section-title {
    font-size: 18px;
    font-weight: 700;
    color: #1E293B;
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 0;
}

:global(.dark) .section-title {
    color: #F1F5F9;
}

.section-title i {
    color: #3B82F6;
    font-size: 20px;
}

.form-label {
    font-weight: 600;
    color: #1E293B;
    margin-bottom: 8px;
}

:global(.dark) .form-label {
    color: #E2E8F0;
}

.form-control,
.form-select {
    border: 2px solid #E2E8F0;
    border-radius: 8px;
    padding: 10px 14px;
    transition: all 0.2s ease;
}

:global(.dark) .form-control,
:global(.dark) .form-select {
    background: #0F172A;
    border-color: #334155;
    color: #E2E8F0;
}

.form-control:focus,
.form-select:focus {
    border-color: #3B82F6;
    box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
}

.text-muted {
    font-size: 12px;
    color: #64748B;
    margin-top: 4px;
    display: block;
}

:global(.dark) .text-muted {
    color: #94A3B8;
}

.form-check-input {
    width: 48px;
    height: 24px;
    cursor: pointer;
}

.form-check-input:checked {
    background-color: #3B82F6;
    border-color: #3B82F6;
}

.form-check-label {
    margin-left: 12px;
    color: #1E293B;
    font-weight: 500;
}

:global(.dark) .form-check-label {
    color: #E2E8F0;
}

.form-actions {
    padding: 24px 32px;
    background: #F8FAFC;
    display: flex;
    justify-content: flex-end;
    gap: 12px;
}

:global(.dark) .form-actions {
    background: #0F172A;
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

.btn-primary:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(59, 130, 246, 0.3);
}

.btn-secondary {
    background: #E2E8F0;
    border: none;
    color: #475569;
}

:global(.dark) .btn-secondary {
    background: #334155;
    color: #94A3B8;
}

.btn-secondary:hover {
    background: #CBD5E1;
}

:global(.dark) .btn-secondary:hover {
    background: #475569;
}

.spinner {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

@media (max-width: 768px) {
    .form-section {
        padding: 20px;
    }

    .form-actions {
        padding: 16px 20px;
        flex-direction: column;
    }

    .btn {
        width: 100%;
        justify-content: center;
    }
}
</style>







