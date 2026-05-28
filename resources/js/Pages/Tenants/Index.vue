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
                <Link :href="route('superadmin.tenants.create')" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i>
                    Add New Tenant
                </Link>
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
                            <option value="Starter">Starter</option>
                            <option value="Professional">Professional</option>
                            <option value="Enterprise">Enterprise</option>
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
                                <th>ID</th>
                                <th>Tenant</th>
                                <th>Subdomain</th>
                                <th>Contact</th>
                                <th>Plan</th>
                                <th>Status</th>
                                <th>Users</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="tenant in tenants.data" :key="tenant.id">
                                <td>
                                    <span class="text-muted small">#{{ tenant.id }}</span>
                                </td>
                                <td>
                                    <div class="tenant-name-cell">
                                        <div class="tenant-avatar">
                                            {{ tenant.name.charAt(0).toUpperCase() }}
                                        </div>
                                        <strong>{{ tenant.name }}</strong>
                                    </div>
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
                                        {{ tenant.subscription_plan || 'N/A' }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge" :class="getStatusBadgeClass(tenant.status)">
                                        {{ tenant.status }}
                                    </span>
                                    <div v-if="tenant.trial_ends_at" class="text-muted small mt-1">
                                        Trial ends: {{ formatDate(tenant.trial_ends_at) }}
                                    </div>
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
                                            v-if="tenant.status !== 'active' || !tenant.is_active"
                                            @click="activateTenant(tenant.id)" 
                                            class="btn-action btn-success" 
                                            title="Activate"
                                        >
                                            <i class="bi bi-check-circle"></i>
                                        </button>
                                        <button 
                                            v-if="tenant.status === 'active' && tenant.is_active"
                                            @click="suspendTenant(tenant.id)" 
                                            class="btn-action btn-danger" 
                                            title="Suspend"
                                        >
                                            <i class="bi bi-pause-circle"></i>
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
        </div>
    </SuperAdminLayout>
</template>

<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';

const props = defineProps({
    tenants: Object,
    filters: Object,
});

const searchForm = ref({
    search: props.filters.search || '',
    status: props.filters.status || '',
    plan: props.filters.plan || '',
    sort_by: props.filters.sort_by || 'id',
    sort_dir: props.filters.sort_dir || 'desc',
});

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
    const classes = {
        'Starter': 'bg-primary',
        'Professional': 'bg-success',
        'Enterprise': 'bg-warning'
    };
    return classes[plan] || 'bg-secondary';
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
    if (confirm('Are you sure you want to activate this tenant?')) {
        router.post(route('superadmin.tenants.activate', tenantId), {}, {
            preserveScroll: true,
        });
    }
};

const suspendTenant = (tenantId) => {
    if (confirm('Are you sure you want to suspend this tenant? They will lose access to their billing system.')) {
        router.post(route('superadmin.tenants.suspend', tenantId), {}, {
            preserveScroll: true,
        });
    }
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
}
</style>



