<template>
    <SuperAdminLayout>
        <Head :title="`Tenant: ${tenant.name}`" />
        
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h3 class="page-title">Tenant Details</h3>
                    <p class="page-subtitle">{{ tenant.name }}</p>
                </div>
                <div class="d-flex gap-2">
                    <Link :href="route('superadmin.tenants.gateways.tripay.show', tenant.id)" class="btn btn-info text-white">
                        <i class="bi bi-credit-card"></i>
                        Tripay Config
                    </Link>
                    <Link :href="route('superadmin.tenants.edit', tenant.id)" class="btn btn-warning">
                        <i class="bi bi-pencil"></i>
                        Edit
                    </Link>
                    <Link :href="route('superadmin.tenants.index')" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i>
                        Back
                    </Link>
                </div>
            </div>

            <!-- Tenant Information Card -->
            <div class="info-card mb-4">
                <h5 class="card-title">Tenant Information</h5>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="info-item">
                            <label>Name</label>
                            <div>{{ tenant.name }}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-item">
                            <label>Subdomain</label>
                            <div>
                                <a :href="`https://${tenant.subdomain}.kitabill.site`" target="_blank" class="subdomain-link">
                                    {{ tenant.subdomain }}.kitabill.site
                                    <i class="bi bi-box-arrow-up-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-item">
                            <label>Email</label>
                            <div>{{ tenant.email }}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-item">
                            <label>Phone</label>
                            <div>{{ tenant.phone || '-' }}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-item">
                            <label>Subscription Plan</label>
                            <div>
                                <span class="badge" :class="getPlanBadgeClass(tenant.subscription_plan)">
                                    {{ tenant.subscription_plan ? tenant.subscription_plan.charAt(0).toUpperCase() + tenant.subscription_plan.slice(1) : 'N/A' }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-item">
                            <label>Status</label>
                            <div>
                                <span class="badge" :class="getStatusBadgeClass(tenant.status)">
                                    {{ tenant.status }}
                                </span>
                                <span v-if="tenant.is_active" class="badge bg-success ms-2">Active</span>
                                <span v-else class="badge bg-secondary ms-2">Inactive</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6" v-if="tenant.trial_ends_at">
                        <div class="info-item">
                            <label>Trial Ends</label>
                            <div>{{ formatDate(tenant.trial_ends_at) }}</div>
                        </div>
                    </div>
                    <div class="col-md-6" v-if="tenant.subscription_expires_at">
                        <div class="info-item">
                            <label>Subscription Expires</label>
                            <div>{{ formatDate(tenant.subscription_expires_at) }}</div>
                        </div>
                    </div>
                    <div class="col-md-12" v-if="tenant.address">
                        <div class="info-item">
                            <label>Address</label>
                            <div>{{ tenant.address }}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-item">
                            <label>Created At</label>
                            <div>{{ formatDate(tenant.created_at) }}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-item">
                            <label>Last Updated</label>
                            <div>{{ formatDate(tenant.updated_at) }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Users Section -->
            <div class="info-card mb-4">
                <h5 class="card-title">Users ({{ users.length }})</h5>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="user in users" :key="user.id">
                                <td>{{ user.name }}</td>
                                <td>{{ user.email }}</td>
                                <td>
                                    <span class="badge" :class="user.status === 'active' ? 'bg-success' : 'bg-secondary'">
                                        {{ user.status }}
                                    </span>
                                </td>
                                <td>{{ formatDate(user.created_at) }}</td>
                            </tr>
                            <tr v-if="users.length === 0">
                                <td colspan="4" class="text-center text-muted">No users found</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent Payments Section -->
            <div class="info-card mb-4">
                <h5 class="card-title">Recent Payments ({{ recentPayments.length }})</h5>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Invoice Number</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="payment in recentPayments" :key="payment.id">
                                <td>{{ payment.invoice_number || '-' }}</td>
                                <td>Rp {{ formatNumber(payment.amount || 0) }}</td>
                                <td>
                                    <span class="badge" :class="getPaymentStatusBadgeClass(payment.status)">
                                        {{ payment.status }}
                                    </span>
                                </td>
                                <td>{{ formatDate(payment.created_at) }}</td>
                            </tr>
                            <tr v-if="recentPayments.length === 0">
                                <td colspan="4" class="text-center text-muted">No payments found</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Referral System Section -->
            <div class="info-card mb-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="card-title mb-0 border-0 p-0 text-primary">Referral System Management</h5>
                    <div class="form-check form-switch custom-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="refSystemToggle" 
                            :checked="tenant.referral_system_enabled" @change="toggleReferralSystem">
                        <label class="form-check-label fw-bold" for="refSystemToggle">
                            {{ tenant.referral_system_enabled ? 'Enabled' : 'Disabled' }}
                        </label>
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-md-3">
                        <div class="info-item">
                            <label>Referral Code</label>
                            <div class="d-flex align-items-center gap-2">
                                <span class="font-monospace fw-bold text-dark">{{ tenant.referral_code || '-' }}</span>
                                <button v-if="tenant.referral_code" @click="copyToClipboard(tenant.referral_code)" class="btn btn-icon-sm">
                                    <i class="bi bi-clipboard"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="info-item">
                            <label>Current Balance</label>
                            <div class="text-success fs-5 fw-bold">Rp {{ formatNumber(tenant.referral_balance || 0) }}</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="info-item">
                            <label>Total Commissions</label>
                            <div class="fw-bold">{{ formatNumber(tenant.total_commissions || 0) }} IDR</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="info-item">
                            <label>Referred Tenants</label>
                            <div class="fw-bold">{{ tenant.referred_tenants_count || 0 }} Accounts</div>
                        </div>
                    </div>

                    <div class="col-12 mt-4 pt-3 border-top">
                        <div class="row">
                            <div class="col-md-6 border-end">
                                <div class="info-item">
                                    <label>Referrer Account</label>
                                    <div v-if="tenant.referrer" class="d-flex align-items-center gap-2">
                                        <div class="referrer-avatar">{{ tenant.referrer?.name?.substring(0,1) || '?' }}</div>
                                        <div>
                                            <div class="fw-bold">
                                                <Link :href="route('superadmin.tenants.show', tenant.referrer.id)" class="text-blue-600 hover:text-blue-800">
                                                    {{ tenant.referrer.name }}
                                                </Link>
                                            </div>
                                            <small class="text-muted">{{ tenant.referrer.subdomain }}.kitabill.site</small>
                                        </div>
                                    </div>
                                    <div v-else class="text-muted italic">No Referrer</div>
                                </div>
                            </div>
                            <div class="col-md-6 ps-md-4">
                                <div class="info-item">
                                    <label>Commission Rate (for their referrals)</label>
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="rate-badge">
                                            <span class="fs-4 fw-bold">{{ tenant.referral_commission_rate || '10' }}</span>
                                            <span class="fs-6">%</span>
                                        </div>
                                        <div>
                                            <div class="text-sm fw-semibold">{{ tenant.referral_commission_rate ? 'Custom Rate' : 'Global Default Rate' }}</div>
                                            <button @click="editCommissionRate" class="btn btn-sm btn-link p-0 text-primary">
                                                Change Rate
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ACS Configuration Section -->
            <div class="info-card mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="card-title mb-0">ACS Configuration (TR-069)</h5>
                    <span class="badge" :class="tenant.acs_enabled ? 'bg-success' : 'bg-secondary'">
                        {{ tenant.acs_enabled ? 'ENABLED' : 'DISABLED' }}
                    </span>
                </div>
                
                <div class="alert alert-info py-2" v-if="tenant.acs_enabled">
                    <i class="bi bi-info-circle me-2"></i> Only active tenants can manage devices.
                </div>

                <div class="row g-3 align-items-center">
                    <div class="col-md-6">
                        <div class="info-item">
                            <label>ACS Tenant ID</label>
                            <div class="font-monospace text-muted">{{ tenant.acs_tenant_id || tenant.subdomain || '-' }}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex gap-2 justify-content-end">
                            <button v-if="!tenant.acs_enabled" @click="toggleAcs(true)" class="btn btn-success text-white">
                                <i class="bi bi-power"></i> Enable ACS
                            </button>
                            <button v-else @click="toggleAcs(false)" class="btn btn-danger text-white">
                                <i class="bi bi-power"></i> Disable ACS
                            </button>
                        </div>
                    </div>
                    
                    <div class="col-md-12" v-if="tenant.acs_enabled">
                        <hr class="my-3 border-light">
                        <div class="info-item">
                            <label class="d-flex justify-content-between">
                                <span>API Key</span>
                                <button @click="regenerateKey" class="btn btn-sm btn-outline-warning py-0" style="font-size: 0.75rem;">
                                    <i class="bi bi-arrow-clockwise"></i> Regenerate Key
                                </button>
                            </label>
                            <div class="input-group mt-1">
                                <input type="text" class="form-control font-monospace" :value="tenant.acs_api_key" readonly disabled>
                                <button class="btn btn-outline-secondary" type="button" @click="copyToClipboard(tenant.acs_api_key)">
                                    <i class="bi bi-clipboard"></i>
                                </button>
                            </div>
                            <small class="text-muted mt-1 d-block">This key is used for billing system to communicate with ACS Core.</small>
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
    tenant: Object,
    users: Array,
    recentPayments: Array,
});

const formatDate = (date) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('id-ID', { 
        day: 'numeric', 
        month: 'short', 
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

const formatNumber = (num) => {
    return new Intl.NumberFormat('id-ID').format(num);
};

const getPlanBadgeClass = (plan) => {
    if (!plan) return 'bg-secondary';
    const p = plan.toLowerCase();
    const classes = {
        'starter': 'bg-primary',
        'professional': 'bg-success',
        'enterprise': 'bg-warning'
    };
    return classes[p] || 'bg-secondary';
};

const getStatusBadgeClass = (status) => {
    const classes = {
        'active': 'bg-success',
        'trial': 'bg-info',
        'suspended': 'bg-danger',
        'expired': 'bg-secondary'
    };
    return classes[status] || 'bg-secondary';
};

const getPaymentStatusBadgeClass = (status) => {
    const classes = {
        'paid': 'bg-success',
        'pending': 'bg-warning',
        'failed': 'bg-danger',
        'expired': 'bg-secondary'
    };
    return classes[status] || 'bg-secondary';
};


import { router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

const toggleAcs = (enable) => {
    const action = enable ? 'enable' : 'disable';
    const confirmText = enable 
        ? 'Enable ACS for this tenant? This will allow them to manage devices.'
        : 'Disable ACS? Existing devices will remain connected but management will be disabled.';
        
    Swal.fire({
        title: 'Are you sure?',
        text: confirmText,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: enable ? '#198754' : '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: `Yes, ${action} it!`
    }).then((result) => {
        if (result.isConfirmed) {
            router.post(route(`superadmin.tenants.acs.${action}`, props.tenant.id), {}, {
                onSuccess: () => {
                    Swal.fire(
                        'Success!',
                        `ACS has been ${action}d.`,
                        'success'
                    )
                }
            });
        }
    })
};

const regenerateKey = () => {
    Swal.fire({
        title: 'Regenerate API Key?',
        text: "The old key will stop working immediately. Validate if this is intended.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ffc107',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, regenerate!'
    }).then((result) => {
        if (result.isConfirmed) {
            router.post(route('superadmin.tenants.acs.regenerate-key', props.tenant.id), {}, {
                onSuccess: () => {
                    Swal.fire(
                        'Regenerated!',
                        'New API Key has been generated.',
                        'success'
                    )
                }
            });
        }
    })
};

const copyToClipboard = (text) => {
    if (!text) return;
    navigator.clipboard.writeText(text).then(() => {
        // Simple toast or alert
        const toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });
        toast.fire({
            icon: 'success',
            title: 'Copied to clipboard'
        });
    });
};

const editCommissionRate = () => {
    Swal.fire({
        title: 'Edit Commission Rate',
        input: 'number',
        inputLabel: 'Commission Rate (%)',
        inputPlaceholder: 'Enter percentage...',
        inputValue: props.tenant.referral_commission_rate || 10,
        showCancelButton: true,
        inputAttributes: {
            min: 0,
            max: 100,
            step: 0.1
        },
        inputValidator: (value) => {
            if (!value && value !== 0) {
                return 'You need to write something!'
            }
        }
    }).then((result) => {
        if (result.isConfirmed) {
            router.post(route('superadmin.tenants.referral.update-rate', props.tenant.id), {
                rate: result.value
            }, {
                onSuccess: () => {
                    Swal.fire('Updated!', 'Commission rate has been updated.', 'success');
                }
            });
        }
    });
};

const toggleReferralSystem = () => {
    router.post(route('superadmin.tenants.referral.toggle', props.tenant.id), {}, {
        onSuccess: () => {
            Swal.fire({
                icon: 'success',
                title: 'Updated',
                text: 'Referral system status updated.',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000
            });
        }
    });
};
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

.info-card {
    background: white;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}

:global(.dark) .info-card {
    background: #1E293B;
}

.card-title {
    font-size: 20px;
    font-weight: 600;
    color: #1E293B;
    margin-bottom: 20px;
    padding-bottom: 12px;
    border-bottom: 2px solid #E2E8F0;
}

:global(.dark) .card-title {
    color: #F1F5F9;
    border-bottom-color: #334155;
}

.info-item {
    margin-bottom: 16px;
}

.info-item label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    color: #64748B;
    margin-bottom: 4px;
}

:global(.dark) .info-item label {
    color: #94A3B8;
}

.info-item div {
    font-size: 16px;
    color: #1E293B;
    font-weight: 500;
}

:global(.dark) .info-item div {
    color: #E2E8F0;
}

.custom-switch .form-check-input {
    width: 3.5rem;
    height: 1.75rem;
    cursor: pointer;
}

.custom-switch .form-check-label {
    margin-left: 0.75rem;
    margin-top: 0.25rem;
}

.btn-icon-sm {
    padding: 2px 6px;
    background: #F1F5F9;
    border-radius: 4px;
    color: #64748B;
    border: none;
    transition: all 0.2s;
}

.btn-icon-sm:hover {
    background: #E2E8F0;
    color: #1E293B;
}

.referrer-avatar {
    width: 32px;
    height: 32px;
    background: #E2E8F0;
    color: #475569;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}

.rate-badge {
    background: #EFF6FF;
    color: #2563EB;
    padding: 10px 16px;
    border-radius: 12px;
    display: flex;
    align-items: baseline;
    gap: 2px;
}

:global(.dark) .rate-badge {
    background: rgba(37, 99, 235, 0.1);
}

.subdomain-link {
    color: #3B82F6;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.subdomain-link:hover {
    text-decoration: underline;
}

.subdomain-link i {
    font-size: 12px;
}

.table {
    margin: 0;
}

.table thead th {
    background: #F8FAFC;
    border-bottom: 2px solid #E2E8F0;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    color: #64748B;
    padding: 12px;
}

:global(.dark) .table thead th {
    background: #0F172A;
    border-bottom-color: #334155;
    color: #94A3B8;
}

.table tbody td {
    padding: 12px;
    vertical-align: middle;
    border-bottom: 1px solid #E2E8F0;
    color: #1E293B;
}

:global(.dark) .table tbody td {
    border-bottom-color: #334155;
    color: #E2E8F0;
}
</style>







