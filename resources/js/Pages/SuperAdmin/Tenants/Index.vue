<template>
    <SuperAdminLayout>
        <Head title="Tenant Management" />
        
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h3 class="page-title">Tenant Management</h3>
                    <p class="page-subtitle">Manage all billing system tenants</p>
                </div>
                <div class="d-flex gap-2">
                    <div v-if="selectedIds.length > 0" class="bulk-actions-wrapper">
                        <span class="me-2 text-muted small">{{ selectedIds.length }} selected</span>
                        <button @click="bulkEnableAcs" class="btn btn-sm btn-outline-success">
                            <i class="bi bi-shield-check"></i> Bulk Enable ACS
                        </button>
                        <button @click="bulkDisableAcs" class="btn btn-sm btn-outline-danger">
                            <i class="bi bi-shield-slash"></i> Bulk Disable ACS
                        </button>
                        <button @click="bulkDeleteTenants" class="btn btn-sm btn-outline-danger">
                            <i class="bi bi-trash"></i> Bulk Delete Selected
                        </button>
                    </div>
                    
                    <!-- NEW: Delete All Expired Button -->
                    <button @click="bulkDeleteExpiredTenants" class="btn btn-danger d-flex align-items-center gap-2 shadow-sm">
                        <i class="bi bi-trash3-fill"></i> Empty Trash (All Expired)
                    </button>

                    <Link :href="route('superadmin.tenants.create')" class="btn btn-primary d-flex align-items-center gap-2">
                        <i class="bi bi-plus-circle"></i>
                        Add New Tenant
                    </Link>
                </div>

            </div>

            <!-- Filters & Search -->
            <div class="filter-card">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="search-box">
                            <i class="bi bi-search"></i>
                            <input 
                                type="text" 
                                v-model="searchForm.search" 
                                @input="debouncedSearch"
                                placeholder="Search by name, subdomain, email..."
                                class="form-control"
                            >
                        </div>
                    </div>
                    <div class="col-md-2">
                        <select v-model="searchForm.status" @change="search" class="form-select">
                            <option value="">All Status</option>
                            <option value="trial">Trial</option>
                            <option value="active">Active</option>
                            <option value="suspended">Suspended</option>
                            <option value="expired">Expired</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select v-model="searchForm.plan" @change="search" class="form-select">
                            <option value="">All Plans</option>
                            <option v-for="plan in plans" :key="plan.id" :value="plan.slug">
                                {{ plan.name }}
                            </option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select v-model="searchForm.sort_by" @change="search" class="form-select">
                            <option value="id">ID Tenant</option>
                            <option value="created_at">Created Date</option>
                            <option value="name">Name</option>
                            <option value="status">Status</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select v-model="searchForm.sort_dir" @change="search" class="form-select">
                            <option value="desc">Descending</option>
                            <option value="asc">Ascending</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Tenants Table -->
            <div class="table-card">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th style="width: 40px;">
                                    <input 
                                        type="checkbox" 
                                        class="form-check-input" 
                                        :checked="allSelected" 
                                        @change="toggleSelectAll"
                                    >
                                </th>
                                <th>ID</th>
                                <th>Tenant</th>
                                <th>Username</th>
                                <th>Subdomain</th>
                                <th>Contact</th>
                                <th>Plan</th>
                                <th>Status</th>
                                <th class="text-center">ACS</th>
                                <th>Users</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>

                        </thead>
                        <tbody>
                            <tr v-for="tenant in tenants.data" :key="tenant.id">
                                <td>
                                    <input 
                                        type="checkbox" 
                                        class="form-check-input" 
                                        v-model="selectedIds" 
                                        :value="tenant.id"
                                    >
                                </td>
                                <td>
                                    <span class="text-muted small">#{{ tenant.id }}</span>
                                </td>
                                <td>
                                    <div class="tenant-name-cell">
                                        <div class="tenant-avatar">
                                            {{ tenant?.name ? tenant.name.charAt(0).toUpperCase() : 'T' }}
                                        </div>
                                        <strong>{{ tenant?.name || 'Unknown' }}</strong>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">{{ tenant.username || '---' }}</span>
                                </td>
                                <td>
                                    <a :href="`https://${tenant.subdomain}.kitabill.site`" target="_blank" class="subdomain-link">
                                        {{ tenant.subdomain }}.kitabill.site
                                        <i class="bi bi-box-arrow-up-right"></i>
                                    </a>
                                </td>
                                <td>
                                    <div class="contact-cell">
                                        <div><i class="bi bi-envelope"></i> {{ tenant.email }}</div>
                                        <div v-if="tenant.phone"><i class="bi bi-phone"></i> {{ tenant.phone }}</div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge" :class="getPlanBadgeClass(tenant.subscription_plan)">
                                        {{ tenant.subscription_plan ? tenant.subscription_plan.charAt(0).toUpperCase() + tenant.subscription_plan.slice(1) : 'N/A' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex flex-column gap-1">
                                        <span class="badge" :class="getStatusBadgeClass(tenant.status)">
                                            {{ tenant.status }}
                                        </span>
                                        <span v-if="!tenant.is_active" class="badge bg-warning text-dark">
                                            <i class="bi bi-exclamation-triangle"></i> Inactive
                                        </span>
                                        <span v-else class="badge bg-success">
                                            <i class="bi bi-check-circle"></i> Active
                                        </span>
                                        <div v-if="tenant.trial_ends_at" class="text-muted small mt-1">
                                            Trial ends: {{ formatDate(tenant.trial_ends_at) }}
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span v-if="tenant.acs_enabled" class="badge bg-success" title="ACS Enabled">
                                        <i class="bi bi-shield-check"></i>
                                    </span>
                                    <span v-else class="badge bg-light text-muted border" title="ACS Disabled">
                                        <i class="bi bi-shield-slash"></i>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-secondary">{{ tenant.user_count }} users</span>
                                </td>
                                <td>{{ formatDate(tenant.created_at) }}</td>

                                <td>
                                    <div class="action-buttons">
                                        <Link :href="route('superadmin.tenants.show', tenant.id)" class="btn-action btn-info" title="View Details">
                                            <i class="bi bi-eye"></i>
                                        </Link>
                                        <Link :href="route('superadmin.tenants.edit', tenant.id)" class="btn-action btn-warning" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </Link>
                                        <button 
                                            v-if="tenant.status !== 'active'"
                                            @click="activateTenant(tenant.id)" 
                                            class="btn-action btn-success" 
                                            title="Activate"
                                        >
                                            <i class="bi bi-check-circle"></i>
                                        </button>
                                        <button 
                                            v-if="tenant?.status === 'active'"
                                            @click="suspendTenant(tenant.id)" 
                                            class="btn-action btn-danger" 
                                            title="Suspend"
                                        >
                                            <i class="bi bi-pause-circle"></i>
                                        </button>
                                        <button 
                                            @click="confirmDelete(tenant)" 
                                            class="btn-action btn-danger" 
                                            title="Delete Tenant"
                                        >
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="pagination-wrapper" v-if="tenants.last_page > 1">
                    <nav>
                        <ul class="pagination">
                            <li class="page-item" :class="{ disabled: !tenants.prev_page_url }">
                                <Link :href="tenants.prev_page_url" class="page-link">Previous</Link>
                            </li>
                            <li 
                                v-for="page in pageNumbers" 
                                :key="page" 
                                class="page-item" 
                                :class="{ active: page === tenants.current_page }"
                            >
                                <Link :href="getPageUrl(page)" class="page-link">{{ page }}</Link>
                            </li>
                            <li class="page-item" :class="{ disabled: !tenants.next_page_url }">
                                <Link :href="tenants.next_page_url" class="page-link">Next</Link>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>

            <!-- Delete Confirmation Modal (Outside Table) -->
            <div v-if="showingDeleteModal" class="modal-backdrop" @click.self="showingDeleteModal = false">
                <div v-if="tenantToDelete" class="modal-container">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="m-0"><i class="bi bi-exclamation-triangle"></i> Hapus Tenant?</h5>
                        <button @click="showingDeleteModal = false" class="btn-close btn-close-white"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <i class="bi bi-shield-lock"></i> <strong>Tindakan Irreversibel!</strong><br>
                            Seluruh data tenant (pelanggan, invoice, voucher, log) akan dihapus permanen. WhatsApp Gateway akan dimatikan.
                        </div>
                        <p>Penghapusan: <strong>{{ tenantToDelete.name || 'Unknown' }}</strong> ({{ tenantToDelete.subdomain || '---' }}.kitabill.site)</p>
                        <hr>
                        <div class="form-group">
                            <label class="form-label">Ketik subdomain <strong>{{ tenantToDelete.subdomain || '---' }}</strong> untuk konfirmasi:</label>
                            <input 
                                type="text" 
                                v-model="deleteConfirmation" 
                                class="form-control" 
                                :placeholder="tenantToDelete.subdomain || ''"
                                @keyup.enter="deleteConfirmation === tenantToDelete.subdomain && deleteTenant()"
                            >
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button @click="showingDeleteModal = false" class="btn btn-secondary">Batal</button>
                        <button 
                            @click="deleteTenant" 
                            class="btn btn-danger" 
                            :disabled="!tenantToDelete?.subdomain || deleteConfirmation !== tenantToDelete.subdomain"
                        >
                            Hapus Permanen
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </SuperAdminLayout>
</template>

<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import Swal from 'sweetalert2';


const props = defineProps({
    tenants: Object,
    plans: Array,
    filters: Object,
});

const searchForm = ref({
    search: props.filters.search || '',
    status: props.filters.status || '',
    plan: props.filters.plan || '',
    sort_by: props.filters.sort_by || 'id',
    sort_dir: props.filters.sort_dir || 'desc',
});

const selectedIds = ref([]);

const allSelected = computed(() => {
    return props.tenants.data.length > 0 && selectedIds.value.length === props.tenants.data.length;
});

const toggleSelectAll = (e) => {
    if (e.target.checked) {
        selectedIds.value = props.tenants.data.map(t => t.id);
    } else {
        selectedIds.value = [];
    }
};

const bulkEnableAcs = () => {
    if (!selectedIds.value.length) return;
    
    Swal.fire({
        title: 'Aktifkan ACS?',
        text: `Aktifkan ACS untuk ${selectedIds.value.length} tenant yang dipilih?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Aktifkan',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#10B981',
    }).then((result) => {
        if (result.isConfirmed) {
            router.post(route('superadmin.tenants.acs.bulk-enable'), {
                ids: selectedIds.value
            }, {
                onSuccess: () => {
                    selectedIds.value = [];
                    Swal.fire({
                        title: 'Berhasil!',
                        text: 'ACS berhasil diaktifkan untuk tenant terpilih.',
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false
                    });
                },
                onError: () => {
                    Swal.fire('Gagal', 'Terjadi kesalahan saat mengaktifkan ACS.', 'error');
                },
                preserveScroll: true
            });
        }
    });
};

const bulkDisableAcs = () => {
    if (!selectedIds.value.length) return;
    
    Swal.fire({
        title: 'Nonaktifkan ACS?',
        text: `Nonaktifkan ACS untuk ${selectedIds.value.length} tenant yang dipilih?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Nonaktifkan',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#EF4444',
    }).then((result) => {
        if (result.isConfirmed) {
            router.post(route('superadmin.tenants.acs.bulk-disable'), {
                ids: selectedIds.value
            }, {
                onSuccess: () => {
                    selectedIds.value = [];
                    Swal.fire({
                        title: 'Berhasil!',
                        text: 'ACS berhasil dinonaktifkan untuk tenant terpilih.',
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false
                    });
                },
                onError: () => {
                    Swal.fire('Gagal', 'Terjadi kesalahan saat menonaktifkan ACS.', 'error');
                },
                preserveScroll: true
            });
        }
    });
};

const bulkDeleteTenants = () => {
    if (!selectedIds.value.length) return;
    
    Swal.fire({
        title: 'Hapus Massal Tenant?',
        text: `Seluruh data untuk ${selectedIds.value.length} tenant yang dipilih akan dihapus permanen. Lanjutkan?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Hapus Semua',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#EF4444',
    }).then((result) => {
        if (result.isConfirmed) {
            router.post(route('superadmin.tenants.bulk-destroy'), {
                ids: selectedIds.value
            }, {
                onSuccess: () => {
                    selectedIds.value = [];
                    Swal.fire({
                        title: 'Berhasil!',
                        text: 'Tenant yang dipilih berhasil dihapus.',
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false
                    });
                },
                onError: (err) => {
                    const msg = (err && typeof err === 'object') ? Object.values(err)[0] : (err || 'Gagal menghapus tenant.');
                    Swal.fire('Gagal', msg, 'error');
                },
                preserveScroll: true
            });
        }
    });
};

const bulkDeleteExpiredTenants = () => {
    Swal.fire({
        title: 'Kosongkan Tong Sampah?',
        text: 'PERINGATAN! Anda akan menghapus SELURUH tenant yang berstatus kedaluwarsa (Expired) secara permanen beserta semua data dan database-nya. Tindakan ini tidak bisa dibatalkan.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Hapus Semua Expired',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#EF4444',
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Menghapus...',
                text: 'Harap tunggu, server sedang membersihkan database tenant.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            router.post(route('superadmin.tenants.bulk-destroy-expired'), {}, {
                onSuccess: () => {
                    selectedIds.value = [];
                    // Sweetalert success is handled by the flash message / inertia response mostly,
                    // but we can ensure the loading state is closed.
                    Swal.fire({
                        title: 'Berhasil!',
                        text: 'Proses penghapusan massal selesai.',
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false
                    });
                },
                onError: (err) => {
                    const msg = (err && typeof err === 'object') ? Object.values(err)[0] : (err || 'Gagal menghapus tenant.');
                    Swal.fire('Gagal', msg, 'error');
                },
                preserveScroll: true
            });
        }
    });
};


let debounceTimer = null;

const debouncedSearch = () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        search();
    }, 300);
};

const search = () => {
    router.get(route('superadmin.tenants.index'), searchForm.value, {
        preserveState: true,
        preserveScroll: true,
    });
};

const pageNumbers = computed(() => {
    const pages = [];
    for (let i = 1; i <= props.tenants.last_page; i++) {
        pages.push(i);
    }
    return pages;
});

const getPageUrl = (page) => {
    return route('superadmin.tenants.index', { ...searchForm.value, page });
};

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('id-ID', { 
        day: 'numeric', 
        month: 'short', 
        year: 'numeric' 
    });
};

const getPlanBadgeClass = (plan) => {
    if (!plan) return 'bg-secondary';
    const p = plan.toLowerCase();
    const classes = {
        'starter': 'bg-primary',
        'professional': 'bg-success',
        'enterprise': 'bg-warning',
        'ultra-pro': 'bg-purple',
        'kitapro': 'bg-info'
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

const activateTenant = (tenantId) => {
    if (!tenantId) return;
    
    Swal.fire({
        title: 'Aktifkan Tenant?',
        text: 'Anda yakin ingin mengaktifkan tenant ini?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Aktifkan',
        confirmButtonColor: '#10B981',
    }).then((result) => {
        if (result.isConfirmed) {
            router.post(route('superadmin.tenants.activate', tenantId), {}, {
                preserveScroll: true,
                onSuccess: () => Swal.fire('Berhasil', 'Tenant berhasil diaktifkan.', 'success'),
            });
        }
    });
};

const suspendTenant = (tenantId) => {
    if (!tenantId) return;

    Swal.fire({
        title: 'Suspend Tenant?',
        text: 'Tenant akan kehilangan akses ke sistem billing. Lanjutkan?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Suspend',
        confirmButtonColor: '#EF4444',
    }).then((result) => {
        if (result.isConfirmed) {
            router.post(route('superadmin.tenants.suspend', tenantId), {}, {
                preserveScroll: true,
                onSuccess: () => Swal.fire('Berhasil', 'Tenant berhasil ditangguhkan.', 'success'),
            });
        }
    });
};


const showingDeleteModal = ref(false);
const tenantToDelete = ref(null);
const deleteConfirmation = ref('');

const confirmDelete = (tenant) => {
    tenantToDelete.value = tenant;
    deleteConfirmation.value = '';
    showingDeleteModal.value = true;
};

const deleteTenant = () => {
    if (!tenantToDelete.value || !tenantToDelete.value.subdomain || deleteConfirmation.value !== tenantToDelete.value.subdomain) return;
    
    const id = tenantToDelete.value.id;
    
    router.delete(route('superadmin.tenants.destroy', id), {
        onSuccess: () => {
            showingDeleteModal.value = false;
            // Don't clear tenantToDelete immediately to avoid transition crashes
            setTimeout(() => {
                tenantToDelete.value = null;
                deleteConfirmation.value = '';
            }, 300);
        },
        onError: (err) => {
            const msg = (err && typeof err === 'object') ? Object.values(err)[0] : (err || 'Gagal menghapus tenant.');
            alert(msg);
        }
    });
};
</script>

<style scoped>
/* Page Header */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 32px;
}

.bulk-actions-wrapper {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    background: #F1F5F9;
    border-radius: 8px;
    border: 1px solid #E2E8F0;
}

:global(.dark) .bulk-actions-wrapper {
    background: #0F172A;
    border-color: #334155;
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

/* Filter Card */
.filter-card {
    background: white;
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 24px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}

:global(.dark) .filter-card {
    background: #1E293B;
}

.search-box {
    position: relative;
}

.search-box i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #94A3B8;
    font-size: 18px;
}

.search-box input {
    padding-left: 40px;
}

/* Table Card */
.table-card {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}

:global(.dark) .table-card {
    background: #1E293B;
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
    padding: 16px;
}

:global(.dark) .table thead th {
    background: #0F172A;
    border-bottom-color: #334155;
    color: #94A3B8;
}

.table tbody td {
    padding: 16px;
    vertical-align: middle;
    border-bottom: 1px solid #E2E8F0;
}

:global(.dark) .table tbody td {
    border-bottom-color: #334155;
    color: #E2E8F0;
}

.tenant-name-cell {
    display: flex;
    align-items: center;
    gap: 12px;
}

.tenant-avatar {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: linear-gradient(135deg, #3B82F6 0%, #8B5CF6 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 16px;
}

.subdomain-link {
    color: #3B82F6;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 14px;
}

.subdomain-link:hover {
    text-decoration: underline;
}

.subdomain-link i {
    font-size: 12px;
}

.contact-cell {
    font-size: 13px;
    color: #64748B;
}

:global(.dark) .contact-cell {
    color: #94A3B8;
}

.contact-cell i {
    font-size: 12px;
    margin-right: 4px;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 8px;
}

.btn-action {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
    color: white;
}

.btn-action.btn-info {
    background: #0EA5E9;
}

.btn-action.btn-warning {
    background: #F59E0B;
}

.btn-action.btn-success {
    background: #10B981;
}

.btn-action.btn-danger {
    background: #EF4444;
}

.btn-action:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.bg-purple {
    background-color: #8B5CF6;
    color: white;
}

/* Pagination */
.pagination-wrapper {
    padding: 20px;
    border-top: 1px solid #E2E8F0;
    display: flex;
    justify-content: center;
}

:global(.dark) .pagination-wrapper {
    border-top-color: #334155;
}

.pagination {
    margin: 0;
}

.page-link {
    color: #3B82F6;
}

:global(.dark) .page-link {
    background: #1E293B;
    border-color: #334155;
    color: #94A3B8;
}

.page-item.active .page-link {
    background: #3B82F6;
    border-color: #3B82F6;
}

/* Modal Styles */
.modal-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    backdrop-filter: blur(4px);
}

.modal-container {
    background: white;
    border-radius: 12px;
    width: 100%;
    max-width: 500px;
    overflow: hidden;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

:global(.dark) .modal-container {
    background: #1E293B;
    color: #F1F5F9;
}

.modal-header {
    padding: 16px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-body {
    padding: 24px;
}

.modal-footer {
    padding: 16px 24px;
    border-top: 1px solid #E2E8F0;
    display: flex;
    justify-content: flex-end;
    gap: 12px;
}

:global(.dark) .modal-footer {
    border-top-color: #334155;
}

/* Responsive */
@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 16px;
    }

    .page-header .btn {
        width: 100%;
    }

    .action-buttons {
        flex-wrap: wrap;
    }

    .modal-container {
        margin: 16px;
    }
}
</style>



