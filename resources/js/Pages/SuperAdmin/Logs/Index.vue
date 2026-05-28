<template>
    <SuperAdminLayout>
        <Head title="Activity Logs" />
        
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h3 class="page-title">Activity Logs</h3>
                    <p class="page-subtitle">Track system-wide actions and audit trails</p>
                </div>
                <div class="d-flex gap-2">
                    <button @click="resetFilters" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-counterclockwise"></i> Reset
                    </button>
                    <button @click="search" class="btn btn-primary">
                        <i class="bi bi-filter"></i> Apply Filters
                    </button>
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
                                v-model="filterForm.search" 
                                @keyup.enter="search"
                                placeholder="Search description or actor email..."
                                class="form-control"
                            >
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select v-model="filterForm.action" @change="search" class="form-select">
                            <option value="">All Actions</option>
                            <option v-for="act in availableActions" :key="act" :value="act">
                                {{ act }}
                            </option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input 
                            type="number" 
                            v-model="filterForm.tenant_id" 
                            @keyup.enter="search"
                            placeholder="Tenant ID"
                            class="form-control"
                        >
                    </div>
                    <div class="col-md-3 text-end">
                        <p class="text-muted small mb-0 mt-2">Showing {{ logs.to || 0 }} of {{ logs.total }} logs</p>
                    </div>
                </div>
            </div>

            <!-- Logs Table -->
            <div class="table-card mt-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Action</th>
                                <th>Tenant</th>
                                <th>Actor</th>
                                <th>Description</th>
                                <th class="text-end">Detail</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="log in logs.data" :key="log.id">
                                <td class="text-nowrap">
                                    <div class="d-flex flex-column">
                                        <span class="fw-medium">{{ formatDate(log.created_at) }}</span>
                                        <span class="text-muted smaller">{{ formatTime(log.created_at) }}</span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge" :class="getActionBadgeClass(log.action)">
                                        {{ log.action }}
                                    </span>
                                </td>
                                <td>
                                    <span v-if="log.tenant_id" class="badge bg-light text-dark border">
                                        ID: {{ log.tenant_id }}
                                    </span>
                                    <span v-else class="text-muted small">-</span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="actor-avatar me-2" v-if="log.user">
                                            {{ log.user.name.charAt(0).toUpperCase() }}
                                        </div>
                                        <div class="d-flex flex-column">
                                            <span class="fw-medium">{{ log.user ? log.user.name : 'System' }}</span>
                                            <span class="text-muted smaller">{{ log.user ? log.user.email : 'system@kitabill.site' }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="description-cell" :title="log.description">
                                        {{ truncate(log.description, 60) }}
                                    </div>
                                </td>
                                <td class="text-end">
                                    <button @click="showDetail(log)" class="btn btn-sm btn-icon btn-light">
                                        <i class="bi bi-info-circle"></i>
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="logs.data.length === 0">
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-1 d-block mb-3"></i>
                                    No activity logs found matching your filters.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="pagination-wrapper border-top p-3" v-if="logs.last_page > 1">
                    <nav>
                        <ul class="pagination pagination-sm m-0">
                            <li class="page-item" :class="{ disabled: !logs.prev_page_url }">
                                <Link :href="logs.prev_page_url || '#'" class="page-link">Previous</Link>
                            </li>
                            <li 
                                v-for="page in pageNumbers" 
                                :key="page" 
                                class="page-item" 
                                :class="{ active: page === logs.current_page }"
                            >
                                <Link :href="getPageUrl(page)" class="page-link">{{ page }}</Link>
                            </li>
                            <li class="page-item" :class="{ disabled: !logs.next_page_url }">
                                <Link :href="logs.next_page_url || '#'" class="page-link">Next</Link>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>
        </div>

        <!-- Detail Modal -->
        <div v-if="selectedLog" class="modal-backdrop" @click.self="selectedLog = null">
            <div class="modal-container modal-lg">
                <div class="modal-header">
                    <h5 class="m-0"><i class="bi bi-info-circle"></i> Log Detail</h5>
                    <button @click="selectedLog = null" class="btn-close"></button>
                </div>
                <div class="modal-body overflow-auto" style="max-height: 80vh;">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="text-muted small text-uppercase fw-bold">Basic Info</label>
                            <table class="table table-sm table-borderless mt-2">
                                <tr>
                                    <td class="text-muted" width="120">Timestamp:</td>
                                    <td>{{ formatDate(selectedLog.created_at) }} {{ formatTime(selectedLog.created_at) }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Action:</td>
                                    <td><span class="badge bg-primary">{{ selectedLog.action }}</span></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Tenant ID:</td>
                                    <td>{{ selectedLog.tenant_id || '-' }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Actor:</td>
                                    <td>{{ selectedLog.user ? selectedLog.user.name : 'System' }} ({{ selectedLog.user ? selectedLog.user.email : 'system' }})</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">IP Address:</td>
                                    <td><code>{{ selectedLog.ip_address }}</code></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small text-uppercase fw-bold">Involved Entity</label>
                            <table class="table table-sm table-borderless mt-2">
                                <tr>
                                    <td class="text-muted" width="120">Model Type:</td>
                                    <td>{{ selectedLog.model_type || '-' }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Model ID:</td>
                                    <td>{{ selectedLog.model_id || '-' }}</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-12 border-top pt-3">
                            <label class="text-muted small text-uppercase fw-bold">Description</label>
                            <p class="mt-2">{{ selectedLog.description }}</p>
                        </div>
                        <div class="col-12 border-top pt-3">
                            <label class="text-muted small text-uppercase fw-bold">Metadata (Properties)</label>
                            <div class="metadata-box mt-2">
                                <pre>{{ JSON.stringify(selectedLog.properties, null, 2) }}</pre>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button @click="selectedLog = null" class="btn btn-secondary">Close</button>
                </div>
            </div>
        </div>
    </SuperAdminLayout>
</template>

<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';

const props = defineProps({
    logs: Object,
    filters: Object,
    availableActions: Array,
});

const filterForm = ref({
    search: props.filters.search || '',
    action: props.filters.action || '',
    tenant_id: props.filters.tenant_id || '',
});

const selectedLog = ref(null);

const search = () => {
    router.get(route('superadmin.logs'), filterForm.value, {
        preserveState: true,
        preserveScroll: true,
    });
};

const resetFilters = () => {
    filterForm.value = {
        search: '',
        action: '',
        tenant_id: '',
    };
    search();
};

const showDetail = (log) => {
    selectedLog.value = log;
};

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('id-ID', { 
        day: 'numeric', 
        month: 'short', 
        year: 'numeric' 
    });
};

const formatTime = (date) => {
    return new Date(date).toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    });
};

const truncate = (text, length) => {
    if (!text) return '-';
    return text.length > length ? text.substring(0, length) + '...' : text;
};

const pageNumbers = computed(() => {
    const pages = [];
    for (let i = 1; i <= props.logs.last_page; i++) {
        pages.push(i);
    }
    return pages;
});

const getPageUrl = (page) => {
    return route('superadmin.logs', { ...filterForm.value, page });
};

const getActionBadgeClass = (action) => {
    if (action.includes('delete')) return 'bg-danger-subtle text-danger border border-danger-subtle';
    if (action.includes('create') || action.includes('active')) return 'bg-success-subtle text-success border border-success-subtle';
    if (action.includes('update') || action.includes('edit')) return 'bg-warning-subtle text-warning border border-warning-subtle';
    return 'bg-info-subtle text-info border border-info-subtle';
};

const getStatusBadgeClass = (status) => {
    const classes = {
        'trial': 'bg-info',
        'active': 'bg-success',
        'suspended': 'bg-danger',
        'expired': 'bg-warning text-dark',
    };
    return classes[status] || 'bg-secondary';
};
</script>

<style scoped>
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
}

.page-title {
    font-size: 24px;
    font-weight: 700;
    color: #1E293B;
    margin-bottom: 4px;
}

.page-subtitle {
    color: #64748B;
    margin: 0;
}

:global(.dark) .page-title {
    color: #F1F5F9;
}

.filter-card {
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

:global(.dark) .filter-card {
    background: #1E293B;
    border: 1px solid #334155;
}

.table-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    overflow: hidden;
}

:global(.dark) .table-card {
    background: #1E293B;
    border: 1px solid #334155;
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
}

.search-box .form-control {
    padding-left: 36px;
}

.actor-avatar {
    width: 32px;
    height: 32px;
    background: #E2E8F0;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 14px;
    color: #475569;
}

.smaller {
    font-size: 11px;
}

.description-cell {
    max-width: 400px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.btn-icon {
    width: 32px;
    height: 32px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
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
    z-index: 1050;
    backdrop-filter: blur(4px);
}

.modal-container {
    background: white;
    border-radius: 16px;
    width: 100%;
    max-width: 800px;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
    animation: modal-in 0.3s ease-out;
}

:global(.dark) .modal-container {
    background: #1E293B;
    color: #F8FAFC;
    border: 1px solid #334155;
}

@keyframes modal-in {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.modal-header {
    padding: 20px 24px;
    border-bottom: 1px solid #E2E8F0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

:global(.dark) .modal-header {
    border-bottom-color: #334155;
}

.modal-body {
    padding: 32px;
}

.modal-footer {
    padding: 20px 24px;
    border-top: 1px solid #E2E8F0;
    display: flex;
    justify-content: flex-end;
}

:global(.dark) .modal-footer {
    border-top-color: #334155;
}

.metadata-box {
    background: #F8FAFC;
    padding: 16px;
    border-radius: 8px;
    border: 1px solid #E2E8F0;
}

:global(.dark) .metadata-box {
    background: #0F172A;
    border-color: #334155;
}

.metadata-box pre {
    margin: 0;
    font-size: 13px;
    color: #1E293B;
    font-family: 'Fira Code', 'Courier New', Courier, monospace;
}

:global(.dark) .metadata-box pre {
    color: #94A3B8;
}

/* Custom Checkbox/Badge Colors */
.bg-danger-subtle { background-color: #FEE2E2; }
.text-danger { color: #DC2626; }
.bg-success-subtle { background-color: #DCFCE7; }
.text-success { color: #16A34A; }
.bg-warning-subtle { background-color: #FEF3C7; }
.text-warning { color: #D97706; }
.bg-info-subtle { background-color: #E0F2FE; }
.text-info { color: #0284C7; }
</style>
