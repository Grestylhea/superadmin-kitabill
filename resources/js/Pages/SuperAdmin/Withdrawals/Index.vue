<template>
    <Head title="Withdrawal Requests" />
    
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="fw-bold mb-1">Withdrawal Requests</h4>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><Link :href="route('superadmin.dashboard')">Dashboard</Link></li>
                            <li class="breadcrumb-item active">Withdrawals</li>
                        </ol>
                    </nav>
                </div>
                <div class="status-summary d-flex gap-3">
                    <div class="summary-badge bg-warning-subtle text-warning p-2 rounded-3 border border-warning-subtle">
                        <span class="label small fw-bold text-uppercase me-2">Pending</span>
                        <span class="value badge bg-warning text-dark">{{ requests.data.filter(r => r.status === 'pending').length }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="info-card p-0 overflow-hidden shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4 py-3 text-muted small text-uppercase fw-bold">Tenant Details</th>
                        <th class="py-3 text-muted small text-uppercase fw-bold">Amount</th>
                        <th class="py-3 text-muted small text-uppercase fw-bold">Status</th>
                        <th class="py-3 text-muted small text-uppercase fw-bold">Requested Date</th>
                        <th class="pe-4 py-3 text-end text-muted small text-uppercase fw-bold">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="request in requests.data" :key="request.id">
                        <td class="ps-4">
                            <div class="d-flex align-items-center gap-3">
                                <div class="tenant-avatar">{{ request.tenant?.name?.substring(0,1) }}</div>
                                <div>
                                    <div class="fw-bold text-dark">{{ request.tenant?.name }}</div>
                                    <small class="text-muted">{{ request.tenant?.subdomain }}.kitabill.site</small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="fw-bold text-primary">Rp {{ formatCurrency(request.amount) }}</div>
                        </td>
                        <td>
                            <span :class="getStatusBadgeClass(request.status)" class="badge-custom">
                                {{ getStatusLabel(request.status) }}
                            </span>
                        </td>
                        <td class="text-muted small">
                            {{ formatDate(request.created_at) }}
                        </td>
                        <td class="pe-4 text-end">
                            <button @click="openModal(request)" class="btn btn-sm btn-primary rounded-pill px-3 shadow-sm">
                                <i class="bi bi-gear-fill me-1"></i> Process
                            </button>
                        </td>
                    </tr>
                    <tr v-if="requests.data.length === 0">
                        <td colspan="5" class="py-5 text-center">
                            <i class="bi bi-inbox fs-1 text-muted d-block mb-2"></i>
                            <span class="text-muted">No withdrawal requests found.</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div v-if="requests.links.length > 3" class="p-4 border-top bg-light text-center">
             <!-- Pagination placeholder -->
        </div>
    </div>

    <!-- Process Modal -->
    <div v-if="showModal" class="modal-backdrop fade show"></div>
    <div v-if="showModal" class="modal fade show d-block" tabindex="-1" @click.self="showModal = false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg overflow-hidden rounded-4">
                <div class="modal-header bg-primary text-white p-4">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-wallet2 me-2"></i> Process Withdrawal
                    </h5>
                    <button type="button" class="btn-close btn-close-white" @click="showModal = false"></button>
                </div>
                <form @submit.prevent="submitStatus">
                    <div class="modal-body p-4">
                        <div class="request-summary mb-4 p-3 bg-light rounded-4 border">
                            <div class="row align-items-center">
                                <div class="col-auto">
                                    <div class="tenant-avatar large">{{ activeRequest?.tenant?.name?.substring(0,1) || '?' }}</div>
                                </div>
                                <div class="col">
                                    <h6 class="mb-0 fw-bold">{{ activeRequest?.tenant?.name || 'Unknown' }}</h6>
                                    <small class="text-muted">{{ activeRequest?.tenant?.subdomain || 'unknown' }}.kitabill.site</small>
                                </div>
                                <div class="col-auto text-end">
                                    <div class="text-primary fw-bold fs-5">Rp {{ formatCurrency(activeRequest?.amount || 0) }}</div>
                                    <small class="text-muted d-block">Requested Amount</small>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4" v-if="activeRequest.notes">
                            <label class="form-label fw-semibold small text-uppercase text-muted">Tenant Notes</label>
                            <div class="p-3 bg-light rounded-3 text-muted small border-start border-primary border-4">
                                "{{ activeRequest.notes }}"
                            </div>
                        </div>

                        <div class="mb-4 text-center p-3 bg-info-subtle border border-info rounded-3" v-if="form.status === 'paid'">
                            <p class="mb-0 text-info fw-semibold small">
                                <i class="bi bi-info-circle-fill me-1"></i>
                                Switching to <strong>Paid</strong> will automatically deduct the amount from the tenant's balance.
                            </p>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold small text-uppercase text-muted">Update Status</label>
                            <div class="row g-2">
                                <div class="col-6" v-for="opt in ['pending', 'approved', 'paid', 'rejected']" :key="opt">
                                    <button type="button" class="btn w-100 status-opt-btn" 
                                        :class="{ active: form.status === opt, [opt]: true }"
                                        @click="form.status = opt">
                                        {{ getStatusLabel(opt) }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold small text-uppercase text-muted">Admin Notes</label>
                            <textarea v-model="form.admin_notes" class="form-control rounded-3" rows="3" placeholder="Add optional notes for the tenant..."></textarea>
                        </div>

                        <div v-if="form.status === 'paid'" class="mb-0">
                            <label class="form-label fw-semibold small text-uppercase text-muted">Proof of Payment</label>
                            <div class="upload-zone">
                                <input type="file" @change="e => form.proof_file = e.target.files[0]" id="proofFile" class="d-none" />
                                <label for="proofFile" class="cursor-pointer d-block py-3 text-center border border-dashed rounded-3 bg-light hover:bg-white transition-all">
                                    <i class="bi bi-cloud-arrow-up fs-3 text-primary d-block"></i>
                                    <span class="text-muted small">{{ form.proof_file ? form.proof_file.name : 'Click to upload proof' }}</span>
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light p-4 border-0">
                        <button type="button" class="btn btn-link text-muted fw-semibold text-decoration-none" @click="showModal = false">Cancel</button>
                        <button type="submit" class="btn btn-primary px-4 py-2 rounded-pill fw-bold shadow-sm" :disabled="form.processing">
                            <span v-if="form.processing" class="spinner-border spinner-border-sm me-2"></span>
                            {{ form.processing ? 'Saving...' : 'Confirm Update' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    requests: Object,
});

const showModal = ref(false);
const activeRequest = ref(null);

const form = useForm({
    status: '',
    admin_notes: '',
    proof_file: null,
});

const openModal = (request) => {
    activeRequest.value = request;
    form.status = request.status;
    form.admin_notes = request.admin_notes || '';
    form.proof_file = null;
    showModal.value = true;
};

const submitStatus = () => {
    form.post(route('superadmin.withdrawals.update-status', activeRequest.value.id), {
        forceFormData: true,
        onSuccess: () => {
            showModal.value = false;
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: 'Withdrawal status updated successfully.',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000
            });
        },
    });
};

const formatCurrency = (value) => {
    return new Intl.NumberFormat('id-ID').format(value);
};

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric'
    });
};

const getStatusBadgeClass = (status) => {
    const classes = {
        'pending': 'badge-custom pending',
        'approved': 'badge-custom approved',
        'paid': 'badge-custom paid',
        'rejected': 'badge-custom rejected',
    };
    return classes[status] || 'badge bg-secondary';
};

const getStatusLabel = (status) => {
    const labels = {
        'pending': 'Pending',
        'approved': 'Approved',
        'paid': 'Paid',
        'rejected': 'Rejected',
    };
    return labels[status] || status;
};
</script>

<style scoped>
.info-card {
    background: #fff;
    border-radius: 1rem;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

.tenant-avatar {
    width: 40px;
    height: 40px;
    background: #e2e8f0;
    color: #475569;
    border-radius: 0.75rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
}

.tenant-avatar.large {
    width: 56px;
    height: 56px;
    font-size: 1.5rem;
}

.badge-custom {
    padding: 0.35rem 0.75rem;
    border-radius: 50rem;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.badge-custom.pending { background: #fef3c7; color: #92400e; }
.badge-custom.approved { background: #dbeafe; color: #1e40af; }
.badge-custom.paid { background: #dcfce7; color: #166534; }
.badge-custom.rejected { background: #fee2e2; color: #991b1b; }

.status-opt-btn {
    padding: 0.75rem;
    border: 1px solid #e2e8f0;
    background: #fff;
    border-radius: 0.75rem;
    font-size: 0.875rem;
    font-weight: 500;
    color: #64748b;
    transition: all 0.2s;
}

.status-opt-btn:hover {
    border-color: #cbd5e1;
    background: #f8fafc;
}

.status-opt-btn.active.pending { background: #fffbeb; border-color: #f59e0b; color: #b45309; }
.status-opt-btn.active.approved { background: #eff6ff; border-color: #3b82f6; color: #1d4ed8; }
.status-opt-btn.active.paid { background: #f0fdf4; border-color: #22c55e; color: #15803d; }
.status-opt-btn.active.rejected { background: #fef2f2; border-color: #ef4444; color: #b91c1c; }

.upload-zone label {
    transition: all 0.2s;
}

.upload-zone label:hover {
    background: #fff !important;
    border-color: #3b82f6 !important;
}

.hover\:bg-white:hover {
    background-color: #fff !important;
}

.cursor-pointer {
    cursor: pointer;
}
</style>
