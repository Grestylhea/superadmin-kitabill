<template>
    <SuperAdminLayout>
        <Head :title="`Edit Tenant: ${tenant.name}`" />
        
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h3 class="page-title">Edit Tenant</h3>
                    <p class="page-subtitle">{{ tenant.name }}</p>
                </div>
                <Link :href="route('superadmin.tenants.index')" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i>
                    Back
                </Link>
            </div>

            <!-- Edit Form -->
            <div class="form-card">
                <form @submit.prevent="submit">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Company Name <span class="text-danger">*</span></label>
                            <input 
                                type="text" 
                                v-model="form.name" 
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.name }"
                                required
                            >
                            <div v-if="form.errors.name" class="invalid-feedback">
                                {{ form.errors.name }}
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Subdomain</label>
                            <input 
                                type="text" 
                                :value="tenant.subdomain" 
                                class="form-control"
                                disabled
                            >
                            <small class="text-muted">Subdomain cannot be changed</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Username <span class="text-danger">*</span></label>
                            <input 
                                type="text" 
                                v-model="form.username" 
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.username }"
                                required
                            >
                            <div v-if="form.errors.username" class="invalid-feedback">
                                {{ form.errors.username }}
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input 
                                type="email" 
                                v-model="form.email" 
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.email }"
                                required
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
                                :class="{ 'is-invalid': form.errors.phone }"
                            >
                            <div v-if="form.errors.phone" class="invalid-feedback">
                                {{ form.errors.phone }}
                            </div>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">Address</label>
                            <textarea 
                                v-model="form.address" 
                                class="form-control"
                                rows="3"
                                :class="{ 'is-invalid': form.errors.address }"
                            ></textarea>
                            <div v-if="form.errors.address" class="invalid-feedback">
                                {{ form.errors.address }}
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Subscription Plan <span class="text-danger">*</span></label>
                            <select 
                                v-model="form.subscription_plan" 
                                class="form-select"
                                :class="{ 'is-invalid': form.errors.subscription_plan }"
                                required
                            >
                                <option value="">Select Plan</option>
                                <option v-for="plan in plans" :key="plan.id" :value="plan.slug">
                                    {{ plan.name }}
                                </option>
                            </select>
                            <div v-if="form.errors.subscription_plan" class="invalid-feedback">
                                {{ form.errors.subscription_plan }}
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Status <span class="text-danger">*</span></label>
                            <select 
                                v-model="form.status" 
                                class="form-select"
                                :class="{ 'is-invalid': form.errors.status }"
                                required
                            >
                                <option value="trial">Trial</option>
                                <option value="active">Active</option>
                                <option value="suspended">Suspended</option>
                                <option value="expired">Expired</option>
                            </select>
                            <div v-if="form.errors.status" class="invalid-feedback">
                                {{ form.errors.status }}
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Trial Ends At</label>
                            <input 
                                type="datetime-local" 
                                v-model="form.trial_ends_at" 
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.trial_ends_at }"
                            >
                            <div v-if="form.errors.trial_ends_at" class="invalid-feedback">
                                {{ form.errors.trial_ends_at }}
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Subscription Expires At</label>
                            <input 
                                type="datetime-local" 
                                v-model="form.subscription_expires_at" 
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.subscription_expires_at }"
                            >
                            <div v-if="form.errors.subscription_expires_at" class="invalid-feedback">
                                {{ form.errors.subscription_expires_at }}
                            </div>
                        </div>
                    </div>

                    <div class="form-actions mt-4">
                        <button type="submit" class="btn btn-primary" :disabled="form.processing">
                            <i class="bi bi-check-circle"></i>
                            {{ form.processing ? 'Saving...' : 'Save Changes' }}
                        </button>
                        <Link :href="route('superadmin.tenants.index')" class="btn btn-secondary">
                            Cancel
                        </Link>
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
    tenant: Object,
    plans: Array,
});

const form = useForm({
    name: props.tenant.name,
    username: props.tenant.username,
    email: props.tenant.email,
    phone: props.tenant.phone || '',
    address: props.tenant.address || '',
    subscription_plan: props.tenant.subscription_plan,
    status: props.tenant.status,
    trial_ends_at: props.tenant.trial_ends_at ? formatDateTimeLocal(props.tenant.trial_ends_at) : '',
    subscription_expires_at: props.tenant.subscription_expires_at ? formatDateTimeLocal(props.tenant.subscription_expires_at) : '',
});

const submit = () => {
    form.put(route('superadmin.tenants.update', props.tenant.id));
};

function formatDateTimeLocal(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    return `${year}-${month}-${day}T${hours}:${minutes}`;
}
</script>

<style scoped>
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
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
}

:global(.dark) .page-subtitle {
    color: #94A3B8;
}

.form-card {
    background: white;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}

:global(.dark) .form-card {
    background: #1E293B;
}

.form-label {
    font-weight: 600;
    color: #1E293B;
    margin-bottom: 8px;
    display: block;
}

:global(.dark) .form-label {
    color: #E2E8F0;
}

.form-control,
.form-select {
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    padding: 10px 14px;
}

:global(.dark) .form-control,
:global(.dark) .form-select {
    background: #0F172A;
    border-color: #334155;
    color: #E2E8F0;
}

.form-control:disabled {
    background: #F8FAFC;
    color: #94A3B8;
}

:global(.dark) .form-control:disabled {
    background: #1E293B;
    color: #64748B;
}

.form-actions {
    display: flex;
    gap: 12px;
    padding-top: 24px;
    border-top: 1px solid #E2E8F0;
}

:global(.dark) .form-actions {
    border-top-color: #334155;
}

.text-muted {
    font-size: 12px;
    color: #94A3B8;
}
</style>







