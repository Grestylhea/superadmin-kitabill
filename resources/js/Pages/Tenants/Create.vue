<template>
    <SuperAdminLayout>
        <Head title="Add New Tenant" />
        
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <Link :href="route('superadmin.tenants.index')" class="back-link">
                        <i class="bi bi-arrow-left"></i> Back to Tenants
                    </Link>
                    <h3 class="page-title">Add New Tenant</h3>
                    <p class="page-subtitle">Create a new billing system tenant</p>
                </div>
            </div>

            <!-- Form Card -->
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <form @submit.prevent="submit" class="form-card">
                        <!-- Tenant Information -->
                        <div class="form-section">
                            <div class="section-header">
                                <h5 class="section-title">
                                    <i class="bi bi-building"></i>
                                    Tenant Information
                                </h5>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Company Name *</label>
                                    <input 
                                        type="text" 
                                        v-model="form.name" 
                                        class="form-control" 
                                        :class="{ 'is-invalid': form.errors.name }"
                                        placeholder="e.g. PT Internet Cepat"
                                    >
                                    <div v-if="form.errors.name" class="invalid-feedback">
                                        {{ form.errors.name }}
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Subdomain *</label>
                                    <div class="input-group">
                                        <input 
                                            type="text" 
                                            v-model="form.subdomain" 
                                            class="form-control" 
                                            :class="{ 'is-invalid': form.errors.subdomain }"
                                            placeholder="e.g. cepat"
                                        >
                                        <span class="input-group-text">.kitabill.site</span>
                                    </div>
                                    <small class="text-muted">Only lowercase letters, numbers, and hyphens</small>
                                    <div v-if="form.errors.subdomain" class="invalid-feedback d-block">
                                        {{ form.errors.subdomain }}
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Email *</label>
                                    <input 
                                        type="email" 
                                        v-model="form.email" 
                                        class="form-control" 
                                        :class="{ 'is-invalid': form.errors.email }"
                                        placeholder="e.g. admin@company.com"
                                    >
                                    <div v-if="form.errors.email" class="invalid-feedback">
                                        {{ form.errors.email }}
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Phone</label>
                                    <input 
                                        type="text" 
                                        v-model="form.phone" 
                                        class="form-control" 
                                        placeholder="e.g. 081234567890"
                                    >
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Address</label>
                                    <textarea 
                                        v-model="form.address" 
                                        class="form-control" 
                                        rows="3"
                                        placeholder="e.g. Jl. Raya No. 123, Jakarta"
                                    ></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Subscription Plan -->
                        <div class="form-section">
                            <div class="section-header">
                                <h5 class="section-title">
                                    <i class="bi bi-credit-card"></i>
                                    Subscription Plan
                                </h5>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Plan *</label>
                                    <select 
                                        v-model="form.subscription_plan" 
                                        class="form-select"
                                        :class="{ 'is-invalid': form.errors.subscription_plan }"
                                    >
                                        <option value="">Select Plan</option>
                                        <option value="Starter">Starter - Rp 99.000/month</option>
                                        <option value="Professional">Professional - Rp 299.000/month</option>
                                        <option value="Enterprise">Enterprise - Rp 599.000/month</option>
                                    </select>
                                    <div v-if="form.errors.subscription_plan" class="invalid-feedback">
                                        {{ form.errors.subscription_plan }}
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Status *</label>
                                    <select 
                                        v-model="form.status" 
                                        class="form-select"
                                        :class="{ 'is-invalid': form.errors.status }"
                                    >
                                        <option value="trial">Trial</option>
                                        <option value="active">Active</option>
                                        <option value="suspended">Suspended</option>
                                    </select>
                                    <div v-if="form.errors.status" class="invalid-feedback">
                                        {{ form.errors.status }}
                                    </div>
                                </div>

                                <div class="col-md-6" v-if="form.status === 'trial'">
                                    <label class="form-label">Trial Duration (Days)</label>
                                    <input 
                                        type="number" 
                                        v-model="form.trial_days" 
                                        class="form-control" 
                                        placeholder="e.g. 14"
                                        min="0"
                                    >
                                    <small class="text-muted">Leave empty for no expiration</small>
                                </div>
                            </div>
                        </div>

                        <!-- Admin User -->
                        <div class="form-section">
                            <div class="section-header">
                                <h5 class="section-title">
                                    <i class="bi bi-person-badge"></i>
                                    Admin User
                                </h5>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Admin Name *</label>
                                    <input 
                                        type="text" 
                                        v-model="form.admin_name" 
                                        class="form-control" 
                                        :class="{ 'is-invalid': form.errors.admin_name }"
                                        placeholder="e.g. John Doe"
                                    >
                                    <div v-if="form.errors.admin_name" class="invalid-feedback">
                                        {{ form.errors.admin_name }}
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Admin Email *</label>
                                    <input 
                                        type="email" 
                                        v-model="form.admin_email" 
                                        class="form-control" 
                                        :class="{ 'is-invalid': form.errors.admin_email }"
                                        placeholder="e.g. admin@company.com"
                                    >
                                    <div v-if="form.errors.admin_email" class="invalid-feedback">
                                        {{ form.errors.admin_email }}
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Admin Password *</label>
                                    <input 
                                        type="password" 
                                        v-model="form.admin_password" 
                                        class="form-control" 
                                        :class="{ 'is-invalid': form.errors.admin_password }"
                                        placeholder="Min. 8 characters"
                                    >
                                    <div v-if="form.errors.admin_password" class="invalid-feedback">
                                        {{ form.errors.admin_password }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Submit Buttons -->
                        <div class="form-actions">
                            <Link :href="route('superadmin.tenants.index')" class="btn btn-secondary">
                                Cancel
                            </Link>
                            <button type="submit" class="btn btn-primary" :disabled="form.processing">
                                <span v-if="form.processing">
                                    <i class="bi bi-arrow-repeat spinner"></i> Creating...
                                </span>
                                <span v-else>
                                    <i class="bi bi-check-circle"></i> Create Tenant
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </SuperAdminLayout>
</template>

<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';

const form = useForm({
    name: '',
    subdomain: '',
    email: '',
    phone: '',
    address: '',
    subscription_plan: '',
    status: 'trial',
    trial_days: 14,
    admin_name: '',
    admin_email: '',
    admin_password: '',
});

const submit = () => {
    form.post(route('superadmin.tenants.store'), {
        onSuccess: () => {
            // Redirect handled by controller
        },
    });
};
</script>

<style scoped>
/* Page Header */
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

/* Form Card */
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

/* Form Controls */
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

.input-group-text {
    background: #F1F5F9;
    border: 2px solid #E2E8F0;
    border-left: none;
    color: #64748B;
    font-weight: 500;
}

:global(.dark) .input-group-text {
    background: #0F172A;
    border-color: #334155;
    color: #94A3B8;
}

.input-group .form-control {
    border-right: none;
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

/* Form Actions */
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

.btn-primary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
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

/* Responsive */
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



